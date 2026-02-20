<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Resources;

use Nodelapay\Nodela\Http\Request;

abstract class BaseResource
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    abstract protected function getBaseEndpoint(): string;

    protected function endpoint(string $path = ''): string
    {
        $base = $this->getBaseEndpoint();

        if (empty($path)) {
            return $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}
