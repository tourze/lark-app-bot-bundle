<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncResultCollector;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(SyncResultCollector::class)]
#[RunTestsInSeparateProcesses]
class SyncResultCollectorTest extends AbstractIntegrationTestCase
{
    private SyncResultCollector $collector;

    private LoggerInterface $logger;

    public function testCreateEmptyResult(): void
    {
        $result = $this->collector->createEmptyResult();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertEmpty($result['success']);
        $this->assertEmpty($result['failed']);
        $this->assertEmpty($result['skipped']);
    }

    public function testInitializeResult(): void
    {
        $result = $this->collector->initializeResult();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertEmpty($result['success']);
        $this->assertEmpty($result['failed']);
        $this->assertEmpty($result['skipped']);
    }

    public function testAddSuccess(): void
    {
        $result = $this->collector->initializeResult();
        $userData = ['name' => 'John Doe', 'email' => 'john@example.com'];

        $this->collector->addSuccess($result, 'user123', $userData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('user123', $result['success']);
        $this->assertSame($userData, $result['success']['user123']);
    }

    public function testAddFailure(): void
    {
        $result = $this->collector->initializeResult();

        $this->collector->addFailure($result, 'user123');

        $this->assertContains('user123', $result['failed']);
    }

    public function testAddBatchFailures(): void
    {
        $result = $this->collector->initializeResult();
        $userIds = ['user123', 'user456', 'user789'];

        $this->collector->addBatchFailures($result, $userIds);

        $this->assertSame($userIds, $result['failed']);
    }

    public function testAddSkipped(): void
    {
        $result = $this->collector->initializeResult();
        $userIds = ['user123', 'user456'];

        $this->collector->addSkipped($result, $userIds);

        $this->assertSame($userIds, $result['skipped']);
    }

    public function testCreateBatches(): void
    {
        $userIds = array_map(fn ($i) => "user{$i}", range(1, 250));

        $batches = $this->collector->createBatches($userIds);

        $this->assertIsArray($batches);
        $this->assertCount(3, $batches);
        $this->assertCount(100, $batches[0]);
        $this->assertCount(100, $batches[1]);
        $this->assertCount(50, $batches[2]);
    }

    public function testLogBatchSyncStart(): void
    {
        $userIds = ['user1', 'user2', 'user3'];

        $this->logger->expects($this->once())
            ->method('info')
            ->with('开始批量同步用户数据', [
                'user_count' => 3,
                'user_id_type' => 'open_id',
                'force' => true,
            ])
        ;

        $this->collector->logBatchSyncStart($userIds, 'open_id', true);
    }

    public function testLogBatchSyncComplete(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $result = [
            'success' => ['user1' => []],
            'failed' => ['user2'],
            'skipped' => ['user3'],
        ];

        $this->logger->expects($this->once())
            ->method('info')
            ->with('批量同步用户数据完成', [
                'total' => 3,
                'success' => 1,
                'failed' => 1,
                'skipped' => 1,
            ])
        ;

        $this->collector->logBatchSyncComplete($userIds, $result);
    }

    public function testGetResultStats(): void
    {
        $result = [
            'success' => ['user1' => [], 'user2' => []],
            'failed' => ['user3'],
            'skipped' => ['user4'],
        ];

        $stats = $this->collector->getResultStats($result);

        $this->assertSame(4, $stats['total']);
        $this->assertSame(2, $stats['success_count']);
        $this->assertSame(1, $stats['failed_count']);
        $this->assertSame(1, $stats['skipped_count']);
        $this->assertSame(66.67, $stats['success_rate']);
    }

    public function testGetResultStatsWithZeroTotal(): void
    {
        $result = [
            'success' => [],
            'failed' => [],
            'skipped' => ['user1'],
        ];

        $stats = $this->collector->getResultStats($result);

        $this->assertSame(1, $stats['total']);
        $this->assertSame(0.0, $stats['success_rate']);
    }

    protected function onSetUp(): void
    {
        // 获取服务实例，不再设置 mock
        $this->collector = static::getService(SyncResultCollector::class);
        // 创建 mock 对象
        $this->logger = static::createMock(LoggerInterface::class);
        static::getContainer()->set(LoggerInterface::class, $this->logger);
        $this->collector = static::getService(SyncResultCollector::class);
    }
}
