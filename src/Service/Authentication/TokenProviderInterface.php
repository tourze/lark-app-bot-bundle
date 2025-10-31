<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Authentication;

use Tourze\LarkAppBotBundle\Exception\AuthenticationException;

/**
 * 飞书应用访问令牌提供者接口.
 *
 * 用于获取和管理飞书应用的访问令牌
 */
interface TokenProviderInterface
{
    /**
     * 获取当前有效的访问令牌.
     *
     * 如果令牌已过期，应自动刷新并返回新的令牌
     *
     * @throws AuthenticationException 当无法获取有效令牌时
     */
    public function getToken(): string;

    /**
     * 强制刷新访问令牌.
     *
     * 无论当前令牌是否过期，都会获取新的令牌
     *
     * @throws AuthenticationException 当刷新失败时
     */
    public function refresh(): string;

    /**
     * 清除缓存的令牌.
     *
     * 下次调用getToken时将重新获取
     */
    public function clear(): void;

    /**
     * 检查当前令牌是否有效.
     *
     * @return bool true表示令牌有效，false表示需要刷新
     */
    public function isValid(): bool;

    /**
     * 获取令牌的过期时间.
     *
     * @return \DateTimeInterface|null 过期时间，如果没有令牌则返回null
     */
    public function getExpiresAt(): ?\DateTimeInterface;
}
