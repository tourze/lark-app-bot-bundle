<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

/**
 * 用户状态检查器.
 *
 * 负责用户状态检查和判断
 */
class UserStatusChecker
{
    /**
     * 检查用户状态.
     *
     * @param array<string, mixed> $user 用户信息
     *
     * @return array{
     *     is_active: bool,
     *     is_frozen: bool,
     *     is_resigned: bool,
     *     status_text: string
     * }
     */
    public function checkUserStatus(array $user): array
    {
        $status = $user['status'] ?? [];
        assert(\is_array($status), 'User status must be an array');

        $isFrozen = (bool) ($user['is_frozen'] ?? false);
        $isActivated = (bool) ($status['is_activated'] ?? true);
        $isResigned = (bool) ($status['is_resigned'] ?? false);
        $isUnjoin = (bool) ($status['is_unjoin'] ?? false);
        $isExited = (bool) ($status['is_exited'] ?? false);

        $isActive = $isActivated && !$isResigned && !$isFrozen && !$isUnjoin && !$isExited;

        $statusText = $this->getStatusText($isFrozen, $isResigned, $isExited, $isUnjoin, $isActivated);

        return [
            'is_active' => $isActive,
            'is_frozen' => $isFrozen,
            'is_resigned' => $isResigned,
            'status_text' => $statusText,
        ];
    }

    /**
     * 获取状态文本.
     */
    private function getStatusText(
        bool $isFrozen,
        bool $isResigned,
        bool $isExited,
        bool $isUnjoin,
        bool $isActivated,
    ): string {
        return match (true) {
            $isFrozen => '已冻结',
            $isResigned => '已离职',
            $isExited => '已退出',
            $isUnjoin => '未加入',
            !$isActivated => '未激活',
            default => '正常',
        };
    }
}
