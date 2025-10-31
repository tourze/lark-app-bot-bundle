<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Entity\ApiLog;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(ApiLog::class)]
final class ApiLogTest extends AbstractEntityTestCase
{
    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['endpoint', '/api/v1/users'],
            ['method', 'POST'],
            ['requestData', ['name' => 'John', 'email' => 'john@example.com']],
            ['responseData', ['status' => 'success', 'user_id' => 123]],
            ['statusCode', 200],
            ['responseTime', 150],
            ['userId', 'user_12345'],
        ];
    }

    public function testApiLogCanBeCreatedWithDefaultValues(): void
    {
        $apiLog = new ApiLog();

        self::assertNull($apiLog->getId());
        self::assertSame('', $apiLog->getEndpoint());
        self::assertSame('GET', $apiLog->getMethod());
        self::assertNull($apiLog->getRequestData());
        self::assertNull($apiLog->getResponseData());
        self::assertSame(200, $apiLog->getStatusCode());
        self::assertNull($apiLog->getResponseTime());
        self::assertNull($apiLog->getUserId());
        $this->assertLessThanOrEqual(time(), $apiLog->getCreateTime()->getTimestamp());
    }

    public function testApiLogSettersAndGetters(): void
    {
        $apiLog = new ApiLog();
        $requestData = ['query' => 'test'];
        $responseData = ['result' => 'ok'];
        $createTime = new \DateTimeImmutable('2024-01-01 12:00:00');

        $apiLog->setEndpoint('/api/v1/test');
        $apiLog->setMethod('POST');
        $apiLog->setRequestData($requestData);
        $apiLog->setResponseData($responseData);
        $apiLog->setStatusCode(201);
        $apiLog->setResponseTime(250);
        $apiLog->setUserId('user_abc');
        $apiLog->setCreateTime($createTime);

        self::assertSame('/api/v1/test', $apiLog->getEndpoint());
        self::assertSame('POST', $apiLog->getMethod());
        self::assertSame($requestData, $apiLog->getRequestData());
        self::assertSame($responseData, $apiLog->getResponseData());
        self::assertSame(201, $apiLog->getStatusCode());
        self::assertSame(250, $apiLog->getResponseTime());
        self::assertSame('user_abc', $apiLog->getUserId());
        self::assertSame($createTime, $apiLog->getCreateTime());
    }

    public function testToStringMethod(): void
    {
        $apiLog = new ApiLog();
        $apiLog->setMethod('POST');
        $apiLog->setEndpoint('/api/v1/test');
        $apiLog->setStatusCode(404);

        self::assertSame('API日志 POST /api/v1/test [404]', (string) $apiLog);
    }

    public function testRequestDataCanBeNull(): void
    {
        $apiLog = new ApiLog();
        $apiLog->setRequestData(null);

        self::assertNull($apiLog->getRequestData());
    }

    public function testResponseDataCanBeNull(): void
    {
        $apiLog = new ApiLog();
        $apiLog->setResponseData(null);

        self::assertNull($apiLog->getResponseData());
    }

    public function testComplexJsonDataStorage(): void
    {
        $apiLog = new ApiLog();
        $complexRequest = [
            'user' => ['id' => 1, 'name' => 'Test'],
            'items' => ['a', 'b', 'c'],
            'metadata' => ['timestamp' => 123456789],
        ];
        $complexResponse = [
            'status' => 'success',
            'data' => ['result' => 'ok'],
            'errors' => [],
        ];

        $apiLog->setRequestData($complexRequest);
        $apiLog->setResponseData($complexResponse);

        self::assertSame($complexRequest, $apiLog->getRequestData());
        self::assertSame($complexResponse, $apiLog->getResponseData());
    }

    protected function createEntity(): object
    {
        return new ApiLog();
    }
}
