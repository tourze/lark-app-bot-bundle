<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\ExternalCollaboration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\ExternalUserIdentifier;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\PermissionIsolation;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(PermissionIsolation::class)]
#[RunTestsInSeparateProcesses]
final class PermissionIsolationTest extends AbstractIntegrationTestCase
{
    private PermissionIsolation $permissionIsolation;

    private ExternalUserIdentifier $userIdentifier;

    private CacheItemPoolInterface $cache;

    private LoggerInterface $logger;

    public function testCheckPermissionForInternalUser(): void
    {
        $userId = 'ou_123';
        $resource = PermissionIsolation::RESOURCE_MESSAGE;
        $requiredLevel = PermissionIsolation::LEVEL_WRITE;

        $this->userIdentifier->expects($this->once())
            ->method('isExternalUser')
            ->with($userId)
            ->willReturn(false)
        ;

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false)
        ;
        $cacheItem->expects($this->once())
            ->method('set')
            ->with(PermissionIsolation::LEVEL_WRITE)
        ;
        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
        ;

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('permission_ou_123_message')
            ->willReturn($cacheItem)
        ;
        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem)
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Permission check', self::arrayHasKey('has_permission'))
        ;

        $result = $this->permissionIsolation->checkPermission($userId, $resource, $requiredLevel);
        $this->assertTrue($result);
    }

    public function testCheckPermissionForExternalUser(): void
    {
        $userId = 'ou_external_123';
        $resource = PermissionIsolation::RESOURCE_FILE;
        $requiredLevel = PermissionIsolation::LEVEL_WRITE;

        $this->userIdentifier->expects($this->once())
            ->method('isExternalUser')
            ->with($userId)
            ->willReturn(true)
        ;

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false)
        ;
        $cacheItem->expects($this->once())
            ->method('set')
            ->with(PermissionIsolation::LEVEL_NONE)
        ;

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;
        $this->cache->expects($this->once())
            ->method('save')
        ;

        $result = $this->permissionIsolation->checkPermission($userId, $resource, $requiredLevel);
        $this->assertFalse($result);
    }

    public function testCheckPermissionWithCachedValue(): void
    {
        $userId = 'ou_123';
        $resource = PermissionIsolation::RESOURCE_API;
        $requiredLevel = PermissionIsolation::LEVEL_READ;

        $this->userIdentifier->expects($this->once())
            ->method('isExternalUser')
            ->willReturn(false)
        ;

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true)
        ;
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn(PermissionIsolation::LEVEL_WRITE)
        ;

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->permissionIsolation->checkPermission($userId, $resource, $requiredLevel);
        $this->assertTrue($result);
    }

    public function testSetPermission(): void
    {
        $userId = 'ou_123';
        $resource = PermissionIsolation::RESOURCE_MENU;
        $level = PermissionIsolation::LEVEL_ADMIN;

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('set')
            ->with($level)
        ;
        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
        ;

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('permission_ou_123_menu')
            ->willReturn($cacheItem)
        ;
        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem)
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Permission set', [
                'user_id' => $userId,
                'resource' => $resource,
                'level' => $level])
        ;

        $this->permissionIsolation->setPermission($userId, $resource, $level);
    }

    public function testSetPermissions(): void
    {
        $userId = 'ou_123';
        $permissions = [
            PermissionIsolation::RESOURCE_MESSAGE => PermissionIsolation::LEVEL_READ,
            PermissionIsolation::RESOURCE_FILE => PermissionIsolation::LEVEL_NONE];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $this->cache->expects($this->exactly(2))
            ->method('getItem')
            ->willReturn($cacheItem)
        ;
        $this->cache->expects($this->exactly(2))
            ->method('save')
        ;

        $this->permissionIsolation->setPermissions($userId, $permissions);
    }

    public function testGetUserPermissions(): void
    {
        $userId = 'ou_external_123';

        $this->userIdentifier->expects($this->once())
            ->method('isExternalUser')
            ->with($userId)
            ->willReturn(true)
        ;

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->any())
            ->method('isHit')
            ->willReturn(false)
        ;

        $this->cache->expects($this->any())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $permissions = $this->permissionIsolation->getUserPermissions($userId);

        $this->assertIsArray($permissions);
        $this->assertArrayHasKey(PermissionIsolation::RESOURCE_MESSAGE, $permissions);
        $this->assertSame(PermissionIsolation::LEVEL_READ, $permissions[PermissionIsolation::RESOURCE_MESSAGE]);
        $this->assertSame(PermissionIsolation::LEVEL_NONE, $permissions[PermissionIsolation::RESOURCE_FILE]);
    }

    public function testClearUserPermissions(): void
    {
        $userId = 'ou_123';

        $this->cache->expects($this->exactly(6)) // 6 种资源类型
            ->method('deleteItem')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('User permissions cleared', [
                'user_id' => $userId])
        ;

        $this->permissionIsolation->clearUserPermissions($userId);
    }

    public function testFilterAccessibleResources(): void
    {
        $userId = 'ou_123';
        $resources = [
            PermissionIsolation::RESOURCE_MESSAGE,
            PermissionIsolation::RESOURCE_FILE,
            PermissionIsolation::RESOURCE_API];
        $requiredLevel = PermissionIsolation::LEVEL_WRITE;

        $this->userIdentifier->expects($this->exactly(3))
            ->method('isExternalUser')
            ->willReturn(false)
        ;

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->any())
            ->method('isHit')
            ->willReturn(false)
        ;

        $this->cache->expects($this->any())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;
        $this->cache->expects($this->any())
            ->method('save')
        ;

        $accessible = $this->permissionIsolation->filterAccessibleResources($userId, $resources, $requiredLevel);

        $this->assertIsArray($accessible);
        $this->assertCount(3, $accessible); // 内部用户对所有资源都有写权限
    }

    public function testGetLevelName(): void
    {
        $this->assertSame('none', PermissionIsolation::getLevelName(PermissionIsolation::LEVEL_NONE));
        $this->assertSame('read', PermissionIsolation::getLevelName(PermissionIsolation::LEVEL_READ));
        $this->assertSame('write', PermissionIsolation::getLevelName(PermissionIsolation::LEVEL_WRITE));
        $this->assertSame('admin', PermissionIsolation::getLevelName(PermissionIsolation::LEVEL_ADMIN));
        $this->assertSame('unknown', PermissionIsolation::getLevelName(999));
    }

    public function testGetLevelFromName(): void
    {
        $this->assertSame(PermissionIsolation::LEVEL_NONE, PermissionIsolation::getLevelFromName('none'));
        $this->assertSame(PermissionIsolation::LEVEL_READ, PermissionIsolation::getLevelFromName('read'));
        $this->assertSame(PermissionIsolation::LEVEL_WRITE, PermissionIsolation::getLevelFromName('write'));
        $this->assertSame(PermissionIsolation::LEVEL_ADMIN, PermissionIsolation::getLevelFromName('admin'));
        $this->assertSame(PermissionIsolation::LEVEL_NONE, PermissionIsolation::getLevelFromName('invalid'));
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->userIdentifier = $this->createMock(ExternalUserIdentifier::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // 从容器获取 PermissionIsolation 服务
        /** @var PermissionIsolation $permissionIsolation */
        $permissionIsolation = self::getContainer()->get(PermissionIsolation::class);
        $this->permissionIsolation = $permissionIsolation;

        // 通过反射替换依赖为 Mock 版本
        $reflection = new \ReflectionClass($this->permissionIsolation);

        $userIdentifierProperty = $reflection->getProperty('userIdentifier');
        $userIdentifierProperty->setAccessible(true);
        $userIdentifierProperty->setValue($this->permissionIsolation, $this->userIdentifier);

        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue($this->permissionIsolation, $this->cache);

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->permissionIsolation, $this->logger);
    }
}
