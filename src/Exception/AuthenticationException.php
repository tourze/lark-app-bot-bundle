<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * 身份认证异常.
 *
 * 当获取或刷新访问令牌失败时抛出
 */
final class AuthenticationException extends LarkException
{
}
