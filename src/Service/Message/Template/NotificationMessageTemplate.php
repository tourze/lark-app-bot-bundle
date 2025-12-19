<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Template;

use Tourze\LarkAppBotBundle\Service\Message\Builder\MessageBuilderInterface;
use Tourze\LarkAppBotBundle\Service\Message\Builder\RichTextBuilder;

/**
 * 通知消息模板
 * 用于系统通知、提醒等场景.
 */
final class NotificationMessageTemplate extends AbstractMessageTemplate
{
    public const LEVEL_INFO = 'info';
    public const LEVEL_SUCCESS = 'success';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    public function getName(): string
    {
        return 'notification_message';
    }

    public function getDescription(): string
    {
        return '系统通知消息模板，支持不同级别的通知';
    }

    public function render(array $variables = []): MessageBuilderInterface
    {
        $builder = RichTextBuilder::create();

        $this->renderTitle($builder, $variables);
        $this->renderContent($builder, $variables);
        $this->renderMentions($builder, $variables);
        $this->renderActions($builder, $variables);
        $this->renderTimestamp($builder, $variables);
        $this->renderLevelTip($builder, $variables);

        return $builder;
    }

    public function getRequiredVariables(): array
    {
        return [
            'title' => '通知标题',
            'content' => '通知内容',
        ];
    }

    /**
     * 渲染标题部分.
     *
     * @param array<string, mixed> $variables
     */
    private function renderTitle(RichTextBuilder $builder, array $variables): void
    {
        $title = $this->getVariable($variables, 'title');
        \assert(\is_string($title));

        $level = $this->getVariable($variables, 'level', self::LEVEL_INFO);
        \assert(\is_string($level));

        $icon = $this->getLevelIcon($level);

        $builder->setTitle($icon . ' ' . $title);
    }

    /**
     * 渲染内容部分.
     *
     * @param array<string, mixed> $variables
     */
    private function renderContent(RichTextBuilder $builder, array $variables): void
    {
        $content = $this->getVariable($variables, 'content');
        \assert(\is_string($content));
        $builder->addText($content)->newParagraph();
    }

    /**
     * 渲染提及人员部分.
     *
     * @param array<string, mixed> $variables
     */
    private function renderMentions(RichTextBuilder $builder, array $variables): void
    {
        $mentions = $this->getVariable($variables, 'mentions', []);
        \assert(\is_array($mentions));

        if ([] === $mentions) {
            return;
        }

        $builder->addLineBreak()->addText('相关人员：');

        foreach ($mentions as $mention) {
            \assert(\is_array($mention));

            $userId = $mention['user_id'] ?? '';
            \assert(\is_string($userId));

            $userName = $mention['user_name'] ?? '';
            \assert(\is_string($userName));

            $builder->addText(' ')
                ->atUser($userId, $userName)
            ;
        }

        $builder->newParagraph();
    }

    /**
     * 渲染操作按钮部分.
     *
     * @param array<string, mixed> $variables
     */
    private function renderActions(RichTextBuilder $builder, array $variables): void
    {
        $actions = $this->getVariable($variables, 'actions', []);
        \assert(\is_array($actions));

        if ([] === $actions) {
            return;
        }

        $builder->addLineBreak()
            ->addBold('可执行操作：')
            ->newParagraph()
        ;

        foreach ($actions as $action) {
            \assert(\is_array($action));
            /** @var array<string, mixed> $action */
            $this->renderSingleAction($builder, $action);
        }
    }

    /**
     * 渲染单个操作.
     *
     * @param array<string, mixed> $action
     */
    private function renderSingleAction(RichTextBuilder $builder, array $action): void
    {
        $builder->addText('→ ');

        $text = $action['text'] ?? null;
        \assert(\is_string($text));

        if (isset($action['url'])) {
            $url = $action['url'];
            \assert(\is_string($url));
            $builder->addLink($text, $url);
        } else {
            $builder->addText($text);
        }

        $builder->newParagraph();
    }

    /**
     * 渲染时间戳部分.
     *
     * @param array<string, mixed> $variables
     */
    private function renderTimestamp(RichTextBuilder $builder, array $variables): void
    {
        $time = $this->getVariable($variables, 'time', new \DateTime());
        \assert($time instanceof \DateTimeInterface || \is_string($time) || \is_int($time));

        $builder->addLineBreak()
            ->addText('通知时间：')
            ->addText($this->formatTime($time))
            ->newParagraph()
        ;
    }

    /**
     * 渲染级别提示.
     *
     * @param array<string, mixed> $variables
     */
    private function renderLevelTip(RichTextBuilder $builder, array $variables): void
    {
        $level = $this->getVariable($variables, 'level', self::LEVEL_INFO);
        \assert(\is_string($level));
        $this->addLevelTip($builder, $level);
    }

    /**
     * 获取级别对应的图标.
     */
    private function getLevelIcon(string $level): string
    {
        return match ($level) {
            self::LEVEL_SUCCESS => '✅',
            self::LEVEL_WARNING => '⚠️',
            self::LEVEL_ERROR => '❌',
            default => 'ℹ️',
        };
    }

    /**
     * 添加级别相关的提示.
     */
    private function addLevelTip(RichTextBuilder $builder, string $level): void
    {
        switch ($level) {
            case self::LEVEL_ERROR:
                $builder->addLineBreak()
                    ->addBold('请立即处理此错误！')
                ;
                break;
            case self::LEVEL_WARNING:
                $builder->addLineBreak()
                    ->addItalic('请注意关注此警告信息。')
                ;
                break;
        }
    }
}
