<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Entity\GroupInfo;
use Tourze\LarkAppBotBundle\Repository\GroupInfoRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(GroupInfoRepository::class)]
#[RunTestsInSeparateProcesses]
final class GroupInfoRepositoryTest extends AbstractRepositoryTestCase
{
    public function testSaveAndFindGroupInfoShouldWorkCorrectly(): void
    {
        $groupInfo = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $groupInfo);

        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $em->persist($groupInfo);
        $em->flush();

        $foundGroup = $repository->findByChatId($groupInfo->getChatId());
        self::assertNotNull($foundGroup);
        self::assertSame($groupInfo->getName(), $foundGroup->getName());
    }

    public function testFindByChatIdShouldReturnCorrectGroup(): void
    {
        $chatId = 'chat_test_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $group = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $group);
        $group->setChatId($chatId);

        $em->persist($group);
        $em->flush();

        $foundGroup = $repository->findByChatId($chatId);
        self::assertNotNull($foundGroup);
        self::assertSame($chatId, $foundGroup->getChatId());
    }

    public function testFindByOwnerIdShouldReturnOwnerGroups(): void
    {
        $ownerId = 'owner_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create two groups for same owner
        $group1 = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $group1);
        $group1->setOwnerId($ownerId);

        $group2 = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $group2);
        $group2->setOwnerId($ownerId);

        $em->persist($group1);
        $em->persist($group2);
        $em->flush();

        $groups = $repository->findByOwnerId($ownerId);
        $this->assertIsArray($groups);
        self::assertCount(2, $groups);
        foreach ($groups as $group) {
            self::assertSame($ownerId, $group->getOwnerId());
        }
    }

    public function testFindByChatTypeShouldReturnCorrectGroups(): void
    {
        $chatType = 'group';
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $group = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $group);
        $group->setChatType($chatType);

        $em->persist($group);
        $em->flush();

        $groups = $repository->findByChatType($chatType);
        self::assertNotEmpty($groups);
        foreach ($groups as $foundGroup) {
            self::assertSame($chatType, $foundGroup->getChatType());
        }
    }

    public function testFindExternalGroupsShouldReturnOnlyExternalGroups(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create external group
        $externalGroup = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $externalGroup);
        $externalGroup->setExternal(true);

        // Create internal group
        $internalGroup = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $internalGroup);
        $internalGroup->setExternal(false);

        $em->persist($externalGroup);
        $em->persist($internalGroup);
        $em->flush();

        $externalGroups = $repository->findExternalGroups();
        self::assertNotEmpty($externalGroups);
        foreach ($externalGroups as $group) {
            self::assertTrue($group->isExternal());
        }
    }

    public function testFindInternalGroupsShouldReturnOnlyInternalGroups(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create external group
        $externalGroup = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $externalGroup);
        $externalGroup->setExternal(true);

        // Create internal group
        $internalGroup = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $internalGroup);
        $internalGroup->setExternal(false);

        $em->persist($externalGroup);
        $em->persist($internalGroup);
        $em->flush();

        $internalGroups = $repository->findInternalGroups();
        self::assertNotEmpty($internalGroups);
        foreach ($internalGroups as $group) {
            self::assertFalse($group->isExternal());
        }
    }

    public function testFindByMemberCountRangeShouldReturnGroupsInRange(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create small group
        $smallGroup = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $smallGroup);
        $smallGroup->setMemberCount(5);

        // Create medium group
        $mediumGroup = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $mediumGroup);
        $mediumGroup->setMemberCount(50);

        // Create large group
        $largeGroup = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $largeGroup);
        $largeGroup->setMemberCount(200);

        $em->persist($smallGroup);
        $em->persist($mediumGroup);
        $em->persist($largeGroup);
        $em->flush();

        $groups = $repository->findByMemberCountRange(10, 100);
        self::assertNotEmpty($groups);
        foreach ($groups as $group) {
            self::assertGreaterThanOrEqual(10, $group->getMemberCount());
            self::assertLessThanOrEqual(100, $group->getMemberCount());
        }
    }

    public function testFindLargeGroupsShouldReturnGroupsWithManyMembers(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create small group
        $smallGroup = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $smallGroup);
        $smallGroup->setMemberCount(10);

        // Create large group
        $largeGroup = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $largeGroup);
        $largeGroup->setMemberCount(150);

        $em->persist($smallGroup);
        $em->persist($largeGroup);
        $em->flush();

        $largeGroups = $repository->findLargeGroups(100);
        self::assertNotEmpty($largeGroups);
        foreach ($largeGroups as $group) {
            self::assertGreaterThanOrEqual(100, $group->getMemberCount());
        }
    }

    public function testSearchByNameShouldReturnMatchingGroups(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $keyword = 'search_test_' . uniqid();
        $group = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $group);
        $group->setName('Test Group ' . $keyword . ' Chat');

        $em->persist($group);
        $em->flush();

        $results = $repository->searchByName($keyword);
        self::assertNotEmpty($results);
        foreach ($results as $foundGroup) {
            self::assertStringContainsString($keyword, $foundGroup->getName());
        }
    }

    public function testCountGroupsShouldReturnCorrectCount(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $initialCount = $repository->countGroups();

        $group = $this->createNewEntity();
        $em->persist($group);
        $em->flush();

        $newCount = $repository->countGroups();
        self::assertSame($initialCount + 1, $newCount);
    }

    public function testCountExternalGroupsShouldReturnCorrectCount(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $initialCount = $repository->countExternalGroups();

        // Create external group
        $externalGroup = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $externalGroup);
        $externalGroup->setExternal(true);

        // Create internal group
        $internalGroup = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $internalGroup);
        $internalGroup->setExternal(false);

        $em->persist($externalGroup);
        $em->persist($internalGroup);
        $em->flush();

        $newCount = $repository->countExternalGroups();
        self::assertSame($initialCount + 1, $newCount); // Only external group counted
    }

    public function testGetMemberCountStatsShouldReturnCorrectStats(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $group1 = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $group1);
        $group1->setMemberCount(10);

        $group2 = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $group2);
        $group2->setMemberCount(20);

        $group3 = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $group3);
        $group3->setMemberCount(30);

        $em->persist($group1);
        $em->persist($group2);
        $em->persist($group3);
        $em->flush();

        $stats = $repository->getMemberCountStats();
        $this->assertIsArray($stats);
        self::assertArrayHasKey('total', $stats);
        self::assertArrayHasKey('average', $stats);
        self::assertArrayHasKey('maximum', $stats);
        self::assertGreaterThanOrEqual(60, $stats['total']); // At least 60 from our test groups
        self::assertGreaterThanOrEqual(30, $stats['maximum']); // At least 30 from largest test group
    }

    public function testUniqueChatIdConstraintShouldPreventDuplicates(): void
    {
        $chatId = 'unique_chat_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $group1 = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $group1);
        $group1->setChatId($chatId);

        $group2 = $this->createNewEntity();
        self::assertInstanceOf(GroupInfo::class, $group2);
        $group2->setChatId($chatId);

        $em->persist($group1);
        $em->flush();

        // Second group with same chat_id should cause constraint violation
        $this->expectException(\Exception::class);
        $em->persist($group2);
        $em->flush();
    }

    protected function onSetUp(): void
    {
        // No setup required - using self::getService() directly in tests
    }

    protected function createNewEntity(): object
    {
        $group = new GroupInfo();
        $group->setChatId('chat_' . uniqid());
        $group->setName('Test Group ' . uniqid());
        $group->setDescription('Test group for unit tests');
        $group->setOwnerId('owner_' . uniqid());
        $group->setMemberCount(rand(5, 100));
        $group->setBotCount(rand(0, 5));
        $group->setChatType('group');
        $group->setExternal(false);

        return $group;
    }

    protected function getRepository(): GroupInfoRepository
    {
        return self::getService(GroupInfoRepository::class);
    }
}
