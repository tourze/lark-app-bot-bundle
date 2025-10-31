<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tourze\LarkAppBotBundle\Service\Authentication\FileCacheTokenProvider;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(FileCacheTokenProvider::class)]
#[RunTestsInSeparateProcesses]
final class FileCacheTokenProviderTest extends AbstractIntegrationTestCase
{
    private string $cacheDir;

    public function testTokenCaching(): void
    {
        $response = new MockResponse((string) json_encode([
            'code' => 0,
            'msg' => 'success',
            'app_access_token' => 'test_token',
            'expire' => 7200]));

        $httpClient = new MockHttpClient([$response]);

        // 设置环境变量，因为 TokenManager 从环境变量中获取配置
        $_ENV['LARK_APP_ID'] = 'test_app_id';
        $_ENV['LARK_APP_SECRET'] = 'test_app_secret';
        $_ENV['LARK_CACHE_DIR'] = $this->cacheDir;

        // 从服务容器中获取服务实例
        self::getContainer()->set('http_client', $httpClient);
        $provider = self::getService(FileCacheTokenProvider::class);

        // 第一次调用，应该从API获取
        $token1 = $provider->getToken();
        $this->assertSame('test_token', $token1);

        // 第二次调用，应该从缓存获取
        $token2 = $provider->getToken();
        $this->assertSame('test_token', $token2);

        // 验证缓存生效
    }

    public function testRefresh(): void
    {
        $responses = [
            new MockResponse((string) json_encode([
                'code' => 0,
                'msg' => 'success',
                'app_access_token' => 'old_token',
                'expire' => 7200])),
            new MockResponse((string) json_encode([
                'code' => 0,
                'msg' => 'success',
                'app_access_token' => 'new_token',
                'expire' => 7200]))];

        $httpClient = new MockHttpClient($responses);

        // 设置环境变量
        $_ENV['LARK_APP_ID'] = 'test_app_id';
        $_ENV['LARK_APP_SECRET'] = 'test_app_secret';
        $_ENV['LARK_CACHE_DIR'] = $this->cacheDir;

        self::getContainer()->set('http_client', $httpClient);
        $provider = self::getService(FileCacheTokenProvider::class);

        // 获取初始token
        $oldToken = $provider->getToken();
        $this->assertSame('old_token', $oldToken);

        // 刷新token
        $newToken = $provider->refresh();
        $this->assertSame('new_token', $newToken);

        // 验证下次getToken返回新token
        $this->assertSame('new_token', $provider->getToken());
    }

    public function testClearCache(): void
    {
        $responses = [
            new MockResponse((string) json_encode([
                'code' => 0,
                'msg' => 'success',
                'app_access_token' => 'first_token',
                'expire' => 7200])),
            new MockResponse((string) json_encode([
                'code' => 0,
                'msg' => 'success',
                'app_access_token' => 'second_token',
                'expire' => 7200]))];

        $httpClient = new MockHttpClient($responses);

        // 设置环境变量，因为 TokenManager 从环境变量中获取配置
        $_ENV['LARK_APP_ID'] = 'test_app_id';
        $_ENV['LARK_APP_SECRET'] = 'test_app_secret';
        $_ENV['LARK_CACHE_DIR'] = $this->cacheDir;

        // 从服务容器中获取服务实例
        self::getContainer()->set('http_client', $httpClient);
        $provider = self::getService(FileCacheTokenProvider::class);

        // 获取第一个token
        $token1 = $provider->getToken();
        $this->assertSame('first_token', $token1);

        // 清除缓存
        $provider->clear();

        // 再次获取应该得到新的token
        $token2 = $provider->getToken();
        $this->assertSame('second_token', $token2);
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/lark_test_' . uniqid();
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0o777, true);
        }
    }
}
