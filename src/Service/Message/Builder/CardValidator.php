<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Builder;

use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 卡片验证器.
 */
class CardValidator
{
    /**
     * 验证卡片结构.
     *
     * @param array<string, mixed> $card
     *
     * @throws ValidationException
     */
    public function validate(array $card): void
    {
        if ($this->isTemplateCard($card)) {
            $this->validateTemplateCard($card);

            return;
        }

        $this->validateCardElements($card);
        $this->validateElementCount($card);
        $this->validateCardHeader($card);
    }

    /**
     * 检查是否为模板卡片.
     *
     * @param array<string, mixed> $card
     */
    private function isTemplateCard(array $card): bool
    {
        return isset($card['type']) && 'template' === $card['type'];
    }

    /**
     * 验证模板卡片.
     *
     * @param array<string, mixed> $card
     *
     * @throws ValidationException
     */
    private function validateTemplateCard(array $card): void
    {
        $data = $card['data'] ?? [];
        \assert(\is_array($data));

        if (!isset($data['template_id']) || '' === $data['template_id']) {
            throw new ValidationException('模板ID不能为空');
        }
    }

    /**
     * 验证卡片元素.
     *
     * @param array<string, mixed> $card
     *
     * @throws ValidationException
     */
    private function validateCardElements(array $card): void
    {
        if ((!isset($card['elements']) || [] === $card['elements']) && (!isset($card['i18n_elements']) || [] === $card['i18n_elements'])) {
            throw new ValidationException('卡片至少需要包含一个元素');
        }
    }

    /**
     * 验证元素数量.
     *
     * @param array<string, mixed> $card
     *
     * @throws ValidationException
     */
    private function validateElementCount(array $card): void
    {
        $elements = $card['elements'] ?? [];
        \assert(\is_array($elements));
        $elementCount = [] !== $elements ? \count($elements) : 0;
        if ($elementCount > 50) {
            throw new ValidationException('卡片元素数量不能超过50个');
        }
    }

    /**
     * 验证卡片头部.
     *
     * @param array<string, mixed> $card
     *
     * @throws ValidationException
     */
    private function validateCardHeader(array $card): void
    {
        $header = $card['header'] ?? [];
        \assert(\is_array($header));

        /** @var array<string, mixed> $header */
        $this->validateHeaderTitle($header);
        $this->validateHeaderTemplate($header);
    }

    /**
     * 验证头部标题.
     *
     * @param array<string, mixed> $header
     *
     * @throws ValidationException
     */
    private function validateHeaderTitle(array $header): void
    {
        $title = $header['title'] ?? [];
        \assert(\is_array($title));

        if (!isset($title['content']) || '' === $title['content']) {
            throw new ValidationException('卡片头部标题不能为空');
        }
    }

    /**
     * 验证头部模板.
     *
     * @param array<string, mixed> $header
     *
     * @throws ValidationException
     */
    private function validateHeaderTemplate(array $header): void
    {
        if (!isset($header['template'])) {
            return;
        }

        $validTemplates = ['blue', 'turquoise', 'orange', 'red', 'grey', 'green', 'purple', 'indigo', 'wathet'];
        if (!\in_array($header['template'], $validTemplates, true)) {
            throw new ValidationException('无效的头部模板颜色');
        }
    }
}
