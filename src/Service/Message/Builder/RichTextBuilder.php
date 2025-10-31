<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Builder;

use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;

/**
 * 富文本消息构建器
 * 支持构建富文本格式的消息，包含多种文本样式和元素.
 */
class RichTextBuilder implements MessageBuilderInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $content = [];

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $currentParagraph = [];

    private string $currentLocale = 'zh_cn';

    /**
     * 设置当前语言
     *
     * @param string $locale 语言代码（如 zh_cn, en_us）
     */
    public function setLocale(string $locale): void
    {
        $this->currentLocale = $locale;
        if (!isset($this->content[$locale])) {
            $this->content[$locale] = [
                'title' => '',
                'content' => [],
            ];
            $this->currentParagraph[$locale] = [];
        }
    }

    /**
     * 设置标题.
     *
     * @param string      $title  标题内容
     * @param string|null $locale 语言代码，默认使用当前语言
     */
    public function setTitle(string $title, ?string $locale = null): void
    {
        $locale ??= $this->currentLocale;
        if (!isset($this->content[$locale])) {
            $this->content[$locale] = [
                'title' => '',
                'content' => [],
            ];
        }
        $this->content[$locale]['title'] = $title;
    }

    /**
     * 添加纯文本.
     *
     * @param string $text     文本内容
     * @param bool   $unescape 是否不转义（默认false）
     */
    public function addText(string $text, bool $unescape = false): self
    {
        $this->addElement([
            'tag' => 'text',
            'text' => $text,
            'un_escape' => $unescape,
        ]);

        return $this;
    }

    /**
     * 添加链接.
     *
     * @param string $text 链接文本
     * @param string $href 链接地址
     */
    public function addLink(string $text, string $href): self
    {
        $this->addElement([
            'tag' => 'a',
            'text' => $text,
            'href' => $href,
        ]);

        return $this;
    }

    /**
     * 添加@某人.
     *
     * @param string $userId   用户ID
     * @param string $userName 用户名称
     */
    public function atUser(string $userId, string $userName): self
    {
        $this->addElement([
            'tag' => 'at',
            'user_id' => $userId,
            'user_name' => $userName,
        ]);

        return $this;
    }

    /**
     * 添加@所有人.
     */
    public function atAll(): self
    {
        $this->addElement([
            'tag' => 'at',
            'user_id' => 'all',
            'user_name' => '所有人',
        ]);

        return $this;
    }

    /**
     * 添加图片.
     *
     * @param string   $imageKey 图片key
     * @param int|null $width    宽度（可选）
     * @param int|null $height   高度（可选）
     */
    public function addImage(string $imageKey, ?int $width = null, ?int $height = null): self
    {
        $element = [
            'tag' => 'img',
            'image_key' => $imageKey,
        ];

        if (null !== $width) {
            $element['width'] = $width;
        }
        if (null !== $height) {
            $element['height'] = $height;
        }

        $this->addElement($element);

        return $this;
    }

    /**
     * 添加表情.
     *
     * @param string $emojiType 表情类型
     */
    public function addEmoji(string $emojiType): self
    {
        $this->addElement([
            'tag' => 'emotion',
            'emoji_type' => $emojiType,
        ]);

        return $this;
    }

    /**
     * 添加加粗文本.
     *
     * @param string $text 文本内容
     */
    public function addBold(string $text): self
    {
        $this->addElement([
            'tag' => 'text',
            'text' => $text,
            'style' => ['bold' => true],
        ]);

        return $this;
    }

    /**
     * 添加斜体文本.
     *
     * @param string $text 文本内容
     */
    public function addItalic(string $text): self
    {
        $this->addElement([
            'tag' => 'text',
            'text' => $text,
            'style' => ['italic' => true],
        ]);

        return $this;
    }

    /**
     * 添加下划线文本.
     *
     * @param string $text 文本内容
     */
    public function addUnderline(string $text): self
    {
        $this->addElement([
            'tag' => 'text',
            'text' => $text,
            'style' => ['underline' => true],
        ]);

        return $this;
    }

    /**
     * 添加删除线文本.
     *
     * @param string $text 文本内容
     */
    public function addLineThrough(string $text): self
    {
        $this->addElement([
            'tag' => 'text',
            'text' => $text,
            'style' => ['lineThrough' => true],
        ]);

        return $this;
    }

    /**
     * 添加样式文本.
     *
     * @param string              $text   文本内容
     * @param array<string, bool> $styles 样式数组
     */
    public function addStyledText(string $text, array $styles): self
    {
        $this->addElement([
            'tag' => 'text',
            'text' => $text,
            'style' => $styles,
        ]);

        return $this;
    }

    /**
     * 开始新段落.
     */
    public function newParagraph(): self
    {
        if (isset($this->currentParagraph[$this->currentLocale]) && [] !== $this->currentParagraph[$this->currentLocale]) {
            $contentArray = $this->content[$this->currentLocale] ?? [];
            \assert(\is_array($contentArray));
            if (!isset($contentArray['content'])) {
                $contentArray['content'] = [];
            }
            \assert(\is_array($contentArray['content']));
            $contentArray['content'][] = $this->currentParagraph[$this->currentLocale];
            $this->content[$this->currentLocale] = $contentArray;
            $this->currentParagraph[$this->currentLocale] = [];
        }

        return $this;
    }

    /**
     * 添加换行.
     */
    public function addLineBreak(): self
    {
        $this->newParagraph();

        return $this;
    }

    public function getMsgType(): string
    {
        return MessageService::MSG_TYPE_RICH_TEXT;
    }

    public function build(): array
    {
        if (!$this->isValid()) {
            throw new ValidationException('富文本消息内容不能为空');
        }

        $this->ensureLocalesInitialized();
        $this->flushPendingParagraphs();

        return $this->content;
    }

    public function isValid(): bool
    {
        return $this->hasCurrentParagraphContent() || $this->hasExistingContent();
    }

    public function reset(): self
    {
        $this->content = [];
        $this->currentParagraph = [];
        $this->currentLocale = 'zh_cn';

        return $this;
    }

    public function toJson(): string
    {
        $json = json_encode($this->build(), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new ValidationException('Failed to encode rich text message to JSON');
        }

        return $json;
    }

    /**
     * 创建新实例.
     *
     * @param string $title  初始标题（可选）
     * @param string $locale 语言代码（可选）
     */
    public static function create(string $title = '', string $locale = 'zh_cn'): self
    {
        $builder = new self();
        $builder->setLocale($locale);
        if ('' !== $title) {
            $builder->setTitle($title);
        }

        return $builder;
    }

    /**
     * 从文本快速创建富文本消息.
     *
     * @param string $text   文本内容
     * @param string $locale 语言代码
     */
    public static function fromText(string $text, string $locale = 'zh_cn'): self
    {
        return self::create('', $locale)->addText($text);
    }

    /**
     * 确保所有使用的locale都有初始化的内容结构.
     */
    private function ensureLocalesInitialized(): void
    {
        foreach (array_keys($this->currentParagraph) as $locale) {
            if (!isset($this->content[$locale])) {
                $this->content[$locale] = [
                    'title' => '',
                    'content' => [],
                ];
            }
        }
    }

    /**
     * 将所有待处理的段落刷新到内容中.
     */
    private function flushPendingParagraphs(): void
    {
        foreach (array_keys($this->currentParagraph) as $locale) {
            if (isset($this->currentParagraph[$locale]) && [] !== $this->currentParagraph[$locale]) {
                $localeContent = $this->content[$locale] ?? [];
                \assert(\is_array($localeContent));
                if (!isset($localeContent['content'])) {
                    $localeContent['content'] = [];
                }
                \assert(\is_array($localeContent['content']));
                $localeContent['content'][] = $this->currentParagraph[$locale];
                $this->content[$locale] = $localeContent;
                $this->currentParagraph[$locale] = [];
            }
        }
    }

    /**
     * 检查当前段落是否有内容.
     */
    private function hasCurrentParagraphContent(): bool
    {
        foreach ($this->currentParagraph as $paragraph) {
            if ([] !== $paragraph) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查是否有已存在的内容.
     */
    private function hasExistingContent(): bool
    {
        if ([] === $this->content) {
            return false;
        }

        foreach ($this->content as $localeContent) {
            if ($this->isLocaleContentNotEmpty($localeContent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查特定语言的内容是否非空.
     */
    /**
     * @param array<string, mixed> $localeContent
     */
    private function isLocaleContentNotEmpty(array $localeContent): bool
    {
        return [] !== $localeContent['title'] || [] !== $localeContent['content'];
    }

    /**
     * 添加元素到当前段落.
     *
     * @param array<string, mixed> $element
     */
    private function addElement(array $element): void
    {
        if (!isset($this->currentParagraph[$this->currentLocale])) {
            $this->currentParagraph[$this->currentLocale] = [];
        }
        $this->currentParagraph[$this->currentLocale][] = $element;
    }
}
