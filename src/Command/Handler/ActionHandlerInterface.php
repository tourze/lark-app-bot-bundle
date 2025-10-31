<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command\Handler;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 群组管理操作处理器接口.
 */
interface ActionHandlerInterface
{
    /**
     * 获取支持的操作名称.
     */
    public function getActionName(): string;

    /**
     * 执行操作.
     *
     * @return int Command return code
     */
    public function execute(?string $chatId, InputInterface $input, SymfonyStyle $io): int;
}
