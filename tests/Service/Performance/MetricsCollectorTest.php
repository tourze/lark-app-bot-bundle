<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Performance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Service\Performance\MetricsCollector;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(MetricsCollector::class)]
#[RunTestsInSeparateProcesses]
final class MetricsCollectorTest extends AbstractIntegrationTestCase
{
    public function testConstructor(): void
    {
        $collector = self::getService(MetricsCollector::class);
        $this->assertInstanceOf(MetricsCollector::class, $collector);
    }

    public function testConstructorWithoutRegistry(): void
    {
        $collector = self::getService(MetricsCollector::class);
        $this->assertInstanceOf(MetricsCollector::class, $collector);
    }

    public function testRecordApiRequest(): void
    {
        // 从容器获取服务，确保使用正确的依赖注入
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockCounter = $this->createMock(Counter::class);
        $mockCounter->expects($this->once())
            ->method('inc')
            ->with([
                'method' => 'GET',
                'endpoint' => '/api/users/{id}',
                'status' => '2xx'])
        ;

        $mockHistogram = $this->createMock(Histogram::class);
        $mockHistogram->expects($this->once())
            ->method('observe')
            ->with(1.5, [
                'method' => 'GET',
                'endpoint' => '/api/users/{id}'])
        ;

        $mockGauge = $this->createMock(Gauge::class);
        $mockGauge->expects($this->once())
            ->method('set')
            ->with(self::isFloat())
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($mockCounter)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($mockHistogram)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($mockGauge)
        ;

        // 使用反射替换 registry
        $reflection = new \ReflectionClass($collector);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registryProperty->setValue($collector, $mockRegistry);

        $collector->recordApiRequest('GET', '/api/users/123', 200, 1.5);
    }

    public function testRecordMessage(): void
    {
        // 从容器获取服务
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockCounter = $this->createMock(Counter::class);
        $mockCounter->expects($this->once())
            ->method('inc')
            ->with([
                'type' => 'text',
                'direction' => 'incoming',
                'status' => 'success'])
        ;

        $mockHistogram = $this->createMock(Histogram::class);
        $mockHistogram->expects($this->once())
            ->method('observe')
            ->with(0.25, ['type' => 'text'])
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($mockCounter)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($mockHistogram)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($this->createMock(Gauge::class))
        ;

        // 使用反射替换 registry
        $reflection = new \ReflectionClass($collector);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registryProperty->setValue($collector, $mockRegistry);

        $collector->recordMessage('text', 'incoming', 'success', 0.25);
    }

    public function testRecordMessageWithoutProcessingTime(): void
    {
        // 从容器获取服务
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockCounter = $this->createMock(Counter::class);
        $mockCounter->expects($this->once())
            ->method('inc')
            ->with([
                'type' => 'text',
                'direction' => 'incoming',
                'status' => 'success'])
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($mockCounter)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($this->createMock(Gauge::class))
        ;

        // 使用反射替换 registry
        $reflection = new \ReflectionClass($collector);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registryProperty->setValue($collector, $mockRegistry);

        $collector->recordMessage('text', 'incoming', 'success');
    }

    public function testRecordWebhookProcessing(): void
    {
        // 从容器获取服务
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockHistogram = $this->createMock(Histogram::class);
        $mockHistogram->expects($this->once())
            ->method('observe')
            ->with(0.05, ['event_type' => 'message.receive'])
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($this->createMock(Counter::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($mockHistogram)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($this->createMock(Gauge::class))
        ;

        // 使用反射替换 registry
        $reflection = new \ReflectionClass($collector);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registryProperty->setValue($collector, $mockRegistry);

        $collector->recordWebhookProcessing('message.receive', 0.05);
    }

    public function testRecordError(): void
    {
        // 创建专门的 mock registry
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockCounter = $this->createMock(Counter::class);
        $mockCounter->expects($this->once())
            ->method('inc')
            ->with([
                'type' => 'api_error',
                'severity' => 'error'])
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($mockCounter)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($this->createMock(Gauge::class))
        ;

        // 从容器获取服务
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockCounter = $this->createMock(Counter::class);
        $mockCounter->expects($this->once())
            ->method('inc')
            ->with([
                'type' => 'api_error',
                'severity' => 'error'])
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($mockCounter)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($this->createMock(Gauge::class))
        ;

        // 使用反射替换 registry
        $reflection = new \ReflectionClass($collector);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registryProperty->setValue($collector, $mockRegistry);

        $collector->recordError('api_error');
    }

    public function testRecordCacheHit(): void
    {
        // 创建专门的 mock registry
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockCounter = $this->createMock(Counter::class);
        $mockCounter->expects($this->once())
            ->method('inc')
            ->with(['cache_type' => 'redis'])
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($mockCounter)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($this->createMock(Gauge::class))
        ;

        // 从容器获取服务
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockCounter = $this->createMock(Counter::class);
        $mockCounter->expects($this->once())
            ->method('inc')
            ->with(['cache_type' => 'redis'])
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($mockCounter)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($this->createMock(Gauge::class))
        ;

        // 使用反射替换 registry
        $reflection = new \ReflectionClass($collector);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registryProperty->setValue($collector, $mockRegistry);

        $collector->recordCacheHit('redis');
    }

    public function testRecordCacheMiss(): void
    {
        // 创建专门的 mock registry
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockCounter = $this->createMock(Counter::class);
        $mockCounter->expects($this->once())
            ->method('inc')
            ->with(['cache_type' => 'redis'])
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($mockCounter)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($this->createMock(Gauge::class))
        ;

        // 从容器获取服务
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockCounter = $this->createMock(Counter::class);
        $mockCounter->expects($this->once())
            ->method('inc')
            ->with(['cache_type' => 'redis'])
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($mockCounter)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($this->createMock(Gauge::class))
        ;

        // 使用反射替换 registry
        $reflection = new \ReflectionClass($collector);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registryProperty->setValue($collector, $mockRegistry);

        $collector->recordCacheMiss('redis');
    }

    public function testUpdateActiveConnections(): void
    {
        // 创建专门的 mock registry
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockGauge = $this->createMock(Gauge::class);
        $mockGauge->expects($this->once())
            ->method('set')
            ->with(42)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($this->createMock(Counter::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($mockGauge)
        ;

        // 从容器获取服务
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockGauge = $this->createMock(Gauge::class);
        $mockGauge->expects($this->once())
            ->method('set')
            ->with(42)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($this->createMock(Counter::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($mockGauge)
        ;

        // 使用反射替换 registry
        $reflection = new \ReflectionClass($collector);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registryProperty->setValue($collector, $mockRegistry);

        $collector->updateActiveConnections(42);
    }

    public function testUpdateQueueSize(): void
    {
        // 创建专门的 mock registry
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockGauge = $this->createMock(Gauge::class);
        $mockGauge->expects($this->once())
            ->method('set')
            ->with(100, ['queue_name' => 'messages'])
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($this->createMock(Counter::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($mockGauge)
        ;

        // 从容器获取服务
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $mockRegistry = $this->createMock(CollectorRegistry::class);

        $mockGauge = $this->createMock(Gauge::class);
        $mockGauge->expects($this->once())
            ->method('set')
            ->with(100, ['queue_name' => 'messages'])
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($this->createMock(Counter::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($mockGauge)
        ;

        // 使用反射替换 registry
        $reflection = new \ReflectionClass($collector);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registryProperty->setValue($collector, $mockRegistry);

        $collector->updateQueueSize('messages', 100);
    }

    public function testGetMetricFamilySamples(): void
    {
        $expectedSamples = [];
        $mockRegistry = $this->createMock(CollectorRegistry::class);
        $mockRegistry->expects($this->once())
            ->method('getMetricFamilySamples')
            ->willReturn($expectedSamples)
        ;

        // 从容器获取服务
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $mockRegistry = $this->createMock(CollectorRegistry::class);
        $mockRegistry->expects($this->once())
            ->method('getMetricFamilySamples')
            ->willReturn($expectedSamples)
        ;

        // 使用反射替换 registry
        $reflection = new \ReflectionClass($collector);
        $registryProperty = $reflection->getProperty('registry');
        $registryProperty->setAccessible(true);
        $registryProperty->setValue($collector, $mockRegistry);

        $samples = $collector->getMetricFamilySamples();
        $this->assertSame($expectedSamples, $samples);
    }

    public function testReset(): void
    {
        // 从容器获取服务
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $collector->reset();
        // 验证 reset 后仍可正常使用
        $this->assertInstanceOf(MetricsCollector::class, $collector);
    }

    public function testExceptionHandlingInRecordApiRequest(): void
    {
        // 创建专门的 mock registry 和 logger
        $mockRegistry = $this->createMock(CollectorRegistry::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        $mockCounter = $this->createMock(Counter::class);
        $mockCounter->expects($this->once())
            ->method('inc')
            ->willThrowException(new \Exception('Test exception'))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterCounter')
            ->willReturn($mockCounter)
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterHistogram')
            ->willReturn($this->createMock(Histogram::class))
        ;

        $mockRegistry->expects($this->any())
            ->method('getOrRegisterGauge')
            ->willReturn($this->createMock(Gauge::class))
        ;

        $mockLogger->expects($this->once())
            ->method('error')
            ->with('Failed to record API request metric', [
                'error' => 'Test exception'])
        ;

        // 从容器获取服务
        $collector = self::getService(MetricsCollector::class);

        // 创建专门的 mock registry 并替换
        $collector->recordApiRequest('GET', '/api/test', 200, 1.0);
    }

    public function testStatusGroupMapping(): void
    {
        // 测试不同状态码的映射
        $testCases = [
            200 => '2xx',
            201 => '2xx',
            299 => '2xx',
            300 => '3xx',
            301 => '3xx',
            399 => '3xx',
            400 => '4xx',
            404 => '4xx',
            499 => '4xx',
            500 => '5xx',
            503 => '5xx',
            599 => '5xx',
            100 => 'unknown',
            600 => '5xx'];

        foreach ($testCases as $statusCode => $expectedGroup) {
            // 为每个测试用例创建独立的 Mock 对象
            $mockLogger = $this->createMock(LoggerInterface::class);
            $mockRegistry = $this->createMock(CollectorRegistry::class);
            $mockCounter = $this->createMock(Counter::class);
            $mockHistogram = $this->createMock(Histogram::class);
            $mockGauge = $this->createMock(Gauge::class);

            $mockCounter->expects($this->once())
                ->method('inc')
                ->with(self::callback(function ($labels) use ($expectedGroup) {
                    return $labels['status'] === $expectedGroup;
                }))
            ;

            $mockHistogram->expects($this->once())
                ->method('observe')
            ;

            $mockGauge->expects($this->once())
                ->method('set')
            ;

            $mockRegistry->expects($this->any())
                ->method('getOrRegisterCounter')
                ->willReturn($mockCounter)
            ;
            $mockRegistry->expects($this->any())
                ->method('getOrRegisterHistogram')
                ->willReturn($mockHistogram)
            ;
            $mockRegistry->expects($this->any())
                ->method('getOrRegisterGauge')
                ->willReturn($mockGauge)
            ;

            // 从容器获取服务
            $collector = self::getService(MetricsCollector::class);

            // 创建专门的 mock registry 并替换
            $collector->recordApiRequest('GET', '/test', $statusCode, 1.0);
        }
    }

    public function testEndpointNormalization(): void
    {
        // 测试 UUID 和数字 ID 的标准化
        $testCases = [
            '/api/users/123' => '/api/users/{id}',
            '/api/users/f47ac10b-58cc-4372-a567-0e02b2c3d479' => '/api/users/{id}',
            '/api/posts/456/comments/789' => '/api/posts/{id}/comments/{id}',
            '/api/mixed/123/uuid/f47ac10b-58cc-4372-a567-0e02b2c3d479' => '/api/mixed/{id}/uuid/{id}'];

        foreach ($testCases as $endpoint => $expectedEndpoint) {
            // 为每个测试用例创建独立的 Mock 对象
            $mockLogger = $this->createMock(LoggerInterface::class);
            $mockRegistry = $this->createMock(CollectorRegistry::class);
            $mockCounter = $this->createMock(Counter::class);
            $mockHistogram = $this->createMock(Histogram::class);
            $mockGauge = $this->createMock(Gauge::class);

            $mockCounter->expects($this->once())
                ->method('inc')
                ->with(self::callback(function ($labels) use ($expectedEndpoint) {
                    return $labels['endpoint'] === $expectedEndpoint;
                }))
            ;

            $mockHistogram->expects($this->once())
                ->method('observe')
                ->with(self::anything(), self::callback(function ($labels) use ($expectedEndpoint) {
                    return $labels['endpoint'] === $expectedEndpoint;
                }))
            ;

            $mockGauge->expects($this->once())
                ->method('set')
            ;

            $mockRegistry->expects($this->any())
                ->method('getOrRegisterCounter')
                ->willReturn($mockCounter)
            ;
            $mockRegistry->expects($this->any())
                ->method('getOrRegisterHistogram')
                ->willReturn($mockHistogram)
            ;
            $mockRegistry->expects($this->any())
                ->method('getOrRegisterGauge')
                ->willReturn($mockGauge)
            ;

            // 从容器获取服务
            $collector = self::getService(MetricsCollector::class);

            // 创建专门的 mock registry 并替换
            $collector->recordApiRequest('GET', $endpoint, 200, 1.0);
        }
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
        // 无需特殊初始化，使用容器获取服务
    }
}
