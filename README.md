# Inventory Booking SaaS

A multi-tenant inventory and booking management system built with Laravel and React.

This platform allows organisations to manage inventory, create packages, and handle bookings with real-time availability validation to prevent stock conflicts and double-booking.

---

## Frontend Repository

The React frontend for this project can be found here:

https://github.com/Danyy737/inventory-booking-frontend

## Overview

Inventory Booking SaaS is designed for businesses such as event hire companies, equipment rental providers, and service operators that need to manage stock across overlapping bookings.

The core objective of the system is:

> Ensure inventory is never double-booked across overlapping time windows.

The system enforces this using reservation logic, availability preview endpoints, and overlap detection rules.

---

## Core Features

### Authentication & Multi-Tenancy
- User registration and login (Laravel Sanctum)
- Organisation creation and join via code
- Organisation selection per user
- Tenant isolation middleware
- Role-based access (Owner / Staff)

### Inventory Management
- Create, update, and delete inventory items
- Track stock quantities per organisation
- Tenant-scoped inventory isolation

### Packages
- Create reusable item bundles
- Update package contents
- Expand packages into inventory requirements during availability checks

### Addons (Inventory-Backed Upsells)

Addons are optional extras that can be attached to a booking (e.g. Extra Chairs, Extra Table).

Each addon:
- Has pricing (`fixed` or `per_unit`)
- Is linked to one or more inventory items
- Defines `quantity_per_unit` per inventory item
- Reserves inventory when used in a booking
- Is soft-deleted via `is_active` (inactive addons are hidden but preserved for history)

Example:

If:
- Addon “Extra Chairs”
- `quantity_per_unit = 5`
- Booking selects `Extra Chairs x 2`

The system reserves:
- 10 chairs

### Bookings
- Create bookings with start and end date/time
- Overlap detection between bookings
- Reservation system per inventory item
- Booking cancellation endpoint
- Packing list generation per booking

### Availability System
- Standalone availability checker page
- Booking preview availability endpoint
- Required / Available / Shortage breakdown
- Prevents booking when stock is insufficient

---

## Architecture

### Backend
- Laravel 10
- Sanctum authentication
- Tenant middleware
- Reservation-based stock locking
- Service-driven availability logic

### Frontend
- React
- React Router
- Axios API client
- Auth context provider
- Protected routes
- Multi-tenant organisation selection

---

## How It Works

### Booking Flow

1. User selects organisation.
2. User selects:
   - Package
   - Optional addons
3. System builds full inventory requirements:
   - Package items
   - Addon items (multiplied by quantity_per_unit)
4. System:
   - Finds overlapping bookings.
   - Calculates reserved quantities per inventory item.
   - Compares against total stock.
5. If sufficient stock exists → booking is confirmed.
6. If insufficient stock exists → 409 conflict response returned.

---

### Reservation System

When a booking is confirmed:
- All required inventory (from packages and addons) is converted into reservation records.
- Availability checks subtract existing reservations.
- Addons generate additional inventory reservations based on `quantity_per_unit`.
- Addon pricing is snapshotted at the time of booking.

Overlapping bookings are detected using time comparison logic.

Overlap rule:

```
existing.start < new.end
AND
existing.end > new.start
```

This ensures inventory cannot be double-booked across overlapping time windows.

---

## Tech Stack

### Backend
- PHP
- Laravel
- MySQL
- Sanctum

### Frontend
- React
- React Router
- Axios

---

## API Overview

### Inventory
- GET /inventory/items
- POST /inventory/items
- PATCH /inventory/items/{id}
- DELETE /inventory/items/{id}
- POST /inventory/check-availability

### Bookings
- GET /bookings
- GET /bookings/{id}
- POST /bookings
- PATCH /bookings/{id}
- PATCH /bookings/{id}/cancel
- GET /bookings/{id}/packing-list
- POST /bookings/preview-availability

### Packages
- GET /packages
- GET /packages/{id}
- POST /packages
- PATCH /packages/{id}
- PUT /packages/{id}/items
- POST /packages/check-availability

### Addons
- GET /addons
- POST /addons
- GET /addons/{addon}
- PUT /addons/{addon}
- DELETE /addons/{addon}
- POST /addons/{addon}/items
- DELETE /addons/{addon}/items/{item}

---

## Installation

### Backend Setup

```
git clone <repository-url>
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### Frontend Setup

```
cd frontend
npm install
npm run dev
```

---

## Project Status

MVP Complete.

The system currently supports:
- Multi-tenant inventory isolation
- Real-time availability validation (packages + addons)
- Reservation-based booking conflict prevention
- Package expansion logic
- Addon expansion logic
- Addon pricing snapshotting
- Standalone availability checking
- Booking packing lists

---

## Roadmap (Future Enhancements)

- Booking lifecycle states (draft / confirmed / completed)
- Damage and return tracking
- Calendar view UI
- Reporting dashboard
- Public availability checking
- Docker-based deployment

---

## Author

Daniel Mourad

Multi-tenant SaaS architecture project built to demonstrate backend design, booking conflict resolution logic, and full-stack integration.
