<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 用户数据导出器.
 */
#[Autoconfigure(public: true)]
class UserDataExporter
{
    private const DATA_VERSION = '1.0';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $userData
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function export(string $userId, string $userIdType, array $userData, array $options = []): array
    {
        $exportData = $this->initializeExportData($userId, $userIdType);
        $exportData = $this->addDataByOptions($exportData, $userData, $options);

        $this->logger->info('导出用户数据成功', [
            'user_id' => $userId,
            'export_options' => $options,
        ]);

        return $exportData;
    }

    /**
     * @return array<string, mixed>
     */
    private function initializeExportData(string $userId, string $userIdType): array
    {
        return [
            'export_time' => time(),
            'export_version' => self::DATA_VERSION,
            'user_id' => $userId,
            'user_id_type' => $userIdType,
        ];
    }

    /**
     * @param array<string, mixed> $exportData
     * @param array<string, mixed> $userData
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function addDataByOptions(array $exportData, array $userData, array $options): array
    {
        if (true === ($options['include_basic_info'] ?? true)) {
            $exportData['basic_info'] = $userData['basic_info'];
        }

        if (true === ($options['include_departments'] ?? true)) {
            $exportData['departments'] = $userData['departments'];
        }

        if (true === ($options['include_permissions'] ?? true)) {
            $exportData['permissions'] = $userData['permissions'];
        }

        if (true === ($options['include_relations'] ?? true)) {
            $exportData = $this->addRelationData($exportData, $userData);
        }

        if (true === ($options['include_custom_data'] ?? true)) {
            $exportData['custom_data'] = $userData['custom_data'];
        }

        if (true === ($options['include_metadata'] ?? false)) {
            $exportData['metadata'] = $userData['metadata'];
        }

        return $exportData;
    }

    /**
     * @param array<string, mixed> $exportData
     * @param array<string, mixed> $userData
     *
     * @return array<string, mixed>
     */
    private function addRelationData(array $exportData, array $userData): array
    {
        $exportData['leader'] = $userData['leader'] ?? null;

        $subordinates = $userData['subordinates'] ?? [];
        if (!is_array($subordinates)) {
            $subordinates = [];
        }

        $exportData['subordinates'] = array_map(function ($subordinate) {
            // 确保$subordinate是数组类型
            if (!is_array($subordinate)) {
                return [
                    'user_id' => '',
                    'name' => '',
                    'email' => '',
                ];
            }

            $userId = '';
            if (isset($subordinate['user_id']) && is_scalar($subordinate['user_id'])) {
                $userId = (string) $subordinate['user_id'];
            }

            $name = '';
            if (isset($subordinate['name']) && is_scalar($subordinate['name'])) {
                $name = (string) $subordinate['name'];
            }

            $email = '';
            if (isset($subordinate['email']) && is_scalar($subordinate['email'])) {
                $email = (string) $subordinate['email'];
            }

            return [
                'user_id' => $userId,
                'name' => $name,
                'email' => $email,
            ];
        }, $subordinates);

        return $exportData;
    }
}
