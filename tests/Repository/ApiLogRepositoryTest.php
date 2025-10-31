<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Entity\ApiLog;
use Tourze\LarkAppBotBundle\Repository\ApiLogRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(ApiLogRepository::class)]
#[RunTestsInSeparateProcesses]
final class ApiLogRepositoryTest extends AbstractRepositoryTestCase
{
    public function testSaveAndFindApiLogShouldWorkCorrectly(): void
    {
        $apiLog = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $apiLog);

        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $em->persist($apiLog);
        $em->flush();

        $foundLog = $repository->find($apiLog->getId());
        self::assertNotNull($foundLog);
        self::assertSame($apiLog->getEndpoint(), $foundLog->getEndpoint());
    }

    public function testFindByEndpointShouldReturnCorrectLogs(): void
    {
        $endpoint = '/api/test/' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create two logs with same endpoint
        $log1 = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $log1);
        $log1->setEndpoint($endpoint);

        $log2 = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $log2);
        $log2->setEndpoint($endpoint);

        $em->persist($log1);
        $em->persist($log2);
        $em->flush();

        $logs = $repository->findByEndpoint($endpoint);
        $this->assertIsArray($logs);
        self::assertCount(2, $logs);
    }

    public function testFindByMethodShouldReturnCorrectLogs(): void
    {
        $method = 'POST';
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $log = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $log);
        $log->setMethod($method);

        $em->persist($log);
        $em->flush();

        $logs = $repository->findByMethod($method);
        self::assertNotEmpty($logs);
        foreach ($logs as $foundLog) {
            self::assertSame($method, $foundLog->getMethod());
        }
    }

    public function testFindByStatusCodeShouldReturnCorrectLogs(): void
    {
        $statusCode = 404;
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $log = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $log);
        $log->setStatusCode($statusCode);

        $em->persist($log);
        $em->flush();

        $logs = $repository->findByStatusCode($statusCode);
        self::assertNotEmpty($logs);
        foreach ($logs as $foundLog) {
            self::assertSame($statusCode, $foundLog->getStatusCode());
        }
    }

    public function testFindErrorLogsShouldReturnOnly4xxAnd5xxStatusCodes(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create success log
        $successLog = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $successLog);
        $successLog->setStatusCode(200);

        // Create error logs
        $errorLog1 = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $errorLog1);
        $errorLog1->setStatusCode(404);

        $errorLog2 = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $errorLog2);
        $errorLog2->setStatusCode(500);

        $em->persist($successLog);
        $em->persist($errorLog1);
        $em->persist($errorLog2);
        $em->flush();

        $errorLogs = $repository->findErrorLogs();
        self::assertNotEmpty($errorLogs);

        foreach ($errorLogs as $log) {
            self::assertGreaterThanOrEqual(400, $log->getStatusCode());
        }
    }

    public function testFindSuccessLogsShouldReturnOnly2xxStatusCodes(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create success log
        $successLog = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $successLog);
        $successLog->setStatusCode(200);

        // Create error log
        $errorLog = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $errorLog);
        $errorLog->setStatusCode(404);

        $em->persist($successLog);
        $em->persist($errorLog);
        $em->flush();

        $successLogs = $repository->findSuccessLogs();
        self::assertNotEmpty($successLogs);

        foreach ($successLogs as $log) {
            self::assertGreaterThanOrEqual(200, $log->getStatusCode());
            self::assertLessThan(300, $log->getStatusCode());
        }
    }

    public function testFindByUserIdShouldReturnCorrectLogs(): void
    {
        $userId = 'user_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $log = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $log);
        $log->setUserId($userId);

        $em->persist($log);
        $em->flush();

        $logs = $repository->findByUserId($userId);
        self::assertNotEmpty($logs);
        foreach ($logs as $foundLog) {
            self::assertSame($userId, $foundLog->getUserId());
        }
    }

    public function testFindByDateRangeShouldReturnLogsInRange(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $log = $this->createNewEntity();
        $em->persist($log);
        $em->flush();

        $startDate = new \DateTime('-1 day');
        $endDate = new \DateTime('+1 day');

        $logs = $repository->findByDateRange($startDate, $endDate);
        self::assertGreaterThanOrEqual(1, \count($logs));
    }

    public function testFindSlowRequestsShouldReturnSlowLogs(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create slow request
        $slowLog = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $slowLog);
        $slowLog->setResponseTime(2000);  // 2 seconds

        // Create fast request
        $fastLog = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $fastLog);
        $fastLog->setResponseTime(500);   // 0.5 seconds

        $em->persist($slowLog);
        $em->persist($fastLog);
        $em->flush();

        $slowLogs = $repository->findSlowRequests(1000); // > 1 second
        self::assertNotEmpty($slowLogs);

        foreach ($slowLogs as $log) {
            self::assertNotNull($log->getResponseTime());
            self::assertGreaterThan(1000, $log->getResponseTime());
        }
    }

    public function testCountApiCallsShouldReturnCorrectCount(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $initialCount = $repository->countApiCalls();

        $log = $this->createNewEntity();
        $em->persist($log);
        $em->flush();

        $newCount = $repository->countApiCalls();
        self::assertSame($initialCount + 1, $newCount);
    }

    public function testCountApiCallsByDateRangeShouldReturnCorrectCount(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $log = $this->createNewEntity();
        $em->persist($log);
        $em->flush();

        $startDate = new \DateTime('-1 day');
        $endDate = new \DateTime('+1 day');

        $count = $repository->countApiCallsByDateRange($startDate, $endDate);
        self::assertGreaterThanOrEqual(1, $count);
    }

    public function testGetTopEndpointsShouldReturnStatistics(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $endpoint = '/api/popular/' . uniqid();

        // Create multiple calls to same endpoint
        for ($i = 0; $i < 3; ++$i) {
            $log = $this->createNewEntity();
            self::assertInstanceOf(ApiLog::class, $log);
            $log->setEndpoint($endpoint);
            $em->persist($log);
        }
        $em->flush();

        $topEndpoints = $repository->getTopEndpoints();
        self::assertNotEmpty($topEndpoints);
        $this->assertIsArray($topEndpoints);
        self::assertArrayHasKey('endpoint', $topEndpoints[0]);
        self::assertArrayHasKey('count', $topEndpoints[0]);
    }

    public function testGetStatusCodeDistributionShouldReturnStatistics(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $log = $this->createNewEntity();
        $em->persist($log);
        $em->flush();

        $distribution = $repository->getStatusCodeDistribution();
        self::assertNotEmpty($distribution);
        $this->assertIsArray($distribution);
        self::assertArrayHasKey('status_code', $distribution[0]);
        self::assertArrayHasKey('count', $distribution[0]);
    }

    public function testGetAverageResponseTimeShouldReturnCorrectAverage(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $log1 = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $log1);
        $log1->setResponseTime(1000);

        $log2 = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $log2);
        $log2->setResponseTime(2000);

        $em->persist($log1);
        $em->persist($log2);
        $em->flush();

        $average = $repository->getAverageResponseTime();
        self::assertIsFloat($average);
        self::assertGreaterThan(0, $average);
    }

    public function testSearchByEndpointShouldReturnMatchingLogs(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $keyword = 'search_test_' . uniqid();
        $log = $this->createNewEntity();
        self::assertInstanceOf(ApiLog::class, $log);
        $log->setEndpoint('/api/' . $keyword . '/endpoint');

        $em->persist($log);
        $em->flush();

        $results = $repository->searchByEndpoint($keyword);
        self::assertNotEmpty($results);
        foreach ($results as $foundLog) {
            self::assertStringContainsString($keyword, $foundLog->getEndpoint());
        }
    }

    protected function onSetUp(): void
    {
        // No setup required - using self::getService() directly in tests
    }

    protected function createNewEntity(): object
    {
        $apiLog = new ApiLog();
        $apiLog->setEndpoint('/api/test/' . uniqid());
        $apiLog->setMethod('GET');
        $apiLog->setStatusCode(200);
        $apiLog->setRequestData(['param' => 'value']);
        $apiLog->setResponseData(['result' => 'success']);
        $apiLog->setResponseTime(rand(100, 1000));
        $apiLog->setUserId('user_' . uniqid());

        return $apiLog;
    }

    protected function getRepository(): ApiLogRepository
    {
        return self::getService(ApiLogRepository::class);
    }
}
