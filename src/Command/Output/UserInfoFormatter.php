<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Output;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 用户信息格式化器.
 */
final class UserInfoFormatter
{
    /**
     * 显示基础用户信息.
     *
     * @param array<string, mixed> $userInfo
     */
    public function showBasicUserInfo(SymfonyStyle $io, array $userInfo): void
    {
        $io->definitionList(
            ['Open ID' => $userInfo['open_id'] ?? 'N/A'],
            ['用户ID' => $userInfo['user_id'] ?? 'N/A'],
            ['姓名' => $userInfo['name'] ?? 'N/A'],
            ['英文名' => $userInfo['en_name'] ?? 'N/A'],
            ['昵称' => $userInfo['nickname'] ?? 'N/A'],
            ['邮箱' => $userInfo['email'] ?? 'N/A'],
            ['手机' => $userInfo['mobile'] ?? 'N/A'],
            ['员工类型' => $userInfo['employee_type'] ?? 'N/A'],
            ['状态' => $this->formatStatus(isset($userInfo['status']) && \is_int($userInfo['status']) ? $userInfo['status'] : null)],
            ['入职时间' => isset($userInfo['join_time']) && \is_int($userInfo['join_time']) ? date('Y-m-d', $userInfo['join_time']) : 'N/A'],
            ['城市' => $userInfo['city'] ?? 'N/A'],
            ['国家' => $userInfo['country'] ?? 'N/A'],
        );
    }

    /**
     * 显示用户头像.
     *
     * @param array<string, mixed> $userInfo
     */
    public function showUserAvatar(SymfonyStyle $io, array $userInfo): void
    {
        if (isset($userInfo['avatar']) && \is_array($userInfo['avatar'])) {
            $avatar72 = $userInfo['avatar']['avatar_72'] ?? null;
            if (\is_string($avatar72) && '' !== $avatar72) {
                $io->note(\sprintf('头像: %s', $avatar72));
            }
        }
    }

    /**
     * 显示用户所在部门.
     *
     * @param array<string, mixed> $userInfo
     */
    public function showUserDepartments(SymfonyStyle $io, array $userInfo): void
    {
        if (!isset($userInfo['departments']) || [] === $userInfo['departments']) {
            return;
        }

        $io->section('所在部门');
        $rows = [];
        $departments = $userInfo['departments'];
        if (!\is_array($departments)) {
            return;
        }
        foreach ($departments as $dept) {
            if (!\is_array($dept)) {
                continue;
            }
            $path = $dept['path'] ?? [];
            $pathArray = \is_array($path) ? $path : [];
            $rows[] = [
                $dept['id'] ?? 'N/A',
                $dept['name'] ?? 'N/A',
                implode(' > ', $pathArray),
            ];
        }
        $io->table(['部门ID', '部门名称', '部门路径'], $rows);
    }

    /**
     * 显示用户所在群组.
     *
     * @param array<string, mixed> $userInfo
     */
    public function showUserGroups(SymfonyStyle $io, array $userInfo): void
    {
        if (!isset($userInfo['groups']) || [] === $userInfo['groups']) {
            return;
        }

        $io->section('所在群组');
        $rows = [];
        $groups = $userInfo['groups'];
        if (!\is_array($groups)) {
            return;
        }
        foreach ($groups as $group) {
            if (!\is_array($group)) {
                continue;
            }
            $rows[] = [
                $group['chat_id'] ?? 'N/A',
                $group['name'] ?? 'N/A',
                $group['member_count'] ?? 0,
            ];
        }
        $io->table(['群组ID', '群组名称', '成员数'], $rows);
    }

    /**
     * 输出表格格式.
     *
     * @param array<string, mixed> $userInfo
     */
    public function outputTable(SymfonyStyle $io, array $userInfo): void
    {
        $io->title('用户信息');
        $this->showBasicUserInfo($io, $userInfo);
        $this->showUserAvatar($io, $userInfo);
        $this->showUserDepartments($io, $userInfo);
        $this->showUserGroups($io, $userInfo);
    }

    /**
     * 格式化用户状态
     */
    private function formatStatus(?int $status): string
    {
        return match ($status) {
            1 => '已激活',
            2 => '已停用',
            4 => '未激活',
            5 => '已退出',
            null => 'N/A',
            default => \sprintf('未知(%d)', $status),
        };
    }
}
