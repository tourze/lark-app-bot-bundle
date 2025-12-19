<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\Message\Builder\CardMessageBuilder;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * CardMessageBuilder 集成测试.
 *
 * @internal
 */
#[CoversClass(CardMessageBuilder::class)]
#[RunTestsInSeparateProcesses]
final class CardMessageBuilderIntegrationTest extends AbstractIntegrationTestCase
{
    public function testAddActionsMethod(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addActions([['text' => 'OK', 'type' => 'primary', 'value' => 'ok']]);
        $this->assertTrue($b->isValid());
    }

    public function testAddColumnSet(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addColumnSet([[
            'width' => 'weighted',
            'weight' => 1,
            'elements' => [['tag' => 'div', 'text' => ['tag' => 'plain_text', 'content' => 't']]],
        ]]);
        $this->assertTrue($b->isValid());
    }

    public function testAddCustomElement(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addCustomElement(['tag' => 'hr']);
        $this->assertTrue($b->isValid());
    }

    public function testAddDatePicker(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addDatePicker('选择日期', '2024-01-01');
        $this->assertTrue($b->isValid());
    }

    public function testAddDivider(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addDivider();
        $this->assertTrue($b->isValid());
    }

    public function testAddFieldsMethod(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addFields([
            ['name' => 'A', 'value' => 'B'],
        ]);
        $this->assertTrue($b->isValid());
    }

    public function testAddI18nElementsMethod(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addI18nElements(['zh_cn' => [['tag' => 'hr']]]);
        $this->assertTrue($b->isValid());
    }

    public function testAddImageMethod(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addImage('img_key');
        $this->assertTrue($b->isValid());
    }

    public function testAddInputMethod(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addInput('n', 'p', 'v');
        $this->assertTrue($b->isValid());
    }

    public function testAddMarkdownMethod(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addMarkdown('**md**');
        $this->assertTrue($b->isValid());
    }

    public function testAddNote(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addNote(['n']);
        $this->assertTrue($b->isValid());
    }

    public function testAddSelectMenu(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addSelectMenu('p', [['text' => 'A', 'value' => 'a']], 'a');
        $this->assertTrue($b->isValid());
    }

    public function testAddText(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addText('t');
        $this->assertTrue($b->isValid());
    }

    public function testAddTimePicker(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addTimePicker('p', '10:30');
        $this->assertTrue($b->isValid());
    }

    public function testPreview(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addText('t');
        $this->assertIsArray($b->preview());
    }

    public function testReset(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addText('t');
        $this->assertTrue($b->isValid());
        $b->reset();
        $this->assertIsArray($b->build());
    }

    public function testToJson(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addText('t');
        $this->assertJson($b->toJson());
    }

    public function testUseTemplate(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->useTemplate('tid', ['a' => 1]);
        $this->assertIsArray($b->build());
    }

    public function testValidateMethod(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addText('x');
        $b->validate();
        $this->assertTrue($b->isValid());
    }

    public function testSendCardWithBuilder(): void
    {
        $builder = self::getService(CardMessageBuilder::class);
        $builder->setHeader('测试卡片', 'blue');
        $builder
            ->addText('这是一个测试卡片消息')
            ->addDivider()
            ->addFields([
                ['name' => '状态', 'value' => '正常'],
                ['name' => '时间', 'value' => date('Y-m-d H:i:s')],
            ], true)
            ->addActions([
                ['text' => '确认', 'type' => 'primary', 'value' => 'confirm'],
            ])
        ;

        // 验证构建的卡片结构
        $cardData = $builder->build();
        $this->assertIsArray($cardData);
        $this->assertArrayHasKey('schema', $cardData);
        $this->assertSame('2.0', $cardData['schema']);
        $this->assertArrayHasKey('header', $cardData);
        $this->assertArrayHasKey('elements', $cardData);
        $this->assertCount(4, $cardData['elements']);
    }

    public function testComplexCardWithAllElements(): void
    {
        $builder = self::getService(CardMessageBuilder::class);
        $builder->setHeader('综合测试卡片', 'purple');
        $builder
            ->addText('**标题文本**', true)
            ->addText('普通文本内容')
            ->addImage('img_test_key', '测试图片')
            ->addDivider()
            ->addFields([
                ['name' => '字段1', 'value' => '值1'],
                ['name' => '字段2', 'value' => '值2'],
            ], true)
            ->addDivider()
            ->addNote(['备注1', '备注2'])
            ->addSelectMenu('请选择', [
                ['text' => '选项1', 'value' => 'opt1'],
                ['text' => '选项2', 'value' => 'opt2'],
            ], 'opt1')
            ->addDatePicker('选择日期', '2024-01-01')
            ->addTimePicker('选择时间', '10:30')
            ->addInput('input_name', '请输入内容', '默认值')
            ->addActions([
                ['text' => '提交', 'type' => 'primary', 'value' => 'submit'],
                ['text' => '取消', 'type' => 'default', 'value' => 'cancel'],
            ])
        ;

        $cardData = $builder->build();

        // 验证所有元素都被正确添加
        $this->assertGreaterThan(10, \count($cardData['elements']));

        // 验证各种元素类型
        $elementTags = array_column($cardData['elements'], 'tag');
        $this->assertContains('div', $elementTags);
        $this->assertContains('img', $elementTags);
        $this->assertContains('hr', $elementTags);
        $this->assertContains('note', $elementTags);
        $this->assertContains('action', $elementTags);
        $this->assertContains('input', $elementTags);
    }

    public function testI18nCard(): void
    {
        $builder = self::getService(CardMessageBuilder::class);
        $builder->setHeader('多语言卡片');
        $builder->addI18nElements([
            'zh_cn' => [
                ['tag' => 'div', 'text' => ['content' => '中文内容', 'tag' => 'plain_text']],
            ],
            'en_us' => [
                ['tag' => 'div', 'text' => ['content' => 'English content', 'tag' => 'plain_text']],
            ],
        ]);

        $cardData = $builder->build();
        $this->assertIsArray($cardData);
        $this->assertArrayHasKey('i18n_elements', $cardData);
        $this->assertArrayHasKey('zh_cn', $cardData['i18n_elements']);
        $this->assertArrayHasKey('en_us', $cardData['i18n_elements']);
    }

    protected function onSetUp(): void
    {// 集成测试设置
    }

    public function testBuild(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addText('t');
        $this->assertIsArray($b->build());
    }

    public function testClear(): void
    {
        $b = self::getService(CardMessageBuilder::class);
        $b->addText('t')->clear();
        $this->assertIsArray($b->build());
    }
}
