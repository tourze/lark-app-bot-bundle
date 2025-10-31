<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\LarkAppBotBundle\Entity\BotConfiguration;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<BotConfiguration>
 *
 * @method BotConfiguration|null find($id, $lockMode = null, $lockVersion = null)
 * @method BotConfiguration|null findOneBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null)
 * @method BotConfiguration[]    findAll()
 * @method BotConfiguration[]    findBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: BotConfiguration::class)]
class BotConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BotConfiguration::class);
    }

    /**
     * 根据应用ID和配置键查找配置.
     */
    public function findByAppIdAndKey(string $appId, string $configKey): ?BotConfiguration
    {
        return $this->findOneBy([
            'appId' => $appId,
            'configKey' => $configKey,
        ]);
    }

    /**
     * 根据应用ID查找所有配置.
     *
     * @return BotConfiguration[]
     */
    public function findByAppId(string $appId): array
    {
        $result = $this->createQueryBuilder('bc')
            ->andWhere('bc.appId = :appId')
            ->setParameter('appId', $appId)
            ->orderBy('bc.configKey', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        /** @var BotConfiguration[] $result */
        return $result;
    }

    /**
     * 根据应用ID查找所有激活的配置.
     *
     * @return BotConfiguration[]
     */
    public function findActiveByAppId(string $appId): array
    {
        $result = $this->createQueryBuilder('bc')
            ->andWhere('bc.appId = :appId')
            ->andWhere('bc.isActive = :isActive')
            ->setParameter('appId', $appId)
            ->setParameter('isActive', true)
            ->orderBy('bc.configKey', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        /** @var BotConfiguration[] $result */
        return $result;
    }

    /**
     * 查找所有激活的配置.
     *
     * @return BotConfiguration[]
     */
    public function findActiveConfigurations(): array
    {
        $result = $this->createQueryBuilder('bc')
            ->andWhere('bc.isActive = :isActive')
            ->setParameter('isActive', true)
            ->orderBy('bc.appId', 'ASC')
            ->addOrderBy('bc.configKey', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        /** @var BotConfiguration[] $result */
        return $result;
    }

    /**
     * 查找所有非激活的配置.
     *
     * @return BotConfiguration[]
     */
    public function findInactiveConfigurations(): array
    {
        $result = $this->createQueryBuilder('bc')
            ->andWhere('bc.isActive = :isActive')
            ->setParameter('isActive', false)
            ->orderBy('bc.appId', 'ASC')
            ->addOrderBy('bc.configKey', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        /** @var BotConfiguration[] $result */
        return $result;
    }

    /**
     * 根据配置键搜索.
     *
     * @return BotConfiguration[]
     */
    public function searchByConfigKey(string $keyword): array
    {
        $result = $this->createQueryBuilder('bc')
            ->andWhere('bc.configKey LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('bc.appId', 'ASC')
            ->addOrderBy('bc.configKey', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        /** @var BotConfiguration[] $result */
        return $result;
    }

    /**
     * 根据配置名称搜索.
     *
     * @return BotConfiguration[]
     */
    public function searchByName(string $keyword): array
    {
        $result = $this->createQueryBuilder('bc')
            ->andWhere('bc.name LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('bc.appId', 'ASC')
            ->addOrderBy('bc.configKey', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        /** @var BotConfiguration[] $result */
        return $result;
    }

    /**
     * 获取所有不重复的应用ID.
     *
     * @return string[]
     */
    public function getDistinctAppIds(): array
    {
        $result = $this->createQueryBuilder('bc')
            ->select('DISTINCT bc.appId')
            ->orderBy('bc.appId', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        if (!\is_array($result)) {
            return [];
        }

        /** @var array<array{appId: string}> $result */
        return array_column($result, 'appId');
    }

    /**
     * 统计每个应用的配置数量.
     *
     * @return array<string, int>
     */
    public function countConfigurationsByAppId(): array
    {
        $result = $this->createQueryBuilder('bc')
            ->select('bc.appId, COUNT(bc.id) as count')
            ->groupBy('bc.appId')
            ->orderBy('bc.appId', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        if (!\is_array($result)) {
            return [];
        }

        /** @var array<array{appId: string, count: int|string}> $result */
        $counts = [];
        foreach ($result as $row) {
            if (!\is_array($row)) {
                continue;
            }
            /** @var array{appId: string, count: int|string} $row */
            $counts[(string) $row['appId']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * 统计激活和非激活配置数量.
     *
     * @return array<string, int>
     */
    public function getActivationStats(): array
    {
        $result = $this->createQueryBuilder('bc')
            ->select('bc.isActive, COUNT(bc.id) as count')
            ->groupBy('bc.isActive')
            ->getQuery()
            ->getResult()
        ;

        $stats = ['active' => 0, 'inactive' => 0];
        \assert(\is_array($result));
        foreach ($result as $row) {
            \assert(\is_array($row));
            $key = (bool) ($row['isActive'] ?? false) ? 'active' : 'inactive';
            $stats[$key] = (int) ($row['count'] ?? 0);
        }

        return $stats;
    }

    /**
     * 保存机器人配置实体.
     */
    public function save(BotConfiguration $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除机器人配置实体.
     */
    public function remove(BotConfiguration $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
