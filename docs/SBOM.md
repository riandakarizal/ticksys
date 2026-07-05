# Software Bill of Materials — PRISM

Generated: 2026-07-05
Project: PRISM (Project & Issue Management System)
Repository: `c:\xampp\htdocs\prism`

---

## Runtime Environment

| Component | Version | Notes |
|-----------|---------|-------|
| PHP | 8.5.7 | `C:\php85\php.exe` |
| Laravel | 13.2.0 | Web framework |
| MySQL | 8.x (XAMPP) | Port 3306, DB: `prism` |
| Node.js | — | Build toolchain only |
| Apache | 2.4.x (XAMPP) | Port 80 |

---

## PHP — Production Dependencies

> Resolved via `composer.lock`

| Package | Version | License | Purpose |
|---------|---------|---------|---------|
| `laravel/framework` | v13.2.0 | MIT | Core framework |
| `laravel/tinker` | v3.0.0 | MIT | REPL / debugging |
| `laravel/prompts` | v0.3.16 | MIT | CLI prompts |
| `laravel/serializable-closure` | v2.0.10 | MIT | Closure serialization |
| `phpoffice/phpspreadsheet` | 5.8.0 | MIT | Excel/CSV import-export |
| `nesbot/carbon` | 3.11.3 | MIT | Date/time manipulation |
| `monolog/monolog` | 3.10.0 | MIT | Logging |
| `league/commonmark` | 2.8.2 | BSD-3-Clause | Markdown parsing |
| `league/config` | v1.2.0 | BSD-3-Clause | Config component |
| `league/flysystem` | 3.32.0 | MIT | File storage abstraction |
| `league/flysystem-local` | 3.31.0 | MIT | Local disk adapter |
| `league/mime-type-detection` | 1.16.0 | MIT | MIME type detection |
| `league/uri` | 7.8.1 | MIT | URI manipulation |
| `league/uri-interfaces` | 7.8.1 | MIT | URI contracts |
| `guzzlehttp/guzzle` | 7.10.0 | MIT | HTTP client |
| `guzzlehttp/promises` | 2.3.0 | MIT | Async promises |
| `guzzlehttp/psr7` | 2.9.0 | MIT | PSR-7 HTTP messages |
| `guzzlehttp/uri-template` | v1.0.5 | MIT | URI templates |
| `symfony/console` | v7.4.7 | MIT | CLI console |
| `symfony/css-selector` | v7.4.6 | MIT | CSS selector |
| `symfony/error-handler` | v7.4.4 | MIT | Error handling |
| `symfony/event-dispatcher` | v7.4.4 | MIT | Event system |
| `symfony/finder` | v7.4.6 | MIT | File finder |
| `symfony/http-foundation` | v7.4.7 | MIT | HTTP abstraction |
| `symfony/http-kernel` | v7.4.7 | MIT | HTTP kernel |
| `symfony/mailer` | v7.4.6 | MIT | Mail sending |
| `symfony/mime` | v7.4.7 | MIT | MIME types |
| `symfony/process` | v7.4.5 | MIT | Process execution |
| `symfony/routing` | v7.4.6 | MIT | URL routing |
| `symfony/string` | v7.4.6 | MIT | String utilities |
| `symfony/translation` | v7.4.6 | MIT | Translations |
| `symfony/uid` | v7.4.4 | MIT | UUID/ULID |
| `symfony/var-dumper` | v7.4.6 | MIT | Variable dumping |
| `symfony/clock` | v7.4.0 | MIT | Clock abstraction |
| `symfony/polyfill-*` | v1.33.0 | MIT | PHP compatibility shims |
| `brick/math` | 0.14.8 | MIT | Arbitrary precision math |
| `ramsey/uuid` | 4.9.2 | MIT | UUID generation |
| `ramsey/collection` | 2.1.1 | MIT | Collections |
| `egulias/email-validator` | 4.0.4 | MIT | Email validation |
| `fruitcake/php-cors` | v1.4.0 | MIT | CORS support |
| `dragonmantank/cron-expression` | v3.6.0 | MIT | Cron scheduling |
| `vlucas/phpdotenv` | v5.6.3 | BSD-3-Clause | `.env` file loading |
| `phpoption/phpoption` | 1.9.5 | Apache-2.0 | Option type |
| `nikic/php-parser` | v5.7.0 | BSD-3-Clause | PHP code parsing |
| `nette/schema` | v1.3.5 | BSD-3-Clause | Schema validation |
| `nette/utils` | v4.1.3 | BSD-3-Clause | Utility functions |
| `nunomaduro/termwind` | v2.4.0 | MIT | Terminal UI |
| `psy/psysh` | v0.12.22 | MIT | Shell (Tinker) |
| `dflydev/dot-access-data` | v3.0.3 | MIT | Dot-notation data access |
| `composer/pcre` | 3.3.2 | MIT | PCRE wrapper |
| `doctrine/inflector` | 2.1.0 | MIT | String inflection |
| `doctrine/lexer` | 3.0.1 | MIT | Lexer |
| `graham-campbell/result-type` | v1.1.4 | MIT | Result type |
| `maennchen/zipstream-php` | 3.2.2 | MIT | ZIP streaming |
| `markbaker/complex` | 3.0.2 | MIT | Complex numbers |
| `markbaker/matrix` | 3.0.1 | MIT | Matrix operations |
| `carbonphp/carbon-doctrine-types` | 3.2.0 | MIT | Carbon-Doctrine bridge |
| `ralouphie/getallheaders` | 3.0.3 | MIT | HTTP headers polyfill |
| `tijsverkoyen/css-to-inline-styles` | v2.4.0 | BSD-3-Clause | CSS inlining for mail |
| `voku/portable-ascii` | 2.0.3 | MIT | ASCII conversion |
| `psr/clock` | 1.0.0 | MIT | PSR-20 clock |
| `psr/container` | 2.0.2 | MIT | PSR-11 container |
| `psr/event-dispatcher` | 1.0.0 | MIT | PSR-14 events |
| `psr/http-client` | 1.0.3 | MIT | PSR-18 HTTP client |
| `psr/http-factory` | 1.1.0 | MIT | PSR-17 factories |
| `psr/http-message` | 2.0 | MIT | PSR-7 messages |
| `psr/log` | 3.0.2 | MIT | PSR-3 logging |
| `psr/simple-cache` | 3.0.0 | MIT | PSR-16 cache |

---

## JavaScript — Production Dependencies

> Resolved via `package-lock.json` (direct)

| Package | Version | License | Purpose |
|---------|---------|---------|---------|
| `chart.js` | 4.5.1 | MIT | Charts & data visualization |
| `simple-datatables` | 10.2.0 | LGPL-3.0 | Interactive data tables |

---

## JavaScript — Dev Dependencies (Build Toolchain)

> Resolved via `package-lock.json` (direct)

| Package | Version | License | Purpose |
|---------|---------|---------|---------|
| `vite` | 8.0.0 | MIT | Build bundler |
| `tailwindcss` | 4.2.2 | MIT | Utility-first CSS framework |
| `@tailwindcss/vite` | 4.2.2 | MIT | Tailwind v4 Vite plugin |
| `laravel-vite-plugin` | 3.0.0 | MIT | Laravel-Vite integration |
| `axios` | 1.13.6 | MIT | HTTP client (frontend) |
| `concurrently` | 9.2.1 | MIT | Run multiple CLI commands |

---

## PHP — Dev Dependencies

> Used for testing and code quality only, not shipped to production

| Package | Version | License | Purpose |
|---------|---------|---------|---------|
| `pestphp/pest` | v4.4.3 | MIT | Testing framework |
| `pestphp/pest-plugin-laravel` | v4.1.0 | MIT | Laravel test integration |
| `pestphp/pest-plugin-arch` | v4.0.0 | MIT | Architecture tests |
| `pestphp/pest-plugin-mutate` | v4.0.1 | MIT | Mutation testing |
| `phpunit/phpunit` | 12.5.14 | BSD-3-Clause | Unit testing core |
| `laravel/pail` | v1.2.6 | MIT | Log tailing in dev |
| `laravel/pint` | v1.29.0 | MIT | Code style fixer |
| `fakerphp/faker` | v1.24.1 | MIT | Test data generation |
| `mockery/mockery` | 1.6.12 | BSD-3-Clause | Mock objects |
| `nunomaduro/collision` | v8.9.1 | MIT | Better error display |

---

## License Summary

| License | Count | Packages |
|---------|-------|---------|
| MIT | ~70 | Majority of dependencies |
| BSD-3-Clause | ~12 | Symfony, league, phpunit, nette, mockery |
| LGPL-3.0 | 1 | `simple-datatables` |
| Apache-2.0 | 1 | `phpoption/phpoption` |

> **Note on LGPL-3.0** (`simple-datatables`): LGPL allows use in proprietary/closed applications as a linked library without requiring the application source to be open-sourced, as long as the library itself is not modified. If modifications to `simple-datatables` are made, those changes must be released under LGPL.

---

## Excluded from SBOM

- Transitive JS dependencies (rolldown, oxc, emnapi, etc.) — bundled and compiled by Vite at build time, not shipped as runtime modules
- XAMPP runtime components (Apache, MySQL) — infrastructure, not application dependencies
