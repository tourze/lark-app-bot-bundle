<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncStrategyManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(SyncStrategyManager::class)]
#[RunTestsInSeparateProcesses]
final class SyncStrategyManagerTest extends AbstractIntegrationTestCase
{
    private SyncStrategyManager $manager;

    public function testFilterUsersToSyncWithForce(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'user_id';
        $force = true;

        $result = $this->manager->filterUsersToSync($userIds, $userIdType, $force);

        $this->assertSame($userIds, $result['toSync']);
        $this->assertEmpty($result['skipped']);
    }

    public function testFilterUsersToSyncWithoutForce(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'user_id';
        $force = false;

        $result = $this->manager->filterUsersToSync($userIds, $userIdType, $force);

        // 第一次同步，所有用户都需要同步
        $this->assertSame($userIds, $result['toSync']);
        $this->assertEmpty($result['skipped']);
    }

    public function testFilterUsersToSyncAfterRecentSync(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'user_id';

        // 先记录同步时间
        foreach ($userIds as $userId) {
            $this->manager->recordSyncTime($userId, $userIdType);
        }

        // 立即再次检查，应该被跳过
        $result = $this->manager->filterUsersToSync($userIds, $userIdType, false);

        $this->assertEmpty($result['toSync']);
        $this->assertSame($userIds, $result['skipped']);
    }

    public function testFilterUsersToSyncMixedScenario(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'user_id';

        // 只为user1和user2记录同步时间
        $this->manager->recordSyncTime('user1', $userIdType);
        $this->manager->recordSyncTime('user2', $userIdType);

        $result = $this->manager->filterUsersToSync($userIds, $userIdType, false);

        $this->assertSame(['user3'], $result['toSync']);
        $this->assertSame(['user1', 'user2'], $result['skipped']);
    }

    public function testNeedsSyncWithForce(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';

        // 先记录同步时间
        $this->manager->recordSyncTime($userId, $userIdType);

        // 使用强制同步
        $this->assertTrue($this->manager->needsSync($userId, $userIdType, true));
    }

    public function testNeedsSyncWithoutPreviousSync(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';

        // 没有同步历史，应该需要同步
        $this->assertTrue($this->manager->needsSync($userId, $userIdType, false));
    }

    public function testNeedsSyncWithRecentSync(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';

        // 记录同步时间
        $this->manager->recordSyncTime($userId, $userIdType);

        // 立即检查，不应该需要同步
        $this->assertFalse($this->manager->needsSync($userId, $userIdType, false));
    }

    public function testRecordSyncTime(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';

        // 初始状态需要同步
        $this->assertTrue($this->manager->needsSync($userId, $userIdType, false));

        // 记录同步时间
        $this->manager->recordSyncTime($userId, $userIdType);

        // 记录后不需要同步
        $this->assertFalse($this->manager->needsSync($userId, $userIdType, false));
    }

    public function testBatchRecordSyncTime(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'user_id';

        // 初始状态都需要同步
        foreach ($userIds as $userId) {
            $this->assertTrue($this->manager->needsSync($userId, $userIdType, false));
        }

        // 批量记录同步时间
        $this->manager->batchRecordSyncTime($userIds, $userIdType);

        // 记录后都不需要同步
        foreach ($userIds as $userId) {
            $this->assertFalse($this->manager->needsSync($userId, $userIdType, false));
        }
    }

    public function testClearSyncHistory(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';

        // 记录同步时间
        $this->manager->recordSyncTime($userId, $userIdType);
        $this->assertFalse($this->manager->needsSync($userId, $userIdType, false));

        // 清除历史
        $this->manager->clearSyncHistory();

        // 清除后又需要同步了
        $this->assertTrue($this->manager->needsSync($userId, $userIdType, false));
    }

    public function testDifferentUserIdTypes(): void
    {
        $userId = 'user123';

        // 为不同的用户ID类型记录同步时间
        $this->manager->recordSyncTime($userId, 'user_id');
        $this->manager->recordSyncTime($userId, 'union_id');
        $this->manager->recordSyncTime($userId, 'open_id');

        // 检查不同类型的同步状态
        $this->assertFalse($this->manager->needsSync($userId, 'user_id', false));
        $this->assertFalse($this->manager->needsSync($userId, 'union_id', false));
        $this->assertFalse($this->manager->needsSync($userId, 'open_id', false));

        // 清除历史后都需要同步
        $this->manager->clearSyncHistory();
        $this->assertTrue($this->manager->needsSync($userId, 'user_id', false));
        $this->assertTrue($this->manager->needsSync($userId, 'union_id', false));
        $this->assertTrue($this->manager->needsSync($userId, 'open_id', false));
    }

    public function testSyncKeyGeneration(): void
    {
        $userId1 = 'user123';
        $userId2 = 'user456';
        $userIdType = 'user_id';

        // 为不同用户记录同步时间
        $this->manager->recordSyncTime($userId1, $userIdType);

        // 检查只有记录了同步时间的用户不需要同步
        $this->assertFalse($this->manager->needsSync($userId1, $userIdType, false));
        $this->assertTrue($this->manager->needsSync($userId2, $userIdType, false));
    }

    public function testEmptyUserIdsList(): void
    {
        $userIds = [];
        $userIdType = 'user_id';

        $result = $this->manager->filterUsersToSync($userIds, $userIdType, false);

        $this->assertEmpty($result['toSync']);
        $this->assertEmpty($result['skipped']);
    }

    public function testSingleUserFiltering(): void
    {
        $userIds = ['user123'];
        $userIdType = 'user_id';

        // 第一次过滤，需要同步
        $result1 = $this->manager->filterUsersToSync($userIds, $userIdType, false);
        $this->assertSame($userIds, $result1['toSync']);
        $this->assertEmpty($result1['skipped']);

        // 记录同步时间
        $this->manager->recordSyncTime('user123', $userIdType);

        // 第二次过滤，跳过同步
        $result2 = $this->manager->filterUsersToSync($userIds, $userIdType, false);
        $this->assertEmpty($result2['toSync']);
        $this->assertSame($userIds, $result2['skipped']);
    }

    public function testBatchOperationsConsistency(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'user_id';

        // 使用批量记录
        $this->manager->batchRecordSyncTime($userIds, $userIdType);

        // 使用单个检查，应该都不需要同步
        foreach ($userIds as $userId) {
            $this->assertFalse($this->manager->needsSync($userId, $userIdType, false));
        }

        // 使用批量过滤，应该都被跳过
        $result = $this->manager->filterUsersToSync($userIds, $userIdType, false);
        $this->assertEmpty($result['toSync']);
        $this->assertSame($userIds, $result['skipped']);
    }

    public function testTimeBasedSyncLogic(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';

        // 使用反射来测试时间相关的私有方法逻辑
        $reflection = new \ReflectionClass($this->manager);
        $lastSyncTimeProperty = $reflection->getProperty('lastSyncTime');
        $lastSyncTimeProperty->setAccessible(true);

        $getSyncKeyMethod = $reflection->getMethod('getSyncKey');
        $getSyncKeyMethod->setAccessible(true);

        $isTimeToSyncMethod = $reflection->getMethod('isTimeToSync');
        $isTimeToSyncMethod->setAccessible(true);

        // 测试同步键生成
        $syncKey = $getSyncKeyMethod->invoke($this->manager, $userId, $userIdType);
        $this->assertSame('user_id_user123', $syncKey);

        // 测试没有记录时的判断
        $this->assertTrue($isTimeToSyncMethod->invoke($this->manager, $syncKey));

        // 设置最近的同步时间
        $lastSyncTime = $lastSyncTimeProperty->getValue($this->manager);
        $lastSyncTime[$syncKey] = time();
        $lastSyncTimeProperty->setValue($this->manager, $lastSyncTime);

        // 测试最近同步过的判断
        $this->assertFalse($isTimeToSyncMethod->invoke($this->manager, $syncKey));

        // 设置一个很久以前的同步时间（超过间隔）
        $lastSyncTime[$syncKey] = time() - 400; // 超过300秒间隔
        $lastSyncTimeProperty->setValue($this->manager, $lastSyncTime);

        // 测试超过间隔后的判断
        $this->assertTrue($isTimeToSyncMethod->invoke($this->manager, $syncKey));
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->manager = self::getService(SyncStrategyManager::class);
    }
}
