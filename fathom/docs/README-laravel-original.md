# Fathom

Kanban boards for independent professionals. Free forever.

## Requirements

- PHP 8.2+
- Composer
- SQLite (default) or MySQL 8.0+

## Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Copy and configure environment
cp .env.example .env
php artisan key:generate

# 3. Run migrations
php artisan migrate

# 4. Start the development server
php artisan serve
```

Visit [http://localhost:8000](http://localhost:8000) and create your account.

## Key Features

- **Kanban boards** with customizable columns and cards
- **Magic link authentication** — passwordless sign-in via email
- **Card management** — descriptions, checklists, assignees, tags, reactions, comments
- **Client portal** — scoped read/comment access for individual clients
- **Public board sharing** with share tokens
- **QR code generation** for public boards and cards
- **Data export** as JSON
- **Notification system** with email digest support
- **Activity feed** on the home dashboard
- **Multi-tenant** — each account is fully isolated

## Database

SQLite is the default. To use MySQL, update `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fathom
DB_USERNAME=root
DB_PASSWORD=
```

## Deployment

Any standard Laravel hosting works: Forge, Ploi, Vapor, Railway, Render, or a plain VPS. Requires PHP 8.2+ with standard extensions (PDO, mbstring, OpenSSL) and a writable `storage/` directory.

## License

Proprietary.
