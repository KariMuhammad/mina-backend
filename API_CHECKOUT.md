# Customer checkout, orders, and addresses (API v1)

All `/api/v1/customer/*` routes below require authentication: send header `Authorization: Bearer <token>` and `Accept: application/json`.

Base URL examples use `http://localhost:8000`.

## Postman flow (reviewer)

1. **Login** — `POST /api/login` with `email`, `password` (or use `/api/register`). Copy `token` from the response.
2. **Cart** — `GET /api/v1/customer/cart/list` (optional: add items with `POST /api/v1/customer/cart/add` and body `product_id`, `quantity`).
3. **Cart summary** — `GET /api/v1/customer/cart/summary` — returns `items`, `subtotal`, and active `coupons`.
4. **Address** — `POST /api/v1/customer/addresses` with JSON body, for example:

   ```json
   {
     "label": "Home",
     "phone": "+10000000000",
     "address_line": "123 Main St",
     "city": "Cairo",
     "postal_code": "12345",
     "country": "EG",
     "is_default": true
   }
   ```

5. **Checkout** — `POST /api/v1/customer/checkout` with:

   ```json
   {
     "shipping_address_id": 1,
     "coupon_code": null,
     "payment_method": "cod",
     "notes": "Please call on arrival"
   }
   ```

   - `payment_method` must be `"cod"` or `"none"` (Cash on Delivery is supported; no payment gateway).
   - Alternatively, omit `shipping_address_id` and send an inline `shipping_address` object with `address_line` (or `address_line_1`), `city`, and `postal_code` or `zip`.
   - Legacy alias: you may send `address_id` instead of `shipping_address_id`; it is normalized to `shipping_address_id`.

6. **Orders** — `GET /api/v1/customer/orders` (paginated; optional query `per_page`).  
   **Details** — `GET /api/v1/customer/orders/{id}` returns `{ "order", "items" }` in the same shape as the checkout response `order` / `items`.

7. **Logout** — `POST /api/logout` or `POST /api/v1/auth/logout` (with Bearer token). Response: `{ "message": "Logged out successfully" }`.

## Guest checkout (no account)

`POST /api/checkout?guest_id=<guest_uuid>` with guest shipping fields (see `CheckoutRequest` / OpenAPI). Authenticated users can also use this route with `address_id` and optional `payment_method` / `notes`.

## Error responses (JSON)

| Status | When |
|--------|------|
| 400 | Empty cart: `{ "message": "Your cart is empty." }` |
| 403 | Wrong owner for address or order: `{ "message": "Access denied! This resource does not belong to you." }` |
| 404 | Missing address or order: `{ "message": "Resource not found." }` |
| 422 | Validation (including invalid `payment_method`, coupons, or stock messages under `errors`) |

## Order totals in responses

- `subtotal` — sum of line items before coupon.
- `total_price` — same as subtotal (gross).
- `discount` / `discount_amount` — coupon discount.
- `final_price` — amount after discount (stored in DB as `orders.total_price`).

Order `status` values: `Pending`, `Processing`, `Completed`, `Cancelled`.  
For `payment_method: "cod"`, new orders use `payment_status: "unpaid"` until you mark them paid in admin or future payment logic.
