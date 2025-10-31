<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Builder;

/**
 * 卡片元素构建器辅助类.
 */
class CardElementBuilder
{
    /**
     * 构建文本元素.
     *
     * @param string $content  文本内容
     * @param bool   $isLarkMd 是否为Lark Markdown格式
     *
     * @return array<string, mixed>
     */
    public function buildTextElement(string $content, bool $isLarkMd = false): array
    {
        return [
            'tag' => 'div',
            'text' => [
                'content' => $content,
                'tag' => $isLarkMd ? 'lark_md' : 'plain_text',
            ],
        ];
    }

    /**
     * 构建图片元素.
     *
     * @param string      $imageKey 图片key
     * @param string|null $alt      替代文本
     * @param string|null $title    标题
     *
     * @return array<string, mixed>
     */
    public function buildImageElement(string $imageKey, ?string $alt = null, ?string $title = null): array
    {
        $element = [
            'tag' => 'img',
            'img_key' => $imageKey,
        ];

        if (null !== $alt) {
            $element['alt'] = [
                'content' => $alt,
                'tag' => 'plain_text',
            ];
        }

        if (null !== $title) {
            $element['title'] = [
                'content' => $title,
                'tag' => 'plain_text',
            ];
        }

        return $element;
    }

    /**
     * 构建分割线元素.
     *
     * @return array<string, mixed>
     */
    public function buildDividerElement(): array
    {
        return ['tag' => 'hr'];
    }

    /**
     * 构建输入框元素.
     *
     * @param string      $name        输入框名称
     * @param string      $placeholder 占位符
     * @param string|null $value       默认值
     * @param bool        $multiline   是否多行
     * @param int|null    $maxLength   最大长度
     *
     * @return array<string, mixed>
     */
    public function buildInputElement(
        string $name,
        string $placeholder,
        ?string $value = null,
        bool $multiline = false,
        ?int $maxLength = null,
    ): array {
        $element = [
            'tag' => 'input',
            'name' => $name,
            'placeholder' => [
                'content' => $placeholder,
                'tag' => 'plain_text',
            ],
        ];

        if (null !== $value) {
            $element['default_value'] = $value;
        }

        if ($multiline) {
            $element['multiline'] = true;
        }

        if (null !== $maxLength) {
            $element['max_length'] = $maxLength;
        }

        return $element;
    }
}
