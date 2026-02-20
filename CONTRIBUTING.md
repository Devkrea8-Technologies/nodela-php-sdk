# Contributing to the Nodela PHP SDK

Thank you for taking the time to contribute. This document covers everything you need to get the development environment running, the standards we follow, and the process for submitting changes.

---

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Reporting Bugs](#reporting-bugs)
3. [Suggesting Features](#suggesting-features)
4. [Development Setup](#development-setup)
5. [Running Tests](#running-tests)
6. [Code Style](#code-style)
7. [Static Analysis](#static-analysis)
8. [Pull Request Process](#pull-request-process)
9. [Commit Message Convention](#commit-message-convention)

---

## Code of Conduct

Be respectful, constructive, and collaborative. Harassment or abusive behaviour of any kind will not be tolerated.

---

## Reporting Bugs

Before opening a bug report:

- Check the [CHANGELOG](CHANGELOG.md) to see if the issue is already addressed.
- Search existing issues to avoid duplicates.

When opening a report please include:

- PHP version and OS
- SDK version (`composer show nodelapay/nodela`)
- Minimal reproducible example
- Expected vs. actual behaviour
- Full exception message and stack trace (redact any API keys)

---

## Suggesting Features

Open a GitHub issue with the label `enhancement`. Describe the use-case clearly so we can understand the motivation before discussing implementation.

---

## Development Setup

**Prerequisites**

- PHP 8.1+
- Composer 2.x

**Steps**

```bash
# Clone the repository
git clone https://github.com/nodelapay/nodela-php-sdk.git
cd nodela-php-sdk

# Install dependencies (including dev tools)
composer install
```

This installs:

- [PHPUnit 12.5](https://phpunit.de) — test runner
- [PHPStan 2.1](https://phpstan.org) — static analysis
- [PHP-CS-Fixer 3.94](https://cs.symfony.com) — code formatter

---

## Running Tests

```bash
# Run the full test suite
./vendor/bin/phpunit --no-coverage

# Run only unit tests
./vendor/bin/phpunit --testsuite Unit --no-coverage

# Run only integration tests
./vendor/bin/phpunit --testsuite Integration --no-coverage

# Run with HTML coverage report
./vendor/bin/phpunit
```

### Writing Tests

- Place unit tests under `tests/Unit/`, mirroring the `src/` directory structure.
- Place integration tests under `tests/Integration/`.
- Use `createMock(Request::class)` for resource tests — it bypasses the internal GuzzleHTTP constructor.
- For `Request` tests, use `ReflectionClass::newInstanceWithoutConstructor()` and inject a `GuzzleClient` backed by `MockHandler`.
- Every public method and every exception path must have test coverage.
- Tests must be deterministic — no network calls, no sleeps, no random data.

See [docs/testing.md](docs/testing.md) for detailed patterns and examples.

---

## Code Style

We use PHP-CS-Fixer to enforce PSR-12 with a few additional rules (array short syntax, ordered imports, trailing commas). Configuration lives in [.php-cs-fixer.php](.php-cs-fixer.php).

```bash
# Check for violations (dry-run)
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Apply fixes automatically
./vendor/bin/php-cs-fixer fix
```

All pull requests must pass the format check before merge. Running `composer format` is the quickest way to comply.

**Key conventions:**

- `declare(strict_types=1)` in every file.
- Type-hint every parameter and return value — no `mixed` unless unavoidable.
- `readonly` properties where state does not change after construction.
- Keep methods short and focused; prefer multiple small private methods over a single large one.
- PHPDoc blocks only where the type system cannot express the shape (e.g. `@param array{...}` for structured arrays).

---

## Static Analysis

We run PHPStan at the maximum level:

```bash
./vendor/bin/phpstan analyse src --level=max

# Or via Composer script
composer analyse
```

All new code must pass with zero errors. Do not suppress errors with `@phpstan-ignore` unless there is no other option — and if you do, add a comment explaining why.

---

## Pull Request Process

1. Fork the repository and create a branch from `main`.
2. Name your branch descriptively: `feat/refund-support`, `fix/rate-limit-header`, `docs/transactions-guide`.
3. Make your changes, ensuring:
   - All tests pass: `./vendor/bin/phpunit --no-coverage`
   - No style violations: `./vendor/bin/php-cs-fixer fix --dry-run --diff`
   - No static analysis errors: `./vendor/bin/phpstan analyse src --level=max`
4. Write or update tests for every behaviour change.
5. Update relevant documentation files under `docs/` and the `CHANGELOG.md`.
6. Open a pull request against `main` with a clear title and description referencing any related issues.
7. A maintainer will review and may request changes. Address feedback with new commits — do not force-push to an open PR.

---

## Commit Message Convention

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): short description

Optional longer body explaining what and why, not how.
```

**Types:**

| Type       | When to use                                      |
|------------|--------------------------------------------------|
| `feat`     | New user-facing feature                          |
| `fix`      | Bug fix                                          |
| `docs`     | Documentation only                               |
| `test`     | Adding or updating tests                         |
| `refactor` | Code restructure with no behaviour change        |
| `chore`    | Build scripts, CI, dependency updates            |
| `perf`     | Performance improvement                          |

**Examples:**

```
feat(invoices): add refund support
fix(request): handle ConnectException before RequestException
docs(transactions): add pagination example
test(config): cover custom header merging
```

Breaking changes must include `BREAKING CHANGE:` in the commit footer:

```
feat(config): remove deprecated setApiKey method

BREAKING CHANGE: setApiKey() has been removed. Pass the key to the constructor instead.
```

---

## Questions

Open a GitHub Discussion or email [sayhello@nodela.co](mailto:sayhello@nodela.co).
