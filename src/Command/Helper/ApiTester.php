<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Helper;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;

/**
 * API测试辅助类.
 */
final class ApiTester
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly LarkClientInterface $larkClient,
        private readonly array $config,
    ) {
    }

    /**
     * 测试API连接.
     */
    public function testApiConnection(SymfonyStyle $io): bool
    {
        $io->section('API连接测试');

        try {
            return $this->testApiEndpoint($io);
        } catch (\Exception $e) {
            $io->error([
                'API连接测试失败',
                "错误: {$e->getMessage()}",
            ]);

            return true;
        }
    }

    /**
     * 测试网络连接.
     */
    public function testNetworkConnectivity(SymfonyStyle $io): bool
    {
        $io->section('网络连接测试');

        $host = $this->extractApiHost();

        if (null === $host || '' === $host) {
            return $this->handleInvalidApiDomain($io);
        }

        return $this->verifyHostConnection($io, $host);
    }

    /**
     * 测试API端点.
     */
    private function testApiEndpoint(SymfonyStyle $io): bool
    {
        $io->text('正在测试应用信息API...');

        $response = $this->larkClient->request('GET', '/open-apis/app/v6/info');

        return $this->processApiResponse($io, $response);
    }

    /**
     * 处理API响应.
     */
    private function processApiResponse(SymfonyStyle $io, ResponseInterface $response): bool
    {
        if (!$this->isSuccessfulHttpResponse($response)) {
            $io->error("HTTP请求失败，状态码: {$response->getStatusCode()}");

            return true;
        }

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        if (!$this->isSuccessfulApiResponse($data)) {
            $codeValue = $data['code'] ?? 'unknown';
            $code = \is_scalar($codeValue) ? (string) $codeValue : 'unknown';
            $msg = isset($data['msg']) && \is_string($data['msg']) ? $data['msg'] : 'unknown error';
            $io->error([
                'API调用失败',
                "错误码: {$code}",
                "错误信息: {$msg}",
            ]);

            return true;
        }

        $this->showApiResponseInfo($io, $data);

        return false;
    }

    /**
     * 显示API响应信息.
     *
     * @param array<string, mixed> $data
     */
    private function showApiResponseInfo(SymfonyStyle $io, array $data): void
    {
        $io->success('API连接正常');

        if (isset($data['data']) && \is_array($data['data'])) {
            /** @var array<string, mixed> $dataContent */
            $dataContent = $data['data'];
            $appInfo = $this->extractAppInfo($dataContent);

            if ([] !== $appInfo) {
                $io->table(['属性', '值'], array_map(
                    fn (string $key, string $value): array => [$key, $value],
                    array_keys($appInfo),
                    array_values($appInfo)
                ));
            }
        }
    }

    /**
     * 提取应用信息.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function extractAppInfo(array $data): array
    {
        $appInfo = [];

        if (isset($data['app_name']) && (\is_string($data['app_name']) || \is_int($data['app_name']))) {
            $appInfo['应用名称'] = (string) $data['app_name'];
        }

        if (isset($data['app_id']) && (\is_string($data['app_id']) || \is_int($data['app_id']))) {
            $appInfo['应用ID'] = (string) $data['app_id'];
        }

        if (isset($data['status']) && (\is_string($data['status']) || \is_int($data['status']))) {
            $appInfo['状态'] = (string) $data['status'];
        }

        if (isset($data['create_time'])) {
            $appInfo['创建时间'] = $this->formatCreateTime($data);
        }

        return $appInfo;
    }

    /**
     * 格式化创建时间.
     *
     * @param array<string, mixed> $appInfo
     */
    private function formatCreateTime(array $appInfo): string
    {
        if (!isset($appInfo['create_time'])) {
            return '未知';
        }

        $createTime = $appInfo['create_time'];

        // 处理时间戳类型
        if (\is_int($createTime)) {
            return date('Y-m-d H:i:s', $createTime);
        }

        // 处理字符串类型的数字
        if (\is_string($createTime) && is_numeric($createTime)) {
            return date('Y-m-d H:i:s', (int) $createTime);
        }

        // 处理字符串类型的时间格式
        if (\is_string($createTime)) {
            return $createTime;
        }

        return '未知';
    }

    /**
     * 提取API主机名.
     */
    private function extractApiHost(): ?string
    {
        $apiDomain = $this->getParameter('lark_app_bot.api_domain');

        if (!\is_string($apiDomain)) {
            return null;
        }

        $parsedUrl = parse_url($apiDomain);

        return $parsedUrl['host'] ?? null;
    }

    /**
     * 处理无效的API域名.
     */
    private function handleInvalidApiDomain(SymfonyStyle $io): bool
    {
        $io->error('无法解析API域名');

        return true;
    }

    /**
     * 验证主机连接.
     */
    private function verifyHostConnection(SymfonyStyle $io, string $host): bool
    {
        $io->text("正在测试与 {$host} 的连接...");

        if ($this->canConnectToHost($host)) {
            $io->success("网络连接正常: {$host}");

            return false;
        }

        $io->error([
            "无法连接到 {$host}",
            '可能的原因:',
            '  - 网络连接问题',
            '  - 防火墙阻止',
            '  - DNS解析失败',
        ]);

        return true;
    }

    /**
     * 测试是否能连接到指定主机.
     */
    private function canConnectToHost(?string $host): bool
    {
        if (null === $host || '' === $host) {
            return false;
        }

        $socket = @fsockopen($host, 443, $errno, $errstr, 5);

        if (false !== $socket) {
            fclose($socket);

            return true;
        }

        return false;
    }

    /**
     * 检查HTTP响应是否成功
     */
    private function isSuccessfulHttpResponse(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    /**
     * 检查API响应是否成功
     *
     * @param array<string, mixed> $data
     */
    private function isSuccessfulApiResponse(array $data): bool
    {
        return isset($data['code']) && 0 === $data['code'];
    }

    /**
     * 获取配置参数.
     */
    private function getParameter(string $name): mixed
    {
        $keys = explode('.', $name);
        $value = $this->config;

        foreach ($keys as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
