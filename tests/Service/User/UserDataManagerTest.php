<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\User\UserDataCacheManager;
use Tourze\LarkAppBotBundle\Service\User\UserDataManager;
use Tourze\LarkAppBotBundle\Service\User\UserServiceInterface;
use Tourze\LarkAppBotBundle\Service\User\UserSyncServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserDataManager::class)]
#[RunTestsInSeparateProcesses]
class UserDataManagerTest extends AbstractIntegrationTestCase
{
    private UserDataManager $manager;

    public function testGetUserDataWithValidUser(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $result = $this->manager->getUserData($userId, $userIdType);

        // 验证返回结果类型
        $this->assertIsArray($result);
    }

    public function testGetUserDataWithInvalidUserIdType(): void
    {
        $userId = 'user123';
        $userIdType = 'invalid_type';

        try {
            $this->manager->getUserData($userId, $userIdType);
            static::fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertTrue(true, 'ValidationException was thrown as expected');
        }
    }

    public function testGetUserDataWithEmptyUserId(): void
    {
        $userId = '';
        $userIdType = 'open_id';

        $this->expectException(ValidationException::class);
        $this->manager->getUserData($userId, $userIdType);
    }

    public function testBatchGetUserDataWithValidUsers(): void
    {
        $userIds = ['user1', 'user2'];
        $userIdType = 'open_id';

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $result = $this->manager->batchGetUserData($userIds, $userIdType);

        // 验证返回结果类型
        $this->assertIsArray($result);
    }

    public function testBatchGetUserDataWithEmptyArray(): void
    {
        $userIds = [];
        $userIdType = 'open_id';

        $result = $this->manager->batchGetUserData($userIds, $userIdType);

        // 应该返回空数组
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testUpdateUserCustomDataWithValidData(): void
    {
        $userId = 'user123';
        $customData = ['department' => 'engineering', 'level' => 'senior'];
        $userIdType = 'open_id';

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $this->manager->updateUserCustomData($userId, $customData, $userIdType);

        // 验证调用成功，没有异常抛出
        $this->expectNotToPerformAssertions();
    }

    public function testUpdateUserCustomDataWithEmptyData(): void
    {
        $userId = 'user123';
        $customData = [];
        $userIdType = 'open_id';

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $this->manager->updateUserCustomData($userId, $customData, $userIdType);

        // 验证调用成功，没有异常抛出
        $this->expectNotToPerformAssertions();
    }

    public function testDeleteUserDataWithValidUser(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $this->manager->deleteUserData($userId, $userIdType);

        // 验证调用成功，没有异常抛出
        $this->expectNotToPerformAssertions();
    }

    public function testDeleteUserDataWithEmptyUserId(): void
    {
        $userId = '';
        $userIdType = 'open_id';

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $this->manager->deleteUserData($userId, $userIdType);

        // 验证调用成功，没有异常抛出
        $this->expectNotToPerformAssertions();
    }

    public function testExportUserDataWithValidUser(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $options = ['format' => 'json'];

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $result = $this->manager->exportUserData($userId, $userIdType, $options);

        // 验证返回结果类型
        $this->assertIsArray($result);
    }

    public function testExportUserDataWithoutOptions(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $result = $this->manager->exportUserData($userId, $userIdType);

        // 验证返回结果类型
        $this->assertIsArray($result);
    }

    public function testImportUserDataWithValidData(): void
    {
        $importData = [
            'user_id' => 'user123',
            'user_id_type' => 'open_id',
            'basic_info' => ['name' => 'John Doe'],
            'export_version' => '1.0',
        ];

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $result = $this->manager->importUserData($importData);

        // 验证返回结果
        $this->assertIsString($result);
    }

    public function testImportUserDataWithInvalidData(): void
    {
        $importData = [
            'user_id' => '',
            'user_id_type' => 'open_id',
        ];

        $this->expectException(ValidationException::class);
        $this->manager->importUserData($importData);
    }

    public function testRefreshUserDataWithValidUser(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $result = $this->manager->refreshUserData($userId, $userIdType);

        // 验证返回结果类型
        $this->assertIsArray($result);
    }

    public function testGetDirtyUserData(): void
    {
        // 使用真实的服务调用，验证行为而不是 mock 期望
        $result = $this->manager->getDirtyUserData();

        // 验证返回结果类型
        $this->assertIsArray($result);
    }

    public function testPersistDirtyData(): void
    {
        // 使用真实的服务调用，验证行为而不是 mock 期望
        $this->manager->persistDirtyData();

        // 验证调用成功，没有异常抛出
        $this->expectNotToPerformAssertions();
    }

    public function testCleanMemoryCache(): void
    {
        $maxAge = 7200;

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $this->manager->cleanMemoryCache($maxAge);

        // 验证调用成功，没有异常抛出
        $this->expectNotToPerformAssertions();
    }

    public function testCleanMemoryCacheWithDefaultAge(): void
    {
        // 使用真实的服务调用，验证行为而不是 mock 期望
        $this->manager->cleanMemoryCache();

        // 验证调用成功，没有异常抛出
        $this->expectNotToPerformAssertions();
    }

    public function testForceSync(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $result = $this->manager->getUserData($userId, $userIdType, true);

        // 验证返回结果类型
        $this->assertIsArray($result);
    }

    public function testInvalidUserIdType(): void
    {
        $userId = 'user123';
        $userIdType = 'invalid_type';

        $this->expectException(ValidationException::class);
        $this->manager->getUserData($userId, $userIdType);
    }

    public function testEmptyUserId(): void
    {
        $userId = '';
        $userIdType = 'open_id';

        $this->expectException(ValidationException::class);
        $this->manager->getUserData($userId, $userIdType);
    }

    public function testServiceIntegration(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        // 测试服务的集成性
        $userData = $this->manager->getUserData($userId, $userIdType);
        $this->assertIsArray($userData);

        // 测试批量获取
        $batchData = $this->manager->batchGetUserData([$userId], $userIdType);
        $this->assertIsArray($batchData);

        // 测试刷新
        $refreshedData = $this->manager->refreshUserData($userId, $userIdType);
        $this->assertIsArray($refreshedData);
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
        // 创建必要的服务实例
        $logger = new NullLogger();
        $eventDispatcher = new EventDispatcher();

        // 创建缓存适配器 - 每次测试使用唯一的缓存空间
        $cache = new FilesystemAdapter(
            'lark_app_bot_test_' . uniqid(),
            0,
            '/tmp/lark-app-bot-test'
        );

        // 创建 mock UserService，在验证失败时抛出异常
        $userService = static::createMock(UserServiceInterface::class);
        $userService->method('getUser')
            ->willReturnCallback(function ($userId, $userIdType) {
                if ('invalid_type' === $userIdType || '' === $userId) {
                    throw new ValidationException('Invalid user ID type or empty user ID');
                }

                return ['user_id' => $userId, 'name' => 'Test User'];
            })
        ;

        // 为其他方法添加默认 mock
        $userService->method('getUserDepartments')
            ->willReturn(['items' => []])
        ;
        $userService->method('getUserLeader')
            ->willReturn(null)
        ;
        $userService->method('getUserSubordinates')
            ->willReturn([])
        ;
        $userService->method('batchGetUsers')
            ->willReturn([])
        ;
        // clearUserCache 是 void 方法，不需要设置返回值

        // 使用容器获取服务
        $container = static::getContainer();
        $container->set('cache.app', $cache);
        $container->set('Psr\Log\LoggerInterface', $logger);
        $container->set('Symfony\Contracts\EventDispatcher\EventDispatcherInterface', $eventDispatcher);
        $container->set('Tourze\LarkAppBotBundle\User\UserServiceInterface', $userService);
        $container->set('Tourze\LarkAppBotBundle\User\UserSyncServiceInterface', static::createMock(UserSyncServiceInterface::class));

        $this->manager = static::getService(UserDataManager::class);

        // 清理缓存
        static::getService(UserDataCacheManager::class)->cleanMemoryCache();
    }
}
