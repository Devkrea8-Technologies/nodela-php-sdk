<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Resources;

use InvalidArgumentException;
use Nodelapay\Nodela\Http\Response;

class Invoices extends BaseResource
{
    public const SUPPORTED_CURRENCIES = [
        // Americas
        'USD', 'CAD', 'MXN', 'BRL', 'ARS', 'CLP', 'COP', 'PEN', 'JMD', 'TTD',
        // Europe
        'EUR', 'GBP', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON',
        'BGN', 'HRK', 'ISK', 'TRY', 'RUB', 'UAH',
        // Africa
        'NGN', 'ZAR', 'KES', 'GHS', 'EGP', 'MAD', 'TZS', 'UGX', 'XOF', 'XAF', 'ETB',
        // Asia
        'JPY', 'CNY', 'INR', 'KRW', 'IDR', 'MYR', 'THB', 'PHP', 'VND', 'SGD',
        'HKD', 'TWD', 'BDT', 'PKR', 'LKR',
        // Middle East
        'AED', 'SAR', 'QAR', 'KWD', 'BHD', 'OMR', 'ILS', 'JOD',
        // Oceania
        'AUD', 'NZD', 'FJD',
    ];

    protected function getBaseEndpoint(): string
    {
        return '/invoices';
    }

    /**
     * Create a new invoice.
     *
     * @param array{
     *   amount: int|float,
     *   currency: string,
     *   success_url?: string,
     *   cancel_url?: string,
     *   webhook_url?: string,
     *   reference?: string,
     *   customer?: array{name?: string, email: string},
     *   title?: string,
     *   description?: string
     * } $params
     */
    public function create(array $params): Response
    {
        $currency = strtoupper($params['currency'] ?? '');

        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported currency: "%s". Supported currencies: %s',
                $params['currency'] ?? '',
                implode(', ', self::SUPPORTED_CURRENCIES)
            ));
        }

        $params['currency'] = $currency;

        return $this->request->post($this->endpoint(), $params);
    }

    public function verify(string $invoiceId): Response
    {
        return $this->request->get($this->endpoint($invoiceId . '/verify'));
    }
}
