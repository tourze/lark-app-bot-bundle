<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Client;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\CircuitBreakerOpenException;

/**
 * 熔断器.
 *
 * 用于保护API调用，防止雪崩效应
 */
#[Autoconfigure(public: true)]
final class CircuitBreaker
{
    /**
     * 熔断器状态.
     */
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    /**
     * 默认配置.
     */
    private const DEFAULT_FAILURE_THRESHOLD = 5;
    private const DEFAULT_SUCCESS_THRESHOLD = 2;
    private const DEFAULT_TIMEOUT = 60; // 秒
    private const DEFAULT_HALF_OPEN_MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly string $name,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly int $failureThreshold = self::DEFAULT_FAILURE_THRESHOLD,
        private readonly int $successThreshold = self::DEFAULT_SUCCESS_THRESHOLD,
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
        private readonly int $halfOpenMaxAttempts = self::DEFAULT_HALF_OPEN_MAX_ATTEMPTS,
    ) {
    }

    /**
     * 执行受保护的调用.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     * @throws CircuitBreakerOpenException
     * @throws \Throwable
     */
    public function execute(callable $callback): mixed
    {
        $state = $this->getState();

        if (self::STATE_OPEN === $state) {
            if (!$this->shouldAttemptReset()) {
                throw new CircuitBreakerOpenException(\sprintf('熔断器 "%s" 处于打开状态', $this->name));
            }

            // 转换到半开状态
            $this->transitionToHalfOpen();
        }

        try {
            $result = $callback();
            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * 获取当前状态.
     */
    public function getState(): string
    {
        $stateItem = $this->cache->getItem($this->getCacheKey('state'));

        $state = $stateItem->isHit() ? $stateItem->get() : self::STATE_CLOSED;
        \assert(\is_string($state));

        return $state;
    }

    /**
     * 获取统计信息.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $state = $this->getState();
        $stats = [
            'name' => $this->name,
            'state' => $state,
            'failure_threshold' => $this->failureThreshold,
            'success_threshold' => $this->successThreshold,
            'timeout' => $this->timeout,
        ];

        // 添加计数器信息
        $failureItem = $this->cache->getItem($this->getCacheKey('failure_count'));
        if ($failureItem->isHit()) {
            $failureCount = $failureItem->get();
            \assert(\is_int($failureCount));
            $stats['failure_count'] = $failureCount;
        }

        if (self::STATE_HALF_OPEN === $state) {
            $successItem = $this->cache->getItem($this->getCacheKey('half_open_success_count'));
            if ($successItem->isHit()) {
                $successCount = $successItem->get();
                \assert(\is_int($successCount));
                $stats['half_open_success_count'] = $successCount;
            }
        }

        if (self::STATE_OPEN === $state) {
            $lastOpenTimeItem = $this->cache->getItem($this->getCacheKey('last_open_time'));
            if ($lastOpenTimeItem->isHit()) {
                $lastOpenTime = $lastOpenTimeItem->get();
                \assert(\is_int($lastOpenTime));
                $stats['last_open_time'] = $lastOpenTime;
                $stats['remaining_timeout'] = max(0, $this->timeout - (time() - $lastOpenTime));
            }
        }

        return $stats;
    }

    /**
     * 手动重置熔断器.
     */
    public function reset(): void
    {
        $this->transitionToClosed();

        $this->logger->info('熔断器已手动重置', [
            'circuit_breaker' => $this->name,
        ]);
    }

    /**
     * 记录成功.
     */
    private function recordSuccess(): void
    {
        $state = $this->getState();

        if (self::STATE_HALF_OPEN === $state) {
            $this->handleHalfOpenSuccess();
        } elseif (self::STATE_CLOSED === $state) {
            $this->handleClosedSuccess();
        }

        $this->logSuccessRecord($state);
    }

    /**
     * 处理半开状态的成功记录.
     */
    private function handleHalfOpenSuccess(): void
    {
        $successCount = $this->incrementCounter('half_open_success');

        if ($successCount >= $this->successThreshold) {
            $this->transitionToClosed();
        }
    }

    /**
     * 处理关闭状态的成功记录.
     */
    private function handleClosedSuccess(): void
    {
        $this->resetCounter('failure');
    }

    /**
     * 记录成功日志.
     */
    private function logSuccessRecord(string $state): void
    {
        $this->logger->debug('熔断器记录成功', [
            'circuit_breaker' => $this->name,
            'state' => $state,
        ]);
    }

    /**
     * 记录失败.
     */
    private function recordFailure(): void
    {
        $state = $this->getState();

        if (self::STATE_HALF_OPEN === $state) {
            // 半开状态下失败，立即打开
            $this->transitionToOpen();
        } elseif (self::STATE_CLOSED === $state) {
            $failureCount = $this->incrementCounter('failure');

            if ($failureCount >= $this->failureThreshold) {
                $this->transitionToOpen();
            }
        }

        $this->logger->warning('熔断器记录失败', [
            'circuit_breaker' => $this->name,
            'state' => $state,
        ]);
    }

    /**
     * 是否应该尝试重置.
     */
    private function shouldAttemptReset(): bool
    {
        $lastOpenTimeItem = $this->cache->getItem($this->getCacheKey('last_open_time'));

        if (!$lastOpenTimeItem->isHit()) {
            return true;
        }

        $lastOpenTime = $lastOpenTimeItem->get();
        \assert(\is_int($lastOpenTime));

        return (time() - $lastOpenTime) >= $this->timeout;
    }

    /**
     * 转换到关闭状态.
     */
    private function transitionToClosed(): void
    {
        $this->setState(self::STATE_CLOSED);
        $this->resetCounter('failure');
        $this->resetCounter('half_open_success');
        $this->resetCounter('half_open_attempts');

        $this->logger->info('熔断器转换到关闭状态', [
            'circuit_breaker' => $this->name,
        ]);
    }

    /**
     * 转换到打开状态.
     */
    private function transitionToOpen(): void
    {
        $this->setState(self::STATE_OPEN);

        $lastOpenTimeItem = $this->cache->getItem($this->getCacheKey('last_open_time'));
        $lastOpenTimeItem->set(time());
        $lastOpenTimeItem->expiresAfter($this->timeout * 2);
        $this->cache->save($lastOpenTimeItem);

        $this->logger->error('熔断器转换到打开状态', [
            'circuit_breaker' => $this->name,
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * 转换到半开状态.
     */
    private function transitionToHalfOpen(): void
    {
        $attempts = $this->incrementCounter('half_open_attempts');

        if ($attempts > $this->halfOpenMaxAttempts) {
            // 超过最大尝试次数，重新打开
            $this->transitionToOpen();

            return;
        }

        $this->setState(self::STATE_HALF_OPEN);
        $this->resetCounter('half_open_success');

        $this->logger->info('熔断器转换到半开状态', [
            'circuit_breaker' => $this->name,
            'attempt' => $attempts,
        ]);
    }

    /**
     * 设置状态.
     */
    private function setState(string $state): void
    {
        $stateItem = $this->cache->getItem($this->getCacheKey('state'));
        $stateItem->set($state);
        $stateItem->expiresAfter($this->timeout * 2);
        $this->cache->save($stateItem);
    }

    /**
     * 增加计数器.
     */
    private function incrementCounter(string $name): int
    {
        $counterItem = $this->cache->getItem($this->getCacheKey($name . '_count'));
        $count = $counterItem->isHit() ? $counterItem->get() : 0;
        \assert(\is_int($count));
        ++$count;

        $counterItem->set($count);
        $counterItem->expiresAfter($this->timeout * 2);
        $this->cache->save($counterItem);

        return $count;
    }

    /**
     * 重置计数器.
     */
    private function resetCounter(string $name): void
    {
        $this->cache->deleteItem($this->getCacheKey($name . '_count'));
    }

    /**
     * 获取缓存键.
     */
    private function getCacheKey(string $suffix): string
    {
        return \sprintf('circuit_breaker.%s.%s', $this->name, $suffix);
    }
}
