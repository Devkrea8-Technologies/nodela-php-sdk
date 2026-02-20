# Getting Started

This guide walks you through installing the Nodela PHP SDK, configuring your API key, and making your first request.

---

## Prerequisites

- PHP 8.1 or higher
- [Composer](https://getcomposer.org) 2.x
- A Nodela account with an API key — sign up at [nodela.co](https://nodela.co)

---

## Installation

Install the SDK via Composer:

```bash
composer require nodelapay/nodela
```

Composer will pull in GuzzleHTTP 7.10 as a dependency automatically.

---

## Initialise the Client

The `Client` class is the single entry point for the entire SDK:

```php
use Nodelapay\Nodela\Client;

$client = new Client('your-api-key');
```

The client immediately exposes two resource accessors:

- `$client->invoices` — create and verify invoices
- `$client->transactions` — list transaction history

---

## Your First Request

### Create an invoice

```php
use Nodelapay\Nodela\Client;

$client = new Client('your-api-key');

$response = $client->invoices->create([
    'amount'      => 5000,
    'currency'    => 'NGN',
    'success_url' => 'https://example.com/success',
    'cancel_url'  => 'https://example.com/cancel',
    'customer'    => [
        'name'  => 'Jane Doe',
        'email' => 'jane@example.com',
    ],
    'title'       => 'Order #1042',
    'description' => 'Premium plan subscription',
]);

$data = $response->getData();
echo $data['id'];          // invoice ID
echo $data['payment_url']; // redirect the customer here
```

### Verify payment status

Once a customer completes payment, verify the invoice:

```php
$status = $client->invoices->verify('inv_abc123');

$data = $status->getData();
echo $data['status']; // "paid", "pending", "expired", etc.
```

### List transactions

```php
$response = $client->transactions->list([
    'page'  => 1,
    'limit' => 20,
]);

foreach ($response->getData() as $transaction) {
    echo $transaction['id'] . ' — ' . $transaction['amount'];
}
```

---

## Handling Errors

Wrap API calls in a try/catch to handle errors gracefully:

```php
use Nodelapay\Nodela\Exceptions\AuthenticationException;
use Nodelapay\Nodela\Exceptions\ValidationException;
use Nodelapay\Nodela\Exceptions\RateLimitException;
use Nodelapay\Nodela\Exceptions\ApiException;

try {
    $response = $client->invoices->create([
        'amount'   => 5000,
        'currency' => 'NGN',
    ]);
} catch (AuthenticationException $e) {
    // Invalid API key — check your credentials
    echo 'Auth error: ' . $e->getMessage();
} catch (ValidationException $e) {
    // The request data failed validation
    foreach ($e->getErrors() as $field => $messages) {
        echo "$field: " . implode(', ', $messages);
    }
} catch (RateLimitException $e) {
    // Too many requests — wait before retrying
    $wait = $e->getRetryAfter() ?? 60;
    echo "Rate limited. Retry after {$wait}s";
} catch (ApiException $e) {
    // Any other API or network error
    echo 'Error ' . $e->getStatusCode() . ': ' . $e->getMessage();
}
```

Full details on every exception type: [error-handling.md](error-handling.md)

---

## Next Steps

| Topic                            | Guide                                  |
|----------------------------------|----------------------------------------|
| Timeouts and custom headers      | [configuration.md](configuration.md)  |
| All invoice parameters           | [invoices.md](invoices.md)             |
| Pagination and filtering         | [transactions.md](transactions.md)     |
| Exception hierarchy              | [error-handling.md](error-handling.md)|
| HTTP internals                   | [http-layer.md](http-layer.md)         |
| Running and writing tests        | [testing.md](testing.md)               |
