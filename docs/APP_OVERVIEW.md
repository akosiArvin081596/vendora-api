# VENDORA Backend Overview

Last reviewed: 2026-01-12
Last updated by: Codex
Last updated on: 2026-01-12

## Purpose and scope
- API-first Laravel backend for a vendor/store management system.
- Primary domains: products, inventory, customers, orders, payments, stores, staff, and dashboard analytics.
- Authentication is token-based (Laravel Sanctum) and the API is documented using OpenAPI annotations (L5 Swagger).

## Tech stack and tooling
- PHP 8.4.15, Laravel 12.46.0
- Database: MySQL (per application info)
- Auth: Laravel Sanctum (personal access tokens)
- API docs: darkaonline/l5-swagger (OpenAPI attributes in controllers)
- Testing: Pest 4 + Laravel testing utilities
- Frontend tooling (minimal): Vite 7, Tailwind CSS 4, laravel-vite-plugin

## Repo structure (high-level)
- `app/Enums`: Store roles and permissions
- `app/Http/Controllers/Api`: API endpoints for auth, products, orders, etc.
- `app/Http/Requests`: Form Requests for validation
- `app/Http/Resources`: API Resource transformers for JSON output
- `app/Http/Middleware`: Store context middleware
- `app/Models`: Eloquent models for core domain
- `app/Policies`: Store authorization policy
- `app/Traits`: Auditing and store-context helpers
- `database/migrations`: Schema + data migrations
- `database/factories`: Model factories for tests/seeding
- `database/seeders`: Seeders (only Test User wired in DatabaseSeeder)
- `routes/api.php`: API routes (Sanctum-protected)
- `routes/web.php`: Default welcome page only
- `tests/Feature`: API and security tests

## Core domain model (entities + relationships)

### User
- Fields: name, business_name, email, password, subscription_plan, user_type
- Relationships:
  - `products()`, `inventoryAdjustments()`, `customers()`, `orders()`
  - `ownedStores()` (stores created/owned by user)
  - `assignedStores()` (many-to-many via `store_user` pivot)
  - `allAccessibleStores()` merges owned + assigned

### Store
- Fields: user_id (owner), name, code, address, phone, email, is_active, settings (json)
- Relationships:
  - `owner()` -> User
  - `staff()` -> many-to-many users via `store_user` (role, permissions, assigned_at)
  - `products()` -> many-to-many products via `store_products` (stock, price overrides, availability)
  - `storeProducts()` -> StoreProduct records
  - `orders()`, `customers()`, `payments()`, `inventoryAdjustments()`

### Product
- Fields: user_id, category_id, name, sku, price, currency, stock, min_stock, max_stock
- Relationships:
  - `user()`, `category()`
  - `inventoryAdjustments()`, `orderItems()`
  - `storeProducts()` and `stores()` (per-store inventory via `store_products`)

### StoreProduct (per-store inventory)
- Fields: store_id, product_id, stock, min_stock, max_stock, price_override, is_available
- Helpers:
  - `effectivePrice` attribute (override or base product price)
  - `isLowStock()` / `isOutOfStock()`

### Category
- Fields: name (unique)
- Relationship: `products()`

### Customer
- Fields: user_id, store_id, name, email, phone, status, orders_count, total_spent
- Relationships: `user()`, `store()`, `orders()`

### Order
- Fields: user_id, store_id, customer_id, processed_by, order_number, ordered_at, status, items_count, total, currency
- Relationships: `user()`, `store()`, `customer()`, `items()`, `payments()`, `processedBy()`

### OrderItem
- Fields: order_id, product_id, quantity, unit_price, line_total
- Relationships: `order()`, `product()`

### Payment
- Fields: user_id, store_id, order_id, payment_number, paid_at, amount, currency, method, status
- Relationships: `user()`, `order()`, `store()`

### InventoryAdjustment
- Fields: user_id, store_id, product_id, type, quantity, stock_before, stock_after, note
- Relationships: `user()`, `store()`, `product()`

### AuditLog
- Fields: user_id, store_id, action, model_type, model_id, old_values, new_values, ip_address, user_agent
- Behavior:
  - `AuditLog::log(...)` helper writes audit entries
  - `Auditable` trait logs create/update/delete on models using it

## API surface (routes/api.php)

All API routes are protected by `auth:sanctum` except login/register.

### Auth
- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout` (requires auth)
- Rate limit on auth endpoints: `throttle:5,1`

### User
- `GET /api/user` (current authenticated user)

### Dashboard widgets
- `GET /api/dashboard/kpis`
- `GET /api/dashboard/sales-trend`
- `GET /api/dashboard/orders-by-channel`
- `GET /api/dashboard/payment-methods`
- `GET /api/dashboard/top-products`
- `GET /api/dashboard/inventory-health`
- `GET /api/dashboard/low-stock-alerts`
- `GET /api/dashboard/pending-orders`
- `GET /api/dashboard/recent-activity`

### Categories
- `GET /api/categories`

### Products
- `GET /api/products`
- `POST /api/products`
- `GET /api/products/{product}`
- `PUT/PATCH /api/products/{product}`
- `DELETE /api/products/{product}`

### Inventory
- `GET /api/inventory`
- `GET /api/inventory/summary`
- `POST /api/inventory/adjustments`

### Customers
- `GET /api/customers`
- `GET /api/customers/summary`
- `POST /api/customers`
- `GET /api/customers/{customer}`
- `PUT/PATCH /api/customers/{customer}`
- `DELETE /api/customers/{customer}`

### Orders
- `GET /api/orders`
- `GET /api/orders/summary`
- `POST /api/orders`
- `GET /api/orders/{order}`
- `PUT/PATCH /api/orders/{order}`
- `DELETE /api/orders/{order}`

### Payments
- `GET /api/payments`
- `GET /api/payments/summary`
- `POST /api/payments`
- `GET /api/payments/{payment}`
- `PUT/PATCH /api/payments/{payment}`
- `DELETE /api/payments/{payment}`

### Stores & staff
- `GET /api/stores`
- `POST /api/stores`
- `GET /api/stores/{store}`
- `PUT/PATCH /api/stores/{store}`
- `DELETE /api/stores/{store}`
- `GET /api/stores/{store}/staff`
- `POST /api/stores/{store}/staff`
- `PATCH /api/stores/{store}/staff/{user}`
- `DELETE /api/stores/{store}/staff/{user}`
- `GET /api/store-roles`

### Store products (per-store inventory)
- `GET /api/stores/{store}/products`
- `POST /api/stores/{store}/products`
- `GET /api/stores/{store}/products/{product}`
- `PATCH /api/stores/{store}/products/{product}`
- `DELETE /api/stores/{store}/products/{product}`

## Authentication & authorization
- Uses Sanctum personal access tokens; `auth:sanctum` middleware guards API routes.
- Login/register include `user_type` and `subscription_plan` validation.
- Store access authorization is enforced in `StorePolicy`.
- Store roles and permissions are centralized in `App\Enums\StoreRole`.

## Store context
- `SetStoreContext` middleware supports `X-Store-Id` header or `store_id` input.
- `HasStoreContext` trait adds helpers to resolve or require a current store.
- Note: `store.context` middleware is registered as an alias in `bootstrap/app.php` but is not currently applied to the API route groups, so store context is only set if the middleware is explicitly added to routes.

## Data model (tables, key fields, and indices)
- `users` + `password_reset_tokens` + `sessions`
- `categories` (unique name)
- `products` (unique sku per user, price/stock + thresholds)
- `inventory_adjustments` (product history)
- `customers` (unique email/phone per user, status)
- `orders` (unique order_number per user, status, totals, ordered_at)
- `order_items` (order/product line items)
- `payments` (unique payment_number per user, method/status)
- `audit_logs` (model change + auth event trail)
- `stores` (unique code per user)
- `store_user` (store staff + roles + permissions)
- `store_products` (per-store stock + overrides)

A data migration (`2026_01_12_063717_migrate_existing_data_to_stores`) creates a default `MAIN` store per existing user and migrates product stock into `store_products`, then backfills `store_id` on orders/customers/inventory adjustments.

## API resources and validation
- Uses Form Requests for input validation in `app/Http/Requests`.
- Uses API Resources in `app/Http/Resources` for consistent JSON responses.

## OpenAPI / Swagger docs
- Controllers use OpenAPI attributes (via `OpenApi\Attributes`).
- L5 Swagger config in `config/l5-swagger.php`.
- Default docs routes:
  - UI: `/api/documentation`
  - JSON: `/api/docs`
- Docs output stored under `storage/api-docs`.

## Frontend assets
- Minimal web layer: `routes/web.php` serves the default Laravel welcome page.
- Tailwind CSS 4 is configured via `resources/css/app.css` and Vite.
- No application-specific frontend UI is present in this repo.

## Testing status
- Pest tests exist for dashboard widgets, payments, auth, authorization (IDOR), and validation.
- Default example tests remain in `tests/Unit/ExampleTest.php` and `tests/Feature/ExampleTest.php`.

## Seeders and factories
- Factories exist for core models: User, Store, Product, StoreProduct, Category, Customer, Order, OrderItem, Payment, InventoryAdjustment.
- Seeders exist for several models, but `DatabaseSeeder` currently only creates a single Test User and does not call other seeders.

## Scripts (composer)
- `composer run setup`: install deps, copy env, generate key, migrate, install/build frontend
- `composer run dev`: concurrently runs `php artisan serve`, queue listener, and Vite
- `composer run test`: config clear + test

## Notable gaps / current status notes
- No custom web UI beyond the welcome page.
- No custom console commands besides `inspire`.
- No Jobs, Events, or Listeners defined in `app/`.
- Store context middleware is defined but not attached to the API route group by default.

## Update checklist (keep this file current)
- Add/remove routes: update the API surface section.
- Add/update models or relationships: update the Core domain model section.
- Add/modify migrations: update Data model notes and any data migration notes.
- Add/modify auth/authorization: update Authentication & authorization section.
- Add/modify middleware (especially store context): update Store context section.
- Add/modify API resources or Form Requests: update API resources and validation section.
- Add/modify tests: update Testing status section.
- Add/remove packages or tooling: update Tech stack and scripts.
