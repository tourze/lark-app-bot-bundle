<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Message\Builder\TextMessageBuilder;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;

/**
 * @internal
 */
#[CoversClass(TextMessageBuilder::class)]
final class TextMessageBuilderTest extends TestCase
{
    private TextMessageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new TextMessageBuilder();
    }

    public function testSetText(): void
    {
        $this->builder->setText('Hello, World!');
        $this->assertSame('Hello, World!', $this->builder->getText());
    }

    public function testAppendText(): void
    {
        $this->builder->setText('Hello');
        $this->builder->appendText(', World!');
        $this->assertSame('Hello, World!', $this->builder->getText());
    }

    public function testAppendLine(): void
    {
        $this->builder->setText('Line 1');
        $this->builder->appendLine('Line 2');
        $this->assertSame("Line 1\nLine 2", $this->builder->getText());
    }

    public function testAppendLineOnEmptyText(): void
    {
        $this->builder->appendLine('First line');
        $this->assertSame('First line', $this->builder->getText());
    }

    public function testAtUser(): void
    {
        $this->builder->setText('Hello ');
        $this->builder->atUser('user123', 'John');
        $this->assertSame('Hello <at user_id="user123">John</at>', $this->builder->getText());
    }

    public function testAtUserWithoutName(): void
    {
        $this->builder->setText('Hello ');
        $this->builder->atUser('user123');
        $this->assertSame('Hello <at user_id="user123"></at>', $this->builder->getText());
    }

    public function testAtAll(): void
    {
        $this->builder->setText('Attention: ');
        $this->builder->atAll();
        $this->assertSame('Attention: <at user_id="all">所有人</at>', $this->builder->getText());
    }

    public function testAddLink(): void
    {
        $this->builder->setText('Check out ');
        $this->builder->addLink('https://example.com', 'this link');
        $this->assertSame('Check out <a href="https://example.com">this link</a>', $this->builder->getText());
    }

    public function testAddLinkWithoutText(): void
    {
        $this->builder->setText('Visit: ');
        $this->builder->addLink('https://example.com');
        $this->assertSame('Visit: https://example.com', $this->builder->getText());
    }

    public function testGetMsgType(): void
    {
        $this->assertSame(MessageService::MSG_TYPE_TEXT, $this->builder->getMsgType());
    }

    public function testBuildWithEmptyTextThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('文本消息内容不能为空');

        $this->builder->build();
    }

    public function testIsValid(): void
    {
        $this->assertFalse($this->builder->isValid());

        $this->builder->setText('Some text');
        $this->assertTrue($this->builder->isValid());
    }

    public function testReset(): void
    {
        $this->builder->setText('Some text');
        $this->builder->reset();

        $this->assertSame('', $this->builder->getText());
        $this->assertFalse($this->builder->isValid());
    }

    public function testToJson(): void
    {
        $this->builder->setText('Test message');
        $json = $this->builder->toJson();

        $this->assertJson($json);
        $this->assertSame('{"text":"Test message"}', $json);
    }

    public function testCreate(): void
    {
        $builder = TextMessageBuilder::create('Initial text');

        $this->assertSame('Initial text', $builder->getText());
    }

    public function testCreateEmpty(): void
    {
        $builder = TextMessageBuilder::create();

        $this->assertSame('', $builder->getText());
    }

    public function testChainedMethods(): void
    {
        $result = $this->builder
            ->setText('Hello')
            ->appendText(' ')
            ->atUser('user123', 'John')
            ->appendText(', welcome to ')
            ->addLink('https://example.com', 'our site')
            ->appendLine()
            ->appendLine('Please read the rules.')
            ->atAll()
            ->appendText(' must follow them!')
        ;

        $expected = 'Hello <at user_id="user123">John</at>, welcome to <a href="https://example.com">our site</a>' . "\n" .
                   "\n" .
                   'Please read the rules.<at user_id="all">所有人</at> must follow them!';

        $this->assertSame($expected, $result->getText());
    }
}
