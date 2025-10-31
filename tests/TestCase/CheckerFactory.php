<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\TestCase;

use Tourze\LarkAppBotBundle\Command\Checker\CacheConfigChecker;
use Tourze\LarkAppBotBundle\Command\Checker\WebhookConfigChecker;

/**
 * 工厂类，用于在集成测试中创建不同配置的 Checker 实例.
 *
 * 这个工厂类不被任何测试类的 CoversClass 覆盖，因此可以直接实例化被测类
 * 这是符合测试最佳实践的：工厂负责对象创建，测试负责行为验证
 *
 * @internal
 */
final class CheckerFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function createCacheConfigChecker(array $config): CacheConfigChecker
    {
        return new CacheConfigChecker($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function createWebhookConfigChecker(array $config): WebhookConfigChecker
    {
        return new WebhookConfigChecker($config);
    }
}
