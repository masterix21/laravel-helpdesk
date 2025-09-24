# Laravel Helpdesk

[![Latest Version on Packagist](https://img.shields.io/packagist/v/masterix21/laravel-helpdesk.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-helpdesk)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/masterix21/laravel-helpdesk/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/masterix21/laravel-helpdesk/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/masterix21/laravel-helpdesk/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/masterix21/laravel-helpdesk/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/masterix21/laravel-helpdesk.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-helpdesk)

A comprehensive helpdesk solution for Laravel applications with ticket management, SLA monitoring, knowledge base, and automation workflows.

## 📚 Documentation

Full documentation is available in the [docs](docs/index.md) directory.

## Installation

```bash
composer require masterix21/laravel-helpdesk
```

```bash
php artisan vendor:publish --tag="laravel-helpdesk-migrations"
php artisan migrate
```

```bash
php artisan vendor:publish --tag="laravel-helpdesk-config"
```

## Quick Start

```php
use LucaLongo\LaravelHelpdesk\Facades\LaravelHelpdesk;
use LucaLongo\LaravelHelpdesk\Enums\TicketStatus;

// Create a ticket
$ticket = LaravelHelpdesk::open([
    'type' => 'product_support',
    'subject' => 'Cannot access dashboard',
    'description' => 'Getting error when trying to login...',
    'priority' => 'high',
]);

// Transition status
$ticketService = app(TicketService::class);
$ticketService->transition($ticket, TicketStatus::Resolved);
```

See the [documentation](docs/index.md) for detailed usage and configuration options.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Luca Longo](https://github.com/masterix21)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.