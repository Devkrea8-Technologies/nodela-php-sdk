# Invoices

The `$client->invoices` resource lets you create payment invoices and verify their status.

---

## Methods

| Method                        | HTTP          | Endpoint                          |
|-------------------------------|---------------|-----------------------------------|
| `create(array $params)`       | `POST`        | `/v1/invoices`                    |
| `verify(string $invoiceId)`   | `GET`         | `/v1/invoices/{id}/verify`        |

---

## `create()`

Creates a new payment invoice and returns a URL that you redirect your customer to.

### Signature

```php
public function create(array $params): Response
```

### Parameters

| Field                    | Type              | Required | Description                                              |
|--------------------------|-------------------|----------|----------------------------------------------------------|
| `amount`                 | `int\|float`      | Yes      | Amount to charge                                         |
| `currency`               | `string`          | Yes      | ISO 4217 currency code (see [Supported Currencies](#supported-currencies)) |
| `success_url`            | `string`          | No       | Redirect URL after successful payment                    |
| `cancel_url`             | `string`          | No       | Redirect URL if the customer cancels                     |
| `webhook_url`            | `string`          | No       | URL that receives a POST when payment status changes     |
| `reference`              | `string`          | No       | Your internal order or reference ID                      |
| `title`                  | `string`          | No       | Short title shown on the payment page                    |
| `description`            | `string`          | No       | Longer description shown on the payment page             |
| `customer`               | `array`           | No       | Customer details (see below)                             |
| `customer.name`          | `string`          | No       | Customer display name                                    |
| `customer.email`         | `string`          | No       | Customer email address                                   |

### Currency validation

The `currency` field is validated client-side before the request is sent. Passing an unsupported currency throws an `InvalidArgumentException` immediately — no network round-trip:

```
InvalidArgumentException: Unsupported currency: "XYZ". Supported currencies: USD, EUR, ...
```

The value is normalised to uppercase automatically, so `'ngn'` and `'NGN'` are equivalent.

### Example: minimal invoice

```php
$response = $client->invoices->create([
    'amount'   => 1000,
    'currency' => 'USD',
]);

$data = $response->getData();
echo $data['id'];          // e.g. "inv_abc123"
echo $data['payment_url']; // redirect your customer here
```

### Example: full invoice

```php
$response = $client->invoices->create([
    'amount'      => 25000,
    'currency'    => 'NGN',
    'success_url' => 'https://example.com/thank-you',
    'cancel_url'  => 'https://example.com/checkout',
    'webhook_url' => 'https://example.com/webhooks/nodela',
    'reference'   => 'order_7891',
    'title'       => 'Annual Subscription',
    'description' => 'Pro plan — 12 months',
    'customer'    => [
        'name'  => 'Amara Okafor',
        'email' => 'amara@example.com',
    ],
]);
```

### Response shape

```json
{
  "id": "inv_abc123",
  "status": "pending",
  "amount": 25000,
  "currency": "NGN",
  "payment_url": "https://pay.nodela.co/inv_abc123",
  "reference": "order_7891",
  "created_at": "2026-02-21T10:00:00Z"
}
```

---

## `verify()`

Checks the current payment status of an invoice. Call this from your `success_url` handler or in response to a webhook event.

### Signature

```php
public function verify(string $invoiceId): Response
```

### Parameters

| Parameter    | Type     | Description                          |
|--------------|----------|--------------------------------------|
| `$invoiceId` | `string` | The invoice ID returned by `create()` |

### Example

```php
$response = $client->invoices->verify('inv_abc123');

$data = $response->getData();

switch ($data['status']) {
    case 'paid':
        // Fulfil the order
        break;
    case 'pending':
        // Payment not yet received
        break;
    case 'expired':
        // Invoice has expired — create a new one if needed
        break;
}
```

### Response shape

```json
{
  "id": "inv_abc123",
  "status": "paid",
  "amount": 25000,
  "currency": "NGN",
  "paid_at": "2026-02-21T10:05:42Z"
}
```

---

## Supported Currencies

The SDK validates the currency against this list before sending any request. Currencies are grouped by region.

### Americas

`USD` `CAD` `MXN` `BRL` `ARS` `CLP` `COP` `PEN` `JMD` `TTD`

### Europe

`EUR` `GBP` `CHF` `SEK` `NOK` `DKK` `PLN` `CZK` `HUF` `RON` `BGN` `HRK` `ISK` `TRY` `RUB` `UAH`

### Africa

`NGN` `ZAR` `KES` `GHS` `EGP` `MAD` `TZS` `UGX` `XOF` `XAF` `ETB`

### Asia

`JPY` `CNY` `INR` `KRW` `IDR` `MYR` `THB` `PHP` `VND` `SGD` `HKD` `TWD` `BDT` `PKR` `LKR`

### Middle East

`AED` `SAR` `QAR` `KWD` `BHD` `OMR` `ILS` `JOD`

### Oceania

`AUD` `NZD` `FJD`

The full list is available at runtime via the constant:

```php
Nodelapay\Nodela\Resources\Invoices::SUPPORTED_CURRENCIES
```

---

## Error Handling

```php
use Nodelapay\Nodela\Exceptions\ValidationException;
use Nodelapay\Nodela\Exceptions\ApiException;

try {
    $response = $client->invoices->create([
        'amount'   => 500,
        'currency' => 'NGN',
    ]);
} catch (\InvalidArgumentException $e) {
    // Unsupported currency — thrown before any network call
    echo $e->getMessage();
} catch (ValidationException $e) {
    // API returned 422 — inspect field-level errors
    foreach ($e->getErrors() as $field => $messages) {
        echo "$field: " . implode(', ', (array) $messages) . "\n";
    }
} catch (ApiException $e) {
    echo 'API error ' . $e->getStatusCode() . ': ' . $e->getMessage();
}
```

See [error-handling.md](error-handling.md) for the full exception reference.
