<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(MessageEvent::class)]
final class MessageEventTest extends AbstractEventTestCase
{
    public function testMessageEventCreation(): void
    {
        $eventData = [
            'message_id' => 'msg_123',
            'root_id' => 'root_123',
            'parent_id' => 'parent_123',
            'chat_id' => 'chat_123',
            'chat_type' => 'group',
            'message_type' => 'text',
            'content' => '{"text": "Hello World"}',
            'sender' => [
                'sender_id' => [
                    'union_id' => 'on_123',
                    'user_id' => 'user_123',
                    'open_id' => 'ou_123'],
                'sender_type' => 'user',
                'tenant_key' => 'tenant_123'],
            'mentions' => [
                [
                    'id' => ['open_id' => 'ou_456'],
                    'name' => 'Test User',
                    'tenant_key' => 'tenant_123']],
            'create_time' => '1609073151'];

        $context = [
            'event_id' => 'event_123',
            'tenant_key' => 'tenant_123',
            'app_id' => 'app_123'];

        $event = new MessageEvent('message', $eventData, $context);

        $this->assertSame('message', $event->getEventType());
        $this->assertSame($eventData, $event->getData());
        $this->assertSame($context, $event->getContext());
    }

    public function testGetMessageId(): void
    {
        $eventData = ['message_id' => 'msg_123'];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('msg_123', $event->getMessageId());
    }

    public function testGetMessageIdWithMissingData(): void
    {
        $event = new MessageEvent('message', []);

        $this->assertSame('', $event->getMessageId());
    }

    public function testGetRootId(): void
    {
        $eventData = ['root_id' => 'root_123'];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('root_123', $event->getRootId());
    }

    public function testGetParentId(): void
    {
        $eventData = ['parent_id' => 'parent_123'];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('parent_123', $event->getParentId());
    }

    public function testGetChatId(): void
    {
        $eventData = ['chat_id' => 'chat_123'];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('chat_123', $event->getChatId());
    }

    public function testGetChatType(): void
    {
        $eventData = ['chat_type' => 'group'];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('group', $event->getChatType());
    }

    public function testGetMessageType(): void
    {
        $eventData = ['message_type' => 'text'];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('text', $event->getMessageType());
    }

    public function testGetContent(): void
    {
        $eventData = ['content' => '{"text": "Hello World"}'];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('{"text": "Hello World"}', $event->getContent());
    }

    public function testGetMentions(): void
    {
        $mentions = [
            [
                'id' => ['open_id' => 'ou_456'],
                'name' => 'Test User',
                'tenant_key' => 'tenant_123']];
        $eventData = ['mentions' => $mentions];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame($mentions, $event->getMentions());
    }

    public function testGetSender(): void
    {
        $sender = [
            'sender_id' => [
                'union_id' => 'on_123',
                'user_id' => 'user_123',
                'open_id' => 'ou_123'],
            'sender_type' => 'user',
            'tenant_key' => 'tenant_123'];
        $eventData = ['sender' => $sender];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame($sender, $event->getSender());
    }

    public function testGetSenderId(): void
    {
        $eventData = [
            'sender' => [
                'sender_id' => [
                    'open_id' => 'ou_123']]];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('ou_123', $event->getSenderId());
    }

    public function testGetSenderIdWithMissingData(): void
    {
        $event = new MessageEvent('message', []);

        $this->assertSame('', $event->getSenderId());
    }

    public function testGetSenderType(): void
    {
        $eventData = [
            'sender' => [
                'sender_type' => 'bot']];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('bot', $event->getSenderType());
    }

    public function testGetSenderTypeDefault(): void
    {
        $event = new MessageEvent('message', []);

        $this->assertSame('user', $event->getSenderType());
    }

    public function testIsGroupMessage(): void
    {
        $groupEvent = new MessageEvent('message', ['chat_type' => 'group']);
        $this->assertTrue($groupEvent->isGroupMessage());

        $superGroupEvent = new MessageEvent('message', ['chat_type' => 'supergroup']);
        $this->assertTrue($superGroupEvent->isGroupMessage());

        $privateEvent = new MessageEvent('message', ['chat_type' => 'p2p']);
        $this->assertFalse($privateEvent->isGroupMessage());
    }

    public function testIsPrivateMessage(): void
    {
        $privateEvent = new MessageEvent('message', ['chat_type' => 'p2p']);
        $this->assertTrue($privateEvent->isPrivateMessage());

        $groupEvent = new MessageEvent('message', ['chat_type' => 'group']);
        $this->assertFalse($groupEvent->isPrivateMessage());
    }

    public function testIsMentionedBot(): void
    {
        $mentionsData = [
            'mentions' => [
                [
                    'id' => ['open_id' => '@_all'],
                    'name' => 'All']]];
        $mentionedEvent = new MessageEvent('message', $mentionsData);
        $this->assertTrue($mentionedEvent->isMentionedBot());

        $noMentionsEvent = new MessageEvent('message', []);
        $this->assertFalse($noMentionsEvent->isMentionedBot());

        $otherMentionsEvent = new MessageEvent('message', [
            'mentions' => [
                [
                    'id' => ['open_id' => 'ou_123'],
                    'name' => 'User']]]);
        $this->assertFalse($otherMentionsEvent->isMentionedBot());
    }

    public function testGetPlainTextWithTextMessage(): void
    {
        $eventData = [
            'message_type' => 'text',
            'content' => '{"text": "Hello World"}'];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('Hello World', $event->getPlainText());
    }

    public function testGetPlainTextWithNonTextMessage(): void
    {
        $eventData = [
            'message_type' => 'image',
            'content' => '{"image_key": "img_123"}'];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('', $event->getPlainText());
    }

    public function testGetPlainTextWithInvalidJson(): void
    {
        $eventData = [
            'message_type' => 'text',
            'content' => 'invalid json'];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('invalid json', $event->getPlainText());
    }

    public function testGetPlainTextWithMissingTextKey(): void
    {
        $eventData = [
            'message_type' => 'text',
            'content' => '{"other": "value"}'];
        $event = new MessageEvent('message', $eventData);

        $this->assertSame('', $event->getPlainText());
    }
}
