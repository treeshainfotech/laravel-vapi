<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Vapi\Core\Client\HttpMethod;
use Vapi\Core\Client\RawClient;
use Vapi\Core\Json\JsonApiRequest;
use Vapi\Exceptions\VapiApiException;
use Vapi\Exceptions\VapiException;

/**
 * Escape hatch for Vapi endpoints the SDK does not cover yet. Requests
 * reuse the configured authentication, base URL, retries, and HTTP
 * client; responses are decoded JSON, and non-2xx responses throw a
 * VapiApiException carrying the status code and error body.
 */
final readonly class RawVapiClient
{
    public function __construct(
        private RawClient $client,
        private string $baseUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     *
     * @throws VapiApiException On a non-2xx response.
     * @throws VapiException When the request fails or the response is not JSON.
     */
    public function get(string $path, array $query = [], array $headers = []): mixed
    {
        return $this->send(HttpMethod::GET, $path, $query, null, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     *
     * @throws VapiApiException On a non-2xx response.
     * @throws VapiException When the request fails or the response is not JSON.
     */
    public function post(string $path, mixed $body = null, array $query = [], array $headers = []): mixed
    {
        return $this->send(HttpMethod::POST, $path, $query, $body, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     *
     * @throws VapiApiException On a non-2xx response.
     * @throws VapiException When the request fails or the response is not JSON.
     */
    public function patch(string $path, mixed $body = null, array $query = [], array $headers = []): mixed
    {
        return $this->send(HttpMethod::PATCH, $path, $query, $body, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     *
     * @throws VapiApiException On a non-2xx response.
     * @throws VapiException When the request fails or the response is not JSON.
     */
    public function put(string $path, mixed $body = null, array $query = [], array $headers = []): mixed
    {
        return $this->send(HttpMethod::PUT, $path, $query, $body, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     *
     * @throws VapiApiException On a non-2xx response.
     * @throws VapiException When the request fails or the response is not JSON.
     */
    public function delete(string $path, array $query = [], array $headers = []): mixed
    {
        return $this->send(HttpMethod::DELETE, $path, $query, null, $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     *
     * @throws VapiApiException On a non-2xx response.
     * @throws VapiException When the request fails or the response is not JSON.
     */
    private function send(HttpMethod $method, string $path, array $query, mixed $body, array $headers): mixed
    {
        $path = ltrim($path, '/');

        try {
            $response = $this->client->sendRequest(new JsonApiRequest(
                baseUrl: $this->baseUrl,
                path: $path,
                method: $method,
                headers: $headers,
                query: $query,
                body: $body,
            ));
        } catch (ClientExceptionInterface $exception) {
            throw new VapiException(
                message: sprintf('Vapi request %s /%s failed: %s', $method->name, $path, $exception->getMessage()),
                previous: $exception,
            );
        }

        $status = $response->getStatusCode();
        $contents = $response->getBody()->getContents();

        if ($status >= 200 && $status < 300) {
            return $contents === '' ? null : $this->decode($contents);
        }

        throw new VapiApiException(
            message: sprintf('Vapi request %s /%s failed with status %d.', $method->name, $path, $status),
            statusCode: $status,
            body: $this->decodeLeniently($contents),
        );
    }

    /**
     * @throws VapiException
     */
    private function decode(string $contents): mixed
    {
        try {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new VapiException(message: 'Failed to decode the Vapi response as JSON.', previous: $exception);
        }
    }

    private function decodeLeniently(string $contents): mixed
    {
        try {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $contents;
        }
    }
}
