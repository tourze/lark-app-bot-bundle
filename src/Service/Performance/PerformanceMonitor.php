<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Performance;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * 性能监控服务
 * 集成PerformanceProfiler和MetricsCollector，提供统一的性能监控接口.
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'lark_app_bot')]
final class PerformanceMonitor
{
    private const DEFAULT_SLOW_THRESHOLD = 1000; // 默认慢速阈值：1秒

    private Stopwatch $stopwatch;

    private MetricsCollector $metricsCollector;

    private LoggerInterface $logger;

    /**
     * 活跃的监控会话.
     *
     * @var array<string, array{start: float, metadata: array<string, mixed>}>
     */
    private array $activeSessions = [];

    /**
     * 慢速操作阈值配置（毫秒）.
     *
     * @var array<string, int>
     */
    private array $slowThresholds = [
        'api_request' => 1000,
        'message_send' => 500,
        'message_process' => 300,
        'webhook_process' => 200,
        'cache_operation' => 50,
        'database_query' => 100,
    ];

    public function __construct(
        MetricsCollector $metricsCollector,
        LoggerInterface $logger,
    ) {
        $this->metricsCollector = $metricsCollector;
        $this->logger = $logger;
        $this->stopwatch = new Stopwatch();
    }

    /**
     * 开始监控API请求
     */
    public function startApiRequest(string $method, string $endpoint): string
    {
        $sessionId = $this->generateSessionId('api', $method, $endpoint);

        $this->stopwatch->start($sessionId, 'api_request');
        $this->activeSessions[$sessionId] = [
            'start' => microtime(true),
            'metadata' => [
                'type' => 'api_request',
                'method' => $method,
                'endpoint' => $endpoint,
            ],
        ];

        return $sessionId;
    }

    /**
     * 结束监控API请求
     */
    public function endApiRequest(string $sessionId, int $statusCode): void
    {
        if (!isset($this->activeSessions[$sessionId])) {
            $this->logger->warning('Attempted to end non-existent monitoring session', [
                'session_id' => $sessionId,
            ]);

            return;
        }

        try {
            $event = $this->stopwatch->stop($sessionId);
            $session = $this->activeSessions[$sessionId];
            unset($this->activeSessions[$sessionId]);

            $duration = $event->getDuration() / 1000; // 转换为秒

            // 记录到Prometheus
            $this->metricsCollector->recordApiRequest(
                $session['metadata']['method'],
                $session['metadata']['endpoint'],
                $statusCode,
                $duration
            );

            // 检查是否为慢速请求
            $this->checkSlowOperation('api_request', (int) $event->getDuration(), $session['metadata']);

            // 记录性能日志
            $this->logPerformance('api_request', $event, $session['metadata'] + [
                'status_code' => $statusCode,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to end API request monitoring', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 监控消息发送
     */
    public function monitorMessageSend(string $messageType, callable $operation): mixed
    {
        $sessionId = $this->generateSessionId('message_send', $messageType);

        $this->stopwatch->start($sessionId, 'message');
        $startTime = microtime(true);

        try {
            $result = $operation();

            $event = $this->stopwatch->stop($sessionId);
            $duration = $event->getDuration() / 1000;

            // 记录成功的消息
            $this->metricsCollector->recordMessage(
                $messageType,
                'outbound',
                'success',
                $duration
            );

            $this->checkSlowOperation('message_send', (int) $event->getDuration(), [
                'message_type' => $messageType,
            ]);

            return $result;
        } catch (\Exception $e) {
            if ($this->stopwatch->isStarted($sessionId)) {
                $event = $this->stopwatch->stop($sessionId);
                $duration = $event->getDuration() / 1000;

                // 记录失败的消息
                $this->metricsCollector->recordMessage(
                    $messageType,
                    'outbound',
                    'failed',
                    $duration
                );
            }

            $this->metricsCollector->recordError('message_send_error', 'error');
            throw $e;
        }
    }

    /**
     * 监控消息处理.
     */
    public function monitorMessageProcessing(string $messageType, callable $handler): mixed
    {
        $sessionId = $this->generateSessionId('message_process', $messageType);

        $this->stopwatch->start($sessionId, 'message');

        try {
            $result = $handler();

            $event = $this->stopwatch->stop($sessionId);
            $duration = $event->getDuration() / 1000;

            $this->metricsCollector->recordMessage(
                $messageType,
                'inbound',
                'processed',
                $duration
            );

            $this->checkSlowOperation('message_process', (int) $event->getDuration(), [
                'message_type' => $messageType,
            ]);

            return $result;
        } catch (\Exception $e) {
            if ($this->stopwatch->isStarted($sessionId)) {
                $this->stopwatch->stop($sessionId);
            }

            $this->metricsCollector->recordError('message_processing_error', 'error');
            throw $e;
        }
    }

    /**
     * 监控Webhook处理.
     */
    public function monitorWebhookProcessing(string $eventType, callable $handler): mixed
    {
        $sessionId = $this->generateSessionId('webhook', $eventType);
        $startTime = microtime(true);

        try {
            $result = $handler();

            $duration = microtime(true) - $startTime;
            $this->metricsCollector->recordWebhookProcessing($eventType, $duration);

            $this->checkSlowOperation('webhook_process', (int) ($duration * 1000), [
                'event_type' => $eventType,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->metricsCollector->recordError('webhook_processing_error', 'error');
            throw $e;
        }
    }

    /**
     * 监控缓存操作.
     */
    public function monitorCacheOperation(string $operation, string $cacheType, callable $handler): mixed
    {
        $sessionId = $this->generateSessionId('cache', $operation, $cacheType);

        $this->stopwatch->start($sessionId, 'cache');

        try {
            $result = $handler();

            $event = $this->stopwatch->stop($sessionId);

            // 根据操作类型和结果记录指标
            if ('get' === $operation) {
                if (null !== $result) {
                    $this->metricsCollector->recordCacheHit($cacheType);
                } else {
                    $this->metricsCollector->recordCacheMiss($cacheType);
                }
            }

            $this->checkSlowOperation('cache_operation', (int) $event->getDuration(), [
                'operation' => $operation,
                'cache_type' => $cacheType,
            ]);

            return $result;
        } catch (\Exception $e) {
            if ($this->stopwatch->isStarted($sessionId)) {
                $this->stopwatch->stop($sessionId);
            }

            $this->metricsCollector->recordError('cache_error', 'error');
            throw $e;
        }
    }

    /**
     * 记录自定义性能指标.
     *
     * @param array<string, string> $labels
     */
    public function recordCustomMetric(string $name, float $value, array $labels = []): void
    {
        $this->logger->info('Custom metric recorded', [
            'metric' => $name,
            'value' => $value,
            'labels' => $labels,
        ]);
    }

    /**
     * 清理过期的监控会话.
     */
    public function cleanupStaleSessions(int $maxAgeSeconds = 300): int
    {
        $now = microtime(true);
        $cleaned = 0;

        foreach ($this->activeSessions as $sessionId => $session) {
            if ($now - $session['start'] > $maxAgeSeconds) {
                unset($this->activeSessions[$sessionId]);
                ++$cleaned;

                $this->logger->warning('Cleaned up stale monitoring session', [
                    'session_id' => $sessionId,
                    'age' => $now - $session['start'],
                    'metadata' => $session['metadata'],
                ]);
            }
        }

        if ($cleaned > 0) {
            $this->metricsCollector->recordError('stale_session_cleanup', 'warning');
        }

        return $cleaned;
    }

    /**
     * 设置慢速操作阈值
     */
    public function setSlowThreshold(string $operation, int $thresholdMs): void
    {
        $this->slowThresholds[$operation] = $thresholdMs;
    }

    /**
     * 获取性能统计报告.
     *
     * @return array<string, mixed>
     */
    public function getPerformanceStats(): array
    {
        return [
            'active_sessions' => $this->getActiveSessionCount(),
            'slow_thresholds' => $this->slowThresholds,
            'metrics' => [
                'samples' => \count($this->metricsCollector->getMetricFamilySamples()),
            ],
        ];
    }

    /**
     * 获取当前活跃的监控会话数.
     */
    public function getActiveSessionCount(): int
    {
        $count = \count($this->activeSessions);
        $this->metricsCollector->updateActiveConnections($count);

        return $count;
    }

    /**
     * 生成会话ID.
     */
    private function generateSessionId(string ...$parts): string
    {
        return implode('_', $parts) . '_' . uniqid('', true);
    }

    /**
     * 检查慢速操作.
     *
     * @param array<string, mixed> $context
     */
    private function checkSlowOperation(string $type, int $durationMs, array $context): void
    {
        $threshold = $this->slowThresholds[$type] ?? self::DEFAULT_SLOW_THRESHOLD;

        if ($durationMs > $threshold) {
            $this->logger->warning('Slow operation detected', [
                'type' => $type,
                'duration_ms' => $durationMs,
                'threshold_ms' => $threshold,
                'exceeded_by_ms' => $durationMs - $threshold,
                'context' => $context,
            ]);

            $this->metricsCollector->recordError('slow_operation', 'warning');
        }
    }

    /**
     * 记录性能日志.
     *
     * @param array<string, mixed> $context
     */
    private function logPerformance(string $type, StopwatchEvent $event, array $context): void
    {
        $this->logger->debug('Performance measurement', [
            'type' => $type,
            'duration_ms' => $event->getDuration(),
            'memory_mb' => round($event->getMemory() / 1024 / 1024, 2),
            'context' => $context,
        ]);
    }
}
