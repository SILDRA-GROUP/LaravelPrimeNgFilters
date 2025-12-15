<?php

namespace FrancisBeltre\PrimeNgFilters;

class PrimeNgFiltersHelper
{
    public static function rules(): array
    {
        return [
            'filters' => 'nullable|json',
            'sortField' => 'nullable|string',
            'sortOrder' => 'nullable|string|in:asc,desc,1,-1',
            'globalFilter' => 'nullable|string|max:255',
            'globalFilterFields' => 'nullable|json',
            'first' => 'nullable|integer|min:0',
            'rows' => 'nullable|integer|min:1|max:1000',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:500',
        ];
    }

    /**
     * Get filters from request
     */
    public static function getFilters(array $requestData): array
    {
        if (!isset($requestData['filters'])) {
            return [];
        }

        $filtersJson = json_decode($requestData['filters'], true);

        if (!$filtersJson || !is_array($filtersJson)) {
            return [];
        }

        $transformedFilters = [];

        foreach ($filtersJson as $fieldName => $filterData) {
            if (!is_array($filterData)) {
                continue;
            }

            $transformedFilters[] = [
                'field' => $fieldName,
                'value' => $filterData['value'] ?? null,
                'operator' => $filterData['matchMode'] ?? 'equals',
            ];
        }

        return $transformedFilters;
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
        return json_decode($requestData['globalFilterFields'], true) ?? [];
    }
}
