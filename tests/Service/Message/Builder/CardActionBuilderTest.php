<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\Message\Builder\CardActionBuilder;

/**
 * 卡片交互元素构建器测试.
 *
 * @internal
 */
#[CoversClass(CardActionBuilder::class)]
class CardActionBuilderTest extends TestCase
{
    private CardActionBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CardActionBuilder();
    }

    public function testBuildActionButtonsCreatesEmptyActionsForEmptyArray(): void
    {
        $result = $this->builder->buildActionButtons([]);

        $expected = [
            'tag' => 'action',
            'actions' => [],
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildActionButtonsCreatesBasicButton(): void
    {
        $buttons = [
            ['text' => 'Click Me'],
        ];

        $result = $this->builder->buildActionButtons($buttons);

        $expected = [
            'tag' => 'action',
            'actions' => [
                [
                    'tag' => 'button',
                    'text' => [
                        'content' => 'Click Me',
                        'tag' => 'plain_text',
                    ],
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildActionButtonsCreatesButtonWithAllProperties(): void
    {
        $buttons = [
            [
                'text' => 'Submit',
                'type' => 'primary',
                'url' => 'https://example.com',
                'value' => 'submit_action',
                'multi_url' => ['ios' => 'https://ios.example.com'],
            ],
        ];

        $result = $this->builder->buildActionButtons($buttons);

        $expected = [
            'tag' => 'action',
            'actions' => [
                [
                    'tag' => 'button',
                    'text' => [
                        'content' => 'Submit',
                        'tag' => 'plain_text',
                    ],
                    'type' => 'primary',
                    'url' => 'https://example.com',
                    'value' => 'submit_action',
                    'multi_url' => ['ios' => 'https://ios.example.com'],
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildActionButtonsHandlesMultipleButtons(): void
    {
        $buttons = [
            ['text' => 'Cancel', 'type' => 'default'],
            ['text' => 'Delete', 'type' => 'danger'],
        ];

        $result = $this->builder->buildActionButtons($buttons);

        $this->assertIsArray($result);
        $this->assertCount(2, $result['actions']);
        $this->assertSame('Cancel', $result['actions'][0]['text']['content']);
        $this->assertSame('Delete', $result['actions'][1]['text']['content']);
    }

    public function testBuildSelectMenuCreatesBasicSelect(): void
    {
        $options = [
            ['text' => 'Option 1', 'value' => 'opt1'],
            ['text' => 'Option 2', 'value' => 'opt2'],
        ];

        $result = $this->builder->buildSelectMenu('Choose option', $options);

        $expected = [
            'tag' => 'action',
            'actions' => [
                [
                    'tag' => 'select_static',
                    'placeholder' => [
                        'content' => 'Choose option',
                        'tag' => 'plain_text',
                    ],
                    'options' => [
                        [
                            'text' => [
                                'content' => 'Option 1',
                                'tag' => 'plain_text',
                            ],
                            'value' => 'opt1',
                        ],
                        [
                            'text' => [
                                'content' => 'Option 2',
                                'tag' => 'plain_text',
                            ],
                            'value' => 'opt2',
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildSelectMenuWithInitialValue(): void
    {
        $options = [
            ['text' => 'Option 1', 'value' => 'opt1'],
        ];

        $result = $this->builder->buildSelectMenu('Choose', $options, 'opt1');

        $this->assertSame('opt1', $result['actions'][0]['initial_option']);
    }

    public function testBuildSelectMenuHandlesEmptyOptions(): void
    {
        $result = $this->builder->buildSelectMenu('Choose', []);

        $this->assertEmpty($result['actions'][0]['options']);
    }

    public function testBuildDatePickerCreatesBasicDatePicker(): void
    {
        $result = $this->builder->buildDatePicker('Select date');

        $expected = [
            'tag' => 'action',
            'actions' => [
                [
                    'tag' => 'date_picker',
                    'placeholder' => [
                        'content' => 'Select date',
                        'tag' => 'plain_text',
                    ],
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildDatePickerWithInitialDate(): void
    {
        $result = $this->builder->buildDatePicker('Select date', '2023-12-25');

        $this->assertSame('2023-12-25', $result['actions'][0]['initial_date']);
    }

    public function testBuildTimePickerCreatesBasicTimePicker(): void
    {
        $result = $this->builder->buildTimePicker('Select time');

        $expected = [
            'tag' => 'action',
            'actions' => [
                [
                    'tag' => 'time_picker',
                    'placeholder' => [
                        'content' => 'Select time',
                        'tag' => 'plain_text',
                    ],
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildTimePickerWithInitialTime(): void
    {
        $result = $this->builder->buildTimePicker('Select time', '14:30');

        $this->assertSame('14:30', $result['actions'][0]['initial_time']);
    }

    public function testBuildActionButtonsHandlesMissingText(): void
    {
        $buttons = [
            ['type' => 'primary'],
        ];

        $result = $this->builder->buildActionButtons($buttons);

        $this->assertSame('', $result['actions'][0]['text']['content']);
    }

    public function testBuildSelectMenuHandlesMissingOptionProperties(): void
    {
        $options = [
            [], // 空选项
            ['text' => 'Valid Option'], // 缺失value
        ];

        $result = $this->builder->buildSelectMenu('Choose', $options);

        $this->assertSame('', $result['actions'][0]['options'][0]['text']['content']);
        $this->assertSame('', $result['actions'][0]['options'][0]['value']);
        $this->assertSame('Valid Option', $result['actions'][0]['options'][1]['text']['content']);
        $this->assertSame('', $result['actions'][0]['options'][1]['value']);
    }

    public function testBuildActionButtons(): void
    {
        // 测试基本按钮创建功能
        $buttons = [
            ['text' => 'Button 1', 'type' => 'primary'],
            ['text' => 'Button 2', 'url' => 'https://example.com'],
        ];

        $result = $this->builder->buildActionButtons($buttons);

        $this->assertSame('action', $result['tag']);
        $this->assertIsArray($result);
        $this->assertCount(2, $result['actions']);
        $this->assertSame('Button 1', $result['actions'][0]['text']['content']);
        $this->assertSame('primary', $result['actions'][0]['type']);
        $this->assertSame('Button 2', $result['actions'][1]['text']['content']);
        $this->assertSame('https://example.com', $result['actions'][1]['url']);
    }

    public function testBuildSelectMenu(): void
    {
        // 测试选择菜单创建功能
        $options = [
            ['text' => 'Option A', 'value' => 'a'],
            ['text' => 'Option B', 'value' => 'b'],
        ];

        $result = $this->builder->buildSelectMenu('Select option', $options, 'a');

        $this->assertSame('action', $result['tag']);
        $this->assertIsArray($result);
        $this->assertCount(1, $result['actions']);
        $this->assertSame('select_static', $result['actions'][0]['tag']);
        $this->assertSame('Select option', $result['actions'][0]['placeholder']['content']);
        $this->assertCount(2, $result['actions'][0]['options']);
        $this->assertSame('a', $result['actions'][0]['initial_option']);
    }

    public function testBuildDatePicker(): void
    {
        // 测试日期选择器创建功能
        $result = $this->builder->buildDatePicker('Pick date', '2023-12-25');

        $this->assertSame('action', $result['tag']);
        $this->assertIsArray($result);
        $this->assertCount(1, $result['actions']);
        $this->assertSame('date_picker', $result['actions'][0]['tag']);
        $this->assertSame('Pick date', $result['actions'][0]['placeholder']['content']);
        $this->assertSame('2023-12-25', $result['actions'][0]['initial_date']);
    }

    public function testBuildTimePicker(): void
    {
        // 测试时间选择器创建功能
        $result = $this->builder->buildTimePicker('Pick time', '14:30');

        $this->assertSame('action', $result['tag']);
        $this->assertIsArray($result);
        $this->assertCount(1, $result['actions']);
        $this->assertSame('time_picker', $result['actions'][0]['tag']);
        $this->assertSame('Pick time', $result['actions'][0]['placeholder']['content']);
        $this->assertSame('14:30', $result['actions'][0]['initial_time']);
    }
}
