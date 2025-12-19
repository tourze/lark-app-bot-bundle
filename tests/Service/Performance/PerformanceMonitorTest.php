<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Performance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Exception\PerformanceException;
use Tourze\LarkAppBotBundle\Service\Performance\MetricsCollector;
use Tourze\LarkAppBotBundle\Service\Performance\PerformanceMonitor;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(PerformanceMonitor::class)]
#[RunTestsInSeparateProcesses]
final class PerformanceMonitorTest extends AbstractIntegrationTestCase
{
    private PerformanceMonitor $monitor;

    private MockObject $mockMetricsCollector;

    private MockObject $mockLogger;

    public function testStartApiRequest(): void
    {
        $method = 'POST';
        $endpoint = '/api/messages';

        $sessionId = $this->monitor->startApiRequest($method, $endpoint);

        $this->assertIsString($sessionId);
        $this->assertNotEmpty($sessionId);
        $this->assertStringStartsWith('api_', $sessionId);
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(PerformanceMonitor::class, $this->monitor);
    }

    public function testStartAndEndApiRequest(): void
    {
        $this->mockMetricsCollector->expects($this->once())
            ->method('recordApiRequest')
            ->with('GET', '/api/users', 200, self::greaterThan(0))
        ;

        $this->mockMetricsCollector->expects($this->once())
            ->method('updateActiveConnections')
            ->with(0)
        ;

        $sessionId = $this->monitor->startApiRequest('GET', '/api/users');
        $this->assertIsString($sessionId);
        $this->assertStringContainsString('api_GET_/api/users_', $sessionId);

        // 添加一些延迟以确保有时间记录
        usleep(10000); // 10ms

        $this->monitor->endApiRequest($sessionId, 200);

        // 验证活跃会话已清理
        $this->assertSame(0, $this->monitor->getActiveSessionCount());
    }

    public function testEndApiRequestWithNonExistentSession(): void
    {
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with('Attempted to end non-existent monitoring session', [
                'session_id' => 'non-existent'])
        ;

        $this->monitor->endApiRequest('non-existent', 200);
    }

    public function testMonitorMessageSend(): void
    {
        $this->mockMetricsCollector->expects($this->once())
            ->method('recordMessage')
            ->with('text', 'outbound', 'success', self::greaterThan(0))
        ;

        $result = $this->monitor->monitorMessageSend('text', function () {
            usleep(10000); // 10ms

            return 'message sent';
        });

        $this->assertSame('message sent', $result);
    }

    public function testMonitorMessageSendWithException(): void
    {
        $this->mockMetricsCollector->expects($this->once())
            ->method('recordMessage')
            ->with('text', 'outbound', 'failed', self::greaterThanOrEqual(0))
        ;

        $this->mockMetricsCollector->expects($this->once())
            ->method('recordError')
            ->with('message_send_error', 'error')
        ;

        $this->expectException(PerformanceException::class);
        $this->expectExceptionMessage('Send failed');

        $this->monitor->monitorMessageSend('text', function (): void {
            throw PerformanceException::sendFailed();
        });
    }

    public function testMonitorMessageProcessing(): void
    {
        $this->mockMetricsCollector->expects($this->once())
            ->method('recordMessage')
            ->with('text', 'inbound', 'processed', self::greaterThan(0))
        ;

        $result = $this->monitor->monitorMessageProcessing('text', function () {
            usleep(10000); // 10ms

            return 'processed';
        });

        $this->assertSame('processed', $result);
    }

    public function testMonitorMessageProcessingWithException(): void
    {
        $this->mockMetricsCollector->expects($this->once())
            ->method('recordError')
            ->with('message_processing_error', 'error')
        ;

        $this->expectException(PerformanceException::class);
        $this->expectExceptionMessage('Processing failed');

        $this->monitor->monitorMessageProcessing('text', function (): void {
            throw PerformanceException::processingFailed();
        });
    }

    public function testMonitorWebhookProcessing(): void
    {
        $this->mockMetricsCollector->expects($this->once())
            ->method('recordWebhookProcessing')
            ->with('message.receive', self::greaterThan(0))
        ;

        $result = $this->monitor->monitorWebhookProcessing('message.receive', function () {
            usleep(10000); // 10ms

            return 'webhook processed';
        });

        $this->assertSame('webhook processed', $result);
    }

    public function testMonitorWebhookProcessingWithException(): void
    {
        $this->mockMetricsCollector->expects($this->once())
            ->method('recordError')
            ->with('webhook_processing_error', 'error')
        ;

        $this->expectException(PerformanceException::class);
        $this->expectExceptionMessage('Webhook failed');

        $this->monitor->monitorWebhookProcessing('message.receive', function (): void {
            throw PerformanceException::webhookFailed();
        });
    }

    public function testMonitorCacheOperationGet(): void
    {
        // 测试缓存命中
        $this->mockMetricsCollector->expects($this->once())
            ->method('recordCacheHit')
            ->with('redis')
        ;

        $result = $this->monitor->monitorCacheOperation('get', 'redis', function () {
            return 'cached value';
        });

        $this->assertSame('cached value', $result);

        // 测试缓存未命中
        $this->mockMetricsCollector->expects($this->once())
            ->method('recordCacheMiss')
            ->with('redis')
        ;

        $result = $this->monitor->monitorCacheOperation('get', 'redis', function () {
            return null;
        });

        $this->assertNull($result);
    }

    public function testMonitorCacheOperationWithException(): void
    {
        $this->mockMetricsCollector->expects($this->once())
            ->method('recordError')
            ->with('cache_error', 'error')
        ;

        $this->expectException(PerformanceException::class);
        $this->expectExceptionMessage('Cache error');

        $this->monitor->monitorCacheOperation('get', 'redis', function (): void {
            throw PerformanceException::cacheError();
        });
    }

    public function testRecordCustomMetric(): void
    {
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Custom metric recorded', [
                'metric' => 'custom.metric',
                'value' => 42.5,
                'labels' => ['service' => 'test']])
        ;

        $this->monitor->recordCustomMetric('custom.metric', 42.5, ['service' => 'test']);
    }

    public function testCleanupStaleSessions(): void
    {
        // 启动一个会话
        $sessionId = $this->monitor->startApiRequest('GET', '/api/test');

        // 立即清理（不应清理任何会话）
        $cleaned = $this->monitor->cleanupStaleSessions(300);
        $this->assertSame(0, $cleaned);

        // 使用非常短的过期时间再次清理
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Cleaned up stale monitoring session',
                self::callback(function ($context) use ($sessionId) {
                    return $context['session_id'] === $sessionId
                        && isset($context['age'], $context['metadata']);
                })
            )
        ;

        $this->mockMetricsCollector->expects($this->once())
            ->method('recordError')
            ->with('stale_session_cleanup', 'warning')
        ;

        $cleaned = $this->monitor->cleanupStaleSessions(0);
        $this->assertSame(1, $cleaned);
    }

    public function testSetSlowThreshold(): void
    {
        $this->monitor->setSlowThreshold('custom_operation', 200);

        $stats = $this->monitor->getPerformanceStats();
        $this->assertSame(200, $stats['slow_thresholds']['custom_operation']);
    }

    public function testSlowOperationDetection(): void
    {
        // 设置一个非常低的阈值
        $this->monitor->setSlowThreshold('api_request', 1);

        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Slow operation detected',
                self::callback(function ($context) {
                    return 'api_request' === $context['type']
                        && 1 === $context['threshold_ms']
                        && isset($context['duration_ms'], $context['exceeded_by_ms']);
                })
            )
        ;

        $this->mockMetricsCollector->expects($this->once())
            ->method('recordError')
            ->with('slow_operation', 'warning')
        ;

        $this->mockMetricsCollector->expects($this->once())
            ->method('recordApiRequest')
        ;

        $sessionId = $this->monitor->startApiRequest('GET', '/api/slow');
        usleep(5000); // 5ms，超过1ms阈值
        $this->monitor->endApiRequest($sessionId, 200);
    }

    public function testGetPerformanceStats(): void
    {
        $this->mockMetricsCollector->expects($this->once())
            ->method('getMetricFamilySamples')
            ->willReturn([])
        ;

        $this->mockMetricsCollector->expects($this->once())
            ->method('updateActiveConnections')
            ->with(0)
        ;

        $stats = $this->monitor->getPerformanceStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('active_sessions', $stats);
        $this->assertArrayHasKey('slow_thresholds', $stats);
        $this->assertArrayHasKey('metrics', $stats);
        $this->assertSame(0, $stats['active_sessions']);
        $this->assertSame(0, $stats['metrics']['samples']);
    }

    public function testGetActiveSessionCount(): void
    {
        $this->mockMetricsCollector->expects($this->exactly(3))
            ->method('updateActiveConnections')
            ->with(self::logicalOr(1, 2))
        ;

        $sessionId1 = $this->monitor->startApiRequest('GET', '/api/test1');
        $this->assertSame(1, $this->monitor->getActiveSessionCount());

        $sessionId2 = $this->monitor->startApiRequest('POST', '/api/test2');
        $this->assertSame(2, $this->monitor->getActiveSessionCount());

        $this->monitor->endApiRequest($sessionId1, 200);
        $this->assertSame(1, $this->monitor->getActiveSessionCount());
    }

    public function testPerformanceLogging(): void
    {
        $this->mockLogger->expects($this->once())
            ->method('debug')
            ->with(
                'Performance measurement',
                self::callback(function ($context) {
                    return 'api_request' === $context['type']
                        && isset($context['duration_ms'], $context['memory_mb'])

                        && 'GET' === $context['context']['method']
                        && '/api/debug' === $context['context']['endpoint']
                        && 200 === $context['context']['status_code'];
                })
            )
        ;

        $this->mockMetricsCollector->expects($this->once())
            ->method('recordApiRequest')
        ;

        $sessionId = $this->monitor->startApiRequest('GET', '/api/debug');
        $this->monitor->endApiRequest($sessionId, 200);
    }

    protected function prepareMockServices(): void
    {
        $this->mockMetricsCollector = $this->createMock(MetricsCollector::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->mockMetricsCollector = $this->createMock(MetricsCollector::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);

        // 从服务容器获取 PerformanceMonitor 实例而不是直接实例化
        $monitor = self::getContainer()->get(PerformanceMonitor::class);
        self::assertInstanceOf(PerformanceMonitor::class, $monitor);
        $this->monitor = $monitor;
    }
}
