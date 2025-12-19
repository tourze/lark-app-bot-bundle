<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Checker;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 权限检查器.
 */
final class PermissionsChecker extends BaseChecker
{
    public function getName(): string
    {
        return '权限检查';
    }

    public function check(SymfonyStyle $io, bool $fix = false): bool
    {
        $requiredScopes = [
            'im:message' => '发送消息',
            'im:chat' => '群组管理',
            'contact:user.base:readonly' => '读取用户基本信息',
            'contact:user.email:readonly' => '读取用户邮箱',
            'contact:user.phone:readonly' => '读取用户手机号',
        ];

        $io->comment('建议配置以下权限范围：');

        $rows = [];
        foreach ($requiredScopes as $scope => $description) {
            $rows[] = [$scope, $description];
        }

        $io->table(['权限范围', '说明'], $rows);
        $io->note('请在飞书开放平台的"权限管理"中配置这些权限');

        return false; // 权限检查只是建议，不算错误
    }
}
