# Dispatching System - Backend

Laravel 12 REST API for the Dispatching System field service management platform.

## Stack

- PHP 8.4 with Laravel 12
- MySQL database
- Redis (cache, sessions, queues)
- Laravel Sanctum for API authentication
- Laravel DomPDF for invoice generation

## API

All endpoints are prefixed with `/api/`. See `routes/api.php` for the full route list.

- **Health check**: `GET /api/health`
- **Auth**: Login, logout, current user
- **Jobs**: Full CRUD with status workflow, assignments, recurring jobs
- **Customers**: CRUD with portal access
- **Invoices**: Generate, track, PDF download
- **Parts**: Inventory management with stock tracking
- **GPS/Routing**: Technician location tracking, route optimization, ETA
- **Reports**: Summary, by-status, by-date, technician performance
- **Analytics**: Revenue trends, job trends, service popularity, profitability
- **Customer Portal**: Service requests, job tracking, reviews

## Testing

```bash
php artisan test
```

## Key Directories

```
app/
├── Console/Commands/     # Scheduled tasks (recurring jobs, reports)
├── Events/               # JobStatusChanged, TechnicianAssigned
├── Http/
│   ├── Controllers/Api/  # 19 REST controllers + portal
│   ├── Middleware/        # Role, permission, tenant, rate limiting
│   ├── Requests/         # 13 form request validators
│   └── Resources/        # 8 API resource transformers
├── Models/               # 20 Eloquent models
├── Notifications/        # Email + database notifications
└── Services/             # 11 business logic services
```
