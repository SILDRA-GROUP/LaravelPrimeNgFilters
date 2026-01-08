## Parámetros de Consulta Disponibles

### 1. `filters` (Cadena JSON)

Aplica filtros individuales de campo a la consulta.

**Tipo:** Cadena codificada en JSON
**Requerido:** No
**Formato:**
```json
{
  "nombre_campo": {
    "value": "valor_búsqueda",
    "matchMode": "operador"
  }
}
```

#### Operadores Soportados (`matchMode`)

| Operador | Descripción | Valor de Ejemplo |
|----------|-------------|------------------|
| `equals` | Coincidencia exacta (predeterminado) | `"John"` |
| `contains` | Contiene subcadena (LIKE %valor%) | `"john"` |
| `startsWith` | Comienza con subcadena (LIKE valor%) | `"Jo"` |
| `endsWith` | Termina con subcadena (LIKE %valor) | `"hn"` |
| `lt` | Menor que | `100` |
| `lte` | Menor o igual que | `100` |
| `gt` | Mayor que | `50` |
| `gte` | Mayor o igual que | `50` |
| `in` | Valor en arreglo | `["active", "pending"]` |
| `notIn` | Valor no está en arreglo | `["deleted", "archived"]` |
| `between` | Valor entre rango | `[10, 100]` |

#### Ejemplo de Solicitud

```
GET /api/users?filters={"name":{"value":"John","matchMode":"contains"},"age":{"value":25,"matchMode":"gte"}}
```

Esto filtra usuarios donde `name` contiene "John" Y `age` es mayor o igual a 25.

#### Ejemplo de Múltiples Filtros

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

---

### 2. `globalFilter` (Cadena)

Aplica una búsqueda global en múltiples campos.

**Tipo:** Cadena
**Requerido:** No
**Longitud Máxima:** 255 caracteres
**Nota:** Debe usarse junto con `globalFilterFields`

#### Ejemplo de Solicitud

```
GET /api/users?globalFilter=john&globalFilterFields[]=name&globalFilterFields[]=email
```

Esto busca "john" en ambos campos `name` y `email` usando una consulta LIKE %john%.

---

### 3. `globalFilterFields` (Arreglo)

Especifica en qué campos buscar cuando se usa `globalFilter`.

**Tipo:** Arreglo de nombres de campos
**Requerido:** Solo cuando se usa `globalFilter`

#### Ejemplo de Solicitud

```
GET /api/users?globalFilter=search_term&globalFilterFields[]=name&globalFilterFields[]=email&globalFilterFields[]=phone
```

---

### 4. `sortField` (Cadena)

Nombre del campo por el cual ordenar los resultados.

**Tipo:** Cadena
**Requerido:** No

#### Ejemplo de Solicitud

```
GET /api/users?sortField=created_at
```

---

### 5. `sortOrder` (Cadena/Entero)

Dirección del ordenamiento.

**Tipo:** Cadena o Entero
**Requerido:** No
**Predeterminado:** Ascendente
**Valores Permitidos:**
- `"asc"` o `1` para ascendente
- `"desc"` o `-1` para descendente

#### Ejemplos de Solicitudes

```
GET /api/users?sortField=name&sortOrder=asc
GET /api/users?sortField=created_at&sortOrder=-1
```

---

### 6. `page` (Entero)

Número de página actual para paginación.

**Tipo:** Entero
**Requerido:** No
**Predeterminado:** 1
**Valor Mínimo:** 1

#### Ejemplo de Solicitud

```
GET /api/users?page=2
```

---

### 7. `per_page` (Entero)

Número de elementos por página.

**Tipo:** Entero
**Requerido:** No
**Predeterminado:** 15
**Valor Mínimo:** 1
**Valor Máximo:** 500

#### Ejemplo de Solicitud

```
GET /api/users?page=2&per_page=25
```

---

## Ejemplo Completo

### URL de Solicitud
```
GET /api/users?filters={"status":{"value":"active","matchMode":"equals"},"age":{"value":18,"matchMode":"gte"}}&globalFilter=john&globalFilterFields[]=name&globalFilterFields[]=email&sortField=created_at&sortOrder=desc&page=1&per_page=20
```

### Desglose
- Filtra usuarios donde `status` es igual a "active"
- Y `age` es mayor o igual a 18
- Busca "john" en los campos `name` y `email`
- Ordena por `created_at` en orden descendente
- Muestra página 1 con 20 elementos por página

---

**Reglas:**
- `filters`: nullable, debe ser JSON válido
- `sortField`: nullable, cadena
- `sortOrder`: nullable, cadena, debe ser uno de: asc, desc, 1, -1
- `globalFilter`: nullable, cadena, máximo 255 caracteres
- `globalFilterFields`: nullable, arreglo
- `page`: nullable, entero, mínimo 1
- `per_page`: nullable, entero, mínimo 1, máximo 500
