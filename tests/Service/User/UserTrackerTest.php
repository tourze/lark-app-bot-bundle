<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\LarkAppBotBundle\Event\UserEvent;
use Tourze\LarkAppBotBundle\Service\User\UserTracker;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserTracker::class)]
#[RunTestsInSeparateProcesses]
final class UserTrackerTest extends AbstractIntegrationTestCase
{
    private UserTracker $tracker;

    private MockObject&CacheItemPoolInterface $cache;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    public function testTrackActivity(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';
        $activity = 'message_sent';
        $context = ['chat_id' => 'oc_123'];

        // 期望更新最后活动时间
        $lastActivityItem = self::createMock(CacheItemInterface::class);
        $lastActivityItem->expects($this->once())->method('set');
        $lastActivityItem->expects($this->once())->method('expiresAfter')->with(86400);

        // 期望记录活动历史
        $historyItem = self::createMock(CacheItemInterface::class);
        $historyItem->method('isHit')->willReturn(false);
        $historyItem->expects($this->once())->method('set');

        // 期望更新统计
        $statsItem = self::createMock(CacheItemInterface::class);
        $statsItem->method('isHit')->willReturn(false);
        $statsItem->expects($this->once())->method('set');

        $this->cache->expects($this->exactly(3))
            ->method('getItem')
            ->willReturnOnConsecutiveCalls($lastActivityItem, $historyItem, $statsItem)
        ;

        $this->cache->expects($this->exactly(3))->method('save');

        // 期望触发事件
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(UserEvent::class),
                UserEvent::USER_ACTIVITY
            )
        ;

        $this->tracker->trackActivity($userId, $userIdType, $activity, $context);
    }

    public function testGetUserStatusOnline(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';
        $currentTime = time();

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($currentTime - 100); // 100秒前活动

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $status = $this->tracker->getUserStatus($userId, $userIdType);

        // 确保 status 是数组类型
        $this->assertIsArray($status);
        $this->assertTrue($status['is_online']);
        $this->assertSame('online', $status['status'] ?? null);
        $this->assertIsArray($status);
        $this->assertArrayHasKey('last_activity_time', $status);
    }

    public function testGetUserStatusOffline(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';
        $currentTime = time();

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($currentTime - 400); // 400秒前活动

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $status = $this->tracker->getUserStatus($userId, $userIdType);

        // 确保 status 是数组类型
        $this->assertIsArray($status);
        $this->assertFalse($status['is_online']);
        $this->assertSame('offline', $status['status'] ?? null);
    }

    public function testGetUserStatusUnknown(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $status = $this->tracker->getUserStatus($userId, $userIdType);

        // 确保 status 是数组类型
        $this->assertIsArray($status);
        $this->assertFalse($status['is_online']);
        $this->assertSame('unknown', $status['status'] ?? null);
    }

    public function testBatchGetUserStatus(): void
    {
        $userIds = ['ou_test1', 'ou_test2'];
        $userIdType = 'open_id';

        $cacheItem1 = self::createMock(CacheItemInterface::class);
        $cacheItem1->method('isHit')->willReturn(true);
        $cacheItem1->method('get')->willReturn(time() - 100);

        $cacheItem2 = self::createMock(CacheItemInterface::class);
        $cacheItem2->method('isHit')->willReturn(false);

        $this->cache->expects($this->exactly(2))
            ->method('getItem')
            ->willReturnOnConsecutiveCalls($cacheItem1, $cacheItem2)
        ;

        $statuses = $this->tracker->batchGetUserStatus($userIds, $userIdType);

        // 确保 statuses 是数组类型
        $this->assertIsArray($statuses);
        $this->assertCount(2, $statuses);
        $this->assertTrue($statuses['ou_test1']['is_online']);
        $this->assertFalse($statuses['ou_test2']['is_online']);
    }

    public function testGetUserActivityHistory(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';
        $activities = [
            ['activity' => 'login', 'timestamp' => time() - 3600], ['activity' => 'message_sent', 'timestamp' => time() - 1800],
            ['activity' => 'logout', 'timestamp' => time() - 900]];

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($activities);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->tracker->getUserActivityHistory($userId, $userIdType, 2);

        // 确保 result 和 activities 是数组类型
        $this->assertIsArray($result);
        $this->assertArrayHasKey('activities', $result);
        $this->assertIsArray($result['activities']);
        $this->assertArrayHasKey('activities', $result);
        $this->assertCount(2, $result['activities']);
        $this->assertSame(3, $result['total']);
        // 应该按时间倒序返回
        $this->assertArrayHasKey(0, $result['activities']);
        $this->assertIsArray($result['activities'][0]);
        $this->assertArrayHasKey('activity', $result['activities'][0]);
        $this->assertArrayHasKey(1, $result['activities']);
        $this->assertArrayHasKey('activity', $result['activities'][1]);
        $this->assertSame('logout', $result['activities'][0]['activity']);
        $this->assertSame('message_sent', $result['activities'][1]['activity']);
    }

    public function testGetUserActivityStats(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';
        $currentTime = time();
        $activities = [
            ['activity' => 'login', 'timestamp' => $currentTime - 3600], ['activity' => 'message_sent', 'timestamp' => $currentTime - 1800],
            ['activity' => 'message_sent', 'timestamp' => $currentTime - 900], ['activity' => 'logout', 'timestamp' => $currentTime - 300]];

        $historyItem = self::createMock(CacheItemInterface::class);
        $historyItem->method('isHit')->willReturn(true);
        $historyItem->method('get')->willReturn($activities);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($historyItem)
        ;

        $stats = $this->tracker->getUserActivityStats($userId, $userIdType, 86400);

        // 确保 stats 和 activities_by_type 是数组类型
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('activities_by_type', $stats);
        $this->assertIsArray($stats['activities_by_type']);
        $this->assertArrayHasKey('total_activities', $stats);
        $this->assertSame(4, $stats['total_activities']);
        $this->assertArrayHasKey('activities_by_type', $stats);
        $this->assertArrayHasKey('message_sent', $stats['activities_by_type']);
        $this->assertArrayHasKey('login', $stats['activities_by_type']);
        $this->assertSame(2, $stats['activities_by_type']['message_sent']);
        $this->assertSame(1, $stats['activities_by_type']['login']);
        $this->assertArrayHasKey('most_active_hour', $stats);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('activity_trend', $stats);
    }

    public function testGetOnlineUsers(): void
    {
        $userIds = ['ou_test1', 'ou_test2', 'ou_test3'];
        $userIdType = 'open_id';

        $cacheItem1 = self::createMock(CacheItemInterface::class);
        $cacheItem1->method('isHit')->willReturn(true);
        $cacheItem1->method('get')->willReturn(time() - 100); // 在线

        $cacheItem2 = self::createMock(CacheItemInterface::class);
        $cacheItem2->method('isHit')->willReturn(true);
        $cacheItem2->method('get')->willReturn(time() - 400); // 离线

        $cacheItem3 = self::createMock(CacheItemInterface::class);
        $cacheItem3->method('isHit')->willReturn(true);
        $cacheItem3->method('get')->willReturn(time() - 50); // 在线

        $this->cache->expects($this->exactly(3))
            ->method('getItem')
            ->willReturnOnConsecutiveCalls($cacheItem1, $cacheItem2, $cacheItem3)
        ;

        $onlineUsers = $this->tracker->getOnlineUsers($userIds, $userIdType);

        // 确保 onlineUsers 是数组类型
        $this->assertIsArray($onlineUsers);
        $this->assertCount(2, $onlineUsers);
        $this->assertContains('ou_test1', $onlineUsers);
        $this->assertContains('ou_test3', $onlineUsers);
        $this->assertNotContains('ou_test2', $onlineUsers);
    }

    public function testClearUserTracking(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';

        $this->cache->expects($this->exactly(3))
            ->method('deleteItem')
            ->willReturnCallback(function (string $key) {
                static $callCount = 0;
                $expectedKeys = [
                    'lark_user_tracker_last_open_id_ou_test123',
                    'lark_user_tracker_history_open_id_ou_test123',
                    'lark_user_tracker_stats_open_id_ou_test123'];
                $this->assertSame($expectedKeys[$callCount], $key);
                ++$callCount;

                return true;
            })
        ;

        $this->tracker->clearUserTracking($userId, $userIdType);
    }

    public function testActivityHistoryWithExpiredData(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';
        $currentTime = time();
        $activities = [
            ['activity' => 'old_activity', 'timestamp' => $currentTime - 100000], // 过期
            ['activity' => 'recent_activity', 'timestamp' => $currentTime - 1000], // 未过期
        ];

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($activities);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->tracker->getUserActivityHistory($userId, $userIdType);

        // 确保 result 和 activities 是数组类型
        $this->assertIsArray($result);
        $this->assertArrayHasKey('activities', $result);
        $this->assertIsArray($result['activities']);
        // 应该只返回未过期的活动
        $this->assertIsArray($result);
        $this->assertArrayHasKey('activities', $result);
        $this->assertCount(1, $result['activities']);
        $this->assertArrayHasKey(0, $result['activities']);
        $this->assertArrayHasKey('activity', $result['activities'][0]);
        $this->assertSame('recent_activity', $result['activities'][0]['activity']);
    }

    public function testMemoryCacheOptimization(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';
        $timestamp = time() - 100;

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($timestamp);

        // 第一次调用，从缓存读取
        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $status1 = $this->tracker->getUserStatus($userId, $userIdType);
        // 第二次调用，应该使用内存缓存，不再调用cache
        $status2 = $this->tracker->getUserStatus($userId, $userIdType);

        $this->assertSame($status1, $status2);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->cache = self::createMock(CacheItemPoolInterface::class);
        $this->eventDispatcher = self::createMock(EventDispatcherInterface::class);

        // 直接构造被测服务，确保依赖均为 Mock
        $logger = $this->createMock(LoggerInterface::class);
        $this->tracker = new UserTracker($this->cache, $this->eventDispatcher, $logger);
    }
}
