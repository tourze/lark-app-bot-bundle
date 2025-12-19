<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;

/**
 * 用户基础数据获取服务.
 *
 * 负责与飞书API交互，获取用户基础信息
 */
#[Autoconfigure(public: true)]
final class UserDataService
{
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly LarkClientInterface $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 从API获取单个用户信息.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function fetchUser(string $userId, string $userIdType): array
    {
        try {
            $response = $this->client->request('GET', '/open-apis/contact/v3/users/' . $userId, [
                'query' => [
                    'user_id_type' => $userIdType,
                ],
            ]);

            $data = json_decode($response->getContent(), true);
            \assert(\is_array($data));
            $dataArray = $data['data'] ?? [];
            \assert(\is_array($dataArray));
            $user = $dataArray['user'] ?? [];
            \assert(\is_array($user));

            $this->logger->info('获取用户信息成功', [
                'user_id' => $userId,
                'user_id_type' => $userIdType,
                'user_name' => $user['name'] ?? '',
            ]);

            return $user;
        } catch (\Exception $e) {
            $this->logger->error('获取用户信息失败', [
                'user_id' => $userId,
                'user_id_type' => $userIdType,
                'error' => $e->getMessage(),
            ]);
            throw new GenericApiException('获取用户信息失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 批量获取用户信息.
     *
     * @param string[] $userIds
     *
     * @return array<string, array<string, mixed>>
     * @throws ApiException
     */
    public function batchFetchUsers(array $userIds, string $userIdType): array
    {
        if ([] === $userIds) {
            return [];
        }

        $result = [];
        $chunks = array_chunk($userIds, self::BATCH_SIZE);

        foreach ($chunks as $chunk) {
            $batchResult = $this->fetchBatchChunk($chunk, $userIdType);
            $result = array_merge($result, $batchResult);
        }

        $this->logger->info('批量获取用户信息成功', [
            'total_count' => \count($userIds),
            'fetched_count' => \count($result),
        ]);

        return $result;
    }

    /**
     * 根据用户ID类型获取对应的字段名.
     */
    public function getKeyFieldByType(string $userIdType): string
    {
        return match ($userIdType) {
            'open_id' => 'open_id',
            'union_id' => 'union_id',
            'user_id' => 'user_id',
            'email' => 'email',
            'mobile' => 'mobile',
            default => 'open_id',
        };
    }

    /**
     * 获取用户部门信息.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function fetchUserDepartments(string $userId, string $userIdType): array
    {
        try {
            $response = $this->client->request('GET', \sprintf('/open-apis/contact/v3/users/%s/departments', $userId), [
                'query' => [
                    'user_id_type' => $userIdType,
                    'page_size' => 100,
                ],
            ]);

            $data = json_decode($response->getContent(), true);
            \assert(\is_array($data));
            $departmentData = $data['data'] ?? [];
            \assert(\is_array($departmentData));
            $items = $departmentData['items'] ?? [];
            \assert(\is_array($items));

            $this->logger->info('获取用户部门列表成功', [
                'user_id' => $userId,
                'department_count' => \count($items),
            ]);

            return [
                'items' => $items,
                'has_more' => $departmentData['has_more'] ?? false,
                'page_token' => $departmentData['page_token'] ?? null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('获取用户部门列表失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw new GenericApiException('获取用户部门列表失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 验证用户ID类型.
     *
     * @throws ValidationException
     */
    public function validateUserIdType(string $userIdType): void
    {
        $validTypes = ['open_id', 'union_id', 'user_id', 'email', 'mobile'];
        if (!\in_array($userIdType, $validTypes, true)) {
            throw new ValidationException(\sprintf('无效的用户ID类型: %s，有效类型为: %s', $userIdType, implode(', ', $validTypes)));
        }
    }

    /**
     * 过滤返回的字段.
     *
     * @param array<string, mixed> $user
     * @param string[]             $fields
     *
     * @return array<string, mixed>
     */
    public function filterFields(array $user, array $fields): array
    {
        if ([] === $fields) {
            return $user;
        }

        $filtered = [];
        foreach ($fields as $field) {
            if (\array_key_exists($field, $user)) {
                $filtered[$field] = $user[$field];
            }
        }

        return $filtered;
    }

    /**
     * 获取单个批次的用户数据.
     *
     * @param string[] $userIds
     *
     * @return array<string, array<string, mixed>>
     * @throws ApiException
     */
    private function fetchBatchChunk(array $userIds, string $userIdType): array
    {
        try {
            $response = $this->client->request('POST', '/open-apis/contact/v3/users/batch', [
                'query' => [
                    'user_id_type' => $userIdType,
                ],
                'json' => [
                    'user_ids' => $userIds,
                ],
            ]);

            $data = json_decode($response->getContent(), true);
            \assert(\is_array($data));
            $batchData = $data['data'] ?? [];
            \assert(\is_array($batchData));
            $items = $batchData['items'] ?? [];
            \assert(\is_array($items));

            return $this->processUserItems($items, $userIdType);
        } catch (\Exception $e) {
            $this->logger->error('批量获取用户信息失败', [
                'user_ids' => $userIds,
                'error' => $e->getMessage(),
            ]);
            throw new GenericApiException('批量获取用户信息失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 处理用户项数据.
     *
     * @param array<array<string, mixed>> $items
     *
     * @return array<string, array<string, mixed>>
     */
    private function processUserItems(array $items, string $userIdType): array
    {
        $result = [];
        foreach ($items as $item) {
            $user = $item['user'] ?? null;
            if ($this->isValidUser($user)) {
                $userId = $this->extractUserIdByType($user, $userIdType);
                if (null !== $userId) {
                    $result[$userId] = $user;
                }
            }
        }

        return $result;
    }

    /**
     * 检查用户数据是否有效.
     */
    private function isValidUser(mixed $user): bool
    {
        return null !== $user && \is_array($user);
    }

    /**
     * 根据类型提取用户ID.
     *
     * @param array<string, mixed> $user
     */
    private function extractUserIdByType(array $user, string $userIdType): ?string
    {
        $keyField = $this->getKeyFieldByType($userIdType);
        $userId = $user[$keyField] ?? null;

        return (null !== $userId && '' !== $userId) ? $userId : null;
    }
}
