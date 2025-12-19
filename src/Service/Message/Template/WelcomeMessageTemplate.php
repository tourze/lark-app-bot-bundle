<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Template;

use Tourze\LarkAppBotBundle\Service\Message\Builder\MessageBuilderInterface;
use Tourze\LarkAppBotBundle\Service\Message\Builder\RichTextBuilder;

/**
 * 欢迎消息模板
 * 用于新用户加入时的欢迎消息.
 */
final class WelcomeMessageTemplate extends AbstractMessageTemplate
{
    public function getName(): string
    {
        return 'welcome_message';
    }

    public function getDescription(): string
    {
        return '新用户/新成员欢迎消息模板';
    }

    public function render(array $variables = []): MessageBuilderInterface
    {
        $builder = RichTextBuilder::create();

        $this->renderTitle($builder, $variables);
        $this->renderWelcomeMessage($builder, $variables);
        $this->renderRules($builder, $variables);
        $this->renderTips($builder, $variables);
        $this->renderFooter($builder);

        return $builder;
    }

    public function getRequiredVariables(): array
    {
        return [
            'user_name' => '用户名称',
            'user_id' => '用户ID',
        ];
    }

    /**
     * 渲染标题.
     *
     * @param array<string, mixed> $variables
     */
    private function renderTitle(RichTextBuilder $builder, array $variables): void
    {
        $groupName = $this->getVariable($variables, 'group_name', '');
        \assert(\is_string($groupName));

        if ('' !== $groupName) {
            $builder->setTitle(\sprintf('欢迎加入 %s', $groupName));
        } else {
            $builder->setTitle('欢迎');
        }
    }

    /**
     * 渲染欢迎消息.
     *
     * @param array<string, mixed> $variables
     */
    private function renderWelcomeMessage(RichTextBuilder $builder, array $variables): void
    {
        $userName = $this->getVariable($variables, 'user_name');
        \assert(\is_string($userName));

        $userId = $this->getVariable($variables, 'user_id');
        \assert(\is_string($userId));

        $groupName = $this->getVariable($variables, 'group_name', '');
        \assert(\is_string($groupName));

        $builder->addText('Hi，')
            ->atUser($userId, $userName)
            ->addText('，欢迎加入')
        ;

        if ('' !== $groupName) {
            $builder->addText(' ')->addBold($groupName);
        }

        $builder->addText('！')
            ->addEmoji('SMILE')
            ->newParagraph()
        ;
    }

    /**
     * 渲染群规则.
     *
     * @param array<string, mixed> $variables
     */
    private function renderRules(RichTextBuilder $builder, array $variables): void
    {
        $rules = $this->getVariable($variables, 'rules', []);
        \assert(\is_array($rules));

        if ([] === $rules) {
            return;
        }

        $builder->addLineBreak()
            ->addBold('群规则：')
            ->newParagraph()
        ;

        foreach ($rules as $index => $rule) {
            \assert(\is_string($rule));
            $builder->addText(\sprintf('%d. %s', $index + 1, $rule))
                ->newParagraph()
            ;
        }
    }

    /**
     * 渲染新手提示.
     *
     * @param array<string, mixed> $variables
     */
    private function renderTips(RichTextBuilder $builder, array $variables): void
    {
        $tips = $this->getVariable($variables, 'tips', []);
        \assert(\is_array($tips));

        if ([] === $tips) {
            return;
        }

        $builder->addLineBreak()
            ->addBold('新手提示：')
            ->newParagraph()
        ;

        foreach ($tips as $tip) {
            \assert(\is_string($tip));
            $builder->addText('• ' . $tip)
                ->newParagraph()
            ;
        }
    }

    /**
     * 渲染页脚消息.
     */
    private function renderFooter(RichTextBuilder $builder): void
    {
        $builder->addLineBreak()
            ->addText('如有任何问题，欢迎随时询问。祝您使用愉快！')
            ->addEmoji('THUMBSUP')
        ;
    }
}
