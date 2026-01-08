## Available Query Parameters

### 1. `filters` (JSON String)

Applies individual field filters to the query.

**Type:** JSON-encoded string
**Required:** No
**Format:**
```json
{
  "field_name": {
    "value": "search_value",
    "matchMode": "operator"
  }
}
```

#### Supported Operators (`matchMode`)

| Operator | Description | Example Value |
|----------|-------------|---------------|
| `equals` | Exact match (default) | `"John"` |
| `contains` | Contains substring (LIKE %value%) | `"john"` |
| `startsWith` | Starts with substring (LIKE value%) | `"Jo"` |
| `endsWith` | Ends with substring (LIKE %value) | `"hn"` |
| `lt` | Less than | `100` |
| `lte` | Less than or equal | `100` |
| `gt` | Greater than | `50` |
| `gte` | Greater than or equal | `50` |
| `in` | Value in array | `["active", "pending"]` |
| `notIn` | Value not in array | `["deleted", "archived"]` |
| `between` | Value between range | `[10, 100]` |

#### Example Request

```
GET /api/users?filters={"name":{"value":"John","matchMode":"contains"},"age":{"value":25,"matchMode":"gte"}}
```

This filters users where `name` contains "John" AND `age` is greater than or equal to 25.

#### Multiple Filters Example

```json
{
  "status": {
    "value": ["active", "pending"],
    "matchMode": "in"
  },
  "email": {
    "value": "@gmail.com",
    "matchMode": "endsWith"
  },
  "created_at": {
    "value": ["2024-01-01", "2024-12-31"],
    "matchMode": "between"
  }
}
```

URL-encoded:
```
GET /api/users?filters=%7B%22status%22%3A%7B%22value%22%3A%5B%22active%22%2C%22pending%22%5D%2C%22matchMode%22%3A%22in%22%7D%2C%22email%22%3A%7B%22value%22%3A%22%40gmail.com%22%2C%22matchMode%22%3A%22endsWith%22%7D%7D
```

---

### 2. `globalFilter` (String)

Applies a global search across multiple fields.

**Type:** String
**Required:** No
**Max Length:** 255 characters
**Note:** Must be used together with `globalFilterFields`

#### Example Request

```
GET /api/users?globalFilter=john&globalFilterFields[]=name&globalFilterFields[]=email
```

This searches for "john" in both the `name` and `email` fields using a LIKE %john% query.

---

### 3. `globalFilterFields` (Array)

Specifies which fields to search when using `globalFilter`.

**Type:** Array of field names
**Required:** Only when using `globalFilter`

#### Example Request

```
GET /api/users?globalFilter=search_term&globalFilterFields[]=name&globalFilterFields[]=email&globalFilterFields[]=phone
```

---

### 4. `sortField` (String)

Field name to sort results by.

**Type:** String
**Required:** No

#### Example Request

```
GET /api/users?sortField=created_at
```

---

### 5. `sortOrder` (String/Integer)

Sort direction.

**Type:** String or Integer
**Required:** No
**Default:** Ascending
**Allowed Values:**
- `"asc"` or `1` for ascending
- `"desc"` or `-1` for descending

#### Example Requests

```
GET /api/users?sortField=name&sortOrder=asc
GET /api/users?sortField=created_at&sortOrder=-1
```

---

### 6. `page` (Integer)

Current page number for pagination.

**Type:** Integer
**Required:** No
**Default:** 1
**Min Value:** 1

#### Example Request

```
GET /api/users?page=2
```

---

### 7. `per_page` (Integer)

Number of items per page.

**Type:** Integer
**Required:** No
**Default:** 15
**Min Value:** 1
**Max Value:** 500

#### Example Request

```
GET /api/users?page=2&per_page=25
```

---

## Complete Example

### Request URL
```
GET /api/users?filters={"status":{"value":"active","matchMode":"equals"},"age":{"value":18,"matchMode":"gte"}}&globalFilter=john&globalFilterFields[]=name&globalFilterFields[]=email&sortField=created_at&sortOrder=desc&page=1&per_page=20
```

### Breakdown
- Filter users where `status` equals "active"
- AND `age` is greater than or equal to 18
- Search for "john" in `name` and `email` fields
- Sort by `created_at` in descending order
- Show page 1 with 20 items per page

---

**Rules:**
- `filters`: nullable, must be valid JSON
- `sortField`: nullable, string
- `sortOrder`: nullable, string, must be one of: asc, desc, 1, -1
- `globalFilter`: nullable, string, max 255 characters
- `globalFilterFields`: nullable, array
- `page`: nullable, integer, min 1
- `per_page`: nullable, integer, min 1, max 500
