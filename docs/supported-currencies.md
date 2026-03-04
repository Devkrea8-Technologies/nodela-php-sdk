# Supported Currencies

The Nodela PHP SDK supports 60+ fiat currencies across all major regions. When you create an invoice with a fiat currency, it is automatically converted to the equivalent stablecoin amount at current exchange rates.

## Currency List by Region

### Americas

| Code | Currency |
|------|----------|
| `USD` | United States Dollar |
| `CAD` | Canadian Dollar |
| `MXN` | Mexican Peso |
| `BRL` | Brazilian Real |
| `ARS` | Argentine Peso |
| `CLP` | Chilean Peso |
| `COP` | Colombian Peso |
| `PEN` | Peruvian Sol |
| `JMD` | Jamaican Dollar |
| `TTD` | Trinidad and Tobago Dollar |

### Europe

| Code | Currency |
|------|----------|
| `EUR` | Euro |
| `GBP` | British Pound Sterling |
| `CHF` | Swiss Franc |
| `SEK` | Swedish Krona |
| `NOK` | Norwegian Krone |
| `DKK` | Danish Krone |
| `PLN` | Polish Zloty |
| `CZK` | Czech Koruna |
| `HUF` | Hungarian Forint |
| `RON` | Romanian Leu |
| `BGN` | Bulgarian Lev |
| `HRK` | Croatian Kuna |
| `ISK` | Icelandic Krona |
| `TRY` | Turkish Lira |
| `RUB` | Russian Ruble |
| `UAH` | Ukrainian Hryvnia |

### Africa

| Code | Currency |
|------|----------|
| `NGN` | Nigerian Naira |
| `ZAR` | South African Rand |
| `KES` | Kenyan Shilling |
| `GHS` | Ghanaian Cedi |
| `EGP` | Egyptian Pound |
| `MAD` | Moroccan Dirham |
| `TZS` | Tanzanian Shilling |
| `UGX` | Ugandan Shilling |
| `XOF` | West African CFA Franc |
| `XAF` | Central African CFA Franc |
| `ETB` | Ethiopian Birr |

### Asia

| Code | Currency |
|------|----------|
| `JPY` | Japanese Yen |
| `CNY` | Chinese Yuan |
| `INR` | Indian Rupee |
| `KRW` | South Korean Won |
| `IDR` | Indonesian Rupiah |
| `MYR` | Malaysian Ringgit |
| `THB` | Thai Baht |
| `PHP` | Philippine Peso |
| `VND` | Vietnamese Dong |
| `SGD` | Singapore Dollar |
| `HKD` | Hong Kong Dollar |
| `TWD` | New Taiwan Dollar |
| `BDT` | Bangladeshi Taka |
| `PKR` | Pakistani Rupee |
| `LKR` | Sri Lankan Rupee |

### Middle East

| Code | Currency |
|------|----------|
| `AED` | United Arab Emirates Dirham |
| `SAR` | Saudi Riyal |
| `QAR` | Qatari Riyal |
| `KWD` | Kuwaiti Dinar |
| `BHD` | Bahraini Dinar |
| `OMR` | Omani Rial |
| `ILS` | Israeli New Shekel |
| `JOD` | Jordanian Dinar |

### Oceania

| Code | Currency |
|------|----------|
| `AUD` | Australian Dollar |
| `NZD` | New Zealand Dollar |
| `FJD` | Fijian Dollar |

## Programmatic Access

You can access the full list of supported currency codes at runtime via the `Invoices` resource:

```php
use Nodelapay\Nodela\Resources\Invoices;

print_r(Invoices::SUPPORTED_CURRENCIES);
// ['USD', 'CAD', 'MXN', 'BRL', ..., 'AUD', 'NZD', 'FJD']

// Check if a currency is supported
if (in_array('NGN', Invoices::SUPPORTED_CURRENCIES, true)) {
    echo "Nigerian Naira is supported";
}
```

## Validation

The SDK validates currency codes before sending the request. If you pass an unsupported currency code to `Invoices::create()`, an `\InvalidArgumentException` will be raised.

```php
try {
    $client->invoices->create([
        'amount'   => 100,
        'currency' => 'XYZ' // unsupported
    ]);
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage();
}
```
