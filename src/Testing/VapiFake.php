<?php

declare(strict_types=1);

namespace TreeshaInfotech\Vapi\Testing;

use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Assertion handle returned by Vapi::fake(). All Vapi API calls made
 * through the facade after faking are recorded here instead of hitting
 * the network.
 */
final readonly class VapiFake
{
    public function __construct(private FakeHttpClient $transport) {}

    public function queue(ResponseInterface ...$responses): self
    {
        $this->transport->queue(...$responses);

        return $this;
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return $this->transport->requests();
    }

    public function lastRequest(): ?RequestInterface
    {
        $requests = $this->transport->requests();

        return $requests === [] ? null : $requests[count($requests) - 1];
    }

    /**
     * Assert that at least one recorded request satisfies the callback.
     *
     * @param  callable(RequestInterface): bool  $callback
     */
    public function assertSent(callable $callback): self
    {
        $matched = false;
        foreach ($this->transport->requests() as $request) {
            if ($callback($request) === true) {
                $matched = true;
                break;
            }
        }

        Assert::assertTrue($matched, 'No request sent to Vapi matches the given callback.');

        return $this;
    }

    public function assertSentCount(int $count): self
    {
        Assert::assertCount(
            $count,
            $this->transport->requests(),
            sprintf('Expected %d requests to be sent to Vapi.', $count),
        );

        return $this;
    }

    public function assertNothingSent(): self
    {
        Assert::assertCount(
            0,
            $this->transport->requests(),
            'Expected no requests to be sent to Vapi.',
        );

        return $this;
    }
}
