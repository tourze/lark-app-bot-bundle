<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;

/**
 * 用户搜索服务.
 *
 * 负责处理用户搜索相关功能
 */
#[Autoconfigure(public: true)]
final class UserSearchService
{
    public function __construct(
        private readonly LarkClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly UserDataService $userDataService,
    ) {
    }

    /**
     * 查找指定用户的下属.
     *
     * @return array<array<string, mixed>>
     * @throws ApiException
     */
    public function findSubordinates(string $userId): array
    {
        $subordinates = [];
        $pageToken = null;

        do {
            $result = $this->searchUsers([
                'page_token' => $pageToken,
                'page_size' => 100,
            ]);

            $subordinates = array_merge(
                $subordinates,
                $this->filterSubordinates($result['items'], $userId)
            );

            $pageToken = $result['page_token'] ?? null;
        } while ($result['has_more'] ?? false);

        return $subordinates;
    }

    /**
     * 搜索用户.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function searchUsers(array $params = []): array
    {
        try {
            return $this->performUserSearch($params);
        } catch (\Exception $e) {
            $this->handleSearchError($params, $e);
        }
    }

    /**
     * 查找指定用户的部门信息.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function findUserDepartments(string $userId): array
    {
        try {
            $response = $this->client->request('GET', '/open-apis/contact/v3/users/' . $userId, [
                'query' => [
                    'user_id_type' => 'open_id',
                    'department_id_type' => 'open_department_id',
                ],
            ]);

            $data = json_decode($response->getContent(), true);
            \assert(\is_array($data));
            $responseData = $data['data'] ?? [];
            \assert(\is_array($responseData));
            $userData = $responseData['user'] ?? [];
            \assert(\is_array($userData));
            $departmentIds = $userData['department_ids'] ?? [];
            \assert(\is_array($departmentIds));

            $this->logger->info('查找用户部门成功', [
                'user_id' => $userId,
                'department_count' => \count($departmentIds),
            ]);

            return [
                'items' => $this->formatDepartmentItems($departmentIds),
                'has_more' => false,
            ];
        } catch (\Exception $e) {
            $this->logger->error('查找用户部门失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw new GenericApiException('查找用户部门失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 执行用户搜索.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function performUserSearch(array $params): array
    {
        $query = $this->buildSearchQuery($params);
        $response = $this->executeSearch($query);
        $data = json_decode($response->getContent(), true);

        $this->logSearchSuccess($params, $data);

        return $this->formatSearchResult($data);
    }

    /**
     * 构建搜索查询参数.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    private function buildSearchQuery(array $params): array
    {
        $query = [];

        $query = $this->addBasicSearchParams($query, $params);

        return $this->addPaginationParams($query, $params);
    }

    /**
     * 添加基本搜索参数.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    private function addBasicSearchParams(array $query, array $params): array
    {
        if (isset($params['query'])) {
            $query['query'] = $params['query'];
        }
        if (isset($params['user_id_type'])) {
            $this->userDataService->validateUserIdType($params['user_id_type']);
            $query['user_id_type'] = $params['user_id_type'];
        }
        if (isset($params['department_id'])) {
            $query['department_id'] = $params['department_id'];
        }
        if (isset($params['include_resigned'])) {
            $query['include_resigned'] = !\in_array($params['include_resigned'], [false, null, '', 0], true) ? 'true' : 'false';
        }

        return $query;
    }

    /**
     * 添加分页参数.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function addPaginationParams(array $query, array $params): array
    {
        if (isset($params['page_token'])) {
            $query['page_token'] = $params['page_token'];
        }
        if (isset($params['page_size'])) {
            $query['page_size'] = min($params['page_size'], 100); // 最大100
        }

        return $query;
    }

    /**
     * 执行搜索请求.
     *
     * @param array<string, mixed> $query
     *
     * @throws \Exception
     */
    private function executeSearch(array $query): ResponseInterface
    {
        return $this->client->request('GET', '/open-apis/contact/v3/users/search', [
            'query' => $query,
        ]);
    }

    /**
     * 记录搜索成功日志.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $data
     */
    private function logSearchSuccess(array $params, array $data): void
    {
        $dataItems = $data['data'] ?? [];
        \assert(\is_array($dataItems));
        $items = $dataItems['items'] ?? [];
        \assert(\is_array($items));

        $this->logger->info('搜索用户成功', [
            'query' => $params['query'] ?? '',
            'result_count' => \count($items),
        ]);
    }

    /**
     * 格式化搜索结果.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function formatSearchResult(array $data): array
    {
        $resultData = $data['data'] ?? [];
        \assert(\is_array($resultData));

        return [
            'items' => $resultData['items'] ?? [],
            'has_more' => $resultData['has_more'] ?? false,
            'page_token' => $resultData['page_token'] ?? null,
        ];
    }

    /**
     * 处理搜索错误.
     *
     * @param array<string, mixed> $params
     *
     * @throws ApiException
     */
    private function handleSearchError(array $params, \Exception $e): never
    {
        $this->logger->error('搜索用户失败', [
            'params' => $params,
            'error' => $e->getMessage(),
        ]);
        throw new GenericApiException('搜索用户失败: ' . $e->getMessage(), 0, $e);
    }

    /**
     * 格式化部门项目.
     *
     * @param array<string> $departmentIds
     *
     * @return array<array<string, string>>
     */
    private function formatDepartmentItems(array $departmentIds): array
    {
        $items = [];
        foreach ($departmentIds as $departmentId) {
            $items[] = ['department_id' => $departmentId];
        }

        return $items;
    }

    /**
     * 过滤出指定用户的下属.
     *
     * @param array<array<string, mixed>> $items
     *
     * @return array<array<string, mixed>>
     */
    private function filterSubordinates(array $items, string $userId): array
    {
        $subordinates = [];
        foreach ($items as $item) {
            \assert(\is_array($item));
            $user = $item['user'] ?? [];
            \assert(\is_array($user));
            if (($user['leader_user_id'] ?? '') === $userId) {
                $subordinates[] = $user;
            }
        }

        return $subordinates;
    }
}
