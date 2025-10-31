<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Command;

final class CommandExecutionContext
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $type,
        public readonly bool $isBatch,
        public readonly bool $showDepartment,
        public readonly bool $showGroups,
        public readonly string $format,
        /** @var array<string> */ public readonly array $fields,
    ) {
    }
}
