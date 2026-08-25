<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Testing;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that records every request and answers from a queue.
 * When the queue is empty it returns an empty 200 response, which the
 * Vapi SDK treats as a null result.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface> */
    private array $queue = [];

    /** @var list<RequestInterface> */
    private array $requests = [];

    public function queue(ResponseInterface ...$responses): void
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return array_shift($this->queue) ?? new Response(200, ['Content-Type' => 'application/json'], '');
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return $this->requests;
    }
}
