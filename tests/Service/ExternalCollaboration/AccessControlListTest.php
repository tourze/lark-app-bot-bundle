<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\ExternalCollaboration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\AccessControlList;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AccessControlList::class)]
#[RunTestsInSeparateProcesses]
final class AccessControlListTest extends AbstractIntegrationTestCase
{
    private AccessControlList $acl;

    private CacheItemPoolInterface $cache;

    /** @var array<string, mixed> */
    private array $cacheStorage;

    public function testAddRule(): void
    {
        // 测试添加规则不抛出异常即可
        $this->acl->addRule(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'user_123',
            AccessControlList::TYPE_ALLOW
        );

        $this->expectNotToPerformAssertions();
    }

    public function testCheckAccessWithNoRules(): void
    {
        $result = $this->acl->checkAccess(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'user_123'
        );

        // Default policy for FILE is deny
        $this->assertFalse($result);
    }

    public function testCheckAccessWithAllowRule(): void
    {
        // First add a rule
        $this->acl->addRule(
            AccessControlList::RESOURCE_CHAT,
            'chat_001',
            'user_123',
            AccessControlList::TYPE_ALLOW
        );

        $result = $this->acl->checkAccess(
            AccessControlList::RESOURCE_CHAT,
            'chat_001',
            'user_123'
        );

        $this->assertTrue($result);
    }

    public function testCheckAccessWithDenyRule(): void
    {
        $this->acl->addRule(
            AccessControlList::RESOURCE_API,
            'api_001',
            'user_123',
            AccessControlList::TYPE_DENY
        );

        $result = $this->acl->checkAccess(
            AccessControlList::RESOURCE_API,
            'api_001',
            'user_123'
        );

        $this->assertFalse($result);
    }

    public function testDenyOverridesAllow(): void
    {
        // Add allow rule
        $this->acl->addRule(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'user_123',
            AccessControlList::TYPE_ALLOW
        );

        // Add deny rule for same resource
        $this->acl->addRule(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'user_123',
            AccessControlList::TYPE_DENY
        );

        $result = $this->acl->checkAccess(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'user_123'
        );

        // Deny should override allow
        $this->assertFalse($result);
    }

    public function testCheckAccessWithRoleMatching(): void
    {
        $this->acl->addRule(
            AccessControlList::RESOURCE_FEATURE,
            'feature_001',
            'role:admin',
            AccessControlList::TYPE_ALLOW
        );

        $context = ['roles' => ['admin', 'user']];

        $result = $this->acl->checkAccess(
            AccessControlList::RESOURCE_FEATURE,
            'feature_001',
            'user_123',
            $context
        );

        $this->assertTrue($result);
    }

    public function testCheckAccessWithExternalUserMatching(): void
    {
        $this->acl->addRule(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'external:*',
            AccessControlList::TYPE_DENY
        );

        $context = ['is_external' => true];

        $result = $this->acl->checkAccess(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'ou_external_123',
            $context
        );

        $this->assertFalse($result);
    }

    public function testRemoveRule(): void
    {
        // First add a rule
        $this->acl->addRule(
            AccessControlList::RESOURCE_CHAT,
            'chat_001',
            'user_123',
            AccessControlList::TYPE_ALLOW
        );

        // Remove the rule
        $this->acl->removeRule(
            AccessControlList::RESOURCE_CHAT,
            'chat_001',
            'user_123'
        );

        $result = $this->acl->checkAccess(
            AccessControlList::RESOURCE_CHAT,
            'chat_001',
            'user_123'
        );

        // Should return default policy for CHAT (allow)
        $this->assertTrue($result);
    }

    public function testClearRules(): void
    {
        // Add multiple rules for same resource
        $this->acl->addRule(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'user_123',
            AccessControlList::TYPE_ALLOW
        );
        $this->acl->addRule(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'user_456',
            AccessControlList::TYPE_DENY
        );

        // Verify rules exist by checking access
        $this->assertTrue($this->acl->checkAccess(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'user_123'
        ));

        // Clear all rules for the resource
        $this->acl->clearRules(
            AccessControlList::RESOURCE_FILE,
            'file_001'
        );

        // After clearing, should return default policy for FILE (deny)
        $this->assertFalse($this->acl->checkAccess(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'user_123'
        ));
        $this->assertFalse($this->acl->checkAccess(
            AccessControlList::RESOURCE_FILE,
            'file_001',
            'user_456'
        ));
    }

    public function testClearRulesForNonExistentResource(): void
    {
        // Clear rules for resource that has no rules
        $this->acl->clearRules(
            AccessControlList::RESOURCE_API,
            'api_nonexistent'
        );

        // Should not cause any issues
        $this->expectNotToPerformAssertions();
    }

    protected function onSetUp(): void
    {
        // 从容器获取 AccessControlList 服务
        $acl = self::getContainer()->get(AccessControlList::class);
        if (!$acl instanceof AccessControlList) {
            throw new \RuntimeException('Failed to get AccessControlList service');
        }
        $this->acl = $acl;

        // 创建 mock 缓存用于测试
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cacheStorage = [];

        // Mock cache behavior
        $this->cache->method('getItem')->willReturnCallback(function ($key) {
            $cacheItem = $this->createMock(CacheItemInterface::class);
            $cacheItem->method('isHit')->willReturn(isset($this->cacheStorage[$key]));
            $cacheItem->method('get')->willReturn($this->cacheStorage[$key] ?? null);
            $cacheItem->method('set')->willReturnCallback(function ($value) use ($key) {
                $this->cacheStorage[$key] = $value;

                return $this->createMock(CacheItemInterface::class);
            });

            return $cacheItem;
        });

        $this->cache->method('save')->willReturn(true);

        // 通过反射替换缓存为 Mock 版本
        $reflection = new \ReflectionClass($this->acl);
        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue($this->acl, $this->cache);
    }
}
