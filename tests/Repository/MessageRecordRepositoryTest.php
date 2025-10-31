<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Entity\MessageRecord;
use Tourze\LarkAppBotBundle\Repository\MessageRecordRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(MessageRecordRepository::class)]
#[RunTestsInSeparateProcesses]
final class MessageRecordRepositoryTest extends AbstractRepositoryTestCase
{
    public function testSaveAndFindMessageRecordShouldWorkCorrectly(): void
    {
        $messageRecord = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $messageRecord);

        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $em->persist($messageRecord);
        $em->flush();

        $foundRecord = $repository->findByMessageId($messageRecord->getMessageId());
        self::assertNotNull($foundRecord);
        self::assertSame($messageRecord->getMessageId(), $foundRecord->getMessageId());
    }

    public function testFindByChatIdShouldReturnCorrectRecords(): void
    {
        $chatId = 'chat_test_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create two messages in the same chat
        $message1 = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $message1);
        $message1->setChatId($chatId);

        $message2 = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $message2);
        $message2->setChatId($chatId);

        $em->persist($message1);
        $em->persist($message2);
        $em->flush();

        $messages = $repository->findByChatId($chatId);
        $this->assertIsArray($messages);
        self::assertCount(2, $messages);
    }

    public function testFindBotMessagesShouldReturnOnlyBotMessages(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create a user message
        $userMessage = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $userMessage);
        $userMessage->setSenderType('user');

        // Create a bot message
        $botMessage = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $botMessage);
        $botMessage->setSenderType('bot');

        $em->persist($userMessage);
        $em->persist($botMessage);
        $em->flush();

        $botMessages = $repository->findBotMessages();
        self::assertNotEmpty($botMessages);

        foreach ($botMessages as $message) {
            self::assertSame('bot', $message->getSenderType());
        }
    }

    public function testFindGroupMessagesShouldReturnOnlyGroupMessages(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create a private message
        $privateMessage = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $privateMessage);
        $privateMessage->setChatType('p2p');

        // Create a group message
        $groupMessage = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $groupMessage);
        $groupMessage->setChatType('group');

        $em->persist($privateMessage);
        $em->persist($groupMessage);
        $em->flush();

        $groupMessages = $repository->findGroupMessages();
        self::assertNotEmpty($groupMessages);

        foreach ($groupMessages as $message) {
            self::assertSame('group', $message->getChatType());
        }
    }

    public function testCountMessagesByDateRangeShouldReturnCorrectCount(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $message = $this->createNewEntity();
        $em->persist($message);
        $em->flush();

        $startDate = new \DateTime('-1 day');
        $endDate = new \DateTime('+1 day');

        $count = $repository->countMessagesByDateRange($startDate, $endDate);
        self::assertGreaterThanOrEqual(1, $count);
    }

    public function testFindBySenderIdShouldReturnCorrectRecords(): void
    {
        $senderId = 'sender_test_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create two messages from the same sender
        $message1 = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $message1);
        $message1->setSenderId($senderId);

        $message2 = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $message2);
        $message2->setSenderId($senderId);

        $em->persist($message1);
        $em->persist($message2);
        $em->flush();

        $messages = $repository->findBySenderId($senderId);
        $this->assertIsArray($messages);
        self::assertCount(2, $messages);
        foreach ($messages as $message) {
            self::assertSame($senderId, $message->getSenderId());
        }
    }

    public function testFindByMessageTypeShouldReturnCorrectRecords(): void
    {
        $messageType = 'image';
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create a text message
        $textMessage = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $textMessage);
        $textMessage->setMessageType('text');

        // Create an image message
        $imageMessage = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $imageMessage);
        $imageMessage->setMessageType($messageType);

        $em->persist($textMessage);
        $em->persist($imageMessage);
        $em->flush();

        $imageMessages = $repository->findByMessageType($messageType);
        self::assertNotEmpty($imageMessages);
        foreach ($imageMessages as $message) {
            self::assertSame($messageType, $message->getMessageType());
        }
    }

    public function testFindByMessageIdShouldReturnCorrectRecord(): void
    {
        $messageId = 'msg_test_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $message = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $message);
        $message->setMessageId($messageId);

        $em->persist($message);
        $em->flush();

        $foundMessage = $repository->findByMessageId($messageId);
        self::assertNotNull($foundMessage);
        self::assertSame($messageId, $foundMessage->getMessageId());
    }

    public function testSaveMethodShouldPersistEntity(): void
    {
        $repository = $this->getRepository();
        $message = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $message);

        // Test save without flush
        $repository->save($message, false);

        // Entity should be persisted but not flushed yet
        $em = self::getEntityManager();
        self::assertTrue($em->contains($message));

        // Now flush and verify it's saved
        $em->flush();
        $foundMessage = $repository->findByMessageId($message->getMessageId());
        self::assertNotNull($foundMessage);
        self::assertSame($message->getId(), $foundMessage->getId());
    }

    public function testSaveMethodWithFlushShouldPersistAndFlushEntity(): void
    {
        $repository = $this->getRepository();
        $message = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $message);

        // Test save with flush (default behavior)
        $repository->save($message);

        // Entity should be immediately available
        $foundMessage = $repository->findByMessageId($message->getMessageId());
        self::assertNotNull($foundMessage);
        self::assertSame($message->getId(), $foundMessage->getId());
    }

    public function testRemoveMethodShouldDeleteEntity(): void
    {
        $repository = $this->getRepository();
        $message = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $message);

        // First save the entity
        $repository->save($message);
        $messageId = $message->getId();

        // Verify it exists
        $foundMessage = $repository->find($messageId);
        self::assertNotNull($foundMessage);

        // Now remove it
        $repository->remove($message);

        // Verify it's deleted
        $deletedMessage = $repository->find($messageId);
        self::assertNull($deletedMessage);
    }

    public function testRemoveMethodWithoutFlushShouldNotDeleteImmediately(): void
    {
        $repository = $this->getRepository();
        $message = $this->createNewEntity();
        self::assertInstanceOf(MessageRecord::class, $message);

        // First save the entity
        $repository->save($message);
        $messageId = $message->getId();

        // Remove without flush
        $repository->remove($message, false);

        // Entity should still be findable before flush
        $foundMessage = $repository->find($messageId);
        self::assertNotNull($foundMessage);

        // After flush, it should be deleted
        self::getEntityManager()->flush();
        $deletedMessage = $repository->find($messageId);
        self::assertNull($deletedMessage);
    }

    protected function onSetUp(): void
    {
        // No setup required - using self::getService() directly in tests
    }

    protected function createNewEntity(): object
    {
        $messageRecord = new MessageRecord();
        $messageRecord->setMessageId('msg_' . uniqid());
        $messageRecord->setChatId('chat_' . uniqid());
        $messageRecord->setSenderId('user_' . uniqid());
        $messageRecord->setMessageType('text');
        $messageRecord->setContent(['text' => 'Test message']);

        return $messageRecord;
    }

    protected function getRepository(): MessageRecordRepository
    {
        return self::getService(MessageRecordRepository::class);
    }
}
