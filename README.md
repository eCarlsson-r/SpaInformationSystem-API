# SpaCashier API

A comprehensive RESTful API backend for spa and wellness center management. Built with Laravel 12, this system handles point-of-sale operations, employee management, customer relations, financial tracking, and more.

## Features

### Core Business Operations
- **Session Management** - Track customer spa sessions, treatments, and bed assignments
- **Treatment Catalog** - Manage available spa treatments and services
- **Room & Bed Management** - Track room occupancy and bed availability
- **Voucher System** - Issue and manage customer vouchers with discount support

### Customer & Employee Management
- **Customer Database** - Store customer information and service history
- **Employee Management** - Manage therapists and staff with attendance tracking
- **Walk-in Customers** - Handle walk-in customer registrations

### Financial Management
- **Income & Expense Tracking** - Record all business transactions
- **Journal Entries** - Double-entry accounting with journal records
- **Wallet System** - Manage business wallets and cash flow
- **Payment Processing** - Track income and expense payments
- **Transfer Management** - Handle fund transfers between accounts

### Supporting Features
- **Multi-Branch Support** - Manage multiple spa locations
- **Agent & Supplier Management** - Track business partners
- **Banner Management** - Manage promotional banners
- **Push Notifications** - Web push notification support via Laravel WebPush

## Tech Stack

- **Framework**: Laravel 12
- **PHP Version**: 8.2+
- **Authentication**: Laravel Sanctum
- **Database**: SQLite (default), MySQL/PostgreSQL supported
- **Queue**: Database driver
- **Asset Bundling**: Vite
- **Additional Integrations**: 
  - ZKTeco SDK for attendance devices
  - WebPush for push notifications

## Requirements

- PHP >= 8.2
- Composer
- Node.js & npm
- Database (SQLite, MySQL, or PostgreSQL)

## Installation

### Quick Setup

```bash
# Clone the repository
git clone <repository-url>
cd spacashier

# Run the setup script (installs dependencies, generates key, runs migrations)
composer setup
```

### Manual Setup

```bash
# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Install Node.js dependencies
npm install

# Build frontend assets
npm run build
```

## Configuration

1. Copy `.env.example` to `.env`
2. Configure your database connection in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spacashier
DB_USERNAME=root
DB_PASSWORD=
```

3. Configure other settings as needed (mail, queue, cache, etc.)

## Development

### Running the Development Server

```bash
# Run all services concurrently (server, queue, logs, vite)
composer dev
```

This command starts:
- Laravel development server
- Queue worker
- Laravel Pail (log viewer)
- Vite dev server

### Running Individual Services

```bash
# Start Laravel server only
php artisan serve

# Start queue worker
php artisan queue:listen

# Start Vite dev server
npm run dev
```

## Testing

```bash
# Run all tests
composer test

# Or manually
php artisan config:clear
php artisan test
```

## API Endpoints

All API endpoints are prefixed with `/api`. The following resources are available:

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | User authentication |
| POST | `/api/subscribe` | Push notification subscription |
| GET | `/api/user` | Get authenticated user (requires auth) |

### Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/dashboard` | Get dashboard data |
| GET | `/api/daily` | Get daily report |

### RESTful Resources

The following endpoints support full CRUD operations (`index`, `store`, `show`, `update`, `destroy`):

| Resource | Endpoint |
|----------|----------|
| Accounts | `/api/account` |
| Agents | `/api/agent` |
| Attendance | `/api/attendance` |
| Banks | `/api/bank` |
| Banners | `/api/banner` |
| Beds | `/api/bed` |
| Bonuses | `/api/bonus` |
| Branches | `/api/branch` |
| Categories | `/api/category` |
| Compensations | `/api/compensation` |
| Customers | `/api/customer` |
| Discounts | `/api/discount` |
| Employees | `/api/employee` |
| Expenses | `/api/expense` |
| Expense Items | `/api/expenseitem` |
| Expense Payments | `/api/expensepayment` |
| Incomes | `/api/income` |
| Income Items | `/api/incomeitem` |
| Income Payments | `/api/incomepayment` |
| Journals | `/api/journal` |
| Journal Records | `/api/journalrecord` |
| Periods | `/api/period` |
| Rooms | `/api/room` |
| Sales | `/api/sales` |
| Sessions | `/api/session` |
| Suppliers | `/api/supplier` |
| Transfers | `/api/transfer` |
| Treatments | `/api/treatment` |
| Vouchers | `/api/voucher` |
| Walk-ins | `/api/walkin` |
| Wallets | `/api/wallet` |

## Project Structure

```
spacashier/
├── app/
│   ├── Http/
│   │   └── Controllers/     # API Controllers
│   └── Models/              # Eloquent Models
├── database/
│   ├── factories/           # Model Factories
│   ├── migrations/          # Database Migrations
│   └── seeders/             # Database Seeders
├── routes/
│   └── api.php              # API Routes
├── tests/                   # Test Files
└── ...
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
