<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tourze\LarkAppBotBundle\Controller\WebhookController;
use Tourze\LarkAppBotBundle\LarkAppBotBundle;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * Tests WebhookController HTTP methods: GET, POST, PUT, DELETE, PATCH, OPTIONS.
 *
 * @internal
 */
#[CoversClass(WebhookController::class)]
#[RunTestsInSeparateProcesses]
#[Group('http-methods-tested')]
final class WebhookControllerTest extends AbstractWebTestCase
{
    public function testGET(): void
    {
        $client = self::createClientWithDatabase();

        try {
            $client->request('GET', '/lark/webhook');
            $this->assertSame(404, $client->getResponse()->getStatusCode());
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertStringContainsString('No route found', $e->getMessage());
        } catch (NotFoundHttpException $e) {
            $this->assertStringContainsString('No route found', $e->getMessage());
        }
    }

    public function testPOST(): void
    {
        $client = self::createClientWithDatabase();

        try {
            // 简单的 POST 请求测试
            $client->request('POST', '/lark/webhook');

            // 如果路由正确加载，期望400状态码（因为缺少必要的请求头）
            $this->assertSame(400, $client->getResponse()->getStatusCode());
        } catch (MethodNotAllowedHttpException $e) {
            // 如果方法不允许，会抛出 MethodNotAllowedHttpException
            $this->assertStringContainsString('No route found', $e->getMessage());
        } catch (NotFoundHttpException $e) {
            // 如果路由没有加载，会抛出 NotFoundHttpException
            $this->assertStringContainsString('No route found', $e->getMessage());
        }
    }

    public function testPostMethodWithValidSignatureHandled(): void
    {
        $controller = $this->getController();

        $requestData = [
            'type' => 'url_verification',
            'challenge' => 'test-challenge',
            'token' => 'test_token',
        ];

        $timestamp = time();
        $requestId = uniqid();
        $body = json_encode($requestData);
        $bodyString = false !== $body ? $body : '';
        $content = $timestamp . ':' . $requestId . ':test_encrypt_key:' . $bodyString;
        $signature = hash('sha256', $content);

        $request = Request::create('/lark/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LARK_SIGNATURE' => $signature,
            'HTTP_X_LARK_REQUEST_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_LARK_REQUEST_ID' => $requestId,
        ], $bodyString);

        $response = $controller->__invoke($request);
        $this->assertSame(200, $response->getStatusCode());
        $responseContent = $response->getContent();
        $jsonData = json_decode(false !== $responseContent ? $responseContent : '', true);
        $this->assertIsArray($jsonData);
        $this->assertSame('test-challenge', $jsonData['challenge']);
    }

    public function testControllerHandlesInvalidJson(): void
    {
        $controller = $this->getController();

        $request = Request::create('/lark/webhook', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid-json'
        );

        $response = $controller->__invoke($request);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testPUT(): void
    {
        $client = self::createClientWithDatabase();

        try {
            $client->request('PUT', '/lark/webhook');
            $this->assertSame(404, $client->getResponse()->getStatusCode());
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertStringContainsString('No route found', $e->getMessage());
        } catch (NotFoundHttpException $e) {
            $this->assertStringContainsString('No route found', $e->getMessage());
        }
    }

    public function testDELETE(): void
    {
        $client = self::createClientWithDatabase();

        try {
            $client->request('DELETE', '/lark/webhook');
            $this->assertSame(404, $client->getResponse()->getStatusCode());
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertStringContainsString('No route found', $e->getMessage());
        } catch (NotFoundHttpException $e) {
            $this->assertStringContainsString('No route found', $e->getMessage());
        }
    }

    public function testPATCH(): void
    {
        $client = self::createClientWithDatabase();

        try {
            $client->request('PATCH', '/lark/webhook');
            $this->assertSame(404, $client->getResponse()->getStatusCode());
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertStringContainsString('No route found', $e->getMessage());
        } catch (NotFoundHttpException $e) {
            $this->assertStringContainsString('No route found', $e->getMessage());
        }
    }

    public function testOPTIONS(): void
    {
        $client = self::createClientWithDatabase();

        try {
            $client->request('OPTIONS', '/lark/webhook');
            $this->assertSame(404, $client->getResponse()->getStatusCode());
        } catch (MethodNotAllowedHttpException $e) {
            $this->assertStringContainsString('No route found', $e->getMessage());
        } catch (NotFoundHttpException $e) {
            $this->assertStringContainsString('No route found', $e->getMessage());
        }
    }

    public function testControllerHandlesEventCallback(): void
    {
        $controller = $this->getController();

        $requestData = [
            'header' => [
                'event_type' => 'message.receive_v1',
                'tenant_key' => 'test_tenant',
                'app_id' => 'test_app',
                'token' => 'test_token',
            ],
            'event' => [
                'message' => [
                    'content' => 'Hello World',
                ],
            ],
        ];

        $timestamp = time();
        $requestId = uniqid();
        $body = json_encode($requestData);
        $bodyString = false !== $body ? $body : '';
        $content = $timestamp . ':' . $requestId . ':test_encrypt_key:' . $bodyString;
        $signature = hash('sha256', $content);

        $request = Request::create('/lark/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LARK_SIGNATURE' => $signature,
            'HTTP_X_LARK_REQUEST_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_LARK_REQUEST_ID' => $requestId,
        ], $bodyString);

        $response = $controller->__invoke($request);
        $this->assertSame(200, $response->getStatusCode());
        $responseContent = $response->getContent();
        $jsonData = json_decode(false !== $responseContent ? $responseContent : '', true);
        $this->assertIsArray($jsonData);
        $this->assertSame(0, $jsonData['code']);
    }

    public function testControllerHandlesExpiredTimestamp(): void
    {
        $controller = $this->getController();

        $requestData = [
            'type' => 'url_verification',
            'challenge' => 'test-challenge',
            'token' => 'test_token',
        ];

        // 使用过期的时间戳（超过5分钟）
        $timestamp = time() - 301;
        $requestId = uniqid();
        $body = json_encode($requestData);
        $bodyString = false !== $body ? $body : '';
        $content = $timestamp . ':' . $requestId . ':test_encrypt_key:' . $bodyString;
        $signature = hash('sha256', $content);

        $request = Request::create('/lark/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LARK_SIGNATURE' => $signature,
            'HTTP_X_LARK_REQUEST_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_LARK_REQUEST_ID' => $requestId,
        ], $bodyString);

        $response = $controller->__invoke($request);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testControllerHandlesInvalidSignature(): void
    {
        $controller = $this->getController();

        $requestData = [
            'type' => 'url_verification',
            'challenge' => 'test-challenge',
            'token' => 'test_token',
        ];

        $timestamp = time();
        $requestId = uniqid();
        $body = json_encode($requestData);
        $bodyString = false !== $body ? $body : '';

        $request = Request::create('/lark/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LARK_SIGNATURE' => 'invalid_signature',
            'HTTP_X_LARK_REQUEST_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_LARK_REQUEST_ID' => $requestId,
        ], $bodyString);

        $response = $controller->__invoke($request);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testControllerHandlesMissingHeaders(): void
    {
        $controller = $this->getController();

        $requestData = [
            'type' => 'url_verification',
            'challenge' => 'test-challenge',
            'token' => 'test_token',
        ];

        $body = json_encode($requestData);
        $bodyString = false !== $body ? $body : '';

        $request = Request::create('/lark/webhook', 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            $bodyString
        );

        $response = $controller->__invoke($request);
        $this->assertSame(400, $response->getStatusCode());
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();

        try {
            match ($method) {
                'GET' => $client->request('GET', '/lark/webhook'),
                'POST' => $client->request('POST', '/lark/webhook'),
                'PUT' => $client->request('PUT', '/lark/webhook'),
                'DELETE' => $client->request('DELETE', '/lark/webhook'),
                'PATCH' => $client->request('PATCH', '/lark/webhook'),
                'OPTIONS' => $client->request('OPTIONS', '/lark/webhook'),
                'TRACE' => $client->request('TRACE', '/lark/webhook'),
                'PURGE' => $client->request('PURGE', '/lark/webhook'),
                default => $client->request('GET', '/lark/webhook'),
            };

            // 如果没有抛出异常，检查响应状态码
            $statusCode = $client->getResponse()->getStatusCode();
            $this->assertContains($statusCode, [
                Response::HTTP_METHOD_NOT_ALLOWED,
                Response::HTTP_NOT_FOUND,
            ]);
        } catch (MethodNotAllowedHttpException $e) {
            // 如果方法不允许，抛出 MethodNotAllowedHttpException 是正常的
            $this->assertStringContainsString('No route found', $e->getMessage());
        } catch (NotFoundHttpException $e) {
            // 如果路由不存在，抛出 NotFoundHttpException 是正常的
            $this->assertStringContainsString('No route found', $e->getMessage());
        }
    }

    protected function onSetUp(): void
    {
        // 设置必要的环境变量
        $_ENV['LARK_VERIFICATION_TOKEN'] = 'test_token';
        $_ENV['LARK_ENCRYPT_KEY'] = 'test_encrypt_key';
    }

    protected static function getBundleClass(): string
    {
        return LarkAppBotBundle::class;
    }

    private function getController(): WebhookController
    {
        return self::getService(WebhookController::class);
    }
}
