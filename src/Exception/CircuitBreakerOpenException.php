<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * 熔断器打开异常.
 *
 * 当熔断器处于打开状态时抛出
 */
final class CircuitBreakerOpenException extends LarkException
{
}
