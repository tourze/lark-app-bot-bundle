<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Builder\Card;

/**
 * 按钮元素.
 */
class ButtonElement extends CardElement
{
    /**
     * 按钮类型常量.
     */
    public const TYPE_PRIMARY = 'primary';
    public const TYPE_DEFAULT = 'default';
    public const TYPE_DANGER = 'danger';

    /**
     * 按钮文本.
     */
    private string $text;

    /**
     * 按钮类型.
     */
    private string $type = self::TYPE_DEFAULT;

    /**
     * 构造函数.
     *
     * @param string $text 按钮文本
     */
    public function __construct(string $text)
    {
        parent::__construct();
        $this->text = $text;
        $this->buildData();
    }

    /**
     * 设置按钮文本.
     *
     * @param string $text 按钮文本
     */
    public function setText(string $text): void
    {
        $this->text = $text;
        $this->buildData();
    }

    /**
     * 设置按钮类型.
     *
     * @param string $type 按钮类型
     */
    public function setType(string $type): void
    {
        $this->type = $type;
        $this->buildData();
    }

    /**
     * 设置为主要按钮.
     */
    public function asPrimary(): void
    {
        $this->setType(self::TYPE_PRIMARY);
    }

    /**
     * 设置为默认按钮.
     */
    public function asDefault(): void
    {
        $this->setType(self::TYPE_DEFAULT);
    }

    /**
     * 设置为危险按钮.
     */
    public function asDanger(): void
    {
        $this->setType(self::TYPE_DANGER);
    }

    /**
     * 设置链接.
     *
     * @param string $url 链接地址
     */
    public function setUrl(string $url): void
    {
        $this->setData('url', $url);
    }

    /**
     * 设置回调值
     *
     * @param array<string, mixed>|string $value 回调值
     */
    public function setValue(array|string $value): void
    {
        $this->setData('value', $value);
    }

    /**
     * 设置多端链接.
     *
     * @param array<string, string> $urls 多端链接配置
     */
    public function setMultiUrl(array $urls): void
    {
        $this->setData('multi_url', $urls);
    }

    /**
     * 设置确认对话框.
     *
     * @param string $title   对话框标题
     * @param string $content 对话框内容
     */
    public function setConfirm(string $title, string $content): void
    {
        $this->setData('confirm', [
            'title' => [
                'content' => $title,
                'tag' => 'plain_text',
            ],
            'text' => [
                'content' => $content,
                'tag' => 'plain_text',
            ],
        ]);
    }

    /**
     * 获取元素标签.
     */
    protected function getTag(): string
    {
        return 'button';
    }

    /**
     * 构建数据.
     */
    private function buildData(): void
    {
        $this->setData('text', [
            'content' => $this->text,
            'tag' => 'plain_text',
        ]);
        $this->setData('type', $this->type);
    }
}
