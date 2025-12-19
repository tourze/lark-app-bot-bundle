<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 用户比较器.
 *
 * 负责用户信息比较和摘要生成
 */
#[Autoconfigure(public: true)]
final class UserComparator
{
    public function __construct(
        private readonly UserStatusChecker $statusChecker,
        private readonly UserFormatter $formatter,
    ) {
    }

    /**
     * 比较两个用户信息的差异.
     *
     * @param array<string, mixed> $oldUser 旧用户信息
     * @param array<string, mixed> $newUser 新用户信息
     *
     * @return array{
     *     added: array<string, mixed>,
     *     removed: array<string, mixed>,
     *     changed: array<string, array{old: mixed, new: mixed}>
     * }
     */
    public function compareUserInfo(array $oldUser, array $newUser): array
    {
        /** @var array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{old: mixed, new: mixed}>} $result */
        $result = [
            'added' => [],
            'removed' => [],
            'changed' => [],
        ];

        $allKeys = array_unique(array_merge(array_keys($oldUser), array_keys($newUser)));

        return $this->processUserDifferences($allKeys, $oldUser, $newUser, $result);
    }

    /**
     * 生成用户摘要信息.
     *
     * @param array<string, mixed> $user 用户信息
     *
     * @return array<string, mixed> 摘要信息
     */
    public function generateUserSummary(array $user): array
    {
        $status = $this->statusChecker->checkUserStatus($user);

        $departmentIds = $user['department_ids'] ?? [];
        $departmentId = \is_array($departmentIds) && [] !== $departmentIds && isset($departmentIds[0]) && is_scalar($departmentIds[0]) ? (string) $departmentIds[0] : '';

        return [
            'id' => $user['user_id'] ?? $user['open_id'] ?? '',
            'name' => $this->formatter->formatDisplayName($user),
            'avatar' => $this->formatter->getAvatarUrl($user, '72'),
            'email' => $user['enterprise_email'] ?? $user['email'] ?? '',
            'mobile' => true === ($user['mobile_visible'] ?? true) ? $user['mobile'] ?? '' : '',
            'department' => $departmentId,
            'position' => $user['job_title'] ?? '',
            'status' => $status['status_text'],
            'is_active' => $status['is_active'],
        ];
    }

    /**
     * 处理用户信息差异.
     *
     * @param string[]                                                                                                                 $allKeys
     * @param array<string, mixed>                                                                                                     $oldUser
     * @param array<string, mixed>                                                                                                     $newUser
     * @param array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{old: mixed, new: mixed}>} $result
     *
     * @return array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{old: mixed, new: mixed}>}
     */
    private function processUserDifferences(array $allKeys, array $oldUser, array $newUser, array $result): array
    {
        foreach ($allKeys as $key) {
            $result = $this->compareFieldValue($key, $oldUser, $newUser, $result);
        }

        return $result;
    }

    /**
     * 比较字段值.
     *
     * @param array<string, mixed>                                                                                                     $oldUser
     * @param array<string, mixed>                                                                                                     $newUser
     * @param array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{old: mixed, new: mixed}>} $result
     *
     * @return array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{old: mixed, new: mixed}>}
     */
    private function compareFieldValue(string $key, array $oldUser, array $newUser, array $result): array
    {
        $oldValue = $oldUser[$key] ?? null;
        $newValue = $newUser[$key] ?? null;
        $oldExists = \array_key_exists($key, $oldUser);
        $newExists = \array_key_exists($key, $newUser);

        if (!$oldExists && $newExists) {
            $result['added'][$key] = $newValue;
        } elseif ($oldExists && !$newExists) {
            $result['removed'][$key] = $oldValue;
        } elseif ($oldExists && $newExists && $oldValue !== $newValue) {
            $result['changed'][$key] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $result;
    }
}
