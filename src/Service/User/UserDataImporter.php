<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 用户数据导入器.
 */
#[Autoconfigure(public: true)]
class UserDataImporter
{
    private const DATA_VERSION = '1.0';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function import(array $data): array
    {
        $this->validateImportData($data);

        // 确保数组访问安全
        assert(array_key_exists('user_id', $data), 'user_id key must exist in data array');
        assert(array_key_exists('user_id_type', $data), 'user_id_type key must exist in data array');

        /** @var string $userId */
        $userId = $data['user_id'];
        /** @var string $userIdType */
        $userIdType = $data['user_id_type'];
        /** @var string|int $importTime */
        $importTime = $data['export_time'] ?? 'unknown';

        $userData = $this->buildUserDataFromImport($data);

        $this->logger->info('导入用户数据成功', [
            'user_id' => $userId,
            'user_id_type' => $userIdType,
            'import_time' => $importTime,
        ]);

        return $userData;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    private function validateImportData(array $data): void
    {
        if (!isset($data['user_id'], $data['user_id_type'], $data['export_version'])) {
            throw new ValidationException('导入数据格式无效：缺少必要字段');
        }

        if (self::DATA_VERSION !== $data['export_version']) {
            throw new ValidationException(\sprintf('导入数据版本不兼容：期望 %s，实际 %s', self::DATA_VERSION, $data['export_version']));
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function buildUserDataFromImport(array $data): array
    {
        // 安全地获取数组值，使用 array_key_exists 检查键存在性
        $basicInfo = array_key_exists('basic_info', $data) ? $data['basic_info'] : [];
        assert(is_array($basicInfo), 'basic_info must be an array');

        $departments = array_key_exists('departments', $data) ? $data['departments'] : [];
        assert(is_array($departments), 'departments must be an array');

        $permissions = array_key_exists('permissions', $data) ? $data['permissions'] : [];
        assert(is_array($permissions), 'permissions must be an array');

        $leader = array_key_exists('leader', $data) ? $data['leader'] : null;

        $subordinates = array_key_exists('subordinates', $data) ? $data['subordinates'] : [];
        assert(is_array($subordinates), 'subordinates must be an array');

        $customData = array_key_exists('custom_data', $data) ? $data['custom_data'] : [];
        assert(is_array($customData), 'custom_data must be an array');

        $exportTime = array_key_exists('export_time', $data) ? $data['export_time'] : time();
        assert(is_int($exportTime) || is_string($exportTime), 'export_time must be int or string');

        return [
            'basic_info' => $basicInfo,
            'departments' => $departments,
            'permissions' => $permissions,
            'leader' => $leader,
            'subordinates' => $subordinates,
            'custom_data' => $customData,
            'metadata' => [
                'version' => self::DATA_VERSION,
                'last_sync' => time(),
                'sync_status' => 'imported',
                'data_source' => 'import',
                'import_time' => $exportTime,
            ],
        ];
    }
}
