# Repository Guidelines

## Project Structure & Module Organization
- Core package logic lives in `src/`, including the service provider, facade, and console command that expose the helpdesk integration.
- Publishable settings stay under `config/`; schema scaffolding sits in `database/migrations` and seed blueprints in `database/factories`.
- Blade resources render from `resources/views`; automated tooling references `phpstan.neon.dist` and `phpunit.xml.dist`.
- Tests live in `tests/` with the Pest bootstrap at `tests/Pest.php` and the shared base case in `tests/TestCase.php`.

## Build, Test, and Development Commands
- `composer install` — install package dependencies for local development.
- `composer test` — run the Pest suite via Orchestra Testbench; use before every PR.
- `composer test-coverage` — generate coverage metrics when validating new features.
- `composer analyse` — execute PHPStan with the bundled baseline; keep the level clean.
- `composer format` — run Laravel Pint to enforce PSR-12 styling.
- `composer dump-autoload` — refresh Testbench discovery after adding autoloaded classes or migrations.

## Coding Style & Naming Conventions
Follow PSR-12 with four-space indentation and strict types on new PHP files. Name classes and traits in StudlyCase, helpers in snake_case, and configuration keys in dot.notation. Let Pint format changes before committing, and document tests with fluent names such as `it_dispatches_tickets_successfully`.

## Testing Guidelines
Pest powers the suite; extend `Tests\TestCase` for container access. Group features by directory (e.g. `tests/Feature/TicketTest.php`) and prefer descriptive `it()` blocks over anonymous closures. Assert database state with Testbench helpers and confirm migrations run cleanly via `composer test` before publishing changes.

## Commit & Pull Request Guidelines
Write imperative commit subjects, optionally prefixed with Conventional Commit tags like `feat:` or `fix:`. Each PR should summarise the change, list tests executed, link related issues, and attach screenshots or terminal output for user-facing updates. Ensure `composer test` and `composer format` pass locally prior to requesting review.
