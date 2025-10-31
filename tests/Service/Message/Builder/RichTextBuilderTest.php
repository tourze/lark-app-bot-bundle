<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Message\Builder\RichTextBuilder;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;

/**
 * @internal
 */
#[CoversClass(RichTextBuilder::class)]
final class RichTextBuilderTest extends TestCase
{
    private RichTextBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new RichTextBuilder();
    }

    public function testSetLocale(): void
    {
        $this->builder->setLocale('en_us');
        $this->builder->setTitle('English Title');

        $result = $this->builder->build();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('en_us', $result);
        $this->assertSame('English Title', $result['en_us']['title']);
    }

    public function testSetTitle(): void
    {
        $this->builder->setTitle('测试标题');

        $result = $this->builder->build();
        $this->assertSame('测试标题', $result['zh_cn']['title']);
    }

    public function testAddText(): void
    {
        $this->builder->addText('Hello World');

        $result = $this->builder->build();
        $content = $result['zh_cn']['content'][0];

        $this->assertIsArray($content);
        $this->assertCount(1, $content);
        $this->assertSame('text', $content[0]['tag']);
        $this->assertSame('Hello World', $content[0]['text']);
    }

    public function testAddLink(): void
    {
        $this->builder->addLink('Click here', 'https://example.com');

        $result = $this->builder->build();
        $content = $result['zh_cn']['content'][0];

        $this->assertSame('a', $content[0]['tag']);
        $this->assertSame('Click here', $content[0]['text']);
        $this->assertSame('https://example.com', $content[0]['href']);
    }

    public function testAtUser(): void
    {
        $this->builder->atUser('user123', 'John Doe');

        $result = $this->builder->build();
        $content = $result['zh_cn']['content'][0];

        $this->assertSame('at', $content[0]['tag']);
        $this->assertSame('user123', $content[0]['user_id']);
        $this->assertSame('John Doe', $content[0]['user_name']);
    }

    public function testAtAll(): void
    {
        $this->builder->atAll();

        $result = $this->builder->build();
        $content = $result['zh_cn']['content'][0];

        $this->assertSame('at', $content[0]['tag']);
        $this->assertSame('all', $content[0]['user_id']);
        $this->assertSame('所有人', $content[0]['user_name']);
    }

    public function testAddImage(): void
    {
        $this->builder->addImage('img_key_123', 300, 200);

        $result = $this->builder->build();
        $content = $result['zh_cn']['content'][0];

        $this->assertSame('img', $content[0]['tag']);
        $this->assertSame('img_key_123', $content[0]['image_key']);
        $this->assertSame(300, $content[0]['width']);
        $this->assertSame(200, $content[0]['height']);
    }

    public function testAddEmoji(): void
    {
        $this->builder->addEmoji('SMILE');

        $result = $this->builder->build();
        $content = $result['zh_cn']['content'][0];

        $this->assertSame('emotion', $content[0]['tag']);
        $this->assertSame('SMILE', $content[0]['emoji_type']);
    }

    public function testAddStyledText(): void
    {
        $this->builder->addBold('Bold text');
        $this->builder->addItalic('Italic text');
        $this->builder->addUnderline('Underlined text');
        $this->builder->addLineThrough('Strikethrough text');

        $result = $this->builder->build();
        $content = $result['zh_cn']['content'][0];

        $this->assertTrue($content[0]['style']['bold']);
        $this->assertTrue($content[1]['style']['italic']);
        $this->assertTrue($content[2]['style']['underline']);
        $this->assertTrue($content[3]['style']['lineThrough']);
    }

    public function testAddUnderline(): void
    {
        $this->builder->addUnderline('u');
        $this->assertTrue($this->builder->isValid());
    }

    public function testAddLineThrough(): void
    {
        $this->builder->addLineThrough('s');
        $this->assertTrue($this->builder->isValid());
    }

    public function testNewParagraph(): void
    {
        $this->builder->addText('Paragraph 1');
        $this->builder->newParagraph();
        $this->builder->addText('Paragraph 2');

        $result = $this->builder->build();
        $content = $result['zh_cn']['content'];

        $this->assertIsArray($content);
        $this->assertCount(2, $content);
        $this->assertSame('Paragraph 1', $content[0][0]['text']);
        $this->assertSame('Paragraph 2', $content[1][0]['text']);
    }

    public function testGetMsgType(): void
    {
        $this->assertSame(MessageService::MSG_TYPE_RICH_TEXT, $this->builder->getMsgType());
    }

    public function testBuildWithEmptyContentThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('富文本消息内容不能为空');

        $this->builder->build();
    }

    public function testIsValid(): void
    {
        $this->assertFalse($this->builder->isValid());

        $this->builder->addText('Some content');
        $this->assertTrue($this->builder->isValid());

        $this->builder->reset();
        $this->assertFalse($this->builder->isValid());

        $this->builder->setTitle('Just a title');
        $this->assertTrue($this->builder->isValid());
    }

    public function testReset(): void
    {
        $this->builder->setTitle('Title');
        $this->builder->addText('Content');
        $this->builder->reset();

        $this->assertFalse($this->builder->isValid());
    }

    public function testToJson(): void
    {
        $this->builder->setTitle('Test');
        $this->builder->addText('Content');

        $json = $this->builder->toJson();
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertSame('Test', $decoded['zh_cn']['title']);
    }

    public function testCreate(): void
    {
        $builder = RichTextBuilder::create('Initial Title', 'en_us');

        $result = $builder->build();
        $this->assertSame('Initial Title', $result['en_us']['title']);
    }

    public function testFromText(): void
    {
        $builder = RichTextBuilder::fromText('Quick text', 'en_us');

        $result = $builder->build();
        $content = $result['en_us']['content'][0];

        $this->assertSame('Quick text', $content[0]['text']);
    }

    public function testMultipleLocales(): void
    {
        $this->builder->setLocale('zh_cn');
        $this->builder->setTitle('中文标题');
        $this->builder
            ->addText('中文内容')
            ->newParagraph()
        ;
        $this->builder->setLocale('en_us');
        $this->builder->setTitle('English Title');
        $this->builder->addText('English content');

        $result = $this->builder->build();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('zh_cn', $result);
        $this->assertArrayHasKey('en_us', $result);
        $this->assertSame('中文标题', $result['zh_cn']['title']);
        $this->assertSame('English Title', $result['en_us']['title']);
    }

    public function testComplexMessage(): void
    {
        $this->builder->setTitle('项目进度更新');
        $this->builder
            ->addText('Hi ')
            ->atUser('user123', '张三')
            ->addText('，以下是本周项目进度：')
            ->newParagraph()
            ->addBold('已完成：')
            ->newParagraph()
            ->addText('• 前端开发 - 100%')
            ->newParagraph()
            ->addText('• 后端API - 80%')
            ->newParagraph()
            ->addLineBreak()
            ->addText('详情请查看：')
            ->addLink('项目看板', 'https://example.com/board')
            ->newParagraph()
            ->addText('截止时间：')
            ->addUnderline('2024年1月31日')
            ->addEmoji('ALARM_CLOCK')
        ;

        $result = $this->builder->build();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('zh_cn', $result);
        $this->assertSame('项目进度更新', $result['zh_cn']['title']);
        $this->assertCount(6, $result['zh_cn']['content']);
    }

    public function testAddBold(): void
    {
        $this->builder->addBold('Bold text');

        $result = $this->builder->build();
        $content = $result['zh_cn']['content'][0];

        $this->assertSame('text', $content[0]['tag']);
        $this->assertSame('Bold text', $content[0]['text']);
        $this->assertTrue($content[0]['style']['bold']);
    }

    public function testAddItalic(): void
    {
        $this->builder->addItalic('Italic text');

        $result = $this->builder->build();
        $content = $result['zh_cn']['content'][0];

        $this->assertSame('text', $content[0]['tag']);
        $this->assertSame('Italic text', $content[0]['text']);
        $this->assertTrue($content[0]['style']['italic']);
    }

    public function testAddLineBreak(): void
    {
        $this->builder->addText('Line 1');
        $this->builder->addLineBreak();
        $this->builder->addText('Line 2');

        $result = $this->builder->build();
        $content = $result['zh_cn']['content'];

        $this->assertIsArray($content);
        $this->assertCount(2, $content);
        $this->assertSame('Line 1', $content[0][0]['text']);
        $this->assertSame('Line 2', $content[1][0]['text']);
    }
}
