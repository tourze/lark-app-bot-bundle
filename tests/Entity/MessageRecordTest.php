<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Entity\MessageRecord;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(MessageRecord::class)]
final class MessageRecordTest extends AbstractEntityTestCase
{
    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['messageId', 'msg_123456'],
            ['chatId', 'chat_789012'],
            ['chatType', 'group'],
            ['senderId', 'user_abc123'],
            ['senderType', 'bot'],
            ['messageType', 'card'],
            ['content', ['text' => 'Test message', 'mentions' => []]],
        ];
    }

    public function testMessageRecordCanBeCreatedWithDefaultValues(): void
    {
        $messageRecord = new MessageRecord();

        self::assertNull($messageRecord->getId());
        self::assertSame('', $messageRecord->getMessageId());
        self::assertSame('', $messageRecord->getChatId());
        self::assertSame('p2p', $messageRecord->getChatType());
        self::assertSame('', $messageRecord->getSenderId());
        self::assertSame('user', $messageRecord->getSenderType());
        self::assertSame('text', $messageRecord->getMessageType());
        self::assertSame([], $messageRecord->getContent());
    }

    public function testMessageRecordSettersAndGetters(): void
    {
        $messageRecord = new MessageRecord();
        $content = ['text' => 'Hello World', 'mentions' => []];

        $messageRecord->setMessageId('msg_12345');
        $messageRecord->setChatId('chat_67890');
        $messageRecord->setChatType('group');
        $messageRecord->setSenderId('user_abc');
        $messageRecord->setSenderType('bot');
        $messageRecord->setMessageType('card');
        $messageRecord->setContent($content);

        self::assertSame('msg_12345', $messageRecord->getMessageId());
        self::assertSame('chat_67890', $messageRecord->getChatId());
        self::assertSame('group', $messageRecord->getChatType());
        self::assertSame('user_abc', $messageRecord->getSenderId());
        self::assertSame('bot', $messageRecord->getSenderType());
        self::assertSame('card', $messageRecord->getMessageType());
        self::assertSame($content, $messageRecord->getContent());
    }

    public function testIsFromBotMethod(): void
    {
        $messageRecord = new MessageRecord();

        $messageRecord->setSenderType('user');
        self::assertFalse($messageRecord->isFromBot());

        $messageRecord->setSenderType('bot');
        self::assertTrue($messageRecord->isFromBot());
    }

    public function testIsGroupMessageMethod(): void
    {
        $messageRecord = new MessageRecord();

        $messageRecord->setChatType('p2p');
        self::assertFalse($messageRecord->isGroupMessage());

        $messageRecord->setChatType('group');
        self::assertTrue($messageRecord->isGroupMessage());
    }

    public function testToStringMethod(): void
    {
        $messageRecord = new MessageRecord();
        $messageRecord->setMessageId('msg_test');
        $messageRecord->setMessageType('text');

        self::assertSame('消息 #msg_test [text]', (string) $messageRecord);
    }

    protected function createEntity(): object
    {
        return new MessageRecord();
    }
}
