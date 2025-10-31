<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\ExternalCollaboration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClient;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\ExternalGroupManager;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\ExternalUserIdentifier;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ExternalGroupManager::class)]
#[RunTestsInSeparateProcesses]
final class ExternalGroupManagerTest extends AbstractIntegrationTestCase
{
    private ExternalGroupManager $groupManager;

    private LarkClient $client;

    private ExternalUserIdentifier $userIdentifier;

    private CacheItemPoolInterface $cache;

    private LoggerInterface $logger;

    public function testGetExternalGroupInfoForInternalGroup(): void
    {
        $chatId = 'oc_123';

        $this->userIdentifier->expects($this->once())
            ->method('isExternalGroup')
            ->with($chatId)
            ->willReturn(false)
        ;

        $result = $this->groupManager->getExternalGroupInfo($chatId);

        $this->assertNull($result);
    }

    public function testGetExternalGroupInfoWithApiError(): void
    {
        $chatId = 'oc_external_123';

        $this->userIdentifier->expects($this->once())
            ->method('isExternalGroup')
            ->willReturn(true)
        ;

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false)
        ;

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $this->client->expects($this->once())
            ->method('request')
            ->willThrowException(new GenericApiException('API Error'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to get external group info', self::arrayHasKey('error'))
        ;

        $result = $this->groupManager->getExternalGroupInfo($chatId);

        $this->assertNull($result);
    }

    public function testHasExternalMembers(): void
    {
        $chatId = 'oc_123';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true)
        ;
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn([
                ['user_id' => 'ou_external_user1', 'name' => 'External User'],
            ])
        ;

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->groupManager->hasExternalMembers($chatId);

        $this->assertTrue($result);
    }

    public function testUpdateExternalGroupSettings(): void
    {
        $chatId = 'oc_external_123';
        $settings = [
            'allow_file_sharing' => true,
            'message_retention_days' => 180,
        ];

        $this->userIdentifier->expects($this->exactly(2))
            ->method('isExternalGroup')
            ->with($chatId)
            ->willReturn(true)
        ;

        $groupInfo = [
            'chat_id' => $chatId,
            'name' => 'External Group',
            'is_external' => true,
            'security_settings' => [
                'allow_file_sharing' => false,
                'allow_screen_capture' => false,
            ],
        ];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true)
        ;
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($groupInfo)
        ;

        $this->cache->expects($this->exactly(2))
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $cacheItem->expects($this->once())
            ->method('set')
        ;
        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
        ;
        $this->cache->expects($this->once())
            ->method('save')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('External group settings updated', self::arrayHasKey('settings'))
        ;

        $result = $this->groupManager->updateExternalGroupSettings($chatId, $settings);

        $this->assertTrue($result);
    }

    public function testUpdateExternalGroupSettingsForInternalGroup(): void
    {
        $chatId = 'oc_123';

        $this->userIdentifier->expects($this->once())
            ->method('isExternalGroup')
            ->with($chatId)
            ->willReturn(false)
        ;

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Attempted to update settings for non-external group')
        ;

        $result = $this->groupManager->updateExternalGroupSettings($chatId, []);

        $this->assertFalse($result);
    }

    public function _testGetUserExternalGroups(): void
    {
        $userId = 'ou_123';

        $chats = [
            [
                'chat_id' => 'oc_group1',
                'name' => 'Group 1',
                'chat_type' => 'group',
                'member_count' => 10,
            ],
            [
                'chat_id' => 'oc_group2',
                'name' => 'Group 2',
                'chat_type' => 'group',
                'member_count' => 5,
            ],
        ];

        $this->client->expects($this->once())
            ->method('request')
            ->with('GET', '/open-apis/im/v1/users/' . $userId . '/chats')
            ->willReturn(['data' => ['items' => $chats]])
        ;

        // Mock hasExternalMembers to return true for first group only
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->any())
            ->method('isHit')
            ->willReturn(true)
        ;
        $cacheItem->expects($this->any())
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                [['user_id' => 'ou_external_user']], // First group has external members
                [] // Second group has no external members
            )
        ;

        $this->cache->expects($this->any())
            ->method('getItem')
            ->willReturn($cacheItem)
        ;

        $result = $this->groupManager->getUserExternalGroups($userId);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('oc_group1', $result[0]['chat_id']);
        $this->assertArrayHasKey('external_member_count', $result[0]);
    }

    public function testClearGroupCache(): void
    {
        $chatId = 'oc_external_123';

        $this->cache->expects($this->exactly(2))
            ->method('deleteItem')
            ->willReturnCallback(function (string $key) {
                static $callCount = 0;
                $expectedKeys = [
                    'external_group:oc_external_123',
                    'external_members:oc_external_123',
                ];
                $this->assertSame($expectedKeys[$callCount], $key);
                ++$callCount;

                return true;
            })
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('External group cache cleared', ['chat_id' => $chatId])
        ;

        $this->groupManager->clearGroupCache($chatId);
    }

    protected function onSetUp(): void
    {
        // 从容器获取 ExternalGroupManager 服务
        $groupManager = self::getContainer()->get(ExternalGroupManager::class);
        self::assertInstanceOf(ExternalGroupManager::class, $groupManager);
        $this->groupManager = $groupManager;

        // 创建 mock 对象用于测试
        $this->client = $this->createMock(LarkClient::class);
        $this->userIdentifier = $this->createMock(ExternalUserIdentifier::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // 通过反射替换依赖为 Mock 版本
        $reflection = new \ReflectionClass($this->groupManager);

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->groupManager, $this->client);

        $userIdentifierProperty = $reflection->getProperty('userIdentifier');
        $userIdentifierProperty->setAccessible(true);
        $userIdentifierProperty->setValue($this->groupManager, $this->userIdentifier);

        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue($this->groupManager, $this->cache);

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->groupManager, $this->logger);
    }
}
