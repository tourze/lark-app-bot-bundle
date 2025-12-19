<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * 不支持的类型异常.
 *
 * 当提供的类型参数不在支持的范围内时抛出
 */
final class UnsupportedTypeException extends \InvalidArgumentException
{
    /**
     * @param string   $type       提供的类型
     * @param string[] $validTypes 有效的类型列表
     * @param string   $context    上下文描述（如：用户ID类型、成员类型等）
     */
    public static function create(string $type, array $validTypes, string $context = ''): self
    {
        $message = \sprintf(
            '不支持的%s类型: %s，有效类型为: %s',
            '' !== $context ? $context : '参数',
            $type,
            implode(', ', $validTypes)
        );

        return new self($message);
    }

    /**
     * 创建用户ID类型异常.
     */
    public static function forUserIdType(string $type): self
    {
        return self::create($type, ['open_id', 'union_id', 'user_id', 'email', 'mobile'], '用户ID');
    }

    /**
     * 创建成员类型异常.
     */
    public static function forMemberType(string $type): self
    {
        return self::create($type, ['user_id', 'union_id', 'open_id'], '成员');
    }

    /**
     * 创建输出格式异常.
     */
    public static function forOutputFormat(string $format): self
    {
        return self::create($format, ['table', 'json', 'csv'], '输出格式');
    }

    /**
     * 创建文件格式异常.
     */
    public static function forFileFormat(string $format): self
    {
        return self::create($format, ['json', 'yaml', 'php'], '文件格式');
    }
}
