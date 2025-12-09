<?php

namespace FrancisBeltre\PrimeNgFilters;

class PrimeNgFiltersHelper
{
    /**
     * Get PrimeNG filter rules for validation
     */
    public static function rules(): array
    {
        return [
            'first' => 'nullable|integer|min:0',
            'rows' => 'nullable|integer|min:1|max:1000',
            'sortField' => 'nullable|string',
            'sortOrder' => 'nullable|integer|in:1,-1,0',
            'filters' => 'nullable|array',
            'filters.*.field' => 'required_with:filters|string',
            'filters.*.operator' => 'required_with:filters|string|in:equals,contains,startsWith,endsWith,lt,lte,gt,gte,in,notIn,between',
            'filters.*.value' => 'required_with:filters',
            'globalFilter' => 'nullable|string|max:255',
            'globalFilterFields' => 'nullable|array',
            'globalFilterFields.*' => 'string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:500',
        ];
    }
    
    /**
     * Extract filters from request data
     */
    public static function getFilters(array $requestData): array
    {
        return $requestData['filters'] ?? [];
    }
    
    /**
     * Extract sorting from request data
     */
    public static function getSorting(array $requestData): ?array
    {
        if (empty($requestData['sortField'])) {
            return null;
        }
        
        return [
            'field' => $requestData['sortField'],
            'direction' => ($requestData['sortOrder'] ?? 1) == 1 ? 'asc' : 'desc',
        ];
    }
    
    /**
     * Get pagination parameters
     */
    public static function getPagination(array $requestData): array
    {
        if (isset($requestData['first']) && isset($requestData['rows'])) {
            $rows = max(1, (int) ($requestData['rows'] ?? 15));
            $first = max(0, (int) ($requestData['first'] ?? 0));
            
            return [
                'page' => floor($first / $rows) + 1,
                'per_page' => $rows,
            ];
        }
        
        return [
            'page' => (int) ($requestData['page'] ?? 1),
            'per_page' => (int) ($requestData['per_page'] ?? 15),
        ];
    }
    
    /**
     * Get global filter
     */
    public static function getGlobalFilter(array $requestData): ?string
    {
        return $requestData['globalFilter'] ?? null;
    }
    
    /**
     * Get global filter fields from request
     */
    public static function getGlobalFilterFields(array $requestData): array
    {
        return $requestData['globalFilterFields'] ?? [];
    }
}
