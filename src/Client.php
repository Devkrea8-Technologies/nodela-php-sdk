<?php

declare(strict_types=1);

namespace Nodelapay\Nodela;

use Nodelapay\Nodela\Http\Request;
use Nodelapay\Nodela\Resources\Invoices;
use Nodelapay\Nodela\Resources\Transactions;

class Client
{
    private Config $config;
    private Request $request;

    public readonly Invoices $invoices;
    public readonly Transactions $transactions;

    public function __construct(string $apiKey, ?Config $config = null)
    {
        $this->config = $config ?? new Config($apiKey);
        $this->request = new Request($this->config);
        $this->invoices = new Invoices($this->request);
        $this->transactions = new Transactions($this->request);
    }
}
