<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\LarkAppBotBundle\Entity\ApiLog;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<ApiLog>
 *
 * @method ApiLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApiLog|null findOneBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null)
 * @method ApiLog[]    findAll()
 * @method ApiLog[]    findBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: ApiLog::class)]
final class ApiLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiLog::class);
    }

    /**
     * 根据端点查找API日志.
     *
     * @return ApiLog[]
     */
    public function findByEndpoint(string $endpoint, int $limit = 100): array
    {
        $result = $this->createQueryBuilder('al')
            ->andWhere('al.endpoint = :endpoint')
            ->setParameter('endpoint', $endpoint)
            ->orderBy('al.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var ApiLog[] $result */
        return $result;
    }

    /**
     * 根据HTTP方法查找API日志.
     *
     * @return ApiLog[]
     */
    public function findByMethod(string $method, int $limit = 100): array
    {
        $result = $this->createQueryBuilder('al')
            ->andWhere('al.method = :method')
            ->setParameter('method', $method)
            ->orderBy('al.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var ApiLog[] $result */
        return $result;
    }

    /**
     * 根据状态码查找API日志.
     *
     * @return ApiLog[]
     */
    public function findByStatusCode(int $statusCode, int $limit = 100): array
    {
        $result = $this->createQueryBuilder('al')
            ->andWhere('al.statusCode = :statusCode')
            ->setParameter('statusCode', $statusCode)
            ->orderBy('al.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var ApiLog[] $result */
        return $result;
    }

    /**
     * 查找错误日志（4xx和5xx状态码）.
     *
     * @return ApiLog[]
     */
    public function findErrorLogs(int $limit = 100): array
    {
        $result = $this->createQueryBuilder('al')
            ->andWhere('al.statusCode >= 400')
            ->orderBy('al.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var ApiLog[] $result */
        return $result;
    }

    /**
     * 查找成功日志（2xx状态码）.
     *
     * @return ApiLog[]
     */
    public function findSuccessLogs(int $limit = 100): array
    {
        $result = $this->createQueryBuilder('al')
            ->andWhere('al.statusCode >= 200')
            ->andWhere('al.statusCode < 300')
            ->orderBy('al.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var ApiLog[] $result */
        return $result;
    }

    /**
     * 根据用户ID查找API日志.
     *
     * @return ApiLog[]
     */
    public function findByUserId(string $userId, int $limit = 100): array
    {
        $result = $this->createQueryBuilder('al')
            ->andWhere('al.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('al.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var ApiLog[] $result */
        return $result;
    }

    /**
     * 根据时间范围查找API日志.
     *
     * @return ApiLog[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $limit = 1000): array
    {
        $result = $this->createQueryBuilder('al')
            ->andWhere('al.createTime >= :startDate')
            ->andWhere('al.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('al.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var ApiLog[] $result */
        return $result;
    }

    /**
     * 查找慢请求日志（响应时间超过指定阈值）.
     *
     * @return ApiLog[]
     */
    public function findSlowRequests(int $thresholdMs = 1000, int $limit = 100): array
    {
        $result = $this->createQueryBuilder('al')
            ->andWhere('al.responseTime > :threshold')
            ->setParameter('threshold', $thresholdMs)
            ->orderBy('al.responseTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var ApiLog[] $result */
        return $result;
    }

    /**
     * 统计API调用次数.
     */
    public function countApiCalls(): int
    {
        $result = $this->createQueryBuilder('al')
            ->select('COUNT(al.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $result;
    }

    /**
     * 统计指定时间范围内的API调用次数.
     */
    public function countApiCallsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        $result = $this->createQueryBuilder('al')
            ->select('COUNT(al.id)')
            ->andWhere('al.createTime >= :startDate')
            ->andWhere('al.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $result;
    }

    /**
     * 获取最常调用的API端点统计
     *
     * @return array<array{endpoint: string, count: int}>
     */
    public function getTopEndpoints(int $limit = 10): array
    {
        $result = $this->createQueryBuilder('al')
            ->select('al.endpoint, COUNT(al.id) as count')
            ->groupBy('al.endpoint')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var array<array{endpoint: string, count: int}> $result */
        return $result;
    }

    /**
     * 获取状态码分布统计
     *
     * @return array<array{status_code: int, count: int}>
     */
    public function getStatusCodeDistribution(): array
    {
        $result = $this->createQueryBuilder('al')
            ->select('al.statusCode as status_code, COUNT(al.id) as count')
            ->groupBy('al.statusCode')
            ->orderBy('al.statusCode', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        /** @var array<array{status_code: int, count: int}> $result */
        return $result;
    }

    /**
     * 获取平均响应时间统计
     */
    public function getAverageResponseTime(): ?float
    {
        $result = $this->createQueryBuilder('al')
            ->select('AVG(al.responseTime)')
            ->andWhere('al.responseTime IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return null !== $result ? (float) $result : null;
    }

    /**
     * 按端点搜索API日志.
     *
     * @return ApiLog[]
     */
    public function searchByEndpoint(string $keyword, int $limit = 100): array
    {
        $result = $this->createQueryBuilder('al')
            ->andWhere('al.endpoint LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('al.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        /** @var ApiLog[] $result */
        return $result;
    }

    /**
     * 保存API日志实体.
     */
    public function save(ApiLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除API日志实体.
     */
    public function remove(ApiLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
