# SpaSystem API

[![Enterprise Ready](https://img.shields.io/badge/Enterprise-Ready-blue.svg)](https://laravel.com)
[![Laravel 12](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

> **The Enterprise Engine for Modern Wellness.**
> A robust, high-performance backend architecture designed to power premium spa and wellness centers. From hardware integration to double-entry accounting, SpaSystem API delivers the reliability your business demands.

## 🚀 Vision

SpaSystem API is more than just a POS backend; it's a comprehensive Business Operating System. It bridges the gap between physical operations (hardware attendance, room management) and digital excellence (real-time notifications, unified finance).

## ✨ Key Features

### 🏦 Financial Integrity

-   **Double-Entry Accounting**: Bulletproof journal records for every transaction.
-   **Unified Finance**: Holistic tracking of Incomes, Expenses, and multi-wallet transfers.
-   **Automated Balancing**: Real-time updates to account balances and branch-specific financial health.

### 🛰️ Real-time & Connectivity

-   **Laravel Reverb**: Blazing fast, sub-second real-time notifications and state updates.
-   **Hardware Integration**: Native support for **ZKTeco Biometric SDK** for seamless staff attendance synchronization.
-   **WebPush Notifications**: Directly engage staff and management via browser-level push alerts.

### 💆 Operations Management

-   **Advanced Session Logic**: Precise tracking of treatments, therapist assignments, and bed occupancy.
-   **Capacity Intelligent**: Real-time lookups for available rooms and therapists.
-   **Dynamic Catalog**: Managed categories, treatments, and promotional banners.

### 👤 Management & Security

-   **Sanctum Powered**: Secure, token-based API authentication.
-   **Granular Resources**: Full CRUD control over Branches, Employees, Customers, and Suppliers.
-   **Smart Cart System**: Integrated booking and voucher purchase through a unified cart logic.

## 🛠️ Tech Stack

-   **Framework**: Laravel 12 (The latest in PHP innovation)
-   **Engine**: PHP 8.2+
-   **Database**: Optimized for SQLite/MySQL/PostgreSQL
-   **Real-time**: Laravel Reverb + Echo
-   **Hardware**: ZKTeco SDK Integration
-   **Notification**: Laravel WebPush

## 🏁 Getting Started

### Quick Start

```bash
# Clone and enter the nexus
git clone <repository-url>
cd SpaSystem-API

# Interactive setup (Dependencies, Keys, Migrations, Assets)
composer setup
```

### Manual Configuration

1. **Prepare Environment**: `cp .env.example .env`
2. **Install Core**: `composer install`
3. **Ignition**: `php artisan key:generate`
4. **Data Sync**: `php artisan migrate`
5. **Real-time Engine**: Ensure Reverb keys are configured in `.env`

## 🛠️ Development

Start the entire ecosystem with a single command:

```bash
composer dev
```

_Launch server, queue listeners, log streams, and asset watchers concurrently._

## 🧪 Verification

Ensure enterprise-grade reliability with our automated suite:

```bash
composer test
```

## 📖 API Context

| Resource       | Logic Handler          | Capability                                    |
| :------------- | :--------------------- | :-------------------------------------------- |
| **Sessions**   | `SessionController`    | Multi-stage booking flow (`start` / `finish`) |
| **Accounting** | `JournalController`    | High-integrity ledger records                 |
| **Attendance** | `AttendanceController` | Biometric sync & hardware polling             |
| **Sales/Cart** | `CartController`       | Unified booking & voucher logic               |

---

Developed with ❤️ for the Wellness Industry. Licensed under [MIT](LICENSE).
