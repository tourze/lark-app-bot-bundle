<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Service\User\UserCacheManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 用户缓存管理器测试.
 *
 * @internal
 */
#[CoversClass(UserCacheManager::class)]
#[RunTestsInSeparateProcesses]
final class UserCacheManagerTest extends AbstractIntegrationTestCase
{
    private UserCacheManager $cacheManager;

    private MockObject&CacheItemPoolInterface $cache;

    private MockObject&LoggerInterface $logger;

    public function testCacheUser(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';
        $userData = ['name' => '测试用户', 'email' => 'test@example.com'];

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('set')->with($userData);
        $cacheItem->expects($this->once())->method('expiresAfter')->with(3600);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('lark_user_user_info_open_id_ou_test123')
            ->willReturn($cacheItem)
        ;

        $this->cache->expects($this->once())->method('save')->with($cacheItem);

        $this->cacheManager->cacheUser($userId, $userIdType, $userData);
    }

    public function testGetCachedUser(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';
        $userData = ['name' => '测试用户'];

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($userData);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('lark_user_user_info_open_id_ou_test123')
            ->willReturn($cacheItem)
        ;

        $result = $this->cacheManager->getCachedUser($userId, $userIdType);

        $this->assertSame($userData, $result);
    }

    public function testGetCachedUserMiss(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->cacheManager->getCachedUser($userId, $userIdType);

        $this->assertNull($result);
    }

    public function testBatchCacheUsers(): void
    {
        $users = [
            'ou_test1' => ['name' => '用户1'],
            'ou_test2' => ['name' => '用户2']];
        $userIdType = 'open_id';

        $cacheItem1 = self::createMock(CacheItemInterface::class);
        $cacheItem1->expects($this->once())->method('set')->with(['name' => '用户1']);
        $cacheItem1->expects($this->once())->method('expiresAfter')->with(3600);

        $cacheItem2 = self::createMock(CacheItemInterface::class);
        $cacheItem2->expects($this->once())->method('set')->with(['name' => '用户2']);
        $cacheItem2->expects($this->once())->method('expiresAfter')->with(3600);

        $this->cache->expects($this->exactly(2))
            ->method('getItem')
            ->willReturnOnConsecutiveCalls($cacheItem1, $cacheItem2)
        ;

        $this->cache->expects($this->once())
            ->method('saveDeferred')
            ->with($cacheItem1, $cacheItem2)
        ;

        $this->cache->expects($this->once())->method('commit');

        $this->cacheManager->batchCacheUsers($users, $userIdType);
    }

    public function testBatchGetCachedUsers(): void
    {
        $userIds = ['ou_test1', 'ou_test2', 'ou_test3'];
        $userIdType = 'open_id';

        $cacheItem1 = self::createMock(CacheItemInterface::class);
        $cacheItem1->method('isHit')->willReturn(true);
        $cacheItem1->method('get')->willReturn(['name' => '用户1']);

        $cacheItem2 = self::createMock(CacheItemInterface::class);
        $cacheItem2->method('isHit')->willReturn(false);

        $cacheItem3 = self::createMock(CacheItemInterface::class);
        $cacheItem3->method('isHit')->willReturn(true);
        $cacheItem3->method('get')->willReturn(['name' => '用户3']);

        $this->cache->expects($this->exactly(3))
            ->method('getItem')
            ->willReturnOnConsecutiveCalls($cacheItem1, $cacheItem2, $cacheItem3)
        ;

        $result = $this->cacheManager->batchGetCachedUsers($userIds, $userIdType);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('cached', $result);
        $this->assertArrayHasKey('uncached', $result);
        $this->assertIsArray($result['cached']);
        $this->assertIsArray($result['uncached']);
        $this->assertCount(2, $result['cached']);
        $this->assertCount(1, $result['uncached']);
        $this->assertSame(['name' => '用户1'], $result['cached']['ou_test1']);
        $this->assertSame(['name' => '用户3'], $result['cached']['ou_test3']);
        $this->assertSame(['ou_test2'], $result['uncached']);
    }

    public function testCacheSearchResults(): void
    {
        $searchKey = '张三';
        $results = [
            'items' => [
                ['name' => '张三'], ['name' => '张三丰']],
            'has_more' => false];
        $params = ['department_id' => 'dept_123'];

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('set')->with($results);
        $cacheItem->expects($this->once())->method('expiresAfter')->with(300);

        $jsonParams = json_encode($params);
        $expectedKey = 'lark_user_search_张三_' . md5(false !== $jsonParams ? $jsonParams : '');
        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItem)
        ;

        $this->cache->expects($this->once())->method('save')->with($cacheItem);

        $this->cacheManager->cacheSearchResults($searchKey, $results, $params);
    }

    public function testInvalidateUser(): void
    {
        $userId = 'ou_test123';
        $userIdType = 'open_id';

        $expectedKeys = [
            'lark_user_user_info_open_id_ou_test123',
            'lark_user_user_departments_open_id_ou_test123',
            'lark_user_user_permissions_open_id_ou_test123',
            'lark_user_search_results_open_id_ou_test123',
            'lark_user_batch_results_open_id_ou_test123'];

        $this->cache->expects($this->exactly(5))
            ->method('deleteItem')
            ->willReturnCallback(function (string $key) use ($expectedKeys) {
                static $callCount = 0;
                $this->assertSame($expectedKeys[$callCount], $key);
                ++$callCount;

                return true;
            })
        ;

        $this->cacheManager->invalidateUser($userId, $userIdType);
    }

    public function testBatchInvalidateUsers(): void
    {
        $userIds = ['ou_test1', 'ou_test2'];
        $userIdType = 'open_id';

        // 每个用户有5种数据类型，总共10次删除
        $this->cache->expects($this->exactly(10))
            ->method('deleteItem')
            ->willReturn(true)
        ;

        $this->cacheManager->batchInvalidateUsers($userIds, $userIdType);
    }

    public function testClearAllCache(): void
    {
        $this->cache->expects($this->once())->method('clear');

        $this->cacheManager->clearAllCache();
    }

    public function testSetTtlConfig(): void
    {
        $dataType = 'custom_data';
        $ttl = 7200;

        $this->cacheManager->setTtlConfig($dataType, $ttl);

        // 测试新的TTL配置是否生效
        $userId = 'ou_test123';
        $userData = ['custom' => 'data'];

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('expiresAfter')->with($ttl);

        $this->cache->method('getItem')->willReturn($cacheItem);

        $this->cacheManager->cacheUser($userId, 'open_id', $userData, $dataType);
    }

    public function testWarmupCache(): void
    {
        $userIds = ['ou_test1', 'ou_test2'];
        $userIdType = 'open_id';
        $users = [
            'ou_test1' => ['name' => '用户1'],
            'ou_test2' => ['name' => '用户2']];

        $dataLoader = function ($ids) use ($users) {
            return $users;
        };

        // 期望调用batchCacheUsers
        $cacheItem1 = self::createMock(CacheItemInterface::class);
        $cacheItem2 = self::createMock(CacheItemInterface::class);

        $this->cache->expects($this->exactly(2))
            ->method('getItem')
            ->willReturnOnConsecutiveCalls($cacheItem1, $cacheItem2)
        ;

        $this->cache->expects($this->once())->method('saveDeferred');
        $this->cache->expects($this->once())->method('commit');

        $this->cacheManager->warmupCache($userIds, $userIdType, $dataLoader);
    }

    public function testGetCacheStats(): void
    {
        // 默认情况下返回空数组
        $stats = $this->cacheManager->getCacheStats();
        $this->assertSame([], $stats);
    }

    public function testCacheException(): void
    {
        $userId = 'ou_test123';
        $userData = ['name' => '测试用户'];

        $this->cache->method('getItem')
            ->willThrowException(new \Exception('缓存错误'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('缓存用户数据失败')
        ;

        // 不应该抛出异常，只记录日志
        $this->cacheManager->cacheUser($userId, 'open_id', $userData);
    }

    public function testInvalidateSearchCache(): void
    {
        // 测试清除所有搜索缓存
        $this->logger->expects($this->once())
            ->method('info')
            ->with('请求清除搜索缓存', ['params' => 'all'])
        ;

        $this->cacheManager->invalidateSearchCache();
    }

    public function testInvalidateSearchCacheWithDepartmentId(): void
    {
        $params = ['department_id' => 'dept_123'];

        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with('lark_user_search_dept_dept_123')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('搜索缓存失效完成', [
                'params' => $params,
                'keys_count' => 1])
        ;

        $this->cacheManager->invalidateSearchCache($params);
    }

    public function testInvalidateSearchCacheWithQuery(): void
    {
        $params = ['query' => '张三'];
        $expectedKey = 'lark_user_search_query_' . md5('张三');

        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with($expectedKey)
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('搜索缓存失效完成', [
                'params' => $params,
                'keys_count' => 1])
        ;

        $this->cacheManager->invalidateSearchCache($params);
    }

    public function testInvalidateSearchCacheWithMultipleParams(): void
    {
        $params = [
            'department_id' => 'dept_456',
            'query' => '李四'];

        $this->cache->expects($this->exactly(2))
            ->method('deleteItem')
            ->willReturnCallback(function (string $key) {
                $expectedKeys = [
                    'lark_user_search_dept_dept_456',
                    'lark_user_search_query_' . md5('李四')];
                static $callCount = 0;

                $this->assertSame($expectedKeys[$callCount], $key);
                ++$callCount;

                return true;
            })
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('搜索缓存失效完成', [
                'params' => $params,
                'keys_count' => 2])
        ;

        $this->cacheManager->invalidateSearchCache($params);
    }

    public function testInvalidateSearchCacheWithException(): void
    {
        $params = ['department_id' => 'dept_789'];

        $this->cache->method('deleteItem')
            ->willThrowException(new \Exception('删除缓存失败'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('搜索缓存失效失败', [
                'params' => $params,
                'error' => '删除缓存失败'])
        ;

        // 不应该抛出异常
        $this->cacheManager->invalidateSearchCache($params);
    }

    public function testWarmupUsers(): void
    {
        $userIds = ['ou_user1', 'ou_user2', 'ou_user3'];
        $userIdType = 'open_id';

        $this->logger->expects($this->once())
            ->method('info')
            ->with('预热用户缓存请求', [
                'user_count' => 3,
                'user_id_type' => 'open_id'])
        ;

        $this->cacheManager->warmupUsers($userIds, $userIdType);
    }

    public function testWarmupUsersWithDefaultIdType(): void
    {
        $userIds = ['ou_user1', 'ou_user2'];

        $this->logger->expects($this->once())
            ->method('info')
            ->with('预热用户缓存请求', [
                'user_count' => 2,
                'user_id_type' => 'open_id'])
        ;

        // 使用默认的 user_id_type
        $this->cacheManager->warmupUsers($userIds);
    }

    public function testWarmupUsersWithEmptyList(): void
    {
        $userIds = [];

        $this->logger->expects($this->once())
            ->method('info')
            ->with('预热用户缓存请求', [
                'user_count' => 0,
                'user_id_type' => 'open_id'])
        ;

        $this->cacheManager->warmupUsers($userIds);
    }

    protected function prepareMockServices(): void
    {
        // 创建 mock 对象
        $this->cache = self::createMock(CacheItemPoolInterface::class);
        $this->logger = self::createMock(LoggerInterface::class);
    }

    protected function onSetUp(): void
    {
        $this->prepareMockServices();
        // 从服务容器获取 UserCacheManager 实例而不是直接实例化
        /** @var UserCacheManager $manager */
        $manager = self::getContainer()->get(UserCacheManager::class);
        $this->cacheManager = $manager;
    }
}
