<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\TestDouble;

use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;

/**
 * LarkClient 的测试桩实现.
 *
 * @internal 仅用于测试
 */
final class StubLarkClient implements LarkClientInterface
{
    private ?ResponseInterface $mockResponse = null;

    public function __construct(?ResponseInterface $mockResponse = null)
    {
        $this->mockResponse = $mockResponse;
    }

    /**
     * @param array<mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (null === $this->mockResponse) {
            throw new \LogicException('Mock response not set');
        }

        // Cast to mixed array to satisfy type checking
        /** @var array<string, mixed> $typedOptions */
        $typedOptions = $options;

        return $this->mockResponse;
    }

    public function getBaseUrl(): string
    {
        return 'https://open.feishu.cn';
    }

    public function getAppId(): string
    {
        return 'test_app_id';
    }

    public function isDebug(): bool
    {
        return false;
    }

    public function setDebug(bool $debug): void
    {
        // Mock implementation
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return new class implements ResponseStreamInterface {
            public function current(): ChunkInterface
            {
                throw new \LogicException('Not implemented in mock');
            }

            public function next(): void
            {
            }

            public function key(): ResponseInterface
            {
                throw new \LogicException('Not implemented in mock');
            }

            public function valid(): bool
            {
                return false;
            }

            public function rewind(): void
            {
            }
        };
    }

    /**
     * @param array<mixed> $options
     */
    public function withOptions(array $options): static
    {
        // Cast to mixed array to satisfy type checking
        /** @var array<string, mixed> $typedOptions */
        $typedOptions = $options;

        return $this;
    }

    public function setMockResponse(ResponseInterface $response): void
    {
        $this->mockResponse = $response;
    }
}
