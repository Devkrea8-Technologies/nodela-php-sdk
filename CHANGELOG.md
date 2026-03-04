# Changelog

All notable changes to this project will be documented in this file.

Entries are generated automatically from commit messages following [Conventional Commits](https://www.conventionalcommits.org/) spec.
Format: `type(scope): description` — e.g. `feat(invoices): add refund support`

---

## [1.1.0](https://github.com/Devkrea8-Technologies/nodela-php-sdk/compare/nodelapay/nodela-v1.0.0...nodelapay/nodela-v1.1.0) (2026-03-04)


### Features

* bootstrap release-please ([17ae975](https://github.com/Devkrea8-Technologies/nodela-php-sdk/commit/17ae9757572a5f2ce48179b4bfdd8bc87a8af757))


### Documentation

* Add supported currencies page, update documentation structure and formatting, remove Response unit tests, and introduce a docs notification workflow. ([2912f3b](https://github.com/Devkrea8-Technologies/nodela-php-sdk/commit/2912f3b4268f548d7ef64b7f3852313cf554ffde))

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
