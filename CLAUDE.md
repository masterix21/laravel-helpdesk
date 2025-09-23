# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Package Overview

This is a Laravel package that integrates helpdesk features into any Laravel application. It provides ticket management, comments, attachments, and subscription functionality.

## Development Commands

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Static analysis
composer analyse

# Code formatting
composer format

# Rebuild autoload files
composer dump-autoload
```

## Architecture

### Requirements
- Laravel 12
- Pest 4

### Core Components

- **Service Provider**: `src/LaravelHelpdeskServiceProvider.php` - Registers the package services, configuration, and migrations
- **Main Service**: `src/Services/TicketService.php` - Core business logic for ticket management
- **Facade**: `src/Facades/LaravelHelpdesk.php` - Provides static access to the main service

### Models & Database

- `Ticket` - Main ticket entity
- `TicketComment` - Comments on tickets
- `TicketAttachment` - File attachments for tickets
- `TicketSubscription` - User subscriptions to ticket notifications

Migrations are publishable stubs in `database/migrations/`:
- `create_helpdesk_tickets_table.php.stub`
- `create_helpdesk_ticket_comments_table.php.stub`
- `create_helpdesk_ticket_attachments_table.php.stub`
- `create_helpdesk_ticket_subscriptions_table.php.stub`

### Enums

Located in `src/Enums/`:
- `TicketType` - Types of tickets (ProductSupport, Commercial)
- `TicketStatus` - Ticket statuses
- `TicketPriority` - Priority levels (Low, Normal, High, Urgent)

All enums use the `ProvidesEnumValues` concern for value/label handling.

### Events

Located in `src/Events/`:
- `TicketCreated`
- `TicketStatusChanged`
- `TicketAssigned`
- `TicketCommentAdded`
- `TicketSubscriptionCreated`
- `TicketSubscriptionTriggered`

## Testing

Tests use Pest PHP with Orchestra Testbench for Laravel package testing. Test files are in `tests/` with:
- `tests/TestCase.php` - Base test class extending Orchestra Testbench
- `tests/Feature/` - Feature tests
- `tests/Fakes/` - Test doubles and mocks

Database factories in `database/factories/` provide test data generation.

## Configuration

The package config (`config/helpdesk.php`) defines:
- Default ticket settings (due time, priority)
- Ticket types with specific configurations
- Notification preferences

## Code Standards

- Follow PSR-12 with Laravel conventions
- Use Laravel Pint for formatting (`composer format`)
- PHPStan level 5 for static analysis
- Pest for testing with descriptive test names
- Typed properties over docblocks
- Early returns for better readability
- Use the early returns.
- Never put ulid or uuid fields to $fillables