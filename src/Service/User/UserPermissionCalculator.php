<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

/**
 * 用户权限计算器.
 *
 * 负责用户权限解析和组织层级计算
 */
class UserPermissionCalculator
{
    /**
     * 解析用户权限.
     *
     * @param array<string, mixed> $user 用户信息
     *
     * @return string[] 权限列表
     */
    public function parsePermissions(array $user): array
    {
        $permissions = [];

        $permissions = $this->addTenantAdminPermissions($permissions, $user);
        $permissions = $this->addCustomAttributePermissions($permissions, $user);
        $permissions = $this->addPositionPermissions($permissions, $user);
        $permissions = $this->addEmployeeTypePermission($permissions, $user);

        return array_unique($permissions);
    }

    /**
     * 计算用户的组织层级.
     *
     * @param array<string, mixed> $user        用户信息
     * @param array<string, mixed> $departments 部门信息列表
     *
     * @return int 组织层级（0表示最高级）
     */
    public function calculateOrgLevel(array $user, array $departments = []): int
    {
        if (true === ($user['is_tenant_manager'] ?? false)) {
            return 0;
        }

        $levelByJobTitle = $this->getOrgLevelByJobTitle($user);
        if ($levelByJobTitle >= 0) {
            return $levelByJobTitle;
        }

        return $this->getOrgLevelByLeadership($user);
    }

    /**
     * 计算用户权限.
     *
     * @param array<string, mixed> $user 用户信息
     *
     * @return array<string, string[]> 包含各种权限分类的数组
     */
    public function calculateUserPermissions(array $user): array
    {
        return [
            'department_permissions' => $this->getDepartmentPermissions($user),
            'employee_permissions' => $this->getEmployeePermissions($user),
            'leadership_permissions' => $this->getLeadershipPermissions($user),
        ];
    }

    /**
     * 检查是否拥有指定权限.
     *
     * @param string[] $permissions 权限列表
     * @param string   $permission  要检查的权限
     */
    public function hasPermission(array $permissions, string $permission): bool
    {
        if (\in_array('*', $permissions, true)) {
            return true;
        }

        return \in_array($permission, $permissions, true);
    }

    /**
     * 添加租户管理员权限.
     *
     * @param string[]             $permissions
     * @param array<string, mixed> $user
     *
     * @return string[]
     */
    private function addTenantAdminPermissions(array $permissions, array $user): array
    {
        if (true === ($user['is_tenant_manager'] ?? false)) {
            $permissions[] = 'tenant_admin';
            $permissions[] = '*';
        }

        return $permissions;
    }

    /**
     * 从自定义属性添加权限.
     *
     * @param string[]             $permissions
     * @param array<string, mixed> $user
     *
     * @return string[]
     */
    private function addCustomAttributePermissions(array $permissions, array $user): array
    {
        $customAttrs = $user['custom_attrs'] ?? [];
        if (!\is_array($customAttrs)) {
            return $permissions;
        }

        foreach ($customAttrs as $attr) {
            if (!\is_array($attr)) {
                continue;
            }
            if ('permissions' === ($attr['key'] ?? '') && \is_array($attr['value'] ?? null)) {
                $permissions = array_merge($permissions, $attr['value']);
            }
        }

        return $permissions;
    }

    /**
     * 根据职位添加权限.
     *
     * @param string[]             $permissions
     * @param array<string, mixed> $user
     *
     * @return string[]
     */
    private function addPositionPermissions(array $permissions, array $user): array
    {
        $positions = $user['positions'] ?? [];
        if (!\is_array($positions)) {
            return $permissions;
        }

        foreach ($positions as $position) {
            if (!\is_array($position)) {
                continue;
            }
            if (isset($position['position_code']) && '' !== $position['position_code']) {
                $permissions[] = 'position:' . $position['position_code'];
            }
            if (isset($position['position_name']) && '' !== $position['position_name']) {
                $permissions[] = 'position_name:' . $position['position_name'];
            }
        }

        return $permissions;
    }

    /**
     * 根据员工类型添加权限.
     *
     * @param string[]             $permissions
     * @param array<string, mixed> $user
     *
     * @return string[]
     */
    private function addEmployeeTypePermission(array $permissions, array $user): array
    {
        $employeeType = $user['employee_type'] ?? 1;
        $permissions[] = 'employee_type:' . $employeeType;

        return $permissions;
    }

    /**
     * 根据职级名称获取组织层级.
     *
     * @param array<string, mixed> $user
     */
    private function getOrgLevelByJobTitle(array $user): int
    {
        $jobLevelName = $user['job_level_name'] ?? '';
        if ('' === $jobLevelName) {
            return -1;
        }

        return match (true) {
            str_contains($jobLevelName, 'VP') || str_contains($jobLevelName, '副总') => 2,
            str_contains($jobLevelName, 'CEO') || str_contains($jobLevelName, '总裁') => 1,
            str_contains($jobLevelName, 'Director') || str_contains($jobLevelName, '总监') => 3,
            str_contains($jobLevelName, 'Manager') || str_contains($jobLevelName, '经理') => 4,
            default => -1,
        };
    }

    /**
     * 获取部门权限.
     *
     * @param array<string, mixed> $user
     *
     * @return string[]
     */
    private function getDepartmentPermissions(array $user): array
    {
        $permissions = [];
        $departmentIds = $user['department_ids'] ?? [];

        if (!\is_array($departmentIds)) {
            return $permissions;
        }

        foreach ($departmentIds as $departmentId) {
            if (\is_string($departmentId) || \is_int($departmentId)) {
                $permissions[] = 'department:' . $departmentId;
            }
        }

        return $permissions;
    }

    /**
     * 获取员工权限.
     *
     * @param array<string, mixed> $user
     *
     * @return string[]
     */
    private function getEmployeePermissions(array $user): array
    {
        $permissions = [];
        $employeeType = $user['employee_type'] ?? 1;
        $permissions[] = 'employee_type:' . $employeeType;

        return $permissions;
    }

    /**
     * 获取领导权限.
     *
     * @param array<string, mixed> $user
     *
     * @return string[]
     */
    private function getLeadershipPermissions(array $user): array
    {
        $permissions = [];

        if (\array_key_exists('leader_user_id', $user) && '' !== $user['leader_user_id']) {
            $permissions[] = 'has_leader';
        } else {
            $permissions[] = 'is_leader';
        }

        return $permissions;
    }

    /**
     * 根据上下级关系获取组织层级.
     *
     * @param array<string, mixed> $user
     */
    private function getOrgLevelByLeadership(array $user): int
    {
        if (isset($user['leader_user_id']) && '' !== $user['leader_user_id']) {
            return 5;
        }

        return 6;
    }
}
