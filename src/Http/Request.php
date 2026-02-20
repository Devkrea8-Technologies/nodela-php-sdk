<?php

declare(strict_types=1);

namespace Nodelapay\Nodela\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Nodelapay\Nodela\Config;
use Nodelapay\Nodela\Exceptions\ApiException;
use Nodelapay\Nodela\Exceptions\AuthenticationException;
use Nodelapay\Nodela\Exceptions\RateLimitException;
use Nodelapay\Nodela\Exceptions\ValidationException;

class Request
{
    private GuzzleClient $client;
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->client = new GuzzleClient([
          'timeout' => $config->getTimeout(),
          'headers' => $this->buildHeaders(),
        ]);
    }

    private function buildHeaders(): array
    {
        $headers = $this->config->getHeaders();
        $headers['Authorization'] = 'Bearer ' . $this->config->getApiKey();

        return $headers;
    }

    public function get(string $endpoint, array $query = []): Response
    {
        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    public function post(string $endpoint, array $data = []): Response
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    public function put(string $endpoint, array $data = []): Response
    {
        return $this->request('PUT', $endpoint, ['json' => $data]);
    }

    public function patch(string $endpoint, array $data = []): Response
    {
        return $this->request('PATCH', $endpoint, ['json' => $data]);
    }

    public function delete(string $endpoint): Response
    {
        return $this->request('DELETE', $endpoint);
    }

    private function request(string $method, string $endpoint, array $options = []): Response
    {
        try {
            $url = $this->config->getFullUrl($endpoint);
            $response = $this->client->request($method, $url, $options);

            return new Response(
                $response->getStatusCode(),
                json_decode($response->getBody()->getContents(), true) ?? [],
                $response->getHeaders()
            );
        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (ServerException $e) {
            $this->handleServerException($e);
        } catch (ConnectException $e) {
            throw new ApiException(
                'Request failed: ' . $e->getMessage(),
                $e->getCode(),
                [],
                $e
            );
        } catch (RequestException $e) {
            throw new ApiException(
                'Request failed: ' . $e->getMessage(),
                $e->getCode(),
                [],
                $e
            );
        }
    }

    private function handleClientException(ClientException $e): void
    {
        $response = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents(), true) ?? [];
        $message = $body['message'] ?? $body['error'] ?? 'Client error occurred';

        switch ($statusCode) {
            case 401:
                throw new AuthenticationException($message, $statusCode, $body);
            case 422:
                throw new ValidationException(
                    $message,
                    $body['errors'] ?? [],
                    $statusCode,
                    $body
                );
            case 429:
                $retryAfter = isset($response->getHeader('Retry-After')[0])
                  ? (int) $response->getHeader('Retry-After')[0]
                  : null;

                throw new RateLimitException($message, $retryAfter, $statusCode, $body);
            default:
                throw new ApiException($message, $statusCode, $body);
        }
    }

    private function handleServerException(ServerException $e): void
    {
        $response = $e->getResponse();
        $body = json_decode($response->getBody()->getContents(), true) ?? [];
        $message = $body['message'] ?? 'Server error occurred';

        throw new ApiException(
            $message,
            $response->getStatusCode(),
            $body,
            $e
        );
    }
}
