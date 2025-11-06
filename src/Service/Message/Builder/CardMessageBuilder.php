<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Builder;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\JsonEncodingException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 卡片消息构建器
 * 支持构建飞书卡片消息的JSON结构（Schema 2.0）.
 */
#[Autoconfigure(public: true)]
class CardMessageBuilder implements MessageBuilderInterface
{
    /**
     * 卡片配置.
     *
     * @var array<string, mixed>
     */
    private array $config = [
        'wide_screen_mode' => true,
    ];

    /**
     * 卡片头部.
     *
     * @var array<string, mixed>|null
     */
    private ?array $header = null;

    /**
     * 卡片元素列表.
     *
     * @var array<array<string, mixed>>
     */
    private array $elements = [];

    /**
     * 国际化内容.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $i18nElements = [];

    /**
     * 卡片模板
     */
    private ?string $templateId = null;

    /**
     * 模板变量.
     *
     * @var array<string, mixed>
     */
    private array $templateVariables = [];

    private readonly CardElementBuilder $elementBuilder;

    private readonly CardActionBuilder $actionBuilder;

    private readonly CardValidator $validator;

    public function __construct()
    {
        $this->elementBuilder = new CardElementBuilder();
        $this->actionBuilder = new CardActionBuilder();
        $this->validator = new CardValidator();
    }

    /**
     * 获取消息类型.
     */
    public function getMsgType(): string
    {
        return 'interactive';
    }

    /**
     * 构建消息内容.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        // 如果设置了模板ID，返回模板格式
        if (null !== $this->templateId) {
            return [
                'type' => 'template',
                'data' => [
                    'template_id' => $this->templateId,
                    'template_variable' => $this->templateVariables,
                ],
            ];
        }

        // 构建卡片JSON
        $card = [
            'schema' => '2.0',
            'config' => $this->config,
        ];

        if (null !== $this->header) {
            $card['header'] = $this->header;
        }

        // 如果有国际化内容，使用i18n_elements
        if ([] !== $this->i18nElements) {
            $card['i18n_elements'] = $this->i18nElements;
        } else {
            $card['elements'] = $this->elements;
        }

        return $card;
    }

    /**
     * 设置宽屏模式.
     *
     * @param bool $enable 是否启用宽屏模式
     */
    public function setWideScreenMode(bool $enable): void
    {
        $this->config['wide_screen_mode'] = $enable;
    }

    /**
     * 设置卡片更新模式.
     *
     * @param bool $enable 是否启用更新模式
     */
    public function setUpdateMode(bool $enable): void
    {
        if ($enable) {
            $this->config['update_multi'] = true;
        } else {
            unset($this->config['update_multi']);
        }
    }

    /**
     * 设置卡片头部.
     *
     * @param string      $title    标题
     * @param string|null $template 模板颜色 (blue|turquoise|orange|red|grey|green|purple|indigo|wathet)
     * @param string|null $icon     图标
     *
     */
    public function setHeader(string $title, ?string $template = null, ?string $icon = null): self
    {
        $this->header = [
            'title' => [
                'content' => $title,
                'tag' => 'plain_text',
            ],
        ];

        if (null !== $template) {
            $this->header['template'] = $template;
        }

        if (null !== $icon) {
            $this->header['icon'] = [
                'img_key' => $icon,
            ];
        }

        return $this;
    }

    /**
     * 添加文本元素.
     *
     * @param string $content  文本内容
     * @param bool   $isLarkMd 是否为Lark Markdown格式
     */
    public function addText(string $content, bool $isLarkMd = false): self
    {
        $this->elements[] = $this->elementBuilder->buildTextElement($content, $isLarkMd);

        return $this;
    }

    /**
     * 添加Markdown元素.
     *
     * @param string $content Markdown内容
     */
    public function addMarkdown(string $content): self
    {
        return $this->addText($content, true);
    }

    /**
     * 添加图片元素.
     *
     * @param string      $imageKey 图片key
     * @param string|null $alt      替代文本
     * @param string|null $title    标题
     */
    public function addImage(string $imageKey, ?string $alt = null, ?string $title = null): self
    {
        $this->elements[] = $this->elementBuilder->buildImageElement($imageKey, $alt, $title);

        return $this;
    }

    /**
     * 添加分割线
     */
    public function addDivider(): self
    {
        $this->elements[] = $this->elementBuilder->buildDividerElement();

        return $this;
    }

    /**
     * 添加按钮组.
     *
     * @param array<array<string, mixed>> $buttons 按钮配置数组
     */
    public function addActions(array $buttons): self
    {
        if ([] !== $buttons) {
            $this->elements[] = $this->actionBuilder->buildActionButtons($buttons);
        }

        return $this;
    }

    /**
     * 添加字段组.
     *
     * @param array<array<string, string>> $fields 字段数组
     * @param bool                         $short  是否为短字段（并排显示）
     */
    public function addFields(array $fields, bool $short = false): self
    {
        $fieldElements = [];

        foreach ($fields as $field) {
            $fieldElements[] = [
                'is_short' => $short,
                'text' => [
                    'content' => \sprintf('**%s**\n%s', $field['name'] ?? '', $field['value'] ?? ''),
                    'tag' => 'lark_md',
                ],
            ];
        }

        if ([] !== $fieldElements) {
            $this->elements[] = [
                'tag' => 'div',
                'fields' => $fieldElements,
            ];
        }

        return $this;
    }

    /**
     * 添加备注.
     *
     * @param array<string|array<string, mixed>> $elements 备注元素
     */
    public function addNote(array $elements): self
    {
        $noteElements = [];

        foreach ($elements as $element) {
            if (\is_string($element)) {
                $noteElements[] = [
                    'tag' => 'plain_text',
                    'content' => $element,
                ];
            } else {
                $noteElements[] = $element;
            }
        }

        if ([] !== $noteElements) {
            $this->elements[] = [
                'tag' => 'note',
                'elements' => $noteElements,
            ];
        }

        return $this;
    }

    /**
     * 添加列组件.
     *
     * @param array<array<string, mixed>> $columns 列配置
     */
    public function addColumnSet(array $columns): self
    {
        $columnElements = [];

        foreach ($columns as $column) {
            $columnElements[] = $this->buildColumn($column);
        }

        if ([] !== $columnElements) {
            $this->elements[] = [
                'tag' => 'column_set',
                'columns' => $columnElements,
            ];
        }

        return $this;
    }

    /**
     * 添加选择器.
     *
     * @param string                      $placeholder 占位符文本
     * @param array<array<string, mixed>> $options     选项列表
     * @param string|null                 $value       默认值
     */
    public function addSelectMenu(string $placeholder, array $options, ?string $value = null): self
    {
        $this->elements[] = $this->actionBuilder->buildSelectMenu($placeholder, $options, $value);

        return $this;
    }

    /**
     * 添加日期选择器.
     *
     * @param string      $placeholder 占位符文本
     * @param string|null $value       默认值（YYYY-MM-DD格式）
     */
    public function addDatePicker(string $placeholder, ?string $value = null): self
    {
        $this->elements[] = $this->actionBuilder->buildDatePicker($placeholder, $value);

        return $this;
    }

    /**
     * 添加时间选择器.
     *
     * @param string      $placeholder 占位符文本
     * @param string|null $value       默认值（HH:mm格式）
     */
    public function addTimePicker(string $placeholder, ?string $value = null): self
    {
        $this->elements[] = $this->actionBuilder->buildTimePicker($placeholder, $value);

        return $this;
    }

    /**
     * 添加输入框.
     *
     * @param string      $name        输入框名称
     * @param string      $placeholder 占位符
     * @param string|null $value       默认值
     * @param bool        $multiline   是否多行
     * @param int|null    $maxLength   最大长度
     */
    public function addInput(
        string $name,
        string $placeholder,
        ?string $value = null,
        bool $multiline = false,
        ?int $maxLength = null,
    ): self {
        $this->elements[] = $this->elementBuilder->buildInputElement($name, $placeholder, $value, $multiline, $maxLength);

        return $this;
    }

    /**
     * 添加国际化元素.
     *
     * @param array<string, array<array<string, mixed>>> $elements 各语言的元素
     */
    public function addI18nElements(array $elements): self
    {
        foreach ($elements as $locale => $localeElements) {
            if (!isset($this->i18nElements[$locale])) {
                $this->i18nElements[$locale] = [];
            }
            $this->i18nElements[$locale] = array_merge($this->i18nElements[$locale], $localeElements);
        }

        return $this;
    }

    /**
     * 使用模板
     *
     * @param string               $templateId 模板ID
     * @param array<string, mixed> $variables  模板变量
     */
    public function useTemplate(string $templateId, array $variables = []): self
    {
        $this->templateId = $templateId;
        $this->templateVariables = $variables;

        return $this;
    }

    /**
     * 添加自定义元素.
     *
     * @param array<string, mixed> $element 元素配置
     */
    public function addCustomElement(array $element): self
    {
        $this->elements[] = $element;

        return $this;
    }

    /**
     * 清空所有元素.
     */
    public function clear(): self
    {
        $this->elements = [];
        $this->i18nElements = [];
        $this->header = null;
        $this->templateId = null;
        $this->templateVariables = [];

        return $this;
    }

    /**
     * 获取预览数据.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        return $this->build();
    }

    /**
     * 验证卡片结构.
     *
     * @throws ValidationException
     */
    public function validate(): void
    {
        $card = $this->build();
        $this->validator->validate($card);
    }

    /**
     * 验证消息内容是否有效.
     */
    public function isValid(): bool
    {
        try {
            $this->validate();

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * 重置构建器状态
     */
    public function reset(): self
    {
        return $this->clear();
    }

    /**
     * 转换为JSON字符串.
     */
    public function toJson(): string
    {
        $json = json_encode($this->build(), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw JsonEncodingException::fromError(json_last_error_msg());
        }

        return $json;
    }

    /**
     * 构建单个列.
     *
     * @param array<string, mixed> $column
     *
     * @return array<string, mixed>
     */
    private function buildColumn(array $column): array
    {
        $columnElement = [
            'tag' => 'column',
            'width' => $column['width'] ?? 'auto',
            'elements' => $column['elements'] ?? [],
        ];

        if (isset($column['weight'])) {
            $columnElement['weight'] = $column['weight'];
        }

        if (isset($column['vertical_align'])) {
            $columnElement['vertical_align'] = $column['vertical_align'];
        }

        return $columnElement;
    }
}
