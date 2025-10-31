<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Performance;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\MetricFamilySamples;
use Prometheus\Storage\InMemory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Prometheus指标收集器
 * 用于收集和导出性能指标.
 */
#[Autoconfigure(public: true)]
class MetricsCollector
{
    private CollectorRegistry $registry;

    private LoggerInterface $logger;

    // 计数器
    private Counter $apiRequestsTotal;

    private Counter $messagesTotal;

    private Counter $errorsTotal;

    private Counter $cacheHitsTotal;

    private Counter $cacheMissesTotal;

    // 直方图
    private Histogram $apiRequestDuration;

    private Histogram $messageProcessingDuration;

    private Histogram $webhookProcessingDuration;

    // 仪表
    private Gauge $activeConnections;

    private Gauge $memoryUsage;

    private Gauge $queueSize;

    public function __construct(
        LoggerInterface $logger,
        ?CollectorRegistry $registry = null,
    ) {
        $this->logger = $logger;
        $this->registry = $registry ?? new CollectorRegistry(new InMemory());

        $this->initializeMetrics();
    }

    /**
     * 记录API请求
     */
    public function recordApiRequest(string $method, string $endpoint, int $statusCode, float $duration): void
    {
        try {
            $status = $this->getStatusGroup($statusCode);

            $this->apiRequestsTotal->inc([
                'method' => $method,
                'endpoint' => $this->normalizeEndpoint($endpoint),
                'status' => $status,
            ]);

            $this->apiRequestDuration->observe($duration, [
                'method' => $method,
                'endpoint' => $this->normalizeEndpoint($endpoint),
            ]);

            // 更新内存使用
            $this->memoryUsage->set(memory_get_usage(true));
        } catch (\Exception $e) {
            $this->logger->error('Failed to record API request metric', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 记录消息处理.
     */
    public function recordMessage(string $type, string $direction, string $status, ?float $processingTime = null): void
    {
        try {
            $this->messagesTotal->inc([
                'type' => $type,
                'direction' => $direction,
                'status' => $status,
            ]);

            if (null !== $processingTime) {
                $this->messageProcessingDuration->observe($processingTime, [
                    'type' => $type,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to record message metric', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 记录Webhook处理.
     */
    public function recordWebhookProcessing(string $eventType, float $duration): void
    {
        try {
            $this->webhookProcessingDuration->observe($duration, [
                'event_type' => $eventType,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to record webhook processing metric', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 记录错误.
     */
    public function recordError(string $type, string $severity = 'error'): void
    {
        try {
            $this->errorsTotal->inc([
                'type' => $type,
                'severity' => $severity,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to record error metric', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 记录缓存命中.
     */
    public function recordCacheHit(string $cacheType): void
    {
        try {
            $this->cacheHitsTotal->inc(['cache_type' => $cacheType]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to record cache hit metric', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 记录缓存未命中.
     */
    public function recordCacheMiss(string $cacheType): void
    {
        try {
            $this->cacheMissesTotal->inc(['cache_type' => $cacheType]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to record cache miss metric', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 更新活跃连接数.
     */
    public function updateActiveConnections(int $count): void
    {
        try {
            $this->activeConnections->set($count);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update active connections metric', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 更新队列大小.
     */
    public function updateQueueSize(string $queueName, int $size): void
    {
        try {
            $this->queueSize->set($size, ['queue_name' => $queueName]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update queue size metric', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取所有指标的样本.
     *
     * @return array<MetricFamilySamples>
     */
    public function getMetricFamilySamples(): array
    {
        return $this->registry->getMetricFamilySamples();
    }

    /**
     * 重置所有指标（用于测试）.
     */
    public function reset(): void
    {
        $this->registry = new CollectorRegistry(new InMemory());
        $this->initializeMetrics();
    }

    /**
     * 初始化所有指标.
     */
    private function initializeMetrics(): void
    {
        try {
            // API请求计数器
            $this->apiRequestsTotal = $this->registry->getOrRegisterCounter(
                'lark_bot',
                'api_requests_total',
                'Total number of API requests',
                ['method', 'endpoint', 'status']
            );

            // 消息计数器
            $this->messagesTotal = $this->registry->getOrRegisterCounter(
                'lark_bot',
                'messages_total',
                'Total number of messages processed',
                ['type', 'direction', 'status']
            );

            // 错误计数器
            $this->errorsTotal = $this->registry->getOrRegisterCounter(
                'lark_bot',
                'errors_total',
                'Total number of errors',
                ['type', 'severity']
            );

            // 缓存命中计数器
            $this->cacheHitsTotal = $this->registry->getOrRegisterCounter(
                'lark_bot',
                'cache_hits_total',
                'Total number of cache hits',
                ['cache_type']
            );

            // 缓存未命中计数器
            $this->cacheMissesTotal = $this->registry->getOrRegisterCounter(
                'lark_bot',
                'cache_misses_total',
                'Total number of cache misses',
                ['cache_type']
            );

            // API请求持续时间直方图
            $this->apiRequestDuration = $this->registry->getOrRegisterHistogram(
                'lark_bot',
                'api_request_duration_seconds',
                'API request duration in seconds',
                ['method', 'endpoint'],
                [0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
            );

            // 消息处理持续时间直方图
            $this->messageProcessingDuration = $this->registry->getOrRegisterHistogram(
                'lark_bot',
                'message_processing_duration_seconds',
                'Message processing duration in seconds',
                ['type'],
                [0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5]
            );

            // Webhook处理持续时间直方图
            $this->webhookProcessingDuration = $this->registry->getOrRegisterHistogram(
                'lark_bot',
                'webhook_processing_duration_seconds',
                'Webhook processing duration in seconds',
                ['event_type'],
                [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1]
            );

            // 活跃连接数仪表
            $this->activeConnections = $this->registry->getOrRegisterGauge(
                'lark_bot',
                'active_connections',
                'Number of active connections'
            );

            // 内存使用仪表
            $this->memoryUsage = $this->registry->getOrRegisterGauge(
                'lark_bot',
                'memory_usage_bytes',
                'Current memory usage in bytes'
            );

            // 队列大小仪表
            $this->queueSize = $this->registry->getOrRegisterGauge(
                'lark_bot',
                'queue_size',
                'Current queue size',
                ['queue_name']
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取状态码组.
     */
    private function getStatusGroup(int $statusCode): string
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return '2xx';
        }
        if ($statusCode >= 300 && $statusCode < 400) {
            return '3xx';
        }
        if ($statusCode >= 400 && $statusCode < 500) {
            return '4xx';
        }
        if ($statusCode >= 500) {
            return '5xx';
        }

        return 'unknown';
    }

    /**
     * 标准化端点路径.
     */
    private function normalizeEndpoint(string $endpoint): string
    {
        // 移除具体的ID，保留路径模式
        $endpoint = preg_replace('/\/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/', '/{id}', $endpoint) ?? $endpoint;

        return preg_replace('/\/\d+/', '/{id}', $endpoint) ?? $endpoint;
    }
}
