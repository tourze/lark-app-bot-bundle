<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

/**
 * 飞书应用机器人Bundle扩展.
 */
final class LarkAppBotExtension extends AutoExtension
{
    public function getAlias(): string
    {
        return 'lark_app_bot';
    }

    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
