# Nodela PHP SDK

Official PHP SDK for the [Nodela](https://nodela.co) payment API. Provides a clean, typed interface for creating invoices, verifying payments, and listing transactions.

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## Requirements

- PHP 8.1+
- [Composer](https://getcomposer.org)
- GuzzleHTTP 7.10+

---

## Installation

```bash
composer require nodelapay/nodela
```

---

## Quick Start

```php
use Nodelapay\Nodela\Client;

$client = new Client('your-api-key');

// Create an invoice
$invoice = $client->invoices->create([
    'amount'      => 5000,
    'currency'    => 'NGN',
    'success_url' => 'https://example.com/success',
    'cancel_url'  => 'https://example.com/cancel',
    'customer'    => [
        'name'  => 'Jane Doe',
        'email' => 'jane@example.com',
    ],
]);

// Verify payment status
$status = $client->invoices->verify($invoice->getData()['id']);

// List transactions
$transactions = $client->transactions->list(['page' => 1, 'limit' => 20]);
```

---

## Configuration

By default the client uses a 30-second timeout and sends standard JSON headers. You can customise both via `Config`:

```php
use Nodelapay\Nodela\Client;
use Nodelapay\Nodela\Config;

$config = new Config(
    apiKey:  'your-api-key',
    timeout: 60,
    headers: ['X-Custom-Header' => 'value'],
);

$client = new Client('your-api-key', $config);
```

See [docs/configuration.md](docs/configuration.md) for all options.

---

## Resources

### Invoices

| Method                       | Description                                    |
|------------------------------|------------------------------------------------|
| `create(array $params)`      | Create a new payment invoice                   |
| `verify(string $invoiceId)`  | Check the payment status of an invoice         |

Full reference: [docs/invoices.md](docs/invoices.md)

### Transactions

| Method                       | Description                                    |
|------------------------------|------------------------------------------------|
| `list(array $params = [])`   | Retrieve and filter transaction history        |

Full reference: [docs/transactions.md](docs/transactions.md)

---

## Error Handling

The SDK throws typed exceptions for every API error:

```php
use Nodelapay\Nodela\Exceptions\AuthenticationException;
use Nodelapay\Nodela\Exceptions\ValidationException;
use Nodelapay\Nodela\Exceptions\RateLimitException;
use Nodelapay\Nodela\Exceptions\ApiException;

try {
    $invoice = $client->invoices->create([...]);
} catch (AuthenticationException $e) {
    // Invalid or missing API key (HTTP 401)
} catch (ValidationException $e) {
    // Bad request data (HTTP 422)
    $fieldErrors = $e->getErrors();
} catch (RateLimitException $e) {
    // Rate limit exceeded (HTTP 429)
    $retryAfter = $e->getRetryAfter(); // seconds
} catch (ApiException $e) {
    // All other API errors
    $statusCode = $e->getStatusCode();
    $body       = $e->getResponse();
}
```

Full reference: [docs/error-handling.md](docs/error-handling.md)

---

## Documentation

| Document                                     | Description                                       |
|----------------------------------------------|---------------------------------------------------|
| [Getting Started](docs/getting-started.md)   | Installation, first request, common patterns      |
| [Configuration](docs/configuration.md)       | Config class options and custom headers           |
| [Invoices](docs/invoices.md)                 | Create and verify invoices                        |
| [Transactions](docs/transactions.md)         | List and filter transactions                      |
| [Error Handling](docs/error-handling.md)     | Exception hierarchy and recovery strategies       |
| [HTTP Layer](docs/http-layer.md)             | Request/Response internals for advanced use       |
| [Testing](docs/testing.md)                   | Running the test suite, writing tests             |

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on reporting bugs, submitting pull requests, and running the development toolchain.

---

## Changelog

All notable changes are documented in [CHANGELOG.md](CHANGELOG.md).

---

## License

MIT — see [LICENSE](LICENSE) for details.
