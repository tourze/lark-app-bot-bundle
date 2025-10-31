<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Handler;

use Tourze\LarkAppBotBundle\Exception\UnsupportedTypeException;

/**
 * 操作处理器注册表.
 */
final class ActionHandlerRegistry
{
    /**
     * @var array<string, ActionHandlerInterface>
     */
    private array $handlers = [];

    /**
     * @param iterable<ActionHandlerInterface> $handlers
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->registerHandler($handler);
        }
    }

    /**
     * 注册处理器.
     */
    public function registerHandler(ActionHandlerInterface $handler): void
    {
        $this->handlers[$handler->getActionName()] = $handler;
    }

    /**
     * 获取处理器.
     */
    public function getHandler(string $action): ActionHandlerInterface
    {
        if (!isset($this->handlers[$action])) {
            throw UnsupportedTypeException::create($action, array_keys($this->handlers), '操作');
        }

        return $this->handlers[$action];
    }

    /**
     * 获取所有支持的操作名称.
     *
     * @return array<string>
     */
    public function getSupportedActions(): array
    {
        return array_keys($this->handlers);
    }
}
