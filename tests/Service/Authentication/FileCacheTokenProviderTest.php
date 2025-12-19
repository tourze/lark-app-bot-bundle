<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tourze\LarkAppBotBundle\Service\Authentication\FileCacheTokenProvider;
use Tourze\LarkAppBotBundle\Service\Authentication\TokenManager;
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

        // 创建 Mock 缓存服务
        $cache = new FilesystemAdapter(
            namespace: 'lark_app_bot',
            defaultLifetime: 0,
            directory: $this->cacheDir
        );

        // 创建 Mock TokenManager
        $tokenManager = new TokenManager($httpClient, $cache);

        // 将 Mock 服务注入到容器中
        self::getContainer()->set('http_client', $httpClient);
        self::getContainer()->set('lark_app_bot.cache', $cache);
        self::getContainer()->set(TokenManager::class, $tokenManager);

        // 从容器获取 FileCacheTokenProvider
        $provider = self::getContainer()->get(FileCacheTokenProvider::class);

        // 第一次调用，应该从API获取
        $token1 = $provider->getToken();
        $this->assertSame('test_token', $token1);

        // 验证只有一个请求被发送
        $this->assertSame(1, $httpClient->getRequestsCount());

        // 第二次调用，应该从缓存获取
        $token2 = $provider->getToken();
        $this->assertSame('test_token', $token2);

        // 验证缓存生效，请求次数没有增加
        $this->assertSame(1, $httpClient->getRequestsCount());
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

        // 创建 Mock 缓存服务
        $cache = new FilesystemAdapter(
            namespace: 'lark_app_bot',
            defaultLifetime: 0,
            directory: $this->cacheDir
        );

        // 创建 Mock TokenManager
        $tokenManager = new TokenManager($httpClient, $cache);

        // 将 Mock 服务注入到容器中
        self::getContainer()->set('http_client', $httpClient);
        self::getContainer()->set('lark_app_bot.cache', $cache);
        self::getContainer()->set(TokenManager::class, $tokenManager);

        // 从容器获取 FileCacheTokenProvider
        $provider = self::getContainer()->get(FileCacheTokenProvider::class);

        // 获取初始token
        $oldToken = $provider->getToken();
        $this->assertSame('old_token', $oldToken);

        // 验证发送了一个请求
        $this->assertSame(1, $httpClient->getRequestsCount());

        // 刷新token
        $newToken = $provider->refresh();
        $this->assertSame('new_token', $newToken);

        // 验证刷新时发送了第二个请求
        $this->assertSame(2, $httpClient->getRequestsCount());

        // 验证下次getToken返回新token（从缓存）
        $this->assertSame('new_token', $provider->getToken());

        // 验证没有发送新请求（使用缓存）
        $this->assertSame(2, $httpClient->getRequestsCount());
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

        // 创建 Mock 缓存服务
        $cache = new FilesystemAdapter(
            namespace: 'lark_app_bot',
            defaultLifetime: 0,
            directory: $this->cacheDir
        );

        // 创建 Mock TokenManager
        $tokenManager = new TokenManager($httpClient, $cache);

        // 将 Mock 服务注入到容器中
        self::getContainer()->set('http_client', $httpClient);
        self::getContainer()->set('lark_app_bot.cache', $cache);
        self::getContainer()->set(TokenManager::class, $tokenManager);

        // 从容器获取 FileCacheTokenProvider
        $provider = self::getContainer()->get(FileCacheTokenProvider::class);

        // 获取第一个token
        $token1 = $provider->getToken();
        $this->assertSame('first_token', $token1);

        // 验证发送了一个请求
        $this->assertSame(1, $httpClient->getRequestsCount());

        // 清除缓存
        $provider->clear();

        // 再次获取应该得到新的token
        $token2 = $provider->getToken();
        $this->assertSame('second_token', $token2);

        // 验证又发送了一个新请求
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    protected function onSetUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/lark_test_' . uniqid();
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0o777, true);
        }
    }
}
