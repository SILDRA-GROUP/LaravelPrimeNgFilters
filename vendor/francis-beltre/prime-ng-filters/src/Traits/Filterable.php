<?php

namespace FrancisBeltre\PrimeNgFilters\Traits;

use Illuminate\Database\Eloquent\Builder;
use FrancisBeltre\PrimeNgFilters\PrimeNgFilterHelper;

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
        if ($filters = PrimeNgFilterHelper::getFilters($requestData)) {
            $query = $this->applyFilters($query, $filters);
        }

        // Apply global search
        if ($globalFilter = PrimeNgFilterHelper::getGlobalFilter($requestData)) {
            $fields = $this->resolveSearchableFields($searchableFields, $requestData);
            if (!empty($fields)) {
                $query = $this->applyGlobalFilter($query, $globalFilter, $fields);
            }
        }

        // Apply sorting
        if ($sorting = PrimeNgFilterHelper::getSorting($requestData)) {
            $query->orderBy($sorting['field'], $sorting['direction']);
        }

        return $query;
    }

    /**
     * Resolve which searchable fields to use
     */
    protected function resolveSearchableFields($searchableFields, array $requestData): array
    {
        // Priority 1: Explicitly passed from controller
        if ($searchableFields !== null) {
            if ($searchableFields === 'all') {
                return $this->getAllSearchableColumns();
            }
            if (is_array($searchableFields)) {
                return $searchableFields;
            }
        }

        // Priority 2: From request data (if provided)
        if (isset($requestData['globalFilterFields'])) {
            return (array) $requestData['globalFilterFields'];
        }

        // Priority 3: From model property
        if (property_exists($this, 'searchableFields')) {
            return $this->searchableFields;
        }

        // Priority 4: Try to get from model's fillable or visible
        return $this->getDefaultSearchableFields();
    }

    /**
     * Get all database columns for the model (use with caution!)
     */
    protected function getAllSearchableColumns(): array
    {
        if (method_exists($this, 'getConnection') && method_exists($this, 'getTable')) {
            try {
                $columns = $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
                return array_diff($columns, ['created_at', 'updated_at', 'deleted_at']);
            } catch (\Exception $e) {
                // Fallback to empty array
            }
        }
        return [];
    }

    /**
     * Get default searchable fields from model
     */
    protected function getDefaultSearchableFields(): array
    {
        if (property_exists($this, 'fillable')) {
            return $this->fillable;
        }
        
        if (property_exists($this, 'visible')) {
            return $this->visible;
        }
        
        return [];
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

        if (!$field || $value === null || $value === '') {
            return $query;
        }

        switch ($operator) {
            case 'equals': return $query->where($field, $value);
            case 'contains': return $query->where($field, 'LIKE', "%{$value}%");
            case 'startsWith': return $query->where($field, 'LIKE', "{$value}%");
            case 'endsWith': return $query->where($field, 'LIKE', "%{$value}");
            case 'lt': return $query->where($field, '<', $value);
            case 'lte': return $query->where($field, '<=', $value);
            case 'gt': return $query->where($field, '>', $value);
            case 'gte': return $query->where($field, '>=', $value);
            case 'in': return $query->whereIn($field, (array) $value);
            case 'notIn': return $query->whereNotIn($field, (array) $value);
            case 'between': return $query->whereBetween($field, (array) $value);
            default: return $query;
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
