<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\LarkAppBotBundle\Entity\MessageRecord;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<MessageRecord>
 *
 * @method MessageRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageRecord|null findOneBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null)
 * @method MessageRecord[]    findAll()
 * @method MessageRecord[]    findBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: MessageRecord::class)]
final class MessageRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageRecord::class);
    }

    /**
     * 根据聊天ID查找消息记录.
     *
     * @return array<MessageRecord>
     * @psalm-return list<MessageRecord>
     */
    public function findByChatId(string $chatId, int $limit = 50): array
    {
        /** @var list<MessageRecord> */
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.chatId = :chatId')
            ->setParameter('chatId', $chatId)
            ->orderBy('mr.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据发送者ID查找消息记录.
     *
     * @return array<MessageRecord>
     * @psalm-return list<MessageRecord>
     */
    public function findBySenderId(string $senderId, int $limit = 50): array
    {
        /** @var list<MessageRecord> */
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.senderId = :senderId')
            ->setParameter('senderId', $senderId)
            ->orderBy('mr.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据消息类型查找消息记录.
     *
     * @return array<MessageRecord>
     * @psalm-return list<MessageRecord>
     */
    public function findByMessageType(string $messageType, int $limit = 100): array
    {
        /** @var list<MessageRecord> */
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.messageType = :messageType')
            ->setParameter('messageType', $messageType)
            ->orderBy('mr.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 统计指定时间范围内的消息数量.
     */
    public function countMessagesByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        $result = $this->createQueryBuilder('mr')
            ->select('COUNT(mr.id)')
            ->andWhere('mr.createTime >= :startDate')
            ->andWhere('mr.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $result;
    }

    /**
     * 根据飞书消息ID查找消息记录.
     */
    public function findByMessageId(string $messageId): ?MessageRecord
    {
        return $this->findOneBy(['messageId' => $messageId]);
    }

    /**
     * 查找机器人发送的消息.
     *
     * @return array<MessageRecord>
     * @psalm-return list<MessageRecord>
     */
    public function findBotMessages(int $limit = 100): array
    {
        /** @var list<MessageRecord> */
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.senderType = :senderType')
            ->setParameter('senderType', 'bot')
            ->orderBy('mr.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找群组消息.
     *
     * @return array<MessageRecord>
     * @psalm-return list<MessageRecord>
     */
    public function findGroupMessages(int $limit = 100): array
    {
        /** @var list<MessageRecord> */
        return $this->createQueryBuilder('mr')
            ->andWhere('mr.chatType = :chatType')
            ->setParameter('chatType', 'group')
            ->orderBy('mr.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 保存消息记录实体.
     */
    public function save(MessageRecord $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除消息记录实体.
     */
    public function remove(MessageRecord $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
