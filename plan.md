# Frontend Plan: Food Menu & Reservation (React Native)

## Overview

Add a **Food Menu** screen to the vendor's navigation where they can manage menu items and reservations. This follows existing patterns from ProductsScreen, OrdersScreen, and the service/context architecture.

---

## Files to Create (at `c:\vendora`)

### 1. API Services

**`src/services/foodMenuService.js`**
- `getAll(params)` — `GET /food-menu` (search, category, is_available, pagination)
- `getById(id)` — `GET /food-menu/{id}` (with reservations)
- `create(data)` — `POST /food-menu` (FormData for image, JSON otherwise)
- `update(id, data)` — `PUT /food-menu/{id}` (FormData/JSON)
- `delete(id)` — `DELETE /food-menu/{id}`
- `getPublicMenu(userId)` — `GET /food-menu/public/{userId}`
- `publicReserve(userId, data)` — `POST /food-menu/public/{userId}/reserve`
- Follows `productService.js` pattern: `buildQueryString`, `isLocalFile`, FormData for images

**`src/services/foodMenuReservationService.js`**
- `getAll(params)` — `GET /food-menu-reservations` (status, food_menu_item_id filters)
- `create(data)` — `POST /food-menu-reservations`
- `getById(id)` — `GET /food-menu-reservations/{id}`
- `update(id, data)` — `PATCH /food-menu-reservations/{id}`
- `delete(id)` — `DELETE /food-menu-reservations/{id}`
- Simpler service — no image uploads, follows `categoryService.js` pattern

### 2. Context

**`src/context/FoodMenuContext.js`**
- State: `menuItems`, `reservations`, `isLoading`, `error`
- Actions: `fetchMenuItems`, `addMenuItem`, `updateMenuItem`, `deleteMenuItem`
- Actions: `fetchReservations`, `addReservation`, `updateReservation`, `deleteReservation`
- **No offline-first** for this feature (food menu is not POS-critical, always online)
- Direct API calls, simpler than ProductContext (no SQLite/SyncManager)
- Custom hook: `useFoodMenu()`

### 3. Screen

**`src/screens/FoodMenuScreen.js`** — Single screen with two tabs (Menu Items / Reservations)
- **Tab 1: Menu Items** — List with search, category filter, add/edit/delete
  - Each card shows: name, price, servings (remaining/total), availability badge
  - FAB or header button to add new item
  - Tap card → detail modal with reservation list
- **Tab 2: Reservations** — List with status filter chips (All/Pending/Confirmed/Cancelled/Completed)
  - Each card shows: customer name, menu item name, servings, status badge, reserved_at
  - Tap card → detail modal with status change actions
- Pull-to-refresh on both tabs
- Follows OrdersScreen pattern (stats + filter + FlatList)

### 4. Modals/Components

**`src/components/AddFoodMenuItemModal.js`**
- Form fields: name, description, category (picker/input), price, total_servings, image (picker), is_available toggle
- Image picker using `expo-image-picker` (same as AddProductModal)
- Create and edit mode (pass existing item for edit)
- Follows `AddProductModal.js` pattern

**`src/components/FoodMenuItemDetailModal.js`**
- Shows full item details + its reservations list
- Edit and delete actions
- Follows `OrderDetailModal.js` pattern

**`src/components/AddReservationModal.js`**
- Form: menu item picker (dropdown of vendor's items), customer_name, customer_phone, servings, notes, reserved_at (date picker)
- For vendor to create reservation on behalf of customer

**`src/components/ReservationDetailModal.js`**
- Shows reservation details
- Status change buttons: Confirm, Complete, Cancel
- Edit servings, delete
- Follows `OrderDetailModal.js` pattern

### 5. Navigation Changes

**`src/navigation/RootNavigator.js`**
- Import `FoodMenuScreen`
- Add to `SCREENS` object: `FoodMenu: FoodMenuScreen`
- Add `'FoodMenu'` to `VENDOR_SCREEN_ORDER` (after 'Products')
- Add `'FoodMenu'` to `ADMIN_SCREEN_ORDER` (after 'Products')

**`src/components/FloatingNav.js`**
- Add to `NAV_ITEM_CONFIG`: `FoodMenu: { icon: 'restaurant-outline', activeIcon: 'restaurant', label: 'Menu' }`

---

## Files to Modify

| File | Change |
|------|--------|
| `src/navigation/RootNavigator.js` | Import screen, add to SCREENS + screen order arrays |
| `src/components/FloatingNav.js` | Add nav icon config for FoodMenu |

---

## Implementation Order

1. Create `foodMenuService.js` and `foodMenuReservationService.js`
2. Create `FoodMenuContext.js` with provider and hook
3. Create modal components (AddFoodMenuItemModal, FoodMenuItemDetailModal, AddReservationModal, ReservationDetailModal)
4. Create `FoodMenuScreen.js` with two-tab layout
5. Register in RootNavigator + FloatingNav
6. Wrap app with FoodMenuProvider (in App.js or wherever providers are)
7. Test manually

---

## Key Design Decisions

- **Single screen with tabs** instead of two separate screens — keeps nav clean, food menu + reservations are tightly coupled
- **No offline-first** — food menu is not POS-critical; keeping it simple with direct API calls
- **Tab component** — simple inline tab switcher (TouchableOpacity row), not a separate library
- **Price display** — API returns price in dollars (already converted by backend resource), display with `formatCurrency()`
- **Servings display** — Show `remaining / total` with progress bar or fraction text
