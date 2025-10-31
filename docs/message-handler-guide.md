# 消息处理器开发指南

## 概述

消息处理器是飞书机器人处理接收到的消息的核心组件。通过实现 `MessageHandlerInterface` 接口，你可以创建自定义的消息处理逻辑。

## 创建自定义消息处理器

### 1. 实现接口

```php
<?php

declare(strict_types=1);

namespace App\MessageHandler;

use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\Message\Handler\MessageHandlerInterface;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Message\MessageService;

class CustomMessageHandler implements MessageHandlerInterface
{
    public function __construct(
        private readonly MessageService $messageService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supports(MessageEvent $event): bool
    {
        // 判断是否支持处理该消息
        // 例如：只处理包含特定关键词的消息
        $content = $event->getPlainText();
        return str_contains($content, '/help');
    }

    public function handle(MessageEvent $event): void
    {
        // 处理消息逻辑
        $this->messageService->sendText(
            $event->getChatId(),
            '这是帮助信息...',
            $event->getMessageId() // 作为回复
        );
    }

    public function getPriority(): int
    {
        // 返回处理器优先级（数字越大优先级越高）
        return 100;
    }

    public function getName(): string
    {
        return 'custom_help_handler';
    }
}
```

### 2. 注册处理器

在 `services.yaml` 中注册你的处理器：

```yaml
services:
  App\MessageHandler\CustomMessageHandler:
    arguments:
      $messageService: '@Tourze\LarkAppBotBundle\Message\MessageService'
      $logger: '@logger'
    tags:
      - { name: lark.message_handler, priority: 100 }
```

## 使用抽象处理器

为了简化开发，你可以继承 `AbstractMessageHandler`：

```php
<?php

declare(strict_types=1);

namespace App\MessageHandler;

use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\Message\Handler\AbstractMessageHandler;

class CommandHandler extends AbstractMessageHandler
{
    public function supports(MessageEvent $event): bool
    {
        $text = $event->getPlainText();
        return str_starts_with($text, '/');
    }

    public function handle(MessageEvent $event): void
    {
        $text = $event->getPlainText();
        $command = explode(' ', $text)[0];

        match ($command) {
            '/help' => $this->handleHelp($event),
            '/status' => $this->handleStatus($event),
            '/about' => $this->handleAbout($event),
            default => $this->replyText($event, '未知命令：' . $command),
        };
    }

    private function handleHelp(MessageEvent $event): void
    {
        $this->replyRichText($event, [
            'zh_cn' => [
                'title' => '帮助信息',
                'content' => [
                    [
                        [
                            'tag' => 'text',
                            'text' => '可用命令：',
                        ],
                    ],
                    [
                        [
                            'tag' => 'text',
                            'text' => '/help - 显示帮助信息\n',
                        ],
                        [
                            'tag' => 'text',
                            'text' => '/status - 查看状态\n',
                        ],
                        [
                            'tag' => 'text',
                            'text' => '/about - 关于机器人',
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function handleStatus(MessageEvent $event): void
    {
        $this->replyText($event, '机器人运行正常！');
    }

    private function handleAbout(MessageEvent $event): void
    {
        $card = [
            'header' => [
                'title' => [
                    'content' => '关于机器人',
                    'tag' => 'plain_text',
                ],
            ],
            'elements' => [
                [
                    'tag' => 'div',
                    'text' => [
                        'content' => '这是一个基于飞书开放平台的机器人',
                        'tag' => 'plain_text',
                    ],
                ],
                [
                    'tag' => 'hr',
                ],
                [
                    'tag' => 'div',
                    'text' => [
                        'content' => '版本：1.0.0',
                        'tag' => 'plain_text',
                    ],
                ],
            ],
        ];

        $this->replyCard($event, $card);
    }

    public function getPriority(): int
    {
        return 200; // 高优先级
    }

    public function getName(): string
    {
        return 'command_handler';
    }
}
```

## 处理器链

多个处理器可以形成处理链，按优先级顺序执行：

```php
// 高优先级处理器（先执行）
class AuthenticationHandler extends AbstractMessageHandler
{
    public function supports(MessageEvent $event): bool
    {
        return true; // 处理所有消息
    }

    public function handle(MessageEvent $event): void
    {
        if (!$this->isAuthorized($event->getSenderId())) {
            $this->replyText($event, '您没有权限使用此机器人');
            $event->stopPropagation(); // 停止后续处理
        }
    }

    public function getPriority(): int
    {
        return 1000; // 最高优先级
    }
}

// 中等优先级处理器
class LoggingHandler extends AbstractMessageHandler
{
    public function supports(MessageEvent $event): bool
    {
        return true;
    }

    public function handle(MessageEvent $event): void
    {
        $this->log('收到消息', [
            'sender' => $event->getSenderId(),
            'content' => $event->getPlainText(),
        ]);
        // 不停止传播，让其他处理器继续处理
    }

    public function getPriority(): int
    {
        return 500;
    }
}
```

## 条件处理

根据不同条件选择性处理消息：

```php
class GroupMentionHandler extends AbstractMessageHandler
{
    public function supports(MessageEvent $event): bool
    {
        // 只处理群聊中@机器人的消息
        return $event->isGroupMessage() && $event->isMentionedBot();
    }

    public function handle(MessageEvent $event): void
    {
        $this->replyText($event, '收到！我会处理您的请求。');
    }
}

class PrivateMessageHandler extends AbstractMessageHandler
{
    public function supports(MessageEvent $event): bool
    {
        // 只处理私聊消息
        return $event->isPrivateMessage();
    }

    public function handle(MessageEvent $event): void
    {
        // 私聊处理逻辑
    }
}
```

## 异步处理

对于耗时操作，建议使用异步处理：

```php
class AsyncTaskHandler extends AbstractMessageHandler
{
    public function __construct(
        MessageService $messageService,
        LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct($messageService, $logger);
    }

    public function supports(MessageEvent $event): bool
    {
        return str_contains($event->getPlainText(), '/process');
    }

    public function handle(MessageEvent $event): void
    {
        // 立即回复
        $this->replyText($event, '任务已接收，正在处理中...');

        // 异步处理
        $this->messageBus->dispatch(new ProcessTaskMessage(
            $event->getChatId(),
            $event->getMessageId(),
            $event->getPlainText()
        ));
    }
}
```

## 最佳实践

1. **单一职责**：每个处理器应该只负责一种类型的消息处理
2. **优先级设计**：
   - 1000+：认证、权限检查
   - 500-999：日志、监控
   - 100-499：业务逻辑处理
   - 0-99：通用处理
   - 负数：默认处理器

3. **错误处理**：始终捕获异常，避免一个处理器的错误影响整个处理链

4. **性能考虑**：
   - 在 `supports()` 方法中快速判断
   - 耗时操作使用异步处理
   - 避免在处理器中进行大量计算

5. **日志记录**：使用提供的日志方法记录关键操作

## 调试技巧

1. 启用调试日志查看处理器执行顺序
2. 使用 `bin/console debug:container --tag=lark.message_handler` 查看所有注册的处理器
3. 在处理器中添加日志以跟踪执行流程