<?php

namespace FrancisBeltre\PrimeNgFilters\Traits;

use FrancisBeltre\PrimeNgFilters\PrimeNgFiltersHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;

trait Filterable
{
    /**
     * Apply PrimeNG filters to query
     * 
     * @param Builder $query
     * @param array $requestData
     * @param array|string|null $searchableFields 
     *   - Array: List of fields to search in
     *   - 'all': Search in all database columns (use with caution!)
     *   - null: Use model's $searchableFields property or don't apply global filter
     * @return Builder
     */
    public function scopeApplyPrimeNgFilters(
        Builder $query,
        array $requestData,
        $searchableFields = null
    ): Builder {

        $filters = PrimeNgFiltersHelper::getFilters($requestData);
        if (!empty($filters)) {
            $query = $this->applyFilters($query, $filters);
        }

        if ($globalFilter = PrimeNgFiltersHelper::getGlobalFilter($requestData)) {
            $fields = $requestData['globalFilterFields'];
            if (!empty($fields)) {
                $query = $this->applyGlobalFilter($query, $globalFilter, $fields);
            }
        }

        if ($sorting = PrimeNgFiltersHelper::getSorting($requestData)) {
            $this->applySorting($query, $sorting['field'], $sorting['direction']);
        }

        return $query;
    }

    protected function applySorting(Builder $query, string $field, string $direction): void
    {
        if (!$this->isRelationshipField($field)) {
            $query->orderBy($field, $direction);
            return;
        }

        $this->applyRelationshipSorting($query, $field, $direction);
    }

    protected function applyRelationshipSorting(Builder $query, string $field, string $direction): void
    {
        $parsed = $this->parseRelationshipField($field);
        $relations = explode('.', $parsed['relationPath']);
        $column = $parsed['column'];
        $model = $query->getModel();

        $subQuery = $this->buildSortSubquery($model, $relations, $column);

        if ($subQuery !== null) {
            $query->orderBy($subQuery, $direction);
        }
    }

    protected function buildSortSubquery($model, array $relations, string $column): ?Builder
    {
        $currentModel = $model;
        $parentTable = $model->getTable();
        $joinConditions = [];

        foreach ($relations as $relationName) {
            if (!method_exists($currentModel, $relationName)) {
                return null;
            }

            $relation = $currentModel->{$relationName}();
            $relatedModel = $relation->getRelated();
            $relationType = class_basename($relation);

            $joinInfo = match ($relationType) {
                'BelongsTo' => [
                    'table' => $relatedModel->getTable(),
                    'localKey' => $relation->getForeignKeyName(),
                    'foreignKey' => $relation->getOwnerKeyName(),
                    'parentTable' => $currentModel->getTable(),
                ],
                'HasOne' => [
                    'table' => $relatedModel->getTable(),
                    'localKey' => $currentModel->getKeyName(),
                    'foreignKey' => $relation->getForeignKeyName(),
                    'parentTable' => $currentModel->getTable(),
                ],
                default => null,
            };

            if ($joinInfo === null) {
                return null;
            }

            $joinConditions[] = $joinInfo;
            $currentModel = $relatedModel;
        }

        $subQuery = $currentModel->newQuery()->select($column)->limit(1);

        $this->applySubqueryJoinConditions($subQuery, $joinConditions, $parentTable);

        return $subQuery;
    }

    protected function applySubqueryJoinConditions(Builder $subQuery, array $joinConditions, string $rootTable): void
    {
        $lastIndex = count($joinConditions) - 1;

        for ($i = 0; $i < $lastIndex; $i++) {
            $condition = $joinConditions[$i];
            $nextCondition = $joinConditions[$i + 1];

            $subQuery->join(
                $condition['table'],
                "{$nextCondition['table']}.{$nextCondition['foreignKey']}",
                '=',
                "{$condition['table']}.{$condition['foreignKey']}"
            );
        }

        $firstCondition = $joinConditions[0];
        $subQuery->whereColumn(
            "{$firstCondition['table']}.{$firstCondition['foreignKey']}",
            "{$rootTable}.{$firstCondition['localKey']}"
        );
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {

        foreach ($filters as $filter) {
            $query = $this->applyFilter($query, $filter);
        }

        return $query;
    }

    protected function applyFilter(Builder $query, array $filter): Builder
    {
        $field = $filter['field'] ?? null;
        $value = $filter['value'] ?? null;
        $operator = $filter['operator'] ?? 'equals';

        if (!$field || $value === null) {
            return $query;
        }

        if ($field == 'global') {
            return $query;
        }

        if ($this->isRelationshipField($field)) {
            return $this->applyRelationshipFilter($query, $field, $value, $operator);
        }

        $this->applyFilterCondition($query, $field, $value, $operator);

        return $query;
    }

    protected function isRelationshipField(string $field): bool
    {
        return str_contains($field, '.');
    }

    protected function parseRelationshipField(string $field): array
    {
        $parts = explode('.', $field);
        $column = array_pop($parts);
        $relationPath = implode('.', $parts);

        return [
            'relationPath' => $relationPath,
            'column' => $column,
        ];
    }

    protected function applyRelationshipFilter(
        Builder $query,
        string $field,
        mixed $value,
        string $operator
    ): Builder {
        $parsed = $this->parseRelationshipField($field);
        $relationPath = $parsed['relationPath'];
        $column = $parsed['column'];

        return $query->whereHas($relationPath, function ($q) use ($column, $value, $operator) {
            $this->applyFilterCondition($q, $column, $value, $operator);
        });
    }

    protected function applyFilterCondition(
        Builder|QueryBuilder $query,
        string $field,
        mixed $value,
        string $operator
    ): void {
        match ($operator) {
            'equals' => $query->where($field, $value),
            'notEquals' => $query->where($field, '!=', $value),
            'contains' => $query->where($field, 'LIKE', "%{$value}%"),
            'notContains' => $query->where($field, 'NOT LIKE', "%{$value}%"),
            'startsWith' => $query->where($field, 'LIKE', "{$value}%"),
            'endsWith' => $query->where($field, 'LIKE', "%{$value}"),
            'lt' => $query->where($field, '<', $value),
            'lte' => $query->where($field, '<=', $value),
            'gt' => $query->where($field, '>', $value),
            'gte' => $query->where($field, '>=', $value),
            'in' => $query->whereIn($field, (array) $value),
            'notIn' => $query->whereNotIn($field, (array) $value),
            'between' => $query->whereBetween($field, (array) $value),
            'dateIs' => $query->whereDate($field, $value),
            'dateIsNot' => $query->whereDate($field, '!=', $value),
            'dateBefore' => $query->whereDate($field, '<', $value),
            'dateAfter' => $query->whereDate($field, '>', $value),
            default => null,
        };
    }

    protected function applyGlobalFilter(
        Builder $query,
        string $searchTerm,
        array $searchableFields
    ): Builder {
        if (empty($searchableFields)) {
            return $query;
        }

        return $query->where(function ($q) use ($searchTerm, $searchableFields) {
            foreach ($searchableFields as $field) {
                if ($this->isRelationshipField($field)) {
                    $this->applyRelationshipGlobalFilter($q, $field, $searchTerm);
                } else {
                    $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
                }
            }
        });
    }

    protected function applyRelationshipGlobalFilter(
        Builder|QueryBuilder $query,
        string $field,
        string $searchTerm
    ): void {
        $parsed = $this->parseRelationshipField($field);
        $relationPath = $parsed['relationPath'];
        $column = $parsed['column'];

        $query->orWhereHas($relationPath, function ($q) use ($column, $searchTerm) {
            $q->where($column, 'LIKE', "%{$searchTerm}%");
        });
    }

    public function scopeApplyCustomFilters(
        Builder $query,
        array $requestData,
        array $filterConfig
    ): Builder {
        foreach ($filterConfig as $field => $config) {
            if (!isset($requestData[$field]) || $requestData[$field] === null) {
                continue;
            }

            $value = $requestData[$field];
            $type = $config['type'] ?? 'where';
            $callback = $config['callback'] ?? null;

            if ($callback && is_callable($callback)) {
                $callback($query, $value);
                continue;
            }

            switch ($type) {
                case 'where':
                    $query->where($field, $value);
                    break;
                case 'whereIn':
                    $query->whereIn($field, (array) $value);
                    break;
                case 'whereBetween':
                    if (is_array($value) && count($value) === 2) {
                        $query->whereBetween($field, $value);
                    }
                    break;
                case 'whereDate':
                    $query->whereDate($field, $value);
                    break;
                case 'whereLike':
                    $query->where($field, 'LIKE', "%{$value}%");
                    break;
            }
        }

        return $query;
    }
}
