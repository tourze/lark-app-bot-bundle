<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\LarkAppBotBundle\Entity\GroupInfo;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<GroupInfo>
 *
 * @method GroupInfo|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupInfo|null findOneBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null)
 * @method GroupInfo[]    findAll()
 * @method GroupInfo[]    findBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: GroupInfo::class)]
final class GroupInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupInfo::class);
    }

    /**
     * 根据群组ID查找群组信息.
     */
    public function findByChatId(string $chatId): ?GroupInfo
    {
        return $this->findOneBy(['chatId' => $chatId]);
    }

    /**
     * 根据群主ID查找群组.
     *
     * @return GroupInfo[]
     */
    public function findByOwnerId(string $ownerId): array
    {
        $result = $this->createQueryBuilder('gi')
            ->andWhere('gi.ownerId = :ownerId')
            ->setParameter('ownerId', $ownerId)
            ->orderBy('gi.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        /** @var GroupInfo[] $result */
        return $result;
    }

    /**
     * 根据群组类型查找群组.
     *
     * @return GroupInfo[]
     */
    public function findByChatType(string $chatType, int $limit = 100): array
    {
        $result = $this->createQueryBuilder('gi')
            ->andWhere('gi.chatType = :chatType')
            ->setParameter('chatType', $chatType)
            ->orderBy('gi.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var GroupInfo[] $result */
        return $result;
    }

    /**
     * 查找外部群组.
     *
     * @return GroupInfo[]
     */
    public function findExternalGroups(int $limit = 100): array
    {
        $result = $this->createQueryBuilder('gi')
            ->andWhere('gi.external = :external')
            ->setParameter('external', true)
            ->orderBy('gi.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var GroupInfo[] $result */
        return $result;
    }

    /**
     * 查找内部群组.
     *
     * @return GroupInfo[]
     */
    public function findInternalGroups(int $limit = 100): array
    {
        $result = $this->createQueryBuilder('gi')
            ->andWhere('gi.external = :external')
            ->setParameter('external', false)
            ->orderBy('gi.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var GroupInfo[] $result */
        return $result;
    }

    /**
     * 根据成员数量范围查找群组.
     *
     * @return GroupInfo[]
     */
    public function findByMemberCountRange(int $minCount, int $maxCount): array
    {
        $result = $this->createQueryBuilder('gi')
            ->andWhere('gi.memberCount >= :minCount')
            ->andWhere('gi.memberCount <= :maxCount')
            ->setParameter('minCount', $minCount)
            ->setParameter('maxCount', $maxCount)
            ->orderBy('gi.memberCount', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        /** @var GroupInfo[] $result */
        return $result;
    }

    /**
     * 查找大群（成员数超过指定数量）.
     *
     * @return GroupInfo[]
     */
    public function findLargeGroups(int $minMemberCount = 100): array
    {
        $result = $this->createQueryBuilder('gi')
            ->andWhere('gi.memberCount >= :minMemberCount')
            ->setParameter('minMemberCount', $minMemberCount)
            ->orderBy('gi.memberCount', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        /** @var GroupInfo[] $result */
        return $result;
    }

    /**
     * 按群组名称搜索.
     *
     * @return GroupInfo[]
     */
    public function searchByName(string $keyword, int $limit = 50): array
    {
        $result = $this->createQueryBuilder('gi')
            ->andWhere('gi.name LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('gi.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var GroupInfo[] $result */
        return $result;
    }

    /**
     * 统计群组数量.
     */
    public function countGroups(): int
    {
        $result = $this->createQueryBuilder('gi')
            ->select('COUNT(gi.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $result;
    }

    /**
     * 统计外部群组数量.
     */
    public function countExternalGroups(): int
    {
        $result = $this->createQueryBuilder('gi')
            ->select('COUNT(gi.id)')
            ->andWhere('gi.external = :external')
            ->setParameter('external', true)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $result;
    }

    /**
     * 获取成员数量统计
     *
     * @return array<string, int>
     */
    public function getMemberCountStats(): array
    {
        $result = $this->createQueryBuilder('gi')
            ->select('SUM(gi.memberCount) as totalMembers, AVG(gi.memberCount) as avgMembers, MAX(gi.memberCount) as maxMembers')
            ->getQuery()
            ->getSingleResult()
        ;
        \assert(\is_array($result));

        return [
            'total' => is_numeric($result['totalMembers'] ?? 0) ? (int) ($result['totalMembers'] ?? 0) : 0,
            'average' => is_numeric($result['avgMembers'] ?? 0) ? (int) ($result['avgMembers'] ?? 0) : 0,
            'maximum' => is_numeric($result['maxMembers'] ?? 0) ? (int) ($result['maxMembers'] ?? 0) : 0,
        ];
    }

    /**
     * 保存群组信息实体.
     */
    public function save(GroupInfo $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除群组信息实体.
     */
    public function remove(GroupInfo $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
