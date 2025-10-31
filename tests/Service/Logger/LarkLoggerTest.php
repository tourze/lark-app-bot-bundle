<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Logger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tourze\LarkAppBotBundle\Service\Logger\LarkLogger;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * LarkLogger 测试.
 *
 * @internal
 */
#[CoversClass(LarkLogger::class)]
#[RunTestsInSeparateProcesses]
final class LarkLoggerTest extends AbstractIntegrationTestCase
{
    private LoggerInterface&MockObject $baseLogger;

    private RequestStack $requestStack;

    private LarkLogger $larkLogger;

    public function testLarkLoggerFiltering(): void
    {
        // 测试敏感信息过滤
        $context = [
            'headers' => [
                'Authorization' => 'Bearer secret-token-12345678',
                'X-App-Secret' => 'app-secret-value'],
            'data' => [
                'password' => 'user-password',
                'username' => 'testuser']];

        // 配置 mock 期望
        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                '[LarkAppBot] Test message',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id'];
                })
            )
        ;

        $this->larkLogger->info('Test message', $context);

        // 验证 logger 实例工作正常
        $this->assertInstanceOf(LarkLogger::class, $this->larkLogger);
    }

    public function testEmergency(): void
    {
        $message = 'Emergency message';
        $context = ['key' => 'value'];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::EMERGENCY,
                '[LarkAppBot] Emergency message',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id']
                        && 'value' === $logContext['key'];
                })
            )
        ;

        $this->larkLogger->emergency($message, $context);
    }

    public function testAlert(): void
    {
        $message = 'Alert message';
        $context = ['urgency' => 'high'];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::ALERT,
                '[LarkAppBot] Alert message',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id']
                        && 'high' === $logContext['urgency'];
                })
            )
        ;

        $this->larkLogger->alert($message, $context);
    }

    public function testCritical(): void
    {
        $message = 'Critical error occurred';
        $context = ['error_code' => 500];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::CRITICAL,
                '[LarkAppBot] Critical error occurred',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id']
                        && 500 === $logContext['error_code'];
                })
            )
        ;

        $this->larkLogger->critical($message, $context);
    }

    public function testError(): void
    {
        $message = 'Error message';
        $context = ['exception' => 'RuntimeException'];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::ERROR,
                '[LarkAppBot] Error message',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id']
                        && 'RuntimeException' === $logContext['exception'];
                })
            )
        ;

        $this->larkLogger->error($message, $context);
    }

    public function testWarning(): void
    {
        $message = 'Warning message';
        $context = ['deprecation' => true];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::WARNING,
                '[LarkAppBot] Warning message',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id']
                        && true === $logContext['deprecation'];
                })
            )
        ;

        $this->larkLogger->warning($message, $context);
    }

    public function testNotice(): void
    {
        $message = 'Notice message';
        $context = ['user_id' => '12345'];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::NOTICE,
                '[LarkAppBot] Notice message',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id']
                        && '12345' === $logContext['user_id'];
                })
            )
        ;

        $this->larkLogger->notice($message, $context);
    }

    public function testInfo(): void
    {
        $message = 'Info message';
        $context = ['action' => 'user_login'];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                '[LarkAppBot] Info message',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id']
                        && 'user_login' === $logContext['action'];
                })
            )
        ;

        $this->larkLogger->info($message, $context);
    }

    public function testDebugWhenDebugEnabled(): void
    {
        $message = 'Debug message';
        $context = ['debug_data' => 'test'];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                '[LarkAppBot] Debug message',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id']
                        && 'test' === $logContext['debug_data'];
                })
            )
        ;

        $this->larkLogger->debug($message, $context);
    }

    public function testDebugWhenDebugDisabled(): void
    {
        // 创建一个 debug 关闭的 logger 实例（不通过容器，避免已初始化服务替换限制）
        $logger = new LarkLogger($this->baseLogger, $this->requestStack, 'test_app_id', false);

        $this->baseLogger->expects($this->never())->method('log');

        $logger->debug('Debug message', ['debug_data' => 'test']);
    }

    public function testLogWithCustomLevel(): void
    {
        $level = 'custom';
        $message = 'Custom level message';
        $context = ['custom' => true];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                'custom',
                '[LarkAppBot] Custom level message',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id']
                        && true === $logContext['custom'];
                })
            )
        ;

        $this->larkLogger->log($level, $message, $context);
    }

    public function testSensitiveDataFiltering(): void
    {
        $message = 'Login attempt';
        $context = [
            'password' => 'very-secret-password',
            'authorization' => 'Bearer token-123456789',
            'app_secret' => 'app-secret-value',
            'username' => 'testuser',
            'headers' => [
                'Authorization' => 'Bearer another-token',
                'Content-Type' => 'application/json']];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                '[LarkAppBot] Login attempt',
                self::callback(function ($logContext) {
                    return 'very************word' === $logContext['password']
                        && 'Bear**************6789' === $logContext['authorization']
                        && 'app-********alue' === $logContext['app_secret']
                        && 'testuser' === $logContext['username']
                        && 'Bear************oken' === $logContext['headers']['Authorization']
                        && 'application/json' === $logContext['headers']['Content-Type'];
                })
            )
        ;

        $this->larkLogger->info($message, $context);
    }

    public function testContextEnrichmentWithRequest(): void
    {
        $request = new Request();
        $request->headers->set('X-Request-ID', 'req-123');
        $request->headers->set('X-Lark-Request-ID', 'lark-456');
        $this->requestStack->push($request);

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                '[LarkAppBot] Test message',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id']
                        && isset($logContext['timestamp'], $logContext['microtime'])

                        && 'req-123' === $logContext['request_id']
                        && 'lark-456' === $logContext['lark_request_id'];
                })
            )
        ;

        $this->larkLogger->info('Test message');
    }

    public function testContextEnrichmentWithoutRequest(): void
    {
        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                '[LarkAppBot] Test message',
                self::callback(function ($logContext) {
                    return isset($logContext['lark_app_id'])
                        && 'test_app_id' === $logContext['lark_app_id']
                        && isset($logContext['timestamp'], $logContext['microtime'])

                        && !isset($logContext['request_id'])
                        && !isset($logContext['lark_request_id']);
                })
            )
        ;

        $this->larkLogger->info('Test message');
    }

    public function testLogApiRequestWithStringBody(): void
    {
        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                '[LarkAppBot] API请求: POST /api/test',
                self::callback(function ($logContext) {
                    return 'api_request' === $logContext['type']
                        && 'POST' === $logContext['method']
                        && '/api/test' === $logContext['url']
                        && 'string body' === $logContext['body'];
                })
            )
        ;

        $this->larkLogger->logApiRequest('POST', '/api/test', [], 'string body');
    }

    public function testLogApiRequestWithArrayBody(): void
    {
        $body = ['key' => 'value'];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                '[LarkAppBot] API请求: POST /api/test',
                self::callback(function ($logContext) {
                    return 'api_request' === $logContext['type']
                        && 'POST' === $logContext['method']
                        && '/api/test' === $logContext['url']
                        && '{"key":"value"}' === $logContext['body'];
                })
            )
        ;

        $this->larkLogger->logApiRequest('POST', '/api/test', [], $body);
    }

    public function testLogApiResponseSuccess(): void
    {
        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                '[LarkAppBot] API响应: HTTP 200 (150.00ms)',
                self::callback(function ($logContext) {
                    return 'api_response' === $logContext['type']
                        && 200 === $logContext['status_code']
                        && 150.0 === $logContext['duration_ms'];
                })
            )
        ;

        $this->larkLogger->logApiResponse(200, [], null, 0.15);
    }

    public function testLogApiResponseError(): void
    {
        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::ERROR,
                '[LarkAppBot] API响应: HTTP 500 (200.00ms)',
                self::callback(function ($logContext) {
                    return 'api_response' === $logContext['type']
                        && 500 === $logContext['status_code']
                        && 200.0 === $logContext['duration_ms'];
                })
            )
        ;

        $this->larkLogger->logApiResponse(500, [], null, 0.2);
    }

    public function testLogPerformance(): void
    {
        $metrics = ['query_count' => 5, 'cache_hits' => 3];

        $this->baseLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                '[LarkAppBot] 性能: database_query 耗时 45.00ms',
                self::callback(function ($logContext) {
                    return 'performance' === $logContext['type']
                        && 'database_query' === $logContext['operation']
                        && 45.0 === $logContext['duration_ms']
                        && 5 === $logContext['query_count']
                        && 3 === $logContext['cache_hits'];
                })
            )
        ;

        $this->larkLogger->logPerformance('database_query', 0.045, $metrics);
    }

    protected function onSetUp(): void
    {
        // 直接构造被测实例，避免容器中已初始化服务的替换限制
        $this->baseLogger = $this->createMock(LoggerInterface::class);
        $this->requestStack = new RequestStack();
        $this->larkLogger = new LarkLogger($this->baseLogger, $this->requestStack, 'test_app_id', true);
    }
}
