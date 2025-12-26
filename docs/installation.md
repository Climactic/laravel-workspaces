# Installation

## Requirements
- PHP 8.2+
- Laravel 11+

## Install via Composer

```bash
composer require climactic/laravel-workspaces
```

## Publish Config & Migrations

```bash
php artisan workspaces:install
```

This publishes:
- `config/workspaces.php`
- Migration file for workspaces tables

## Run Migrations

```bash
php artisan migrate
```

## Add Trait to User Model

```php
use Climactic\Workspaces\Concerns\HasWorkspaces;

class User extends Authenticatable
{
    use HasWorkspaces;
}
```
