# PrimeNG Filters - Implementation Guide

## What Is This?

This guide helps you build a backend filtering system compatible with PrimeNG tables. Your API will accept query parameters for filtering, sorting, and pagination - then return filtered results.

## How It Works

```
Client sends: ?filters={"name":{"value":"John","matchMode":"contains"}}
    ↓
Your code parses the JSON
    ↓
Builds database query: WHERE name LIKE '%John%'
    ↓
Returns filtered results
```

## What You Need to Build

Three main functions:
1. **Parse Filters** - Convert URL parameters to usable format
2. **Build Query** - Apply filters to database query
3. **Return Results** - Send back data with pagination info

---

## Step 1: Understanding Input Parameters

Your API will receive these parameters:

| Parameter | What It Does | Example |
|-----------|--------------|---------|
| `filters` | JSON with field-specific filters | `{"name":{"value":"John","matchMode":"contains"}}` |
| `globalFilter` | Search term across multiple fields | `"john"` |
| `globalFilterFields` | Which fields to search | `["name","email"]` |
| `sortField` | Field to sort by | `"created_at"` |
| `sortOrder` | Sort direction | `"asc"` or `"desc"` |
| `page` | Page number | `1` |
| `per_page` | Items per page | `15` |

**Example URL:**
```
/api/users?filters={"status":{"value":"active","matchMode":"equals"}}&page=1&per_page=20
```

---

## Step 2: Parse the Filters

Convert the JSON string to a usable format.

**Input from client:**
```json
{
  "name": {
    "value": "John",
    "matchMode": "contains"
  }
}
```

**Your code converts to:**
```json
{
  "field": "name",
  "value": "John",
  "operator": "contains"
}
```

**Simple pseudocode:**
```javascript
function parseFilters(filtersJson) {
    if (!filtersJson) return []

    filters = JSON.parse(filtersJson)
    result = []

    for each (fieldName, filterData) in filters {
        result.push({
            field: fieldName,
            value: filterData.value,
            operator: filterData.matchMode || "equals"
        })
    }

    return result
}
```

---

## Step 3: Apply Filters to Database Query

Convert each operator to a database query.

### Supported Operators

| Operator | SQL Example | What It Does |
|----------|-------------|--------------|
| `equals` | `WHERE name = 'John'` | Exact match |
| `contains` | `WHERE name LIKE '%John%'` | Contains text |
| `startsWith` | `WHERE name LIKE 'John%'` | Starts with text |
| `endsWith` | `WHERE name LIKE '%John'` | Ends with text |
| `lt` | `WHERE age < 30` | Less than |
| `lte` | `WHERE age <= 30` | Less than or equal |
| `gt` | `WHERE age > 30` | Greater than |
| `gte` | `WHERE age >= 30` | Greater than or equal |
| `in` | `WHERE status IN ('active', 'pending')` | Value in list |
| `notIn` | `WHERE status NOT IN ('deleted')` | Value not in list |
| `between` | `WHERE age BETWEEN 18 AND 65` | Value in range |

### Simple Implementation

```javascript
function applyFilter(query, filter) {
    field = filter.field
    value = filter.value
    operator = filter.operator

    switch (operator) {
        case "equals":
            query.where(field, "=", value)
            break

        case "contains":
            query.where(field, "LIKE", "%" + value + "%")
            break

        case "startsWith":
            query.where(field, "LIKE", value + "%")
            break

        case "endsWith":
            query.where(field, "LIKE", "%" + value)
            break

        case "gt":
            query.where(field, ">", value)
            break

        case "gte":
            query.where(field, ">=", value)
            break

        case "lt":
            query.where(field, "<", value)
            break

        case "lte":
            query.where(field, "<=", value)
            break

        case "in":
            query.whereIn(field, value)
            break

        case "notIn":
            query.whereNotIn(field, value)
            break

        case "between":
            query.whereBetween(field, value[0], value[1])
            break
    }

    return query
}
```

---

## Step 4: Build Complete Query

Put everything together.

```javascript
function getFilteredData(params) {
    // Start with base query
    query = db.from('users')

    // 1. Apply individual filters
    if (params.filters) {
        filters = parseFilters(params.filters)
        for each filter in filters {
            query = applyFilter(query, filter)
        }
    }

    // 2. Apply global search (searches multiple fields)
    if (params.globalFilter && params.globalFilterFields) {
        searchTerm = params.globalFilter
        fields = params.globalFilterFields

        query.where(function(q) {
            for each field in fields {
                q.orWhere(field, 'LIKE', '%' + searchTerm + '%')
            }
        })
    }

    // 3. Apply sorting
    if (params.sortField) {
        direction = (params.sortOrder == 'desc' || params.sortOrder == '-1') ? 'desc' : 'asc'
        query.orderBy(params.sortField, direction)
    }

    // 4. Get total count (before pagination)
    totalCount = query.count()

    // 5. Apply pagination
    page = params.page || 1
    perPage = params.per_page || 15

    offset = (page - 1) * perPage
    query.limit(perPage).offset(offset)

    // 6. Execute and return
    data = query.get()

    return {
        data: data,
        total: totalCount,
        page: page,
        per_page: perPage,
        total_pages: Math.ceil(totalCount / perPage)
    }
}
```

---

## Step 5: Security (IMPORTANT!)

### Prevent SQL Injection

**✅ DO: Use parameterized queries**
```javascript
// Good - safe from SQL injection
query.where('name', '=', userInput)
```

**❌ DON'T: Concatenate user input**
```javascript
// Bad - vulnerable to SQL injection
query.raw("WHERE name = '" + userInput + "'")
```

### Whitelist Allowed Fields

Only allow filtering on specific fields:

```javascript
allowedFields = ['name', 'email', 'status', 'created_at']

if (!allowedFields.includes(filter.field)) {
    throw new Error('Invalid field')
}
```

### Validate Input

```javascript
// Check page is positive integer
if (page < 1) page = 1

// Limit per_page
if (per_page > 500) per_page = 500

// Limit global search length
if (globalFilter.length > 255) {
    throw new Error('Search term too long')
}
```

---

## Testing Your Implementation

### Basic Tests

Test with these URLs:

1. **Simple filter:**
   ```
   /api/users?filters={"status":{"value":"active","matchMode":"equals"}}
   ```
   Expected: Only active users

2. **Contains search:**
   ```
   /api/users?filters={"name":{"value":"john","matchMode":"contains"}}
   ```
   Expected: Users with "john" in name

3. **Multiple filters:**
   ```
   /api/users?filters={"status":{"value":"active","matchMode":"equals"},"age":{"value":18,"matchMode":"gte"}}
   ```
   Expected: Active users 18 or older

4. **Global search:**
   ```
   /api/users?globalFilter=john&globalFilterFields[]=name&globalFilterFields[]=email
   ```
   Expected: Users with "john" in name OR email

5. **Pagination:**
   ```
   /api/users?page=2&per_page=10
   ```
   Expected: 10 users, page 2

6. **Sorting:**
   ```
   /api/users?sortField=created_at&sortOrder=desc
   ```
   Expected: Users sorted by date, newest first

---

## Complete Examples by Language

### Python (SQLAlchemy)

```python
import json
from sqlalchemy import or_

def parse_filters(filters_json):
    if not filters_json:
        return []

    filters = json.loads(filters_json)
    result = []

    for field, data in filters.items():
        result.append({
            'field': field,
            'value': data.get('value'),
            'operator': data.get('matchMode', 'equals')
        })

    return result

def apply_filter(query, model, filter_data):
    field = filter_data['field']
    value = filter_data['value']
    operator = filter_data['operator']

    column = getattr(model, field)

    if operator == 'equals':
        return query.filter(column == value)
    elif operator == 'contains':
        return query.filter(column.like(f'%{value}%'))
    elif operator == 'gt':
        return query.filter(column > value)
    elif operator == 'gte':
        return query.filter(column >= value)
    elif operator == 'in':
        return query.filter(column.in_(value))
    # ... add other operators

    return query

def get_filtered_users(params):
    query = db.session.query(User)

    # Apply filters
    if params.get('filters'):
        filters = parse_filters(params['filters'])
        for f in filters:
            query = apply_filter(query, User, f)

    # Apply sorting
    if params.get('sortField'):
        direction = 'desc' if params.get('sortOrder') == 'desc' else 'asc'
        query = query.order_by(getattr(User, params['sortField']).desc() if direction == 'desc' else getattr(User, params['sortField']))

    # Get total
    total = query.count()

    # Apply pagination
    page = int(params.get('page', 1))
    per_page = int(params.get('per_page', 15))
    query = query.offset((page - 1) * per_page).limit(per_page)

    return {
        'data': query.all(),
        'total': total,
        'page': page,
        'per_page': per_page
    }
```

### Node.js (TypeORM)

```javascript
function parseFilters(filtersJson) {
    if (!filtersJson) return [];

    const filters = JSON.parse(filtersJson);
    const result = [];

    for (const [field, data] of Object.entries(filters)) {
        result.push({
            field: field,
            value: data.value,
            operator: data.matchMode || 'equals'
        });
    }

    return result;
}

function applyFilter(query, filter, alias) {
    const { field, value, operator } = filter;

    switch (operator) {
        case 'equals':
            return query.andWhere(`${alias}.${field} = :value`, { value });

        case 'contains':
            return query.andWhere(`${alias}.${field} LIKE :value`, { value: `%${value}%` });

        case 'gt':
            return query.andWhere(`${alias}.${field} > :value`, { value });

        case 'gte':
            return query.andWhere(`${alias}.${field} >= :value`, { value });

        case 'in':
            return query.andWhere(`${alias}.${field} IN (:...values)`, { values: value });

        // ... add other operators
    }

    return query;
}

async function getFilteredUsers(params) {
    let query = getRepository(User).createQueryBuilder('user');

    // Apply filters
    if (params.filters) {
        const filters = parseFilters(params.filters);
        filters.forEach(f => {
            query = applyFilter(query, f, 'user');
        });
    }

    // Apply sorting
    if (params.sortField) {
        const direction = params.sortOrder === 'desc' ? 'DESC' : 'ASC';
        query.orderBy(`user.${params.sortField}`, direction);
    }

    // Get total
    const total = await query.getCount();

    // Apply pagination
    const page = parseInt(params.page) || 1;
    const perPage = parseInt(params.per_page) || 15;
    query.skip((page - 1) * perPage).take(perPage);

    const data = await query.getMany();

    return {
        data,
        total,
        page,
        per_page: perPage
    };
}
```

### PHP (Laravel)

```php
function parseFilters($filtersJson) {
    if (empty($filtersJson)) {
        return [];
    }

    $filters = json_decode($filtersJson, true);
    $result = [];

    foreach ($filters as $field => $data) {
        $result[] = [
            'field' => $field,
            'value' => $data['value'] ?? null,
            'operator' => $data['matchMode'] ?? 'equals'
        ];
    }

    return $result;
}

function applyFilter($query, $filter) {
    $field = $filter['field'];
    $value = $filter['value'];
    $operator = $filter['operator'];

    switch ($operator) {
        case 'equals':
            return $query->where($field, '=', $value);

        case 'contains':
            return $query->where($field, 'LIKE', "%{$value}%");

        case 'startsWith':
            return $query->where($field, 'LIKE', "{$value}%");

        case 'endsWith':
            return $query->where($field, 'LIKE', "%{$value}");

        case 'gt':
            return $query->where($field, '>', $value);

        case 'gte':
            return $query->where($field, '>=', $value);

        case 'in':
            return $query->whereIn($field, $value);

        case 'notIn':
            return $query->whereNotIn($field, $value);

        case 'between':
            return $query->whereBetween($field, $value);
    }

    return $query;
}

function getFilteredUsers($params) {
    $query = User::query();

    // Apply filters
    if (!empty($params['filters'])) {
        $filters = parseFilters($params['filters']);
        foreach ($filters as $filter) {
            $query = applyFilter($query, $filter);
        }
    }

    // Apply global search
    if (!empty($params['globalFilter']) && !empty($params['globalFilterFields'])) {
        $query->where(function($q) use ($params) {
            foreach ($params['globalFilterFields'] as $field) {
                $q->orWhere($field, 'LIKE', "%{$params['globalFilter']}%");
            }
        });
    }

    // Apply sorting
    if (!empty($params['sortField'])) {
        $direction = ($params['sortOrder'] ?? 'asc') == 'desc' ? 'desc' : 'asc';
        $query->orderBy($params['sortField'], $direction);
    }

    // Get total
    $total = $query->count();

    // Apply pagination
    $page = $params['page'] ?? 1;
    $perPage = $params['per_page'] ?? 15;

    $data = $query->offset(($page - 1) * $perPage)
                  ->limit($perPage)
                  ->get();

    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage
    ];
}
```

---

## Response Format

Return data in this format:

```json
{
  "data": [
    {"id": 1, "name": "John", "email": "john@example.com"},
    {"id": 2, "name": "Jane", "email": "jane@example.com"}
  ],
  "total": 100,
  "page": 1,
  "per_page": 15,
  "total_pages": 7
}
```

---

## Quick Checklist

Before going live, make sure you:

- ✅ Use parameterized queries (no SQL injection)
- ✅ Whitelist allowed fields
- ✅ Validate all inputs (page numbers, limits, etc.)
- ✅ Add database indexes on filtered/sorted fields
- ✅ Limit per_page to reasonable max (like 500)
- ✅ Test with real PrimeNG frontend
- ✅ Handle errors gracefully

---

## Common Issues

**Problem:** Filters not working
- Check JSON is properly decoded
- Verify field names match database columns
- Use browser dev tools to see actual request

**Problem:** SQL errors
- Make sure you're using parameterized queries
- Check field names are whitelisted
- Verify data types match (string vs number)

**Problem:** Slow queries
- Add database indexes on filtered fields
- Limit the number of records returned
- Consider caching frequent queries

---

## Need Help?

Check the main documentation or look at the working PHP implementation in this repository for reference.
