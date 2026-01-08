<?php

namespace FrancisBeltre\PrimeNgFilters\Traits;

use FrancisBeltre\PrimeNgFilters\PrimeNgFiltersHelper;
use Illuminate\Database\Eloquent\Builder;

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

        // Apply individual filters
        if ($filters = PrimeNgFiltersHelper::getFilters($requestData)) {
            if (is_array($filters) && !empty($filters)) {
                $query = $this->applyFilters($query, $filters);
            }
        }

        // Apply global search
        if ($globalFilter = PrimeNgFiltersHelper::getGlobalFilter($requestData)) {
            $fields = $requestData['globalFilterFields'];
            if (!empty($fields)) {
                $query = $this->applyGlobalFilter($query, $globalFilter, $fields);
            }
        }

        // Apply sorting
        if ($sorting = PrimeNgFiltersHelper::getSorting($requestData)) {
            $query->orderBy($sorting['field'], $sorting['direction']);
        }

        return $query;
    }

    /**
     * Apply individual filters to query
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {

        foreach ($filters as $filter) {
            $query = $this->applyFilter($query, $filter);
        }

        return $query;
    }

    /**
     * Apply a single filter
     */
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
        
        switch ($operator) {
            case 'equals':
                return $query->where($field, $value);
            case 'contains':
                return $query->where($field, 'LIKE', "%{$value}%");
            case 'startsWith':
                return $query->where($field, 'LIKE', "{$value}%");
            case 'endsWith':
                return $query->where($field, 'LIKE', "%{$value}");
            case 'lt':
                return $query->where($field, '<', $value);
            case 'lte':
                return $query->where($field, '<=', $value);
            case 'gt':
                return $query->where($field, '>', $value);
            case 'gte':
                return $query->where($field, '>=', $value);
            case 'in':
                return $query->whereIn($field, (array) $value);
            case 'notIn':
                return $query->whereNotIn($field, (array) $value);
            case 'between':
                return $query->whereBetween($field, (array) $value);
            default:
                return $query;
        }
    }

    /**
     * Apply global search across multiple fields
     */
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
                $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
            }
        });
    }

    /**
     * Apply custom filters with configuration
     */
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
