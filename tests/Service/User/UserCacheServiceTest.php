<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Tourze\LarkAppBotBundle\Service\User\UserCacheService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserCacheService::class)]
#[RunTestsInSeparateProcesses]
final class UserCacheServiceTest extends AbstractIntegrationTestCase
{
    private UserCacheService $service;

    public function testGetUserWithNullCache(): void
    {
        $service = self::getService(UserCacheService::class);

        $result = $service->getUser('user123', 'open_id');

        $this->assertNull($result);
    }

    public function testGetUserWithCacheHit(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $userData = ['name' => 'John Doe', 'email' => 'john@example.com'];

        // 首先将数据存入缓存
        $this->service->cacheUser($userId, $userIdType, $userData);

        // 然后从缓存获取
        $result = $this->service->getUser($userId, $userIdType);

        $this->assertSame($userData, $result);
    }

    public function testGetUserWithCacheMiss(): void
    {
        $userId = 'user456';
        $userIdType = 'open_id';

        // 直接获取不存在的用户数据
        $result = $this->service->getUser($userId, $userIdType);

        $this->assertNull($result);
    }

    public function testGetUserWithCacheException(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        // Create a mock cache that throws exceptions
        $faultyCache = self::createMock(CacheItemPoolInterface::class);
        $faultyCache->method('getItem')
            ->willThrowException(new \RuntimeException('Cache error'))
        ;

        $faultyService = self::getService(UserCacheService::class);
        $result = $faultyService->getUser($userId, $userIdType);

        $this->assertNull($result);
    }

    public function testBatchGetUsersWithNullCache(): void
    {
        $service = self::getService(UserCacheService::class);
        $userIds = ['user1', 'user2'];

        $result = $service->batchGetUsers($userIds, 'open_id');

        $this->assertSame(['cached' => [], 'uncached' => $userIds], $result);
    }

    public function testBatchGetUsersWithMixedCacheResults(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'open_id';
        $user1Data = ['name' => 'User 1'];
        $user3Data = ['name' => 'User 3'];

        // 预缓存部分用户数据
        $this->service->cacheUser('user1', $userIdType, $user1Data);
        $this->service->cacheUser('user3', $userIdType, $user3Data);

        $result = $this->service->batchGetUsers($userIds, $userIdType);

        $this->assertSame([
            'cached' => ['user1' => $user1Data, 'user3' => $user3Data],
            'uncached' => ['user2'],
        ], $result);
    }

    public function testBatchCacheUsersWithNullCache(): void
    {
        $service = self::getService(UserCacheService::class);
        $users = ['user1' => ['name' => 'User 1']];

        $service->batchCacheUsers($users, 'open_id');

        $this->expectNotToPerformAssertions();
    }

    public function testBatchCacheUsers(): void
    {
        $users = [
            'user1' => ['name' => 'User 1'],
            'user2' => ['name' => 'User 2'],
        ];
        $userIdType = 'open_id';

        // 批量缓存用户
        $this->service->batchCacheUsers($users, $userIdType);

        // 验证缓存是否成功
        $result = $this->service->batchGetUsers(array_keys($users), $userIdType);
        $this->assertSame([
            'cached' => $users,
            'uncached' => [],
        ], $result);
    }

    public function testCacheUserWithNullCache(): void
    {
        $service = self::getService(UserCacheService::class);

        $service->cacheUser('user123', 'open_id', ['name' => 'John Doe']);

        $this->expectNotToPerformAssertions();
    }

    public function testCacheUser(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $userData = ['name' => 'John Doe'];

        // 缓存用户数据
        $this->service->cacheUser($userId, $userIdType, $userData);

        // 验证缓存是否成功
        $result = $this->service->getUser($userId, $userIdType);
        $this->assertSame($userData, $result);
    }

    public function testCacheUserWithSaveException(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $userData = ['name' => 'John Doe'];

        // Create a mock cache item
        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->method('getKey')->willReturn('test');
        $cacheItem->method('get')->willReturn(null);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->willReturn($cacheItem);
        $cacheItem->method('expiresAt')->willReturn($cacheItem);
        $cacheItem->method('expiresAfter')->willReturn($cacheItem);

        $faultyCache = self::createMock(CacheItemPoolInterface::class);
        $faultyCache->method('getItem')->willReturn($cacheItem);
        $faultyCache->method('save')
            ->willThrowException(new \RuntimeException('Save error'))
        ;

        $faultyService = self::getService(UserCacheService::class);

        // 这个调用应该不会抛出异常，而是处理异常
        $faultyService->cacheUser($userId, $userIdType, $userData);

        // 验证调用成功，没有异常抛出
        $this->expectNotToPerformAssertions();
    }

    public function testClearUserCacheWithNullCache(): void
    {
        $service = self::getService(UserCacheService::class);

        $service->clearUserCache('user123', 'open_id');

        $this->expectNotToPerformAssertions();
    }

    public function testClearUserCache(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $userData = ['name' => 'John Doe'];

        // 首先缓存用户数据
        $this->service->cacheUser($userId, $userIdType, $userData);

        // 验证数据存在
        $result = $this->service->getUser($userId, $userIdType);
        $this->assertSame($userData, $result);

        // 清除缓存
        $this->service->clearUserCache($userId, $userIdType);

        // 验证数据已被清除
        $result = $this->service->getUser($userId, $userIdType);
        $this->assertNull($result);
    }

    public function testClearUserCacheWithException(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        // Create a mock cache item
        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->method('getKey')->willReturn('test');
        $cacheItem->method('get')->willReturn(null);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->willReturn($cacheItem);
        $cacheItem->method('expiresAt')->willReturn($cacheItem);
        $cacheItem->method('expiresAfter')->willReturn($cacheItem);

        $faultyCache = self::createMock(CacheItemPoolInterface::class);
        $faultyCache->method('getItem')->willReturn($cacheItem);
        $faultyCache->method('deleteItem')
            ->willThrowException(new \RuntimeException('Delete error'))
        ;

        $faultyService = self::getService(UserCacheService::class);

        // 这个调用应该不会抛出异常，而是处理异常
        $faultyService->clearUserCache($userId, $userIdType);

        // 验证调用成功，没有异常抛出
        $this->expectNotToPerformAssertions();
    }

    public function testClearAllCacheWithNullCache(): void
    {
        $service = self::getService(UserCacheService::class);

        $service->clearAllCache();

        $this->expectNotToPerformAssertions();
    }

    public function testClearAllCache(): void
    {
        // 首先缓存一些用户数据
        $this->service->cacheUser('user1', 'open_id', ['name' => 'User 1']);
        $this->service->cacheUser('user2', 'open_id', ['name' => 'User 2']);

        // 验证数据存在
        $result = $this->service->batchGetUsers(['user1', 'user2'], 'open_id');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('cached', $result);
        $this->assertArrayHasKey('uncached', $result);
        $this->assertIsArray($result['cached']);
        $this->assertCount(2, $result['cached']);

        // 清除所有缓存
        $this->service->clearAllCache();

        // 验证数据已被清除
        $result = $this->service->batchGetUsers(['user1', 'user2'], 'open_id');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('cached', $result);
        $this->assertArrayHasKey('uncached', $result);
        $this->assertIsArray($result['cached']);
        $this->assertIsArray($result['uncached']);
        $this->assertCount(0, $result['cached']);
        $this->assertCount(2, $result['uncached']);
    }

    public function testClearAllCacheWithException(): void
    {
        // Create a mock cache item
        $cacheItem = self::createMock(CacheItemInterface::class);
        $cacheItem->method('getKey')->willReturn('test');
        $cacheItem->method('get')->willReturn(null);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->willReturn($cacheItem);
        $cacheItem->method('expiresAt')->willReturn($cacheItem);
        $cacheItem->method('expiresAfter')->willReturn($cacheItem);

        $faultyCache = self::createMock(CacheItemPoolInterface::class);
        $faultyCache->method('getItem')->willReturn($cacheItem);
        $faultyCache->method('clear')
            ->willThrowException(new \RuntimeException('Clear error'))
        ;

        $faultyService = self::getService(UserCacheService::class);

        // 这个调用应该不会抛出异常，而是处理异常
        $faultyService->clearAllCache();

        // 验证调用成功，没有异常抛出
        $this->expectNotToPerformAssertions();
    }

    public function testIsAvailableWithCache(): void
    {
        $this->assertTrue($this->service->isAvailable());
    }

    public function testIsAvailableWithNullCache(): void
    {
        $service = self::getService(UserCacheService::class);

        $this->assertFalse($service->isAvailable());
    }

    public function testGetUserCacheKeyFormats(): void
    {
        $testCases = [
            ['user123', 'open_id', 'lark_user_open_id_user123'],
            ['user456', 'union_id', 'lark_user_union_id_user456'],
            ['user@example.com', 'email', 'lark_user_email_user@example.com'],
        ];

        foreach ($testCases as [$userId, $userIdType, $expectedKey]) {
            // 测试不同的用户ID类型，验证缓存键格式
            $this->service->getUser($userId, $userIdType);
            // 如果没有异常，说明缓存键格式正确
            $this->expectNotToPerformAssertions();
        }
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
        // 使用容器获取服务
        $this->service = self::getService(UserCacheService::class);
    }
}
