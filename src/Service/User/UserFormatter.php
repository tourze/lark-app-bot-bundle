<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

/**
 * 用户格式化器.
 *
 * 负责用户显示名称、头像URL等格式化功能
 */
class UserFormatter
{
    /**
     * 格式化用户显示名称.
     *
     * @param array<string, mixed> $user         用户信息
     * @param string               $locale       语言环境
     * @param bool                 $includeTitle 是否包含职位
     *
     * @return string 格式化后的显示名称
     */
    public function formatDisplayName(array $user, string $locale = 'zh_CN', bool $includeTitle = false): string
    {
        $name = $this->getFormattedUserName($user, $locale, $includeTitle);

        return '' !== $name ? $name : '未知用户';
    }

    /**
     * 获取用户头像URL.
     *
     * @param array<string, mixed> $user 用户信息
     * @param string               $size 头像尺寸（72, 240, 640）
     *
     * @return string|null 头像URL
     */
    public function getAvatarUrl(array $user, string $size = '240'): ?string
    {
        $avatar = $user['avatar'] ?? [];
        if (!\is_array($avatar)) {
            return null;
        }

        return match ($size) {
            '72' => $avatar['avatar_72'] ?? null,
            '240' => $avatar['avatar_240'] ?? $avatar['avatar_72'] ?? null,
            '640' => $avatar['avatar_640'] ?? $avatar['avatar_240'] ?? $avatar['avatar_72'] ?? null,
            'origin' => $avatar['avatar_origin'] ?? null,
            default => $avatar['avatar_240'] ?? $avatar['avatar_72'] ?? null,
        };
    }

    /**
     * 获取格式化的用户名称.
     *
     * @param array<string, mixed> $user
     */
    private function getFormattedUserName(array $user, string $locale, bool $includeTitle): string
    {
        $name = $this->extractDisplayName($user, $locale);

        return $this->appendJobTitle($name, $user, $includeTitle);
    }

    /**
     * 提取显示名称.
     *
     * @param array<string, mixed> $user
     */
    private function extractDisplayName(array $user, string $locale): string
    {
        $name = $this->getLocalizedName($user, $locale);
        if ('' !== $name) {
            return $name;
        }

        $name = $this->getNameByLocale($user, $locale);
        if ('' !== $name) {
            return $name;
        }

        return $user['nickname'] ?? '';
    }

    /**
     * 获取本地化名称.
     *
     * @param array<string, mixed> $user
     */
    private function getLocalizedName(array $user, string $locale): string
    {
        if (isset($user['display_name_i18n']) && [] !== $user['display_name_i18n'] && \is_array($user['display_name_i18n'])) {
            return $user['display_name_i18n'][$locale] ?? '';
        }

        return '';
    }

    /**
     * 根据语言环境获取名称.
     *
     * @param array<string, mixed> $user
     */
    private function getNameByLocale(array $user, string $locale): string
    {
        if ('zh_CN' === $locale || 'zh' === $locale) {
            return $user['name'] ?? $user['en_name'] ?? '';
        }

        return $user['en_name'] ?? $user['name'] ?? '';
    }

    /**
     * 添加职位信息.
     *
     * @param array<string, mixed> $user
     */
    private function appendJobTitle(string $name, array $user, bool $includeTitle): string
    {
        if ($includeTitle && isset($user['job_title']) && '' !== $user['job_title']) {
            return $name . ' (' . $user['job_title'] . ')';
        }

        return $name;
    }
}
