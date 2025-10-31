<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\ExternalCollaboration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\LarkAppBotBundle\Service\Client\LarkClient;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\ExternalInvitationManager;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\SecurityPolicy;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ExternalInvitationManager::class)]
#[RunTestsInSeparateProcesses]
final class ExternalInvitationManagerTest extends AbstractIntegrationTestCase
{
    private ExternalInvitationManager $manager;

    private CacheItemPoolInterface $cache;

    private EventDispatcherInterface $eventDispatcher;

    private SecurityPolicy $securityPolicy;

    public function testConstants(): void
    {
        $this->assertSame('pending', ExternalInvitationManager::STATUS_PENDING);
        $this->assertSame('approved', ExternalInvitationManager::STATUS_APPROVED);
        $this->assertSame('rejected', ExternalInvitationManager::STATUS_REJECTED);
        $this->assertSame('expired', ExternalInvitationManager::STATUS_EXPIRED);
        $this->assertSame('revoked', ExternalInvitationManager::STATUS_REVOKED);
    }

    public function testCreateInvitation(): void
    {
        $invitationData = [
            'invitee_email' => 'external@example.com',
            'inviter_id' => 'user123',
            'group_id' => 'group456',
            'message' => 'Business collaboration',
            'has_approval' => false];

        $this->securityPolicy
            ->expects($this->once())
            ->method('checkPolicy')
            ->with(
                SecurityPolicy::POLICY_DATA_ACCESS,
                self::callback(function ($data) {
                    return isset($data['inviter_id']) && 'user123' === $data['inviter_id']
                        && isset($data['has_approval']) && false === $data['has_approval'];
                })
            )
            ->willReturn(true)
        ;

        $cacheItem = $this->createMock(CacheItemInterface::class);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $this->cache
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem)
            ->willReturn(true)
        ;

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
        ;

        $result = $this->manager->createInvitation($invitationData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertSame(ExternalInvitationManager::STATUS_PENDING, $result['status']);
        $this->assertTrue($result['security_check_passed']);
    }

    public function testCreateInvitationSecurityPolicyFails(): void
    {
        $invitationData = [
            'invitee_email' => 'external@example.com',
            'inviter_id' => 'user123',
            'has_approval' => false];

        $this->securityPolicy
            ->expects($this->once())
            ->method('checkPolicy')
            ->with(
                SecurityPolicy::POLICY_DATA_ACCESS,
                self::callback(function ($data) {
                    return isset($data['inviter_id']) && 'user123' === $data['inviter_id']
                        && isset($data['has_approval']) && false === $data['has_approval'];
                })
            )
            ->willReturn(false)
        ;

        $cacheItem = $this->createMock(CacheItemInterface::class);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $this->cache
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem)
            ->willReturn(true)
        ;

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
        ;

        $result = $this->manager->createInvitation($invitationData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertSame(ExternalInvitationManager::STATUS_REJECTED, $result['status']);
        $this->assertSame('security_policy_violation', $result['rejection_reason']);
        $this->assertFalse($result['security_check_passed']);
    }

    public function testApproveInvitation(): void
    {
        $invitation = [
            'id' => 'inv_123',
            'status' => ExternalInvitationManager::STATUS_PENDING,
            'expires_at' => time() + 3600,
            'invitee_email' => 'external@example.com',
            'group_id' => 'group456',
            'permissions' => ['read', 'write']];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($invitation);

        $this->cache
            ->expects($this->atLeastOnce())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $this->cache
            ->expects($this->atLeastOnce())
            ->method('save')
            ->willReturn(true)
        ;

        $this->cache
            ->expects($this->once())
            ->method('deleteItem')
        ;

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
        ;

        $result = $this->manager->approveInvitation('inv_123', 'approver123');

        $this->assertTrue($result);
    }

    public function testApproveInvitationNotFound(): void
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->manager->approveInvitation('nonexistent_id', 'approver123');

        $this->assertFalse($result);
    }

    public function testApproveInvitationNotPending(): void
    {
        $invitation = [
            'id' => 'inv_123',
            'status' => ExternalInvitationManager::STATUS_APPROVED,
            'expires_at' => time() + 3600];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($invitation);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->manager->approveInvitation('inv_123', 'approver123');

        $this->assertFalse($result);
    }

    public function testRejectInvitation(): void
    {
        $invitation = [
            'id' => 'inv_123',
            'status' => ExternalInvitationManager::STATUS_PENDING,
            'expires_at' => time() + 3600];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($invitation);

        $this->cache
            ->expects($this->atLeastOnce())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $this->cache
            ->expects($this->atLeastOnce())
            ->method('save')
            ->willReturn(true)
        ;

        $this->cache
            ->expects($this->once())
            ->method('deleteItem')
        ;

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
        ;

        $result = $this->manager->rejectInvitation('inv_123', 'rejector123', 'security_concern');

        $this->assertTrue($result);
    }

    public function testRejectInvitationNotFound(): void
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->manager->rejectInvitation('nonexistent_id', 'rejector123', 'not_found');

        $this->assertFalse($result);
    }

    public function testRejectInvitationNotPending(): void
    {
        $invitation = [
            'id' => 'inv_123',
            'status' => ExternalInvitationManager::STATUS_APPROVED,
            'expires_at' => time() + 3600];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($invitation);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->manager->rejectInvitation('inv_123', 'rejector123', 'too_late');

        $this->assertFalse($result);
    }

    public function testRevokeInvitation(): void
    {
        $invitation = [
            'id' => 'inv_123',
            'status' => ExternalInvitationManager::STATUS_PENDING,
            'expires_at' => time() + 3600];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($invitation);

        $this->cache
            ->expects($this->atLeastOnce())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $this->cache
            ->expects($this->atLeastOnce())
            ->method('save')
            ->willReturn(true)
        ;

        $this->cache
            ->expects($this->once())
            ->method('deleteItem')
        ;

        $result = $this->manager->revokeInvitation('inv_123', 'revoker123');

        $this->assertTrue($result);
    }

    public function testRevokeInvitationNotFound(): void
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->manager->revokeInvitation('nonexistent_id', 'revoker123');

        $this->assertFalse($result);
    }

    public function testRevokeInvitationAlreadyApproved(): void
    {
        $invitation = [
            'id' => 'inv_123',
            'status' => ExternalInvitationManager::STATUS_APPROVED,
            'expires_at' => time() + 3600];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($invitation);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->manager->revokeInvitation('inv_123', 'revoker123');

        $this->assertFalse($result);
    }

    public function testRevokeInvitationAlreadyExpired(): void
    {
        $invitation = [
            'id' => 'inv_123',
            'status' => ExternalInvitationManager::STATUS_EXPIRED,
            'expires_at' => time() - 3600];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($invitation);

        $this->cache
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->manager->revokeInvitation('inv_123', 'revoker123');

        $this->assertFalse($result);
    }

    public function testRevokeInvitationRejectedStatus(): void
    {
        $invitation = [
            'id' => 'inv_123',
            'status' => ExternalInvitationManager::STATUS_REJECTED,
            'expires_at' => time() + 3600];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($invitation);

        $this->cache
            ->expects($this->atLeastOnce())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $this->cache
            ->expects($this->atLeastOnce())
            ->method('save')
            ->willReturn(true)
        ;

        $this->cache
            ->expects($this->once())
            ->method('deleteItem')
        ;

        $result = $this->manager->revokeInvitation('inv_123', 'revoker123');

        $this->assertTrue($result);
    }

    protected function onSetUp(): void
    {
        // 从容器获取 ExternalInvitationManager 服务
        /** @var ExternalInvitationManager $manager */
        $manager = self::getContainer()->get(ExternalInvitationManager::class);
        $this->manager = $manager;

        // 创建 mock 对象用于测试
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->securityPolicy = $this->createMock(SecurityPolicy::class);

        $mockClient = $this->createMock(LarkClient::class);
        $mockLogger = new NullLogger();

        // 通过反射替换依赖为 Mock 版本
        $reflection = new \ReflectionClass($this->manager);

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->manager, $mockClient);

        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue($this->manager, $this->cache);

        $eventDispatcherProperty = $reflection->getProperty('eventDispatcher');
        $eventDispatcherProperty->setAccessible(true);
        $eventDispatcherProperty->setValue($this->manager, $this->eventDispatcher);

        $securityPolicyProperty = $reflection->getProperty('securityPolicy');
        $securityPolicyProperty->setAccessible(true);
        $securityPolicyProperty->setValue($this->manager, $this->securityPolicy);

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->manager, $mockLogger);
    }
}
