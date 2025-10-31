<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\AuthenticationException;
use Tourze\LarkAppBotBundle\Exception\RateLimitException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Client\ResponseHandler;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ResponseHandler::class)]
#[RunTestsInSeparateProcesses]
final class ResponseHandlerTest extends AbstractIntegrationTestCase
{
    private ResponseHandler $handler;

    public function testHandleSuccessResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200)
        ;

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('{"code": 0, "msg": "success", "data": {"key": "value"}}')
        ;

        $result = $this->handler->handle($response);

        $this->assertSame($response, $result);
    }

    public function testHandleBusinessError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200)
        ;

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('{"code": 99991663, "msg": "token无效"}')
        ;

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('[99991663] token无效: token无效');

        $this->handler->handle($response);
    }

    public function testHandleRateLimitError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200)
        ;

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('{"code": 99991429, "msg": "请求过于频繁"}')
        ;

        $this->expectException(RateLimitException::class);

        $this->handler->handle($response);
    }

    public function testHandleValidationError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200)
        ;

        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('{"code": 99991400, "msg": "参数错误"}')
        ;

        $this->expectException(ValidationException::class);

        $this->handler->handle($response);
    }

    public function testHandleHttpError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(500)
        ;

        $response->expects($this->once())
            ->method('getContent')
            ->with(false)
            ->willReturn('{"code": 99991500, "msg": "服务器错误"}')
        ;

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('HTTP 500: 服务器内部错误');

        $this->handler->handle($response);
    }

    public function testExtractData(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('{"code": 0, "msg": "success", "data": {"user": "test"}}')
        ;

        $data = $this->handler->extractData($response);

        $this->assertSame(['user' => 'test'], $data);
    }

    public function testExtractDataWithError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('{"code": 99991663, "msg": "token无效"}')
        ;

        $this->expectException(AuthenticationException::class);

        $this->handler->extractData($response);
    }

    public function testExtractDataInvalidJson(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('invalid json')
        ;

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('响应格式错误：期望JSON格式');

        $this->handler->extractData($response);
    }

    public function testResponseHandlerErrorHandling(): void
    {
        // 测试认证错误
        $responseData = json_encode([
            'code' => 99991663,
            'msg' => 'token无效']);
        $client = new MockHttpClient([
            new MockResponse(false !== $responseData ? $responseData : '{}')]);

        $response = $client->request('GET', 'https://test.com');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('[99991663] token无效: token无效');

        $this->handler->handle($response);
    }

    protected function onSetUp(): void
    {
        // 使用真实的 handler 服务
        $this->handler = self::getService(ResponseHandler::class);
    }
}
