<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

/**
 * 用户权限提取器.
 */
final class UserPermissionExtractor
{
    /**
     * 提取用户权限信息.
     *
     * @param array<string, mixed> $basicInfo
     *
     * @return array<array<string, mixed>>
     */
    public function extractPermissions(array $basicInfo): array
    {
        $permissions = [];

        // 租户管理员拥有所有权限
        $isTenantManager = isset($basicInfo['is_tenant_manager']) && true === $basicInfo['is_tenant_manager'];
        if ($isTenantManager) {
            $permissions[] = ['name' => 'tenant_admin', 'scope' => 'global'];
            $permissions[] = ['name' => '*', 'scope' => 'global']; // 所有权限
        }

        // 从自定义属性中提取权限
        $permissions = array_merge($permissions, $this->extractCustomPermissions($basicInfo));

        // 根据职位提取权限
        $permissions = array_merge($permissions, $this->extractPositionPermissions($basicInfo));

        return $permissions;
    }

    /**
     * @param array<string, mixed> $basicInfo
     *
     * @return array<array<string, mixed>>
     */
    private function extractCustomPermissions(array $basicInfo): array
    {
        $permissions = [];
        $customAttrs = $basicInfo['custom_attrs'] ?? [];
        if (!is_iterable($customAttrs)) {
            return $permissions;
        }

        foreach ($customAttrs as $attr) {
            if (!is_array($attr)) {
                continue;
            }
            if (($attr['key'] ?? null) !== 'permissions') {
                continue;
            }

            $values = $attr['value'] ?? [];
            if (!\is_array($values)) {
                continue;
            }

            foreach ($values as $permission) {
                if (is_scalar($permission)) {
                    $permissions[] = ['name' => (string) $permission, 'scope' => 'custom'];
                }
            }
        }

        return $permissions;
    }

    /**
     * @param array<string, mixed> $basicInfo
     *
     * @return array<array<string, mixed>>
     */
    private function extractPositionPermissions(array $basicInfo): array
    {
        $permissions = [];
        $positions = $basicInfo['positions'] ?? [];
        if (!is_iterable($positions)) {
            $positions = [];
        }

        foreach ($positions as $position) {
            if (is_array($position) && isset($position['position_code']) && '' !== $position['position_code'] && [] !== $position['position_code']) {
                $positionCode = $position['position_code'];
                if (is_scalar($positionCode)) {
                    $permissions[] = [
                        'name' => 'position:' . (string) $positionCode,
                        'scope' => 'position',
                    ];
                }
            }
        }

        return $permissions;
    }
}
