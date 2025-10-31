<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * 无效的调试操作异常.
 */
final class InvalidDebugActionException extends LarkException
{
    /**
     * @param string $action 无效的操作名称
     */
    public function __construct(string $action)
    {
        parent::__construct(\sprintf('不支持的调试操作: %s', $action));
    }
}
