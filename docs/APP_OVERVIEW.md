# VENDORA - Full Stack Project Overview

> **Last updated:** 2026-02-02
> Auto-generated reference for onboarding new conversations and future development.

---

## Architecture Summary

VENDORA is a **multi-tenant, multi-store e-commerce and POS system** composed of three services:

```
Mobile App (React Native/Expo)
    ↕ REST (Axios + Sanctum Bearer Token)
Laravel API (PHP 8.4 / Laravel 12 / MySQL)
    ↓ HMAC-signed webhooks
WebSocket Server (Node.js / Express / Socket.io)
    ↓ broadcast
Mobile App (real-time updates via Socket.io)
```

### Repository Locations

| Service | Path | Stack |
|---------|------|-------|
| Backend REST API | `vendora-backend-rest-api` | PHP 8.4, Laravel 12, MySQL, Sanctum 4, Pest 4 |
| Frontend Mobile | `vendora-frontend-mobile` | Expo 54, React Native 0.81, NativeWind, Axios, Socket.io-client |
| WebSocket Server | `vendora-websocket-server` | Node.js, Express, Socket.io 4, jsonwebtoken |

---

## 1. Backend (Laravel REST API)

### 1.1 Directory Structure

```
app/
├── Console/
├── Enums/
│   ├── UserType.php          # Admin, Vendor, Manager, Cashier, Buyer
│   ├── UserStatus.php        # Active, Inactive, Suspended
│   └── StoreRole.php         # Owner, Manager, Cashier, Staff
├── Http/
│   ├── Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── ProductController.php
│   │   ├── CategoryController.php
│   │   ├── CustomerController.php
│   │   ├── OrderController.php
│   │   ├── PaymentController.php
│   │   ├── InventoryController.php
│   │   ├── DashboardController.php
│   │   ├── StoreController.php
│   │   ├── StoreProductController.php
│   │   ├── StoreStaffController.php
│   │   ├── UserController.php
│   │   └── Admin/
│   │       ├── UserController.php
│   │       └── VendorController.php
│   ├── Middleware/
│   │   └── SetStoreContext.php
│   ├── Requests/              # 25+ Form Request classes
│   └── Resources/             # 20+ API Resource classes
├── Models/
│   ├── User.php
│   ├── Product.php
│   ├── Store.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Customer.php
│   ├── Payment.php
│   ├── Category.php
│   ├── StoreProduct.php
│   ├── InventoryAdjustment.php
│   ├── AuditLog.php
│   ├── VendorProfile.php
│   ├── ProductBulkPrice.php
│   └── Concerns/
│       └── SerializesDatesInAppTimezone.php
├── Services/
│   └── WebhookService.php
└── Traits/
    ├── Auditable.php
    └── HasStoreContext.php
```

### 1.2 Models & Relationships

```
User (Authenticatable, HasApiTokens)
├── HasOne:  vendorProfile → VendorProfile
├── HasMany: products → Product
├── HasMany: orders → Order
├── HasMany: customers → Customer
├── HasMany: inventoryAdjustments → InventoryAdjustment
├── HasMany: ownedStores → Store
└── BelongsToMany: assignedStores → Store (pivot: store_user with role, permissions, assigned_at)

Product (Auditable, SoftDeletes)
├── BelongsTo: user, category
├── HasMany: inventoryAdjustments, orderItems, storeProducts, bulkPrices
└── BelongsToMany: stores (pivot: store_products with stock, min_stock, max_stock, price_override, is_available)

Store (Auditable)
├── BelongsTo: user (owner)
├── HasMany: orders, customers, payments, inventoryAdjustments, storeProducts
├── BelongsToMany: staff → User (pivot: store_user)
└── BelongsToMany: products → Product (pivot: store_products)

Order (Auditable)
├── BelongsTo: user, customer, store, processedBy (User)
└── HasMany: items → OrderItem, payments → Payment

OrderItem
└── BelongsTo: order, product

Customer
├── BelongsTo: user, store
└── HasMany: orders

Payment (Auditable)
└── BelongsTo: user, order, store

Category
└── HasMany: products

StoreProduct
└── BelongsTo: store, product
    Methods: effectivePrice(), isLowStock(), isOutOfStock()

InventoryAdjustment
└── BelongsTo: user, product, store

AuditLog
├── BelongsTo: user
└── MorphTo: auditable
    Static: log(action, model, oldValues, newValues)

VendorProfile
└── BelongsTo: user

ProductBulkPrice
└── BelongsTo: product
```

### 1.3 Enums

**UserType** — `Admin(4)`, `Manager(3)`, `Vendor(2)`, `Cashier(2)`, `Buyer(0)` (hierarchy levels)
- Methods: `label()`, `options()`, `hierarchyLevel()`, `canManage(UserType)`

**UserStatus** — `Active`, `Inactive`, `Suspended`

**StoreRole** — `Owner(*)`, `Manager`, `Cashier`, `Staff`
- Each role has a defined permissions array. Owner has `['*']`.
- Methods: `permissions()`, `hasPermission(string)`, `assignable()`

### 1.4 API Routes

**Auth (throttled 5/min)**
```
POST   /api/auth/register         Public
POST   /api/auth/login            Public
POST   /api/auth/logout           Auth
GET    /api/auth/me               Auth
```

**Products**
```
GET    /api/products                         Public (filters: search, category_id, store_id, user_id, min_price, max_price, in_stock, stock_lte, stock_gte, has_barcode, is_active, category, sort, direction, page, per_page)
GET    /api/products/{product}               Public
GET    /api/products/sku/{sku}               Public
GET    /api/products/barcode/{code}          Public
POST   /api/products                         Auth (vendor only, supports image upload + bulk pricing)
PATCH  /api/products/{product}               Auth (vendor only)
DELETE /api/products/{product}               Auth (vendor only, soft delete)
PATCH  /api/products/{product}/stock         Auth
POST   /api/products/bulk-stock-decrement    Auth (transactional)
```

**Categories**
```
GET    /api/categories              Public
GET    /api/categories/{category}   Public
POST   /api/categories              Auth
PATCH  /api/categories/{category}   Auth
DELETE /api/categories/{category}   Auth
```

**Orders**
```
GET    /api/orders/summary          Auth
GET    /api/orders                  Auth
POST   /api/orders                  Auth (creates items, decrements stock)
GET    /api/orders/{order}          Auth
PATCH  /api/orders/{order}          Auth
DELETE /api/orders/{order}          Auth
```

**Customers**
```
GET    /api/customers/summary       Auth
GET    /api/customers               Auth
POST   /api/customers               Auth
GET    /api/customers/{customer}    Auth
PATCH  /api/customers/{customer}    Auth
DELETE /api/customers/{customer}    Auth
```

**Payments**
```
GET    /api/payments/summary        Auth
GET    /api/payments                Auth
POST   /api/payments                Auth
GET    /api/payments/{payment}      Auth
PATCH  /api/payments/{payment}      Auth
DELETE /api/payments/{payment}      Auth
```

**Inventory**
```
GET    /api/inventory               Auth
GET    /api/inventory/summary       Auth
POST   /api/inventory/adjustments   Auth
```

**Dashboard**
```
GET    /api/dashboard/kpis              Auth
GET    /api/dashboard/sales-trend       Auth
GET    /api/dashboard/orders-by-channel Auth
GET    /api/dashboard/payment-methods   Auth
GET    /api/dashboard/top-products      Auth
GET    /api/dashboard/inventory-health  Auth
GET    /api/dashboard/low-stock-alerts  Auth
GET    /api/dashboard/pending-orders    Auth
GET    /api/dashboard/recent-activity   Auth
```

**Stores**
```
GET    /api/stores                          Auth
POST   /api/stores                          Auth
GET    /api/stores/{store}                  Auth
PATCH  /api/stores/{store}                  Auth
DELETE /api/stores/{store}                  Auth
GET    /api/stores/{store}/staff            Auth
POST   /api/stores/{store}/staff            Auth
PATCH  /api/stores/{store}/staff/{user}     Auth
DELETE /api/stores/{store}/staff/{user}     Auth
GET    /api/store-roles                     Auth
GET    /api/stores/{store}/products                 Auth
POST   /api/stores/{store}/products                 Auth
GET    /api/stores/{store}/products/{product}        Auth
PATCH  /api/stores/{store}/products/{product}        Auth
DELETE /api/stores/{store}/products/{product}        Auth
```

**Admin**
```
GET    /api/admin/users                     Admin
POST   /api/admin/users                     Admin
GET    /api/admin/users/{user}              Admin
PUT    /api/admin/users/{user}              Admin
DELETE /api/admin/users/{user}              Admin
PATCH  /api/admin/users/{user}/status       Admin
POST   /api/admin/vendors                   Admin
```

**User**
```
GET    /api/user                            Auth
```

### 1.5 Authentication & Authorization

- **Sanctum token-based auth.** Token issued on login/register, sent as `Authorization: Bearer {token}`.
- **Store context:** `SetStoreContext` middleware reads `X-Store-Id` header or `store_id` query param. Validates ownership or staff membership.
- **HasStoreContext trait:** `currentStore()`, `requireStore()`, `userStores()`, `resolveStore()`.
- **Role checks in controllers:** `$user->isVendor()`, `$user->isAdmin()`, etc.
- **Audit logging:** `Auditable` trait auto-logs create/update/delete. `AuditLog::log()` for manual entries (auth events).

### 1.6 Key Services & Patterns

**WebhookService** — Sends HMAC-SHA256 signed HTTP POST to WebSocket server. Config: `services.websocket.url`, `services.websocket.secret`. Currently fires `user:login` and `user:logout`.

**Money** — All prices/amounts stored as integers (cents/centavos). Products: `price`, `cost`. Orders: `total`. Payments: `amount`.

**Dual-mode operation:**
- E-commerce: Public browsing (`is_ecommerce=true`), global stock
- POS: Store-scoped via `store_id`, per-store stock via `StoreProduct`

**Soft deletes** on Product model. **Auto-slug** on Category. **Auto order numbering** (ORD-001, ORD-002...).

### 1.7 Database Tables

Core: `users`, `products`, `categories`, `stores`, `orders`, `order_items`, `customers`, `payments`, `inventory_adjustments`, `audit_logs`

Multi-store pivots: `store_user` (role, permissions, assigned_at), `store_products` (stock, min_stock, max_stock, price_override, is_available)

Supporting: `vendor_profiles`, `product_bulk_prices`, `personal_access_tokens`, `jobs`, `failed_jobs`, `cache`, `cache_locks`

---

## 2. Frontend (React Native / Expo)

### 2.1 Directory Structure

```
src/
├── api/
│   ├── client.js              # Axios instance with Sanctum token interceptor
│   ├── auth.js                # Auth API calls
│   └── admin.js               # Admin API calls
├── components/
│   ├── admin/                 # UserManagement, VendorApprovals, ActivityLogs, etc.
│   ├── reviews/               # RatingSummary, ReviewForm, ReviewList, ReviewModal
│   ├── ActionSheet.js, AddProductModal.js, BannerCarousel.js,
│   │   BecomeVendorModal.js, CartPanel.js, CartSheet.js,
│   │   CheckoutModal.js, FilterChip.js, FloatingNav.js,
│   │   LoginModal.js, OrderDetailModal.js, PaymentMethodCard.js,
│   │   PrintableReceipt.js, ProductCard.js, ProductListCard.js,
│   │   ProductQuickViewModal.js, ProfileModal.js, ReceiptModal.js,
│   │   SaveCartModal.js, SavedCartsModal.js, SortFilterModal.js,
│   │   StockUpdateModal.js, StoreCartSheet.js, StoreProductCard.js,
│   │   Toast.js, VendoraLoading.js, VendorProfileModal.js
│   └── (empty: cart/, comparison/, variants/)
├── config/
│   └── env.js                 # API_URL, WEBSOCKET_URL from EXPO_PUBLIC_* env vars
├── context/
│   ├── AuthContext.js          # Auth state, login/logout/register, role checks
│   ├── ProductContext.js       # Product CRUD + silent updates for real-time sync
│   ├── OrderContext.js         # Order management
│   ├── CustomerContext.js      # Customer management
│   ├── CartContext.js          # Cart state
│   ├── SocketContext.js        # WebSocket connection + real-time event handling
│   ├── AdminContext.js         # Admin operations
│   └── ReviewContext.js        # Review management
├── data/
│   ├── defaultSettings.js
│   └── products.js
├── navigation/
│   └── RootNavigator.js       # Custom screen-based nav with FloatingNav + slide animations
├── screens/
│   ├── LoginScreen.js
│   ├── POSScreen.js
│   ├── DashboardScreen.js
│   ├── SalesScreen.js
│   ├── InventoryScreen.js
│   ├── ProductsScreen.js
│   ├── OrdersScreen.js
│   ├── ReportsScreen.js
│   ├── SettingsScreen.js
│   ├── StoreScreen.js
│   └── AdminScreen.js
├── services/
│   ├── api.js
│   ├── authService.js
│   ├── categoryService.js
│   ├── inventoryService.js
│   ├── productService.js
│   ├── socketService.js       # Socket.io client wrapper
│   └── index.js
└── utils/
    ├── checkoutHelpers.js
    ├── permissions.js          # ROLES constant, hasPermission(role, permission)
    ├── receiptHelpers.js
    └── timezone.js
```

### 2.2 Navigation

Custom navigator in `RootNavigator.js` — no React Navigation stack/drawer. Uses `FloatingNav` component with animated slide transitions.

**Screen access by role:**
| Role | Screens | Default |
|------|---------|---------|
| Admin | Admin, POS, Dashboard, Sales, Inventory, Products, Orders, Reports, Settings, Store | Admin |
| Vendor | POS, Dashboard, Sales, Inventory, Products, Settings | POS |
| Manager | Same as Admin | Admin |
| Cashier | POS, Sales, Orders, Settings | POS |
| Buyer/Guest | Store | Store |

Current screen persisted in AsyncStorage (`@vendora_current_screen`).

### 2.3 State Management

React Context pattern. Provider hierarchy:
```
AuthProvider → ProductProvider → OrderProvider → CustomerProvider → CartProvider → SocketProvider → AdminProvider → ReviewProvider
```

**AuthContext** — `currentUser`, `isAuthenticated`, `isAdmin`, `isVendor`, `login()`, `logout()`, `register()`, `hasRole()`, `checkPermission()`. Validates stored token on init via `/api/auth/me`.

**SocketContext** — Connects to WebSocket server (authenticated or guest). Listens for real-time events and updates local state silently (no loading spinners). Handles app foreground/background reconnection.

### 2.4 API Layer

- `src/api/client.js` — Axios instance, baseURL from `env.js`, auto-injects Sanctum token from AsyncStorage.
- Service files per domain call the Axios client.
- Token stored at `@vendora_auth_token` in AsyncStorage.

### 2.5 Key Dependencies

```
expo: ~54.0.31
react-native: 0.81.5
axios: ^1.7.9
socket.io-client: ^4.8.3
nativewind: ^4.2.1 (Tailwind for RN)
@react-navigation/native: ^7.1.27
@react-navigation/bottom-tabs: ^7.9.1
expo-camera: ~17.0.10
expo-image-picker: ~17.0.10
expo-print: ~15.0.8
@react-native-async-storage/async-storage: 2.2.0
```

---

## 3. WebSocket Server (Node.js)

### 3.1 Directory Structure

```
src/
├── index.js                   # Express + HTTP server + Socket.io init
├── config.js                  # PORT, LARAVEL_API_URL, WEBHOOK_SECRET, CORS_ORIGINS
├── socket/
│   ├── index.js               # Socket.io server initialization
│   ├── auth.js                # Sanctum token validation + connection handling
│   └── handlers.js            # Event broadcasting (broadcastEvent, sendToUser, sendToRole)
├── webhook/
│   ├── routes.js              # POST /webhook/events, GET /health, /debug, /logs, /logs/ui
│   ├── handlers.js            # handleWebhookEvent — validates + broadcasts to Socket.io
│   └── verify.js              # HMAC-SHA256 signature verification middleware
└── utils/
    ├── logger.js
    └── log-store.js
```

### 3.2 Authentication

Socket.io middleware validates Sanctum tokens by calling `GET {LARAVEL_API_URL}/auth/me` with the bearer token. Supports **guest connections** (`auth: { guest: true }`) for public events like product updates.

### 3.3 Rooms

| Room | Purpose |
|------|---------|
| `broadcast` | All connected clients (authenticated + guests) |
| `user:{id}` | Per-user targeted messages |
| `role:{role}` | Per-role broadcasts |
| `logs-ui` | Log viewer subscribers |

### 3.4 Sync Events

```
user:login, user:logout
product:created, product:updated, product:deleted
stock:updated
order:created, order:updated
category:created, category:updated, category:deleted
```

### 3.5 Webhook Endpoints

```
POST /webhook/events    — Main endpoint (HMAC-verified). Laravel sends events here.
POST /webhook/batch     — Batch multiple events (HMAC-verified).
GET  /webhook/health    — Connection stats (no auth).
GET  /webhook/debug     — Detailed socket info (no auth).
GET  /webhook/logs      — Recent logs as JSON.
GET  /webhook/logs/ui   — Browser-based log viewer.
```

### 3.6 Webhook Verification

Laravel's `WebhookService` signs payloads with `hash_hmac('sha256', json(payload), secret)` and sends as `X-Webhook-Signature` header. The WebSocket server verifies using `crypto.timingSafeEqual`.

### 3.7 Config (.env)

```
PORT=3001
LARAVEL_API_URL=http://localhost:8000/api
WEBHOOK_SECRET=<shared secret with Laravel>
CORS_ORIGINS=*
TZ=Asia/Manila
```

---

## 4. Key Architectural Patterns

### Multi-Store
- Users own stores (`ownedStores`) or are assigned as staff (`assignedStores`).
- Products exist globally but have per-store inventory via `StoreProduct` (stock, price_override, is_available).
- Orders, customers, payments are store-scoped.
- Store context resolved via `X-Store-Id` header or `store_id` query param.

### Dual-Mode (E-commerce + POS)
- **E-commerce:** Public browsing, `is_ecommerce=true` products, global stock.
- **POS:** Store-specific, requires store context, per-store inventory.

### Real-Time Sync Flow
1. Mobile app calls Laravel API (e.g., create product).
2. Laravel's `WebhookService` sends signed event to WebSocket server.
3. WebSocket server broadcasts to all clients in `broadcast` room.
4. Mobile app's `SocketContext` receives event, updates local state silently.

### Money
All monetary values stored as integers (cents/centavos). Currency field defaults to `PHP`.

### Audit Trail
`Auditable` trait on Product, Store, Order, Payment auto-logs changes to `audit_logs` table. Manual logging for auth events via `AuditLog::log()`.

---

## 5. Testing

- **Framework:** Pest 4
- **Factories:** User, Product, Order, Category, Customer, Store, StoreProduct, InventoryAdjustment, OrderItem, Payment, ProductBulkPrice
- **Run:** `php artisan test --compact` or `php artisan test --compact --filter=testName`
- **Format:** `vendor/bin/pint --dirty`
