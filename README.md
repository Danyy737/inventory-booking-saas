# Grabite – Inventory Booking SaaS

Grabite is a multi-tenant inventory and booking management platform built with Laravel and React.

It is designed for businesses such as event hire companies, equipment rental providers, and service operators that need to manage stock across overlapping bookings without double-booking inventory.

---

## Live Application

Frontend: https://grabite.co/

Frontend Repository:
https://github.com/Danyy737/inventory-booking-frontend

Backend Repository:
https://github.com/Danyy737/inventory-booking-saas

---

## Overview

The core goal of Grabite is simple:

Prevent inventory from being double-booked across overlapping bookings.

The system achieves this using tenant-aware inventory management, reservation-based booking logic, package expansion, addon support, and real-time availability validation.

Each organisation manages its own inventory, packages, addons, members, and bookings in an isolated multi-tenant environment.

---

## Core Features

### Multi-Tenant Organisations
- Users belong to organisations
- Each organisation has isolated data
- Inventory, packages, bookings and addons are tenant-scoped

### Authentication
- User registration and login
- Laravel Sanctum authentication
- Protected API routes

### Inventory Management
- Create inventory items
- Track available stock quantities
- Update and delete inventory items

### Packages
- Create reusable bundles of inventory items
- Expand package contents during booking checks
- Reuse packages across multiple bookings

### Addons
- Optional extras backed by inventory
- Quantity multipliers supported
- Addon quantities included in availability calculations

### Booking Management
- Create bookings with start and end date/time
- Detect overlapping bookings
- Reserve inventory per booking
- Cancel bookings
- Generate packing lists

### Availability Preview
- Preview inventory availability before confirming bookings
- Show required vs available quantities
- Block bookings when stock shortages exist

---

## Booking Logic

Bookings are validated against existing reservations to prevent double booking.

A booking overlaps when:

existing.start < new.end  
AND  
existing.end > new.start  

If inventory is insufficient after accounting for overlapping bookings, the booking request is rejected.

---

## Tech Stack

Backend:
- PHP
- Laravel
- MySQL
- Laravel Sanctum

Frontend:
- React
- React Router
- Axios
- Vite

Development Environment:
- Docker

---

## API Overview

Auth
- Register
- Login
- Current user context

Inventory
- Create inventory items
- Update stock
- Delete items
- Check availability

Packages
- Create packages
- Update packages
- Delete packages

Addons
- Create and manage addons
- Attach addons to bookings

Bookings
- Create bookings
- Update bookings
- Cancel bookings
- Packing list generation
- Availability preview

---

## Local Development Setup

Clone the repository

git clone https://github.com/Danyy737/inventory-booking-saas.git

cd inventory-booking-saas

Install backend dependencies

composer install

Install frontend dependencies

npm install

Copy environment file

cp .env.example .env

Generate application key

php artisan key:generate

Run migrations

php artisan migrate

Start the application

php artisan serve

Run frontend dev server

npm run dev

---

## Project Purpose

Grabite was built as a full-stack SaaS MVP solving a real operational problem:

preventing stock conflicts across overlapping bookings.

The project demonstrates:

- full stack SaaS architecture
- multi-tenant backend design
- reservation-based inventory control
- booking validation workflows
- cloud deployment using modern tooling

---

## Author

Daniel Mourad

Full Stack Developer
