<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

/**
 * 用户数据脱敏器.
 *
 * 负责对敏感用户信息进行脱敏处理
 */
final class UserDataMasker
{
    /**
     * 格式化用户联系方式.
     *
     * @param array<string, mixed> $user          用户信息
     * @param bool                 $maskSensitive 是否脱敏敏感信息
     *
     * @return array{
     *     email?: string,
     *     mobile?: string,
     *     enterprise_email?: string
     * }
     */
    public function formatContactInfo(array $user, bool $maskSensitive = false): array
    {
        $contact = [];

        $contact = $this->addPersonalEmail($contact, $user, $maskSensitive);
        $contact = $this->addMobile($contact, $user, $maskSensitive);

        return $this->addEnterpriseEmail($contact, $user, $maskSensitive);
    }

    /**
     * 邮箱脱敏.
     */
    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (2 !== \count($parts)) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        if (\strlen($name) <= 3) {
            $maskedName = substr($name, 0, 1) . '***';
        } else {
            $maskedName = substr($name, 0, 3) . '***';
        }

        return $maskedName . '@' . $domain;
    }

    /**
     * 手机号脱敏.
     */
    public function maskMobile(string $mobile): string
    {
        if (\strlen($mobile) < 7) {
            return $mobile;
        }

        return substr($mobile, 0, 3) . '****' . substr($mobile, -4);
    }

    /**
     * 添加个人邮箱.
     *
     * @param array<string, mixed> $contact
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    private function addPersonalEmail(array $contact, array $user, bool $maskSensitive): array
    {
        if (isset($user['email']) && '' !== $user['email']) {
            $contact['email'] = $maskSensitive
                ? $this->maskEmail($user['email'])
                : $user['email'];
        }

        return $contact;
    }

    /**
     * 添加手机号.
     *
     * @param array<string, mixed> $contact
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    private function addMobile(array $contact, array $user, bool $maskSensitive): array
    {
        $mobileVisible = (bool) ($user['mobile_visible'] ?? true);
        if (isset($user['mobile']) && '' !== $user['mobile'] && $mobileVisible) {
            $contact['mobile'] = $maskSensitive
                ? $this->maskMobile($user['mobile'])
                : $user['mobile'];
        }

        return $contact;
    }

    /**
     * 添加企业邮箱.
     *
     * @param array<string, mixed> $contact
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    private function addEnterpriseEmail(array $contact, array $user, bool $maskSensitive): array
    {
        if (isset($user['enterprise_email']) && '' !== $user['enterprise_email']) {
            $contact['enterprise_email'] = $maskSensitive
                ? $this->maskEmail($user['enterprise_email'])
                : $user['enterprise_email'];
        }

        return $contact;
    }
}
