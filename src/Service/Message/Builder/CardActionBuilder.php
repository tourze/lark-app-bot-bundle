<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Builder;

/**
 * 卡片交互元素构建器.
 */
class CardActionBuilder
{
    /**
     * 构建按钮组.
     *
     * @param array<array<string, mixed>> $buttons 按钮配置数组
     *
     * @return array<string, mixed>
     */
    public function buildActionButtons(array $buttons): array
    {
        $actions = [];

        foreach ($buttons as $button) {
            $actions[] = $this->buildSingleButton($button);
        }

        return [
            'tag' => 'action',
            'actions' => $actions,
        ];
    }

    /**
     * 构建选择器.
     *
     * @param string                      $placeholder 占位符文本
     * @param array<array<string, mixed>> $options     选项列表
     * @param string|null                 $value       默认值
     *
     * @return array<string, mixed>
     */
    public function buildSelectMenu(string $placeholder, array $options, ?string $value = null): array
    {
        $selectOptions = [];

        foreach ($options as $option) {
            $selectOptions[] = [
                'text' => [
                    'content' => $option['text'] ?? '',
                    'tag' => 'plain_text',
                ],
                'value' => $option['value'] ?? '',
            ];
        }

        $element = [
            'tag' => 'select_static',
            'placeholder' => [
                'content' => $placeholder,
                'tag' => 'plain_text',
            ],
            'options' => $selectOptions,
        ];

        if (null !== $value) {
            $element['initial_option'] = $value;
        }

        return [
            'tag' => 'action',
            'actions' => [$element],
        ];
    }

    /**
     * 构建日期选择器.
     *
     * @param string      $placeholder 占位符文本
     * @param string|null $value       默认值（YYYY-MM-DD格式）
     *
     * @return array<string, mixed>
     */
    public function buildDatePicker(string $placeholder, ?string $value = null): array
    {
        $element = [
            'tag' => 'date_picker',
            'placeholder' => [
                'content' => $placeholder,
                'tag' => 'plain_text',
            ],
        ];

        if (null !== $value) {
            $element['initial_date'] = $value;
        }

        return [
            'tag' => 'action',
            'actions' => [$element],
        ];
    }

    /**
     * 构建时间选择器.
     *
     * @param string      $placeholder 占位符文本
     * @param string|null $value       默认值（HH:mm格式）
     *
     * @return array<string, mixed>
     */
    public function buildTimePicker(string $placeholder, ?string $value = null): array
    {
        $element = [
            'tag' => 'time_picker',
            'placeholder' => [
                'content' => $placeholder,
                'tag' => 'plain_text',
            ],
        ];

        if (null !== $value) {
            $element['initial_time'] = $value;
        }

        return [
            'tag' => 'action',
            'actions' => [$element],
        ];
    }

    /**
     * 构建单个按钮.
     *
     * @param array<string, mixed> $button
     *
     * @return array<string, mixed>
     */
    private function buildSingleButton(array $button): array
    {
        $action = [
            'tag' => 'button',
            'text' => [
                'content' => $button['text'] ?? '',
                'tag' => 'plain_text',
            ],
        ];

        return $this->addButtonProperties($action, $button);
    }

    /**
     * 添加按钮属性.
     *
     * @param array<string, mixed> $action
     * @param array<string, mixed> $button
     *
     * @return array<string, mixed>
     */
    private function addButtonProperties(array $action, array $button): array
    {
        // 设置按钮类型
        if (isset($button['type'])) {
            $action['type'] = $button['type']; // primary, default, danger
        }

        // 链接按钮
        if (isset($button['url'])) {
            $action['url'] = $button['url'];
        }

        // 回调按钮
        if (isset($button['value'])) {
            $action['value'] = $button['value'];
        }

        // 多端链接
        if (isset($button['multi_url'])) {
            $action['multi_url'] = $button['multi_url'];
        }

        return $action;
    }
}
