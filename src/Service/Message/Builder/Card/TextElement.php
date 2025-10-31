<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Builder\Card;

/**
 * 文本元素.
 */
class TextElement extends CardElement
{
    /**
     * 文本内容.
     */
    private string $content;

    /**
     * 文本标签类型.
     */
    private string $textTag;

    /**
     * 构造函数.
     *
     * @param string $content  文本内容
     * @param bool   $isLarkMd 是否为LarkMd格式
     */
    public function __construct(string $content, bool $isLarkMd = false)
    {
        parent::__construct();
        $this->content = $content;
        $this->textTag = $isLarkMd ? 'lark_md' : 'plain_text';
        $this->buildData();
    }

    /**
     * 设置文本内容.
     *
     * @param string $content 文本内容
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
        $this->buildData();
    }

    /**
     * 设置为Markdown格式.
     */
    public function asMarkdown(): void
    {
        $this->textTag = 'lark_md';
        $this->buildData();
    }

    /**
     * 设置为纯文本格式.
     */
    public function asPlainText(): void
    {
        $this->textTag = 'plain_text';
        $this->buildData();
    }

    /**
     * 获取元素标签.
     */
    protected function getTag(): string
    {
        return 'div';
    }

    /**
     * 构建数据.
     */
    private function buildData(): void
    {
        $this->setData('text', [
            'content' => $this->content,
            'tag' => $this->textTag,
        ]);
    }
}
