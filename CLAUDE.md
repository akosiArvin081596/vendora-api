<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.16
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs
- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version-specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== pest/core rules ===

## Pest
### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest {name}`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests that have a lot of duplicated data. This is often the case when testing validation rules, so consider this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>

=== pest/v4 rules ===

## Pest 4

- Pest 4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest 4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>

=== tailwindcss/core rules ===

## Tailwind CSS

- Use Tailwind CSS classes to style HTML; check and use existing Tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc.).
- Think through class placement, order, priority, and defaults. Remove redundant classes, add classes to parent or child carefully to limit repetition, and group elements logically.
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing; don't use margins.

<code-snippet name="Valid Flex Gap Spacing Example" lang="html">
    <div class="flex gap-8">
        <div>Superior</div>
        <div>Michigan</div>
        <div>Erie</div>
    </div>
</code-snippet>

### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.

=== tailwindcss/v4 rules ===

## Tailwind CSS 4

- Always use Tailwind CSS v4; do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.

<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>

### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option; use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>

---

# VENDORA Project Overview

## Project Description
VENDORA is a multi-vendor e-commerce platform with POS (Point of Sale) capabilities. The system supports multiple stores per vendor with staff management, inventory tracking, and comprehensive order/payment processing.

## Full Stack Architecture
| Component | Technology | Location |
|-----------|------------|----------|
| Frontend | React Native (Mobile) | `c:\Users\DSWDSRV-CARAGA\Desktop\VENDORA\development\vendora-frontend-mobile` |
| Backend | Laravel 12 REST API | `c:\Users\DSWDSRV-CARAGA\Desktop\VENDORA\development\vendora-backend-rest-api` |
| Real-time | Node.js WebSocket Server | `c:\Users\DSWDSRV-CARAGA\Desktop\VENDORA\development\vendora-websocket-server` |

---

## Backend Directory Structure

```
app/
├── Enums/
│   ├── StoreRole.php        # Owner, Manager, Cashier, Staff roles with permissions
│   ├── UserStatus.php       # Active, Inactive, Suspended
│   └── UserType.php         # Admin, Vendor, Manager, Cashier, Buyer (with hierarchy)
├── Http/
│   ├── Controllers/Api/
│   │   ├── Admin/
│   │   │   ├── UserController.php     # Admin user CRUD + status
│   │   │   └── VendorController.php   # Vendor creation
│   │   ├── AuthController.php         # Login/Register/Logout
│   │   ├── CategoryController.php     # Product categories CRUD
│   │   ├── CustomerController.php     # Customer management + summary
│   │   ├── DashboardController.php    # KPIs, trends, analytics
│   │   ├── InventoryController.php    # Stock tracking + adjustments
│   │   ├── LedgerController.php       # Financial ledger entries
│   │   ├── OrderController.php        # Order CRUD + summary
│   │   ├── PaymentController.php      # Payment processing + summary
│   │   ├── ProductController.php      # Products CRUD + stock ops + barcode/SKU lookup
│   │   ├── StoreController.php        # Store CRUD
│   │   ├── StoreProductController.php # Per-store inventory
│   │   ├── StoreStaffController.php   # Staff assignment/management
│   │   └── UserController.php         # Current user info
│   ├── Middleware/
│   │   └── SetStoreContext.php        # Store context middleware
│   ├── Requests/                      # Form Request validation classes (20+)
│   └── Resources/                     # API Resource transformers (25+)
├── Models/
│   ├── Concerns/
│   │   └── SerializesDatesInAppTimezone.php
│   ├── AuditLog.php
│   ├── Category.php
│   ├── Customer.php
│   ├── InventoryAdjustment.php
│   ├── LedgerEntry.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Payment.php
│   ├── Product.php
│   ├── ProductBulkPrice.php
│   ├── Store.php
│   ├── StoreProduct.php
│   ├── User.php
│   └── VendorProfile.php
├── Observers/
│   ├── CategoryObserver.php
│   ├── OrderObserver.php
│   └── ProductObserver.php
├── Policies/
│   └── StorePolicy.php
├── Services/
│   └── WebhookService.php
└── Traits/
    ├── Auditable.php
    └── HasStoreContext.php

database/
├── factories/           # 13 factories for all models
├── migrations/          # 28 migrations
└── seeders/

tests/
├── Feature/            # 20+ feature tests (Auth, CRUD, Security, etc.)
└── Unit/
```

---

## Database Schema (MySQL)

### Core Tables
| Table | Description | Key Columns |
|-------|-------------|-------------|
| `users` | All user types | id, name, email, password, user_type, status, phone, last_login_at |
| `vendor_profiles` | Vendor business details | user_id, business_name, business_address, tax_id |
| `stores` | Physical/virtual stores | id, user_id (owner), name, code, address, phone, email, is_active, settings (JSON) |
| `store_user` | Staff assignments (pivot) | store_id, user_id, role, permissions (JSON), assigned_at |
| `products` | Product catalog | id, user_id, category_id, name, sku, barcode, price, cost, stock, min_stock, max_stock, is_active, is_ecommerce |
| `product_bulk_prices` | Tiered pricing | product_id, min_qty, price |
| `store_products` | Per-store inventory | store_id, product_id, stock, min_stock, max_stock, price_override, is_available |
| `categories` | Product categories | id, user_id, name, description, parent_id, image, is_active, sort_order |
| `customers` | Store customers | id, user_id, store_id, name, email, phone, address, notes |
| `orders` | Sales orders | id, user_id, store_id, customer_id, processed_by, order_number, ordered_at, status, items_count, total, currency |
| `order_items` | Order line items | order_id, product_id, quantity, unit_price, total |
| `payments` | Payment records | id, order_id, store_id, amount, payment_method, status, reference |
| `inventory_adjustments` | Stock changes | id, product_id, store_id, user_id, quantity, type, reason |
| `ledger_entries` | Financial ledger | id, user_id, store_id, type, amount, description, reference |
| `audit_logs` | Activity logging | auditable_type, auditable_id, action, old_values, new_values |

### System Tables
- `cache`, `cache_locks` - Laravel cache
- `sessions` - Session management
- `jobs`, `job_batches`, `failed_jobs` - Queue system
- `personal_access_tokens` - Sanctum API tokens

---

## Model Relationships

### User
- `hasOne`: VendorProfile
- `hasMany`: Products, InventoryAdjustments, Customers, Orders, ownedStores
- `belongsToMany`: assignedStores (via store_user pivot)

### Store
- `belongsTo`: owner (User)
- `belongsToMany`: staff (Users), products (via store_products)
- `hasMany`: storeProducts, orders, customers, payments, inventoryAdjustments

### Product
- `belongsTo`: User, Category
- `hasMany`: inventoryAdjustments, orderItems, storeProducts, bulkPrices
- `belongsToMany`: stores (via store_products)

### Order
- `belongsTo`: User, Customer, Store, processedBy (User)
- `hasMany`: items (OrderItem), payments

---

## API Routes Summary

### Authentication (`/api/auth`)
- `POST /register` - Register new user (rate limited: 5/min)
- `POST /login` - Login (rate limited: 5/min)
- `POST /logout` - Logout (auth required)
- `GET /me` - Current user info (auth required)

### Public Routes
- `GET /products` - List products (e-commerce browsing)
- `GET /products/{product}` - Show product
- `GET /products/sku/{sku}` - Find by SKU
- `GET /products/barcode/{code}` - Find by barcode
- `GET /categories` - List categories
- `GET /categories/{category}` - Show category

### POS Endpoint (auth required)
- `GET /products/my` - List authenticated user's products only (for POS mode, secure)

### Protected Routes (require `auth:sanctum`)

#### Products
- `POST /products` - Create product
- `PUT|PATCH /products/{product}` - Update product
- `DELETE /products/{product}` - Delete product
- `PATCH /products/{product}/stock` - Update stock
- `POST /products/bulk-stock-decrement` - Bulk stock decrement

#### Categories
- `POST /categories` - Create category
- `PUT|PATCH /categories/{category}` - Update category
- `DELETE /categories/{category}` - Delete category

#### Dashboard Analytics
- `GET /dashboard/kpis` - Key performance indicators
- `GET /dashboard/sales-trend` - Sales over time
- `GET /dashboard/orders-by-channel` - Channel breakdown
- `GET /dashboard/payment-methods` - Payment method stats
- `GET /dashboard/top-products` - Best sellers
- `GET /dashboard/inventory-health` - Stock health
- `GET /dashboard/low-stock-alerts` - Low stock warnings
- `GET /dashboard/pending-orders` - Pending orders
- `GET /dashboard/recent-activity` - Recent activity

#### Resource Routes (CRUD + summary)
- `/customers` - CustomerController (apiResource + summary)
- `/orders` - OrderController (apiResource + summary)
- `/payments` - PaymentController (apiResource + summary)
- `/stores` - StoreController (apiResource)
- `/inventory` - InventoryController (index, summary, adjustments.store)
- `/ledger` - LedgerController (index, summary, store)

#### Store Management
- `GET|POST /stores/{store}/staff` - List/add staff
- `PATCH|DELETE /stores/{store}/staff/{user}` - Update/remove staff
- `GET /store-roles` - Available roles

#### Store Products (per-store inventory)
- `GET|POST /stores/{store}/products` - List/add products
- `GET|PATCH|DELETE /stores/{store}/products/{product}` - Product operations

#### Admin Routes (`/api/admin`)
- `GET|POST /users` - List/create users
- `GET|PUT|DELETE /users/{user}` - User CRUD
- `PATCH /users/{user}/status` - Update user status
- `POST /vendors` - Create vendor

---

## Key Enums Reference

### UserType (hierarchy levels)
| Type | Level | Description |
|------|-------|-------------|
| Admin | 4 | Full system access |
| Manager | 3 | Can manage Cashiers and Buyers |
| Vendor | 2 | Store owner |
| Cashier | 2 | POS operations |
| Buyer | 0 | Customer/shopper |

### UserStatus
- `Active` - Can login and use system
- `Inactive` - Account disabled
- `Suspended` - Temporarily blocked

### StoreRole (with permissions)
| Role | Permissions |
|------|-------------|
| Owner | `*` (all) |
| Manager | products.*, orders.*, inventory.*, customers.*, payments.*, reports.view, staff.view |
| Cashier | products.view, orders.view/create, customers.view/create, payments.view/create |
| Staff | products.view, orders.view, inventory.view |

---

## Testing Structure

### Feature Tests
| Test File | Coverage |
|-----------|----------|
| `AdminSeederTest.php` | Admin seeding |
| `Auth/*.php` | Authentication flows |
| `CategoryCrudTest.php` | Category CRUD |
| `CategorySeederTest.php` | Category seeding |
| `CustomersApiTest.php` | Customer API |
| `DashboardApiTest.php` | Dashboard endpoints |
| `InventoryApiTest.php` | Inventory operations |
| `LedgerTest.php` | Ledger entries |
| `OrdersApiTest.php` | Order processing |
| `PaymentsApiTest.php` | Payment handling |
| `ProductsApiTest.php` | Product CRUD |
| `ProductExtendedTest.php` | Extended product features |
| `ProductImageUploadTest.php` | Image uploads |
| `PublicProductsApiTest.php` | Public product access |
| `SecurityAuthenticationTest.php` | Auth security |
| `SecurityAuthorizationTest.php` | Authorization |
| `SecurityInputValidationTest.php` | Input validation |
| `StoresApiTest.php` | Store management |
| `TimezoneSerializationTest.php` | Timezone handling |
| `WebhookServiceTest.php` | Webhook service |

---

## Middleware Configuration

Configured in `bootstrap/app.php`:
- `store.context` → `App\Http\Middleware\SetStoreContext`

---

## Traits & Concerns

### Auditable (`App\Traits\Auditable`)
Provides automatic audit logging for model changes.

### HasStoreContext (`App\Traits\HasStoreContext`)
Provides store context awareness for multi-tenant operations.

### SerializesDatesInAppTimezone (`App\Models\Concerns`)
Ensures dates are serialized in the application's configured timezone.

---

## Factories Available

All models have corresponding factories in `database/factories/`:
- CategoryFactory, CustomerFactory, InventoryAdjustmentFactory
- LedgerEntryFactory, OrderFactory, OrderItemFactory
- PaymentFactory, ProductFactory, ProductBulkPriceFactory
- StoreFactory, StoreProductFactory, UserFactory, VendorProfileFactory
