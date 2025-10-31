<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Tourze\LarkAppBotBundle\Exception\AuthenticationException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClient;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(LarkClient::class)]
#[RunTestsInSeparateProcesses]
final class LarkClientTest extends AbstractIntegrationTestCase
{
    private LarkClient $client;

    public function testConstruct(): void
    {
        $this->assertInstanceOf(LarkClient::class, $this->client);
    }

    public function testRequest(): void
    {
        // 这个测试验证客户端的基本请求功能
        // 由于没有真实的认证信息，预期会抛出认证异常
        $this->expectException(AuthenticationException::class);
        $this->client->request('GET', '/open-apis/test');
    }

    public function testGetBaseUrl(): void
    {
        $result = $this->client->getBaseUrl();

        $this->assertSame('https://open.feishu.cn', $result);
    }

    public function testGetAppId(): void
    {
        $result = $this->client->getAppId();

        $this->assertSame('test-app-id', $result);
    }

    public function testIsDebug(): void
    {
        $result = $this->client->isDebug();

        // 在测试环境中，debug 默认是 true
        $this->assertTrue($result);
    }

    public function testSetDebug(): void
    {
        $this->client->setDebug(true);

        $this->assertTrue($this->client->isDebug());

        $this->client->setDebug(false);

        $this->assertFalse($this->client->isDebug());
    }

    public function testReset(): void
    {
        // 确保 reset 方法可以被调用而不抛出异常
        $this->client->reset();

        // 验证调用后客户端仍然正常工作
        $this->assertInstanceOf(LarkClient::class, $this->client);
        $this->assertSame('test-app-id', $this->client->getAppId());
    }

    public function testStream(): void
    {
        // 创建一个模拟的响应
        $mockResponse = $this->createMock(ResponseInterface::class);

        $result = $this->client->stream([$mockResponse]);

        $this->assertInstanceOf(ResponseStreamInterface::class, $result);
    }

    public function testWithOptions(): void
    {
        $options = ['timeout' => 10, 'max_redirects' => 5];

        $newClient = $this->client->withOptions($options);

        // 验证返回的是新的实例
        $this->assertInstanceOf(LarkClient::class, $newClient);
        $this->assertNotSame($this->client, $newClient);

        // 验证原实例的配置没有改变
        $this->assertSame('test-app-id', $this->client->getAppId());
        $this->assertSame('test-app-id', $newClient->getAppId());
    }

    protected function onSetUp(): void
    {
        // 设置环境变量
        $_ENV['LARK_APP_ID'] = 'test-app-id';
        $_ENV['LARK_APP_SECRET'] = 'test-app-secret';

        // 对于这个测试，我们使用真实的服务，而不是 Mock
        // 这样可以避免容器服务冲突问题
        $this->client = self::getService(LarkClient::class);

        // 如果测试需要特定的行为，可以在具体测试方法中进行 Mock
    }
}
