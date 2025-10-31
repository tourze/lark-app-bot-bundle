<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Tourze\LarkAppBotBundle\Service\Authentication\TokenProviderInterface;
use Tourze\LarkAppBotBundle\Service\Client\LarkClient;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * LarkClient 集成测试.
 *
 * @internal
 */
#[CoversClass(LarkClient::class)]
#[RunTestsInSeparateProcesses]
final class LarkClientIntegrationTest extends AbstractIntegrationTestCase
{
    private LarkClient $client;

    private MockHttpClient $mockHttpClient;

    public function testLarkClientIntegration(): void
    {
        // 使用 mock 响应，避免真实的 API 调用
        $mockResponses = [
            // API 请求响应
            new MockResponse((string) json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => ['user_id' => '12345'],
            ]), ['http_code' => 200]),
        ];

        $this->mockHttpClient = new MockHttpClient($mockResponses);
        $this->client = $this->createLarkClientWithMockHttp($this->mockHttpClient);

        // 发送请求
        $response = $this->client->request('GET', '/api/v1/user');

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        $this->assertSame(0, $content['code']);
        $this->assertSame('success', $content['msg']);
        $this->assertSame(['user_id' => '12345'], $content['data']);
    }

    public function testReset(): void
    {
        // 使用设置了正确环境变量的客户端
        $mockHttpClient = new MockHttpClient([]);
        $testClient = $this->createLarkClientWithMockHttp($mockHttpClient);

        // 确保 reset 方法可以被调用而不抛出异常
        $testClient->reset();

        // 验证调用后客户端仍然正常工作
        $this->assertInstanceOf(LarkClient::class, $testClient);
        $this->assertSame('test-app-id', $testClient->getAppId());
    }

    public function testStream(): void
    {
        // 创建 mock 响应
        $mockResponses = [
            new MockResponse((string) json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => ['test' => 'stream'],
            ]), ['http_code' => 200]),
        ];

        $this->mockHttpClient = new MockHttpClient($mockResponses);
        $this->client = $this->createLarkClientWithMockHttp($this->mockHttpClient);

        // 首先发起一个请求以获得响应
        $response = $this->client->request('GET', '/api/test');

        // 使用 stream 方法处理响应
        $stream = $this->client->stream([$response]);

        $this->assertInstanceOf(ResponseStreamInterface::class, $stream);
    }

    public function testRequest(): void
    {
        // 创建 mock 响应 - 注意：由于使用了 mock TokenProvider，不需要 token 请求响应
        $mockResponses = [
            // 第一个API请求响应
            new MockResponse((string) json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => ['test1' => 'value1'],
            ]), ['http_code' => 200]),
            // 第二个API请求响应
            new MockResponse((string) json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => ['test2' => 'value2'],
            ]), ['http_code' => 200]),
        ];

        $this->mockHttpClient = new MockHttpClient($mockResponses);
        $this->client = $this->createLarkClientWithMockHttp($this->mockHttpClient);

        // 发送多个请求
        $response1 = $this->client->request('GET', '/api/test1');
        $response2 = $this->client->request('POST', '/api/test2');

        // 验证响应内容
        $content1 = json_decode($response1->getContent(), true);
        $content2 = json_decode($response2->getContent(), true);

        // 调试输出，了解实际响应结构
        // var_dump('content1:', $content1);
        // var_dump('content2:', $content2);

        // 验证响应结构正确
        $this->assertIsArray($content1);
        $this->assertSame(0, $content1['code']);
        $this->assertArrayHasKey('data', $content1);
        $this->assertIsArray($content2);
        $this->assertSame(0, $content2['code']);
        $this->assertArrayHasKey('data', $content2);
    }

    public function testWithAuthentication(): void
    {
        // 创建 mock 响应
        $mockResponses = [
            // 认证API请求响应
            new MockResponse((string) json_encode([
                'code' => 0,
                'msg' => 'success',
                'data' => ['authenticated' => true],
            ]), ['http_code' => 200]),
        ];

        $this->mockHttpClient = new MockHttpClient($mockResponses);
        $this->client = $this->createLarkClientWithMockHttp($this->mockHttpClient);

        // 发送需要认证的请求
        $response = $this->client->request('GET', '/api/authenticated');
        $content = json_decode($response->getContent(), true);

        $this->assertIsArray($content);
        $this->assertSame(0, $content['code']);
        $this->assertArrayHasKey('data', $content);
    }

    public function testClientHasResetMethod(): void
    {
        // 使用设置了正确环境变量的客户端
        $mockHttpClient = new MockHttpClient([]);
        $testClient = $this->createLarkClientWithMockHttp($mockHttpClient);

        // 验证方法可以被调用 (PHPStan已知该方法存在)
        $testClient->reset();
        $this->assertInstanceOf(LarkClient::class, $testClient);
    }

    public function testWithOptions(): void
    {
        $client = self::getService(LarkClient::class);
        $new = $client->withOptions(['timeout' => 1.5]);
        $this->assertInstanceOf(LarkClient::class, $new);
    }

    protected function prepareMockServices(): void
    {
        // 设置测试环境变量
        $_ENV['LARK_APP_ID'] = 'test-app-id';
        $_ENV['LARK_APP_SECRET'] = 'test-app-secret';
    }

    protected function onSetUp(): void
    {
        // 对于 LarkClientIntegrationTest，由于每个测试都需要不同的 HttpClient
        // 我们不在这里初始化全局的 client，而是在各个测试方法中手动创建
    }

    /**
     * 创建使用 MockHttpClient 的 LarkClient 实例.
     * 从服务容器获取实例并注入Mock依赖.
     */
    private function createLarkClientWithMockHttp(MockHttpClient $mockHttpClient): LarkClient
    {
        // 创建Mock TokenProvider，避免真实的API调用
        $mockTokenProvider = $this->createMock(TokenProviderInterface::class);
        $mockTokenProvider->method('getToken')->willReturn('mock-access-token');

        // 注入Mock依赖到容器
        self::getContainer()->set('http_client', $mockHttpClient);
        self::getContainer()->set(TokenProviderInterface::class, $mockTokenProvider);
        // 不需要设置logger，使用默认的logger服务

        // 从容器获取 LarkClient 实例
        $larkClient = self::getService(LarkClient::class);

        // 设置appId用于某些测试
        $reflection = new \ReflectionClass($larkClient);
        $appIdProperty = $reflection->getProperty('appId');
        $appIdProperty->setAccessible(true);
        $appIdProperty->setValue($larkClient, 'test-app-id');

        return $larkClient;
    }
}
