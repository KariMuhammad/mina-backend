# Admin Orders API

Base URL: `{{BASE_URL}}/api/admin`  
Auth: All endpoints require `Authorization: Bearer {{ADMIN_TOKEN}}`  
Content-Type: `application/json`

---

## Endpoints

### 1. Full Order Update — `PATCH /admin/orders/{id}`

Updates any combination of: `status`, `payment_status`, `payment_method`, `tracking_number`, `notes`.
All fields are optional but **at least one** must be provided.

**Request body:**
```json
{
  "status": "Processing",
  "payment_status": "paid",
  "payment_method": "cod",
  "tracking_number": "TRK-123456",
  "notes": "Payment received offline"
}
```

**Allowed values:**

| Field | Allowed values |
|---|---|
| `status` | `Pending`, `Processing`, `Completed`, `Cancelled` (aliases accepted: `pending`, `shipped`, `delivered`, `done`, `cancelled`, …) |
| `payment_status` | `unpaid`, `paid` (`pending` maps to `unpaid`) |
| `payment_method` | `cod`, `none`, `card`, `wallet`, `bank_transfer` |
| `tracking_number` | Any string ≤ 128 chars, or `null` to clear |
| `notes` | Any string ≤ 2000 chars — persisted on the order AND stored as the admin note on each history row |

**Response 200:**
```json
{
  "message": "Order updated successfully.",
  "order": {
    "id": 2,
    "user_id": 5,
    "status": "Processing",
    "payment_status": "paid",
    "payment_method": "cod",
    "tracking_number": "TRK-123456",
    "notes": "Payment received offline",
    "subtotal": 100.00,
    "total_price": 100.00,
    "discount": 0.00,
    "discount_amount": 0.00,
    "final_price": 90.00,
    "coupon_code": null,
    "shipping_address": { "city": "Cairo" },
    "created_at": "2026-04-12T10:00:00+00:00",
    "updated_at": "2026-04-12T13:00:00+00:00"
  },
  "history": [
    {
      "field": "status",
      "old_value": "Pending",
      "new_value": "Processing",
      "admin_id": 1,
      "created_at": "2026-04-12T13:00:00+00:00"
    },
    {
      "field": "payment_status",
      "old_value": "unpaid",
      "new_value": "paid",
      "admin_id": 1,
      "created_at": "2026-04-12T13:00:00+00:00"
    }
  ]
}
```

**Error responses:**
- `401` — Missing or invalid token
- `403` — Authenticated user is not an admin
- `404` — Order not found
- `422` — Validation failure (`{ "message": "...", "errors": { ... } }`)

---

### 2. Status-Only Update — `PATCH /admin/orders/{id}/status`

Updates only the order `status`.

**Request body:**
```json
{ "status": "Completed" }
```

**Response 200:**
```json
{
  "message": "Order status updated.",
  "order": { /* same CustomerOrderFormatter shape */ },
  "history": [
    { "field": "status", "old_value": "Processing", "new_value": "Completed", "admin_id": 1, "created_at": "..." }
  ]
}
```

---

### 3. Payment-Only Update — `PATCH /admin/orders/{id}/payment`

Updates `payment_status` and/or `payment_method`. At least one must be present.

**Request body:**
```json
{ "payment_status": "paid", "payment_method": "card" }
```

**Response 200:**
```json
{
  "message": "Order payment updated.",
  "order": { /* same CustomerOrderFormatter shape */ },
  "history": [
    { "field": "payment_status", "old_value": "unpaid", "new_value": "paid", "admin_id": 1, "created_at": "..." },
    { "field": "payment_method", "old_value": "cod", "new_value": "card", "admin_id": 1, "created_at": "..." }
  ]
}
```

---

## Verification Steps (Postman)

1. **Run migrations:**
   ```
   php artisan migrate
   ```

2. **Get an admin token** — log in with an admin-role user via `POST /api/login`.

3. **Full update (Example A):**
   - `PATCH {{BASE_URL}}/api/admin/orders/2`
   - Body: `{ "status": "Processing", "payment_status": "paid", "payment_method": "cod", "tracking_number": "TRK-123456", "notes": "Payment received offline" }`
   - Expect 200 + `history` array with changed fields.

4. **Payment-only (Example B):**
   - `PATCH {{BASE_URL}}/api/admin/orders/2/payment`
   - Body: `{ "payment_status": "paid", "payment_method": "card" }`

5. **Status-only (Example C):**
   - `PATCH {{BASE_URL}}/api/admin/orders/2/status`
   - Body: `{ "status": "Completed" }`

6. **Customer verification:**
   - `GET {{BASE_URL}}/api/v1/customer/orders/2` with customer token
   - Confirm `status`, `payment_status`, `payment_method`, `tracking_number` are updated.

7. **DB verification:**
   ```sql
   SELECT status, payment_status, payment_method, tracking_number FROM orders WHERE id=2;
   SELECT * FROM order_histories WHERE order_id=2 ORDER BY created_at DESC;
   ```

8. **Run tests:**
   ```
   php artisan test
   ```

---

## New Database Objects

| Object | Migration file |
|---|---|
| `orders.tracking_number` | `2026_04_12_000001_add_tracking_number_to_orders_table.php` |
| `order_histories` table | `2026_04_12_000002_create_order_histories_table.php` |

## Architecture Notes

- **`AdminOrderUpdateService`** (`app/Services/AdminOrderUpdateService.php`) — single entry point for all admin order mutations. Wraps updates in `DB::transaction()` with `lockForUpdate()`. One `order_histories` row is inserted per changed field.
- **`OrderUpdated` event** (`app/Events/OrderUpdated.php`) — dispatched after commit (`ShouldDispatchAfterCommit`). No listeners by default; extensible.
- **No order caching** — no cache invalidation needed; the repo does not cache order queries.
- **Customer responses unchanged** — `CustomerOrderFormatter` is reused; `tracking_number` was added as a new field (backward compatible).
