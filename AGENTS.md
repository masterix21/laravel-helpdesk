# Repository Guidelines

## Project Structure & Module Organization
Core package code lives in `src/`, including the service provider, facade, and console command exposed by this helpdesk integration. Publishable configuration resides in `config/`, while `database/migrations` and `database/factories` hold schema scaffolding for host apps. Blade templates belong to `resources/views`. Automated checks rely on the settings in `phpstan.neon.dist` and `phpunit.xml.dist`, and all package-level tests sit under `tests/` with the Pest bootstrap in `tests/Pest.php`.

## Build, Test, and Development Commands
Install dependencies with `composer install`. Run the package suite via `composer test`, or add coverage detail with `composer test-coverage`. Static analysis is enforced through `composer analyse`, which wraps PHPStan using the repository baseline. Apply consistent formatting with `composer format`, powered by Laravel Pint. After adjusting autoloaded classes, execute `composer dump-autoload` to keep Testbench discovery accurate.

## Coding Style & Naming Conventions
Follow PSR-12 styling with four-space indentation; Pint handles fixes, so run it before committing. Classes and traits should use StudlyCase, while helper functions stay in snake_case. Configuration keys mirror dot.notation used in Laravel. Tests must read fluently, e.g. `it_dispatches_tickets_successfully`. Keep PHP files strictly typed when introducing new files, aligning with the existing package skeleton.

## Testing Guidelines
Write tests with Pest, grouping features by directory (e.g. `tests/Feature/TicketTest.php`). Extend `Tests/TestCase.php` to boot Orchestra Testbench and gain a working Laravel container. Prefer descriptive `it()` blocks over anonymous closures, and assert database state with Testbench helpers. Aim for comprehensive coverage on new modules and confirm migrations run cleanly by executing `composer test` before opening a PR.

## Commit & Pull Request Guidelines
Recent history is concise (`Initial commit`, `wip`), so establish clarity going forward: craft imperative commit subjects, optionally prefixed with Conventional Commit tokens like `feat:` or `fix:`. Each pull request should describe the change, outline testing performed, and reference linked issues. Include screenshots or terminal output when altering user-facing views or commands, and ensure CI (tests and Pint) is green before requesting review.
