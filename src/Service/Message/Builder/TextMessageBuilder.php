<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Builder;

use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;

/**
 * 文本消息构建器
 * 支持构建简单的文本消息.
 */
class TextMessageBuilder implements MessageBuilderInterface
{
    private string $text = '';

    /**
     * 设置文本内容.
     *
     * @param string $text 文本内容
     *
     * @phpstan-ignore-next-line symplify.noReturnSetterMethod
     */
    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * 追加文本内容.
     *
     * @param string $text 要追加的文本
     */
    public function appendText(string $text): self
    {
        $this->text .= $text;

        return $this;
    }

    /**
     * 追加一行文本（自动添加换行符）.
     *
     * @param string $line 要追加的行
     */
    public function appendLine(string $line = ''): self
    {
        if ('' !== $this->text) {
            $this->text .= "\n";
        }
        $this->text .= $line;

        return $this;
    }

    /**
     * 添加@某人.
     *
     * @param string $userId 用户ID
     * @param string $name   显示的名称（可选）
     */
    public function atUser(string $userId, string $name = ''): self
    {
        if ('' === $name) {
            $this->text .= \sprintf('<at user_id="%s"></at>', $userId);
        } else {
            $this->text .= \sprintf('<at user_id="%s">%s</at>', $userId, $name);
        }

        return $this;
    }

    /**
     * 添加@所有人.
     */
    public function atAll(): self
    {
        $this->text .= '<at user_id="all">所有人</at>';

        return $this;
    }

    /**
     * 添加链接.
     *
     * @param string $url  链接地址
     * @param string $text 链接文本（可选）
     */
    public function addLink(string $url, string $text = ''): self
    {
        if ('' === $text) {
            $this->text .= $url;
        } else {
            $this->text .= \sprintf('<a href="%s">%s</a>', $url, $text);
        }

        return $this;
    }

    public function getMsgType(): string
    {
        return MessageService::MSG_TYPE_TEXT;
    }

    public function build(): array
    {
        if (!$this->isValid()) {
            throw new ValidationException('文本消息内容不能为空');
        }

        return [
            'text' => $this->text,
        ];
    }

    public function isValid(): bool
    {
        return '' !== $this->text;
    }

    public function reset(): self
    {
        $this->text = '';

        return $this;
    }

    public function toJson(): string
    {
        $json = json_encode($this->build(), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new ValidationException('Failed to encode text message to JSON');
        }

        return $json;
    }

    /**
     * 获取当前文本内容.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * 创建新实例.
     *
     * @param string $text 初始文本内容
     */
    public static function create(string $text = ''): self
    {
        $builder = new self();
        if ('' !== $text) {
            $builder->setText($text);
        }

        return $builder;
    }
}
