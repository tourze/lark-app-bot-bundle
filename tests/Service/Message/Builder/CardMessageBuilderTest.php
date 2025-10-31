<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Message\Builder\CardMessageBuilder;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CardMessageBuilder::class)]
#[RunTestsInSeparateProcesses]
final class CardMessageBuilderTest extends AbstractIntegrationTestCase
{
    private CardMessageBuilder $builder;

    public function testGetMsgType(): void
    {
        $this->assertSame('interactive', $this->builder->getMsgType());
    }

    public function testBasicCardStructure(): void
    {
        $this->builder->setHeader('测试标题');
        $result = $this->builder->build();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('schema', $result);
        $this->assertSame('2.0', $result['schema']);
        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('header', $result);
        $this->assertSame('测试标题', $result['header']['title']['content']);
    }

    public function testAddText(): void
    {
        $this->builder->addText('普通文本');
        $result = $this->builder->build();

        $this->assertIsArray($result);
        $this->assertCount(1, $result['elements']);
        $this->assertSame('div', $result['elements'][0]['tag']);
        $this->assertSame('普通文本', $result['elements'][0]['text']['content']);
        $this->assertSame('plain_text', $result['elements'][0]['text']['tag']);
    }

    public function testAddMarkdown(): void
    {
        $this->builder->addMarkdown('**粗体文本**');
        $result = $this->builder->build();

        $this->assertIsArray($result);
        $this->assertCount(1, $result['elements']);
        $this->assertSame('lark_md', $result['elements'][0]['text']['tag']);
        $this->assertSame('**粗体文本**', $result['elements'][0]['text']['content']);
    }

    public function testAddImage(): void
    {
        $this->builder->addImage('img_key_123', '图片说明', '图片标题');
        $result = $this->builder->build();

        $this->assertIsArray($result);
        $this->assertCount(1, $result['elements']);
        $element = $result['elements'][0];
        $this->assertSame('img', $element['tag']);
        $this->assertSame('img_key_123', $element['img_key']);
        $this->assertSame('图片说明', $element['alt']['content']);
        $this->assertSame('plain_text', $element['alt']['tag']);
        $this->assertSame('图片标题', $element['title']['content']);
        $this->assertSame('plain_text', $element['title']['tag']);
    }

    public function testAddActions(): void
    {
        $this->builder->addActions([
            ['text' => '确认', 'type' => 'primary', 'url' => 'https://example.com'],
            ['text' => '取消', 'type' => 'danger', 'value' => ['action' => 'cancel']],
        ]);
        $result = $this->builder->build();

        $this->assertIsArray($result);
        $this->assertCount(1, $result['elements']);
        $element = $result['elements'][0];
        $this->assertSame('action', $element['tag']);
        $this->assertIsArray($element);
        $this->assertCount(2, $element['actions']);

        // 第一个按钮
        $this->assertSame('button', $element['actions'][0]['tag']);
        $this->assertSame('确认', $element['actions'][0]['text']['content']);
        $this->assertSame('plain_text', $element['actions'][0]['text']['tag']);
        $this->assertSame('primary', $element['actions'][0]['type']);
        $this->assertSame('https://example.com', $element['actions'][0]['url']);

        // 第二个按钮
        $this->assertSame('取消', $element['actions'][1]['text']['content']);
        $this->assertSame('plain_text', $element['actions'][1]['text']['tag']);
        $this->assertSame('danger', $element['actions'][1]['type']);
        $this->assertSame(['action' => 'cancel'], $element['actions'][1]['value']);
    }

    public function testAddFields(): void
    {
        $this->builder->addFields([
            ['name' => '姓名', 'value' => '张三'],
            ['name' => '部门', 'value' => '技术部'],
        ], true);
        $result = $this->builder->build();

        $this->assertIsArray($result);
        $this->assertCount(1, $result['elements']);
        $element = $result['elements'][0];
        $this->assertSame('div', $element['tag']);
        $this->assertIsArray($element);
        $this->assertCount(2, $element['fields']);
        $this->assertTrue($element['fields'][0]['is_short']);
        $this->assertStringContainsString('**姓名**', $element['fields'][0]['text']['content']);
        $this->assertStringContainsString('张三', $element['fields'][0]['text']['content']);
    }

    public function testAddColumnSet(): void
    {
        $this->builder->addColumnSet([
            [
                'width' => 'weighted',
                'weight' => 1,
                'elements' => [
                    ['tag' => 'div', 'text' => ['content' => '左列', 'tag' => 'plain_text']],
                ],
            ],
            [
                'width' => 'weighted',
                'weight' => 2,
                'elements' => [
                    ['tag' => 'div', 'text' => ['content' => '右列', 'tag' => 'plain_text']],
                ],
            ],
        ]);
        $result = $this->builder->build();

        $this->assertIsArray($result);
        $this->assertCount(1, $result['elements']);
        $element = $result['elements'][0];
        $this->assertSame('column_set', $element['tag']);
        $this->assertIsArray($element);
        $this->assertCount(2, $element['columns']);
    }

    public function testAddSelectMenu(): void
    {
        $this->builder->addSelectMenu('请选择', [['text' => 'A', 'value' => 'a']], 'a');
        $this->assertIsArray($this->builder->build());
    }

    public function testAddTimePicker(): void
    {
        $this->builder->addTimePicker('选择时间', '10:30');
        $this->assertIsArray($this->builder->build());
    }

    public function testAddNote(): void
    {
        $this->builder->addNote(['备注']);
        $this->assertIsArray($this->builder->build());
    }

    public function testBuild(): void
    {
        $this->builder->addText('t');
        $built = $this->builder->build();
        $this->assertIsArray($built);
    }

    public function testAddInput(): void
    {
        $this->builder->addInput('username', '请输入用户名', 'admin', false, 50);
        $result = $this->builder->build();

        $this->assertIsArray($result);
        $this->assertCount(1, $result['elements']);
        $element = $result['elements'][0];
        $this->assertSame('input', $element['tag']);
        $this->assertSame('username', $element['name']);
        $this->assertSame('请输入用户名', $element['placeholder']['content']);
        $this->assertSame('admin', $element['default_value']);
        $this->assertSame(50, $element['max_length']);
    }

    public function testUseTemplate(): void
    {
        $this->builder->useTemplate('template_123', ['var1' => 'value1']);
        $result = $this->builder->build();

        $this->assertSame('template', $result['type']);
        $this->assertSame('template_123', $result['data']['template_id']);
        $this->assertSame(['var1' => 'value1'], $result['data']['template_variable']);
    }

    public function testValidationEmptyCard(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('卡片至少需要包含一个元素');
        $this->builder->validate();
    }

    public function testValidationTooManyElements(): void
    {
        for ($i = 0; $i < 51; ++$i) {
            $this->builder->addText("元素 {$i}");
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('卡片元素数量不能超过50个');
        $this->builder->validate();
    }

    public function testValidationInvalidHeaderTemplate(): void
    {
        $this->builder->setHeader('标题', 'invalid_color');
        $this->builder->addText('内容');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('无效的头部模板颜色');
        $this->builder->validate();
    }

    public function testComplexCard(): void
    {
        $this->builder->setHeader('任务详情', 'turquoise');
        $this->builder
            ->addText('**任务描述**', true)
            ->addText('完成产品功能开发')
            ->addDivider()
            ->addFields([
                ['name' => '负责人', 'value' => '李四'],
                ['name' => '截止日期', 'value' => '2024-12-31'],
            ], true)
            ->addDivider()
            ->addNote(['创建时间：2024-01-01', '优先级：高'])
            ->addActions([
                ['text' => '接受任务', 'type' => 'primary', 'value' => 'accept'],
                ['text' => '查看详情', 'type' => 'default', 'url' => 'https://example.com'],
            ])
        ;

        $result = $this->builder->build();

        $this->assertSame('2.0', $result['schema']);
        $this->assertIsArray($result);
        $this->assertCount(7, $result['elements']); // 2个文本 + 2个分割线 + 1个字段组 + 1个备注 + 1个按钮组
        $this->assertSame('任务详情', $result['header']['title']['content']);
        $this->assertSame('turquoise', $result['header']['template']);
    }

    public function testClearElements(): void
    {
        $this->builder->setHeader('标题');
        $this->builder
            ->addText('文本')
            ->clear()
        ;

        $result = $this->builder->build();
        $this->assertArrayNotHasKey('header', $result);
        $this->assertEmpty($result['elements']);
    }

    public function testPreview(): void
    {
        $this->builder->setHeader('预览测试');
        $this->builder->addText('测试内容');

        $preview = $this->builder->preview();
        $built = $this->builder->build();

        $this->assertSame($built, $preview);
    }

    public function testValidate(): void
    {
        // Test validation passes with content
        $this->builder->addText('Some content');
        $this->builder->validate();

        // If we reach here, validation passed - verify builder has content
        $this->assertTrue($this->builder->isValid());
    }

    public function testIsValid(): void
    {
        // Empty card should be invalid
        $this->assertFalse($this->builder->isValid());

        // Card with content should be valid
        $this->builder->addText('Some content');
        $this->assertTrue($this->builder->isValid());
    }

    public function testReset(): void
    {
        $this->builder->setHeader('Title');
        $this->builder->addText('Content');

        $this->assertTrue($this->builder->isValid());

        $this->builder->reset();

        $this->assertFalse($this->builder->isValid());
    }

    public function testToJson(): void
    {
        $this->builder->setHeader('Test Title');
        $this->builder->addText('Test Content');

        $json = $this->builder->toJson();

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertSame('Test Title', $decoded['header']['title']['content']);
        $this->assertSame('Test Content', $decoded['elements'][0]['text']['content']);
    }

    public function testAddCustomElement(): void
    {
        $this->builder->addCustomElement(['tag' => 'hr']);
        $this->assertIsArray($this->builder->build());
    }

    public function testAddDatePicker(): void
    {
        $this->builder->addDatePicker('选择', '2024-01-01');
        $this->assertIsArray($this->builder->build());
    }

    public function testAddDivider(): void
    {
        $this->builder->addDivider();
        $this->assertIsArray($this->builder->build());
    }

    public function testAddI18nElements(): void
    {
        $this->builder->addI18nElements(['zh_cn' => [['tag' => 'hr']]]);
        $this->assertIsArray($this->builder->build());
    }

    protected function onSetUp(): void
    {
        $this->builder = self::getService(CardMessageBuilder::class);
    }
}
