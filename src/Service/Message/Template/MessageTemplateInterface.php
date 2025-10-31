<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Template;

use Tourze\LarkAppBotBundle\Service\Message\Builder\MessageBuilderInterface;

/**
 * 消息模板接口
 * 定义了消息模板必须实现的方法.
 */
interface MessageTemplateInterface
{
    /**
     * 获取模板名称.
     */
    public function getName(): string;

    /**
     * 获取模板描述.
     */
    public function getDescription(): string;

    /**
     * 渲染模板
     *
     * @param array<string, mixed> $variables 模板变量
     */
    public function render(array $variables = []): MessageBuilderInterface;

    /**
     * 获取模板需要的变量列表.
     *
     * @return array<string, string> 变量名 => 变量描述
     */
    public function getRequiredVariables(): array;

    /**
     * 验证模板变量.
     *
     * @param array<string, mixed> $variables
     */
    public function validateVariables(array $variables): bool;
}
