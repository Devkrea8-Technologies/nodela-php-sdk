<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Resources;

use Nodelapay\Nodela\Http\Response;

class Transactions extends BaseResource
{
    protected function getBaseEndpoint(): string
    {
        return '/transactions';
    }

    /**
     * List transactions.
     *
     * @param array{page?: int, limit?: int} $params
     */
    public function list(array $params = []): Response
    {
        return $this->request->get($this->endpoint(), $params);
    }
}
