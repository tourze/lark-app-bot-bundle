<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Service\User\UserDataCacheManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserDataCacheManager::class)]
#[RunTestsInSeparateProcesses]
final class UserDataCacheManagerTest extends AbstractIntegrationTestCase
{
    private UserDataCacheManager $cacheManager;

    private CacheItemPoolInterface&MockObject $cache;

    private LoggerInterface&MockObject $logger;

    public function testGetFromMemoryCache(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = ['name' => 'John Doe', 'email' => 'john@example.com'];

        // Set data in memory cache first
        $this->cacheManager->set($userId, $userIdType, $userData);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('从内存缓存获取用户数据', [
                'user_id' => $userId,
                'user_id_type' => $userIdType])
        ;

        $result = $this->cacheManager->get($userId, $userIdType);

        $this->assertSame($userData, $result);
    }

    public function testGetFromPersistentCache(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $cacheKey = 'lark_user_data_user_id_user123';

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true)
        ;

        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($userData)
        ;

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItem)
        ;

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('从持久化缓存获取用户数据', [
                'user_id' => $userId,
                'user_id_type' => $userIdType])
        ;

        $result = $this->cacheManager->get($userId, $userIdType);

        $this->assertSame($userData, $result);
    }

    public function testGetWithCacheMiss(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $cacheKey = 'lark_user_data_user_id_user123';

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false)
        ;

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItem)
        ;

        $this->logger->expects($this->never())
            ->method('debug')
        ;

        $result = $this->cacheManager->get($userId, $userIdType);

        $this->assertNull($result);
    }

    public function testGetWithNullCache(): void
    {
        $container = self::getContainer();
        $container->set('Psr\Cache\CacheItemPoolInterface', null);
        /** @var UserDataCacheManager $cacheManager */
        $cacheManager = $container->get(UserDataCacheManager::class);
        $result = $cacheManager->get('user123', 'user_id');

        $this->assertNull($result);
    }

    public function testSetWithPersistentCache(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = ['name' => 'John Doe'];
        $cacheKey = 'lark_user_data_user_id_user123';

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('set')
            ->with($userData)
        ;

        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(7200)
        ;

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItem)
        ;

        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem)
        ;

        $this->cacheManager->set($userId, $userIdType, $userData);

        // Verify data is in memory cache
        $result = $this->cacheManager->get($userId, $userIdType);
        $this->assertSame($userData, $result);
    }

    public function testSetWithNullCache(): void
    {
        $container = self::getContainer();
        $container->set('Psr\Cache\CacheItemPoolInterface', null);
        /** @var UserDataCacheManager $cacheManager */
        $cacheManager = $container->get(UserDataCacheManager::class);

        // 验证方法调用没有抛出异常
        $cacheManager->set('user123', 'user_id', ['name' => 'John']);

        // 验证没有缓存实际运行（应该返回 null）
        $result = $cacheManager->get('user123', 'user_id');
        $this->assertNull($result);
    }

    public function testDelete(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = ['name' => 'John Doe'];
        $cacheKey = 'lark_user_data_user_id_user123';

        // First set data
        $this->cacheManager->set($userId, $userIdType, $userData);

        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with($cacheKey)
        ;

        $this->cacheManager->delete($userId, $userIdType);

        // Verify data is removed from memory cache
        $result = $this->cacheManager->get($userId, $userIdType);
        $this->assertNull($result);
    }

    public function testDeleteWithException(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $cacheKey = 'lark_user_data_user_id_user123';

        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with($cacheKey)
            ->willThrowException(new \Exception('Delete failed'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('删除用户数据缓存失败', [
                'user_id' => $userId,
                'error' => 'Delete failed'])
        ;

        $this->cacheManager->delete($userId, $userIdType);
    }

    public function testDeleteWithNullCache(): void
    {
        $container = self::getContainer();
        $container->set('Psr\Cache\CacheItemPoolInterface', null);
        /** @var UserDataCacheManager $cacheManager */
        $cacheManager = $container->get(UserDataCacheManager::class);

        // 验证删除操作没有抛出异常
        $cacheManager->delete('user123', 'user_id');

        // 验证仍然返回 null（因为没有实际缓存）
        $result = $cacheManager->get('user123', 'user_id');
        $this->assertNull($result);
    }

    public function testGetDirtyData(): void
    {
        $userData1 = ['name' => 'User 1'];
        $userData2 = ['name' => 'User 2'];

        // Mock cache to fail save operations, keeping data dirty
        $cacheItem = self::createMock(CacheItemInterface::class);
        $this->cache->expects($this->exactly(2))
            ->method('getItem')
            ->willReturn($cacheItem)
        ;
        $this->cache->expects($this->exactly(2))
            ->method('save')
            ->willThrowException(new \Exception('Cache save failed'))
        ;

        $this->cacheManager->set('user1', 'user_id', $userData1);
        $this->cacheManager->set('user2', 'user_id', $userData2);

        $dirtyData = $this->cacheManager->getDirtyData();

        $this->assertIsArray($dirtyData);
        $this->assertCount(2, $dirtyData);
        $this->assertArrayHasKey('lark_user_data_user_id_user1', $dirtyData);
        $this->assertArrayHasKey('lark_user_data_user_id_user2', $dirtyData);
        $this->assertSame($userData1, $dirtyData['lark_user_data_user_id_user1']);
        $this->assertSame($userData2, $dirtyData['lark_user_data_user_id_user2']);
    }

    public function testPersistDirtyData(): void
    {
        $userData1 = ['name' => 'User 1'];
        $userData2 = ['name' => 'User 2'];

        // First, set up mocks for initial set operations that will fail
        $cacheItem = self::createMock(CacheItemInterface::class);
        $this->cache->expects($this->exactly(4)) // 2 for set, 2 for persistDirtyData
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        // First 2 saves will fail (during set), next 2 will succeed (during persistDirtyData)
        $this->cache->expects($this->exactly(4))
            ->method('save')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \Exception('Initial save failed')),
                $this->throwException(new \Exception('Initial save failed')),
                true,
                true
            )
        ;

        // Set data to make it dirty (these will fail and keep data dirty)
        $this->cacheManager->set('user1', 'user_id', $userData1);
        $this->cacheManager->set('user2', 'user_id', $userData2);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('持久化修改数据完成', [
                'persisted_count' => 2])
        ;

        $this->cacheManager->persistDirtyData();

        // Verify dirty data is cleared
        $dirtyData = $this->cacheManager->getDirtyData();
        $this->assertIsArray($dirtyData);
        $this->assertCount(0, $dirtyData);
    }

    public function testPersistDirtyDataWithNullCache(): void
    {
        $container = self::getContainer();
        $container->set('Psr\Cache\CacheItemPoolInterface', null);
        /** @var UserDataCacheManager $cacheManager */
        $cacheManager = $container->get(UserDataCacheManager::class);

        // 验证持久化操作没有抛出异常
        $cacheManager->persistDirtyData();

        // 验证没有实际的脏数据产生
        $dirtyData = $cacheManager->getDirtyData();
        $this->assertEmpty($dirtyData);
    }

    public function testCleanMemoryCache(): void
    {
        $oldData = [
            'name' => 'Old User',
            'metadata' => ['last_access' => time() - 7200], // 2 hours ago
        ];

        $newData = [
            'name' => 'New User',
            'metadata' => ['last_access' => time() - 1800], // 30 minutes ago
        ];

        $this->cacheManager->set('old_user', 'user_id', $oldData);
        $this->cacheManager->set('new_user', 'user_id', $newData);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('清理内存缓存完成', [
                'cleaned_count' => 1,
                'max_age' => 3600])
        ;

        $this->cacheManager->cleanMemoryCache(3600); // 1 hour

        // Old data should be cleaned, new data should remain
        $this->assertNull($this->cacheManager->get('old_user', 'user_id'));
        $this->assertNotNull($this->cacheManager->get('new_user', 'user_id'));
    }

    public function testCleanMemoryCachePreservesDirtyData(): void
    {
        $oldData = [
            'name' => 'Old User',
            'metadata' => ['last_access' => time() - 7200], // 2 hours ago
        ];

        // Mock cache to fail save operation, keeping data dirty
        $cacheItem = self::createMock(CacheItemInterface::class);
        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;
        $this->cache->expects($this->once())
            ->method('save')
            ->willThrowException(new \Exception('Cache save failed'))
        ;

        $this->cacheManager->set('old_user', 'user_id', $oldData);
        // Don't persist, so it remains dirty

        // No logger call expected when nothing is cleaned
        $this->logger->expects($this->never())
            ->method('info')
        ;

        $this->cacheManager->cleanMemoryCache(3600);

        // Dirty data should be preserved
        $this->assertNotNull($this->cacheManager->get('old_user', 'user_id'));
    }

    public function testGetCacheKey(): void
    {
        $testCases = [
            ['user123', 'user_id', 'lark_user_data_user_id_user123'],
            ['open456', 'open_id', 'lark_user_data_open_id_open456'],
            ['union789', 'union_id', 'lark_user_data_union_id_union789']];

        foreach ($testCases as [$userId, $userIdType, $expectedKey]) {
            $actualKey = $this->cacheManager->getCacheKey($userId, $userIdType);
            $this->assertSame($expectedKey, $actualKey);
        }
    }

    public function testGetFromPersistentCacheWithException(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $cacheKey = 'lark_user_data_user_id_user123';

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($cacheKey)
            ->willThrowException(new \Exception('Cache error'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('从缓存读取失败', [
                'key' => $cacheKey,
                'error' => 'Cache error'])
        ;

        $result = $this->cacheManager->get($userId, $userIdType);

        $this->assertNull($result);
    }

    public function testSaveToPersistentCacheWithException(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = ['name' => 'John Doe'];
        $cacheKey = 'lark_user_data_user_id_user123';

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($cacheKey)
            ->willThrowException(new \Exception('Save error'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('保存到缓存失败', [
                'key' => $cacheKey,
                'error' => 'Save error'])
        ;

        $this->cacheManager->set($userId, $userIdType, $userData);

        // Data should still be in memory cache
        $result = $this->cacheManager->get($userId, $userIdType);
        $this->assertSame($userData, $result);
    }

    public function testGetUpdatesLastAccessTime(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'metadata' => ['last_access' => 1000000]];
        $cacheKey = 'lark_user_data_user_id_user123';

        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true)
        ;

        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($userData)
        ;

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItem)
        ;

        $this->logger->expects($this->once())
            ->method('debug')
        ;

        $result = $this->cacheManager->get($userId, $userIdType);

        $this->assertIsArray($result);
        $this->assertIsArray($result['metadata'] ?? null);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('last_access', $result['metadata']);
        $this->assertGreaterThan(1000000, $result['metadata']['last_access']);
    }

    protected function prepareMockServices(): void
    {
        $this->cache = self::createMock(CacheItemPoolInterface::class);
        $this->logger = self::createMock(LoggerInterface::class);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->cache = self::createMock(CacheItemPoolInterface::class);
        $this->logger = self::createMock(LoggerInterface::class);
        // 从容器获取服务
        $container = self::getContainer();
        $container->set('Psr\Cache\CacheItemPoolInterface', $this->cache);
        $container->set('Psr\Log\LoggerInterface', $this->logger);
        /** @var UserDataCacheManager $cacheManager */
        $cacheManager = $container->get(UserDataCacheManager::class);
        $this->cacheManager = $cacheManager;
    }
}
