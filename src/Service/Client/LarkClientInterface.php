<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * 飞书API客户端接口.
 *
 * 定义飞书API客户端的核心方法，方便测试和扩展
 */
interface LarkClientInterface extends HttpClientInterface
{
    /**
     * 获取基础URL.
     *
     * @return string 基础URL
     */
    public function getBaseUrl(): string;

    /**
     * 获取应用ID.
     *
     * @return string 应用ID
     */
    public function getAppId(): string;

    /**
     * 是否开启调试模式.
     *
     * @return bool 是否开启调试模式
     */
    public function isDebug(): bool;

    /**
     * 设置调试模式.
     *
     * @param bool $debug 是否开启调试模式
     */
    public function setDebug(bool $debug): void;
}
