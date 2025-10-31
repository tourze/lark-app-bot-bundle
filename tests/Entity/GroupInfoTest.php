<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Entity\GroupInfo;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(GroupInfo::class)]
final class GroupInfoTest extends AbstractEntityTestCase
{
    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['chatId', 'chat_123456'],
            ['name', 'Test Group'],
            ['description', 'Test group description'],
            ['ownerId', 'owner_123'],
            ['memberCount', 10],
            ['botCount', 2],
            ['chatType', 'group'],
            ['external', true],
        ];
    }

    public function testGroupInfoCanBeCreatedWithDefaultValues(): void
    {
        $groupInfo = new GroupInfo();

        self::assertNull($groupInfo->getId());
        self::assertSame('', $groupInfo->getChatId());
        self::assertSame('', $groupInfo->getName());
        self::assertNull($groupInfo->getDescription());
        self::assertNull($groupInfo->getOwnerId());
        self::assertSame(0, $groupInfo->getMemberCount());
        self::assertSame(0, $groupInfo->getBotCount());
        self::assertNull($groupInfo->getChatType());
        self::assertFalse($groupInfo->isExternal());
    }

    public function testGroupInfoSettersAndGetters(): void
    {
        $groupInfo = new GroupInfo();

        $groupInfo->setChatId('chat_test123');
        $groupInfo->setName('Engineering Team');
        $groupInfo->setDescription('Engineering team group');
        $groupInfo->setOwnerId('owner_abc');
        $groupInfo->setMemberCount(25);
        $groupInfo->setBotCount(3);
        $groupInfo->setChatType('private_chat');
        $groupInfo->setExternal(true);

        self::assertSame('chat_test123', $groupInfo->getChatId());
        self::assertSame('Engineering Team', $groupInfo->getName());
        self::assertSame('Engineering team group', $groupInfo->getDescription());
        self::assertSame('owner_abc', $groupInfo->getOwnerId());
        self::assertSame(25, $groupInfo->getMemberCount());
        self::assertSame(3, $groupInfo->getBotCount());
        self::assertSame('private_chat', $groupInfo->getChatType());
        self::assertTrue($groupInfo->isExternal());
    }

    public function testSetNameWithNullValue(): void
    {
        $groupInfo = new GroupInfo();
        $groupInfo->setName('Initial Name');

        $groupInfo->setName(null);

        self::assertSame('', $groupInfo->getName());
    }

    public function testToStringMethod(): void
    {
        $groupInfo = new GroupInfo();
        $groupInfo->setName('Test Group');
        $groupInfo->setChatId('chat_12345');

        self::assertSame('群组 Test Group (chat_12345)', (string) $groupInfo);
    }

    public function testDescriptionCanBeNull(): void
    {
        $groupInfo = new GroupInfo();
        $groupInfo->setDescription(null);

        self::assertNull($groupInfo->getDescription());
    }

    public function testOwnerIdCanBeNull(): void
    {
        $groupInfo = new GroupInfo();
        $groupInfo->setOwnerId(null);

        self::assertNull($groupInfo->getOwnerId());
    }

    public function testChatTypeCanBeNull(): void
    {
        $groupInfo = new GroupInfo();
        $groupInfo->setChatType(null);

        self::assertNull($groupInfo->getChatType());
    }

    public function testExternalFlagToggle(): void
    {
        $groupInfo = new GroupInfo();

        // Default is false
        self::assertFalse($groupInfo->isExternal());

        $groupInfo->setExternal(true);
        self::assertTrue($groupInfo->isExternal());

        $groupInfo->setExternal(false);
        self::assertFalse($groupInfo->isExternal());
    }

    public function testMemberAndBotCounts(): void
    {
        $groupInfo = new GroupInfo();

        $groupInfo->setMemberCount(100);
        $groupInfo->setBotCount(5);

        self::assertSame(100, $groupInfo->getMemberCount());
        self::assertSame(5, $groupInfo->getBotCount());
    }

    protected function createEntity(): object
    {
        return new GroupInfo();
    }
}
