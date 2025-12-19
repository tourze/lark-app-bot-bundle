<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\LarkAppBotBundle\Entity\UserSync;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<UserSync>
 *
 * @method UserSync|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserSync|null findOneBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null)
 * @method UserSync[]    findAll()
 * @method UserSync[]    findBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: UserSync::class)]
final class UserSyncRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSync::class);
    }

    /**
     * 根据飞书用户ID查找同步记录.
     */
    public function findByUserId(string $userId): ?UserSync
    {
        return $this->findOneBy(['userId' => $userId]);
    }

    /**
     * 根据OpenID查找同步记录.
     */
    public function findByOpenId(string $openId): ?UserSync
    {
        return $this->findOneBy(['openId' => $openId]);
    }

    /**
     * 根据UnionID查找同步记录.
     */
    public function findByUnionId(string $unionId): ?UserSync
    {
        return $this->findOneBy(['unionId' => $unionId]);
    }

    /**
     * 根据邮箱查找同步记录.
     */
    public function findByEmail(string $email): ?UserSync
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * 根据同步状态查找记录.
     *
     * @return array<UserSync>
     * @psalm-return list<UserSync>
     */
    public function findBySyncStatus(string $status, int $limit = 100): array
    {
        /** @var list<UserSync> */
        return $this->createQueryBuilder('us')
            ->andWhere('us.syncStatus = :status')
            ->setParameter('status', $status)
            ->orderBy('us.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找待同步的记录.
     *
     * @return array<UserSync>
     * @psalm-return list<UserSync>
     */
    public function findPendingSync(int $limit = 50): array
    {
        return $this->findBySyncStatus('pending', $limit);
    }

    /**
     * 查找同步失败的记录.
     *
     * @return array<UserSync>
     * @psalm-return list<UserSync>
     */
    public function findFailedSync(int $limit = 100): array
    {
        return $this->findBySyncStatus('failed', $limit);
    }

    /**
     * 查找已成功同步的记录.
     *
     * @return array<UserSync>
     * @psalm-return list<UserSync>
     */
    public function findSuccessfulSync(int $limit = 100): array
    {
        return $this->findBySyncStatus('success', $limit);
    }

    /**
     * 统计各状态的同步记录数量.
     *
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $result = $this->createQueryBuilder('us')
            ->select('us.syncStatus, COUNT(us.id) as count')
            ->groupBy('us.syncStatus')
            ->getQuery()
            ->getResult()
        ;

        if (!\is_array($result)) {
            /** @var array<string, int> */
            return [];
        }

        /** @var array<string, int> $counts */
        $counts = [];
        foreach ($result as $row) {
            if (!\is_array($row)) {
                continue;
            }
            /* @var array{syncStatus: string, count: int|string} $row */
            $status = $row['syncStatus'];
            if (!\is_string($status)) {
                continue;
            }
            $counts[$status] = is_numeric($row['count']) ? (int) $row['count'] : 0;
        }

        return $counts;
    }

    /**
     * 根据同步时间范围查找记录.
     *
     * @return array<UserSync>
     * @psalm-return list<UserSync>
     */
    public function findBySyncDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        /** @var list<UserSync> */
        return $this->createQueryBuilder('us')
            ->andWhere('us.syncAt >= :startDate')
            ->andWhere('us.syncAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('us.syncAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找最近同步的记录.
     *
     * @return array<UserSync>
     * @psalm-return list<UserSync>
     */
    public function findRecentSynced(int $limit = 50): array
    {
        /** @var list<UserSync> */
        return $this->createQueryBuilder('us')
            ->andWhere('us.syncAt IS NOT NULL')
            ->orderBy('us.syncAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 保存用户同步实体.
     */
    public function save(UserSync $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除用户同步实体.
     */
    public function remove(UserSync $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
