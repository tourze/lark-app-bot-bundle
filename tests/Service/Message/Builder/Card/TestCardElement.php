<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder\Card;

use Tourze\LarkAppBotBundle\Service\Message\Builder\Card\CardElement;

/**
 * 用于测试的 CardElement 测试实现类.
 */
class TestCardElement extends CardElement
{
    // Expose protected methods for testing
    public function withTestData(string $key, mixed $value): self
    {
        $this->setData($key, $value);

        return $this;
    }

    public function getTestData(string $key): mixed
    {
        return $this->getData($key);
    }

    protected function getTag(): string
    {
        return 'test_element';
    }
}
