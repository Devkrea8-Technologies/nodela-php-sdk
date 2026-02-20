# Changelog

All notable changes to this project will be documented in this file.

Entries are generated automatically from commit messages following [Conventional Commits](https://www.conventionalcommits.org/) spec.
Format: `type(scope): description` — e.g. `feat(invoices): add refund support`

---

## [1.0.0] - 2026-02-20

### Features

- **Client**: Main SDK entry point with `$invoices` and `$transactions` resource accessors
- **Invoices**: `create(array $data)` — generate a payment invoice with currency validation
- **Invoices**: `verify(string $invoiceId)` — check invoice payment status
- **Transactions**: `list(array $params)` — retrieve and filter transaction history
- **Config**: Configurable API key, base URL, request timeout, and custom HTTP headers
- **Exceptions**: Structured exception hierarchy:
  - `ApiException` — base for all SDK errors
  - `AuthenticationException` — invalid or missing API key
  - `ValidationException` — bad request data
  - `RateLimitException` — API rate limit exceeded
- PHP 8.1+ support
- GuzzleHTTP 7.10 HTTP client
