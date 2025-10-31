<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tourze\LarkAppBotBundle\Exception\AuthenticationException;
use Tourze\LarkAppBotBundle\Exception\ConfigurationException;
use Tourze\LarkAppBotBundle\Exception\ExceptionHandler;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Exception\RateLimitException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ExceptionHandler::class)]
#[RunTestsInSeparateProcesses]
final class ExceptionHandlerTest extends AbstractIntegrationTestCase
{
    private ExceptionHandler $handler;

    public function testOnKernelExceptionWithLarkException(): void
    {
        $exception = new ConfigurationException('Test error');
        $event = $this->createExceptionEvent($exception);

        // 使用真实的 Logger，不设置期望

        $this->handler->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(500, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('type', $data);
        // createErrorResponse 方法返回的格式是不同的
        $this->assertTrue($data['error']);
        $this->assertSame('Test error', $data['message']);
        $this->assertSame('CONFIGURATION', $data['code']);
        $this->assertSame('configuration_error', $data['type']);
    }

    public function testOnKernelExceptionWithApiException(): void
    {
        $exception = new GenericApiException('API error');
        $event = $this->createExceptionEvent($exception);

        $this->handler->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        // ApiException 默认返回 500，因为没有设置有效的 HTTP 状态码
        $this->assertSame(500, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('type', $data);
        // createErrorResponse 方法返回的格式是不同的
        $this->assertTrue($data['error']);
        $this->assertSame('API error', $data['message']);
        // ApiException 的 errorCode 是 0，所以返回 '0'
        $this->assertSame('0', $data['code']);
        $this->assertSame('api_error', $data['type']);
    }

    public function testOnKernelExceptionWithAuthenticationException(): void
    {
        $exception = new AuthenticationException('Auth error');
        $event = $this->createExceptionEvent($exception);

        $this->handler->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('type', $data);
        // createErrorResponse 方法返回的格式是不同的
        $this->assertTrue($data['error']);
        $this->assertSame('Auth error', $data['message']);
        $this->assertSame('AUTHENTICATION', $data['code']);
        $this->assertSame('authentication_error', $data['type']);
    }

    public function testOnKernelExceptionWithRateLimitException(): void
    {
        $exception = new RateLimitException('Rate limit error');
        $event = $this->createExceptionEvent($exception);

        $this->handler->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(429, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('type', $data);
        // createErrorResponse 方法返回的格式是不同的
        $this->assertTrue($data['error']);
        $this->assertSame('Rate limit error', $data['message']);
        // RateLimitException 继承自 ApiException，errorCode 默认是 0
        $this->assertSame('0', $data['code']);
        $this->assertSame('rate_limit_error', $data['type']);
    }

    public function testOnKernelExceptionWithValidationException(): void
    {
        $exception = new ValidationException('Validation error');
        $event = $this->createExceptionEvent($exception);

        $this->handler->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(400, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('type', $data);
        // createErrorResponse 方法返回的格式是不同的
        $this->assertTrue($data['error']);
        $this->assertSame('Validation error', $data['message']);
        // ValidationException 继承自 ApiException，errorCode 默认是 0
        $this->assertSame('0', $data['code']);
        $this->assertSame('validation_error', $data['type']);
    }

    public function testOnKernelExceptionWithConfigurationException(): void
    {
        $exception = new ConfigurationException('Configuration error');
        $event = $this->createExceptionEvent($exception);

        $this->handler->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(500, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        // createErrorResponse 方法返回的格式是不同的
        $this->assertTrue($data['error'] ?? false);
        $this->assertSame('Configuration error', $data['message'] ?? null);
        // ConfigurationException 继承自 LarkException，没有 errorCode 属性
        $this->assertSame('CONFIGURATION', $data['code'] ?? null);
        $this->assertSame('configuration_error', $data['type'] ?? null);
    }

    public function testOnKernelExceptionWithGenericException(): void
    {
        $exception = new \RuntimeException('Generic error');
        $event = $this->createExceptionEvent($exception);

        // ExceptionHandler 只处理 LarkException，所以不应该设置响应
        $this->handler->onKernelException($event);

        // 验证没有设置响应
        $this->assertNull($event->getResponse());
    }

    public function testHandleWithLarkException(): void
    {
        $exception = new ConfigurationException('Test error');

        $result = $this->handler->handle($exception);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $error = $result['error'];
        $this->assertIsArray($error);
        $this->assertArrayHasKey('message', $error);
        $this->assertArrayHasKey('code', $error);
        $this->assertArrayHasKey('type', $error);
        $this->assertSame('Test error', $error['message']);
        $this->assertSame('CONFIGURATION', $error['code']);
        $this->assertSame('configuration_error', $error['type']);
    }

    public function testHandleWithGenericException(): void
    {
        $exception = new \RuntimeException('Not a lark exception');

        $result = $this->handler->handle($exception);

        $this->assertFalse($result['success'] ?? true);
        $error = $result['error'] ?? null;
        $this->assertIsArray($error);
        $this->assertSame('Not a lark exception', $error['message'] ?? null);
        $this->assertSame('RUNTIME', $error['code'] ?? null);
        $this->assertSame('unknown_error', $error['type'] ?? null);
    }

    public function testDebugModeIncludesMoreDetails(): void
    {
        $debugHandler = self::getService(ExceptionHandler::class);
        $exception = new ConfigurationException('Debug test error');

        // 使用反射调用 createErrorResponse 方法来测试调试模式
        $reflection = new \ReflectionClass($debugHandler);
        $method = $reflection->getMethod('createErrorResponse');
        $response = $method->invoke($debugHandler, $exception);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $content = $response->getContent();
        $this->assertIsString($content);
        $result = json_decode($content, true);
        $this->assertIsArray($result);

        $this->assertTrue($result['error'] ?? false);
        $this->assertSame('Debug test error', $result['message'] ?? null);
        $this->assertArrayHasKey('debug', $result);
        $debug = $result['debug'] ?? null;
        $this->assertIsArray($debug);
        $this->assertArrayHasKey('exception', $debug);
        $this->assertArrayHasKey('file', $debug);
        $this->assertArrayHasKey('line', $debug);
        $this->assertArrayHasKey('trace', $debug);
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
        $this->handler = $this->createExceptionHandler();
    }

    private function createExceptionEvent(\Throwable $exception): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }

    private function createExceptionHandler(): ExceptionHandler
    {
        return self::getService(ExceptionHandler::class);
    }
}
