<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 用户验证器.
 *
 * 负责用户ID验证和提取相关功能
 */
class UserValidator
{
    private const VALID_USER_ID_TYPES = ['open_id', 'union_id', 'user_id', 'email', 'mobile'];

    /**
     * 验证用户ID类型.
     *
     * @throws ValidationException
     */
    public function validateUserIdType(string $userIdType): void
    {
        if (!\in_array($userIdType, self::VALID_USER_ID_TYPES, true)) {
            throw new ValidationException(\sprintf('无效的用户ID类型: %s，有效类型为: %s', $userIdType, implode(', ', self::VALID_USER_ID_TYPES)));
        }
    }

    /**
     * 获取用户的所有可用ID.
     *
     * @param array<string, mixed> $user 用户信息
     *
     * @return array<string, string> ID类型 => ID值的映射
     */
    public function getAllUserIds(array $user): array
    {
        $ids = [];

        foreach (self::VALID_USER_ID_TYPES as $type) {
            $id = $this->extractUserId($user, $type);
            if (null !== $id) {
                $ids[$type] = $id;
            }
        }

        return $ids;
    }

    /**
     * 从用户信息中提取指定类型的ID.
     *
     * @param array<string, mixed> $user       用户信息
     * @param string               $targetType 目标ID类型
     *
     * @return string|null 用户ID，如果不存在则返回null
     */
    public function extractUserId(array $user, string $targetType): ?string
    {
        $id = match ($targetType) {
            'open_id' => $user['open_id'] ?? null,
            'union_id' => $user['union_id'] ?? null,
            'user_id' => $user['user_id'] ?? null,
            'email' => $user['email'] ?? null,
            'mobile' => $user['mobile'] ?? null,
            default => null,
        };

        return (\is_string($id) && '' !== $id) ? $id : null;
    }

    /**
     * 获取用户的主要部门ID.
     *
     * @param array<int, array<string, mixed>> $departments 用户的部门列表
     *
     * @return string|null 主要部门ID
     */
    public function getPrimaryDepartmentId(array $departments): ?string
    {
        foreach ($departments as $dept) {
            if (isset($dept['is_primary_dept']) && true === $dept['is_primary_dept']) {
                $deptId = $dept['department_id'] ?? null;

                return \is_string($deptId) ? $deptId : null;
            }
        }

        $deptId = $departments[0]['department_id'] ?? null;

        return \is_string($deptId) ? $deptId : null;
    }
}
