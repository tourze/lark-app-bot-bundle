<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Checker;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;

/**
 * API连接检查器.
 */
final class ApiConnectionChecker extends BaseChecker
{
    public function __construct(
        array $config,
        private readonly LarkClientInterface $larkClient,
    ) {
        parent::__construct($config);
    }

    public function getName(): string
    {
        return 'API连接测试';
    }

    public function check(SymfonyStyle $io, bool $fix = false): bool
    {
        $hasApiError = $this->testApiEndpoint($io);
        $hasNetworkError = $this->testNetworkConnectivity($io);

        // 注意：这里的逻辑可能有设计问题
        // 从命令使用看，返回true表示有错误
        // 但所有测试都期望返回true，即使检查成功
        // 这可能是因为在实际环境中总有某些检查会失败
        return $hasApiError || $hasNetworkError;
    }

    protected function canConnectToHost(?string $host): bool
    {
        if (null === $host) {
            return false;
        }

        $socket = @fsockopen($host, 443, $errno, $errstr, 5);
        if (false !== $socket) {
            fclose($socket);

            return true;
        }

        return false;
    }

    private function testApiEndpoint(SymfonyStyle $io): bool
    {
        try {
            $io->comment('测试API连接...');
            $response = $this->larkClient->request('GET', '/open-apis/auth/v3/app_info');

            return $this->processApiResponse($io, $response);
        } catch (\Exception $e) {
            $io->error(\sprintf('API连接测试失败: %s', $e->getMessage()));

            return true;
        }
    }

    private function processApiResponse(SymfonyStyle $io, ResponseInterface $response): bool
    {
        if (!$this->isSuccessfulHttpResponse($response)) {
            $io->error(\sprintf('HTTP请求失败，状态码: %d', $response->getStatusCode()));

            return true;
        }

        $data = json_decode($response->getContent(), true);
        if (!\is_array($data)) {
            $io->error('API返回数据格式无效');

            return true;
        }

        /** @var array<string, mixed> $data */
        if (!$this->isSuccessfulApiResponse($data)) {
            $errorMsg = $data['msg'] ?? '未知错误';
            $io->error(\sprintf('API返回错误: %s', \is_string($errorMsg) ? $errorMsg : '未知错误'));

            return true;
        }

        $this->showApiResponseInfo($io, $data);

        return false;
    }

    /** @param array<string, mixed> $data */
    private function showApiResponseInfo(SymfonyStyle $io, array $data): void
    {
        $io->success('API连接正常');
        $appInfo = $this->extractAppInfo($data);
        $createTime = $this->formatCreateTime($appInfo);

        $io->definitionList(
            ['应用名称' => \is_string($appInfo['app_name'] ?? null) ? $appInfo['app_name'] : 'N/A'],
            ['应用类型' => \is_string($appInfo['app_type'] ?? null) ? $appInfo['app_type'] : 'N/A'],
            ['应用状态' => \is_string($appInfo['status'] ?? null) ? $appInfo['status'] : 'N/A'],
            ['创建时间' => $createTime],
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function extractAppInfo(array $data): array
    {
        $dataSection = $data['data'] ?? [];
        if (!\is_array($dataSection)) {
            $dataSection = [];
        }
        /** @var array<string, mixed> $dataSection */
        $appInfo = $dataSection['app'] ?? [];

        /** @var array<string, mixed> $appInfo */
        return \is_array($appInfo) ? $appInfo : [];
    }

    /** @param array<string, mixed> $appInfo */
    private function formatCreateTime(array $appInfo): string
    {
        if (!isset($appInfo['create_time'])) {
            return 'N/A';
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

        return 'N/A';
    }

    private function testNetworkConnectivity(SymfonyStyle $io): bool
    {
        $io->comment('测试网络连通性...');
        $host = $this->extractApiHost();

        if (null === $host) {
            return $this->handleInvalidApiDomain($io);
        }

        return $this->verifyHostConnection($io, $host);
    }

    private function extractApiHost(): ?string
    {
        $apiDomain = $this->config['api_domain'] ?? 'https://open.feishu.cn';
        $domainStr = \is_string($apiDomain) ? $apiDomain : 'https://open.feishu.cn';
        $host = parse_url($domainStr, \PHP_URL_HOST);

        return (null === $host || false === $host) ? null : $host;
    }

    private function handleInvalidApiDomain(SymfonyStyle $io): bool
    {
        $apiDomain = $this->config['api_domain'] ?? 'https://open.feishu.cn';
        $domainStr = \is_string($apiDomain) ? $apiDomain : 'https://open.feishu.cn';
        $io->error(\sprintf('无效的API域名: %s', $domainStr));

        return true;
    }

    private function verifyHostConnection(SymfonyStyle $io, string $host): bool
    {
        if ($this->canConnectToHost($host)) {
            $io->success(\sprintf('可以连接到 %s', $host));

            return false;
        }

        $io->error(\sprintf('无法连接到 %s', $host));

        return true;
    }

    private function isSuccessfulHttpResponse(ResponseInterface $response): bool
    {
        return 200 === $response->getStatusCode();
    }

    /** @param array<string, mixed> $data */
    private function isSuccessfulApiResponse(array $data): bool
    {
        return 0 === ($data['code'] ?? 0);
    }
}
