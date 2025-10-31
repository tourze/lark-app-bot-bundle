<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\Message\Builder\CardElementBuilder;

/**
 * 卡片元素构建器辅助类测试.
 *
 * @internal
 */
#[CoversClass(CardElementBuilder::class)]
class CardElementBuilderTest extends TestCase
{
    private CardElementBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CardElementBuilder();
    }

    public function testBuildTextElementCreatesPlainTextByDefault(): void
    {
        $result = $this->builder->buildTextElement('Hello World');

        $expected = [
            'tag' => 'div',
            'text' => [
                'content' => 'Hello World',
                'tag' => 'plain_text',
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildTextElementCreatesLarkMdWhenRequested(): void
    {
        $result = $this->builder->buildTextElement('**Bold Text**', true);

        $expected = [
            'tag' => 'div',
            'text' => [
                'content' => '**Bold Text**',
                'tag' => 'lark_md',
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildTextElementHandlesEmptyContent(): void
    {
        $result = $this->builder->buildTextElement('');

        $this->assertSame('', $result['text']['content']);
        $this->assertSame('plain_text', $result['text']['tag']);
    }

    public function testBuildTextElementHandlesUnicodeContent(): void
    {
        $content = '这是中文测试内容';
        $result = $this->builder->buildTextElement($content);

        $this->assertSame($content, $result['text']['content']);
    }

    public function testBuildImageElementCreatesBasicImage(): void
    {
        $result = $this->builder->buildImageElement('img_v2_1234567890abcdef');

        $expected = [
            'tag' => 'img',
            'img_key' => 'img_v2_1234567890abcdef',
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildImageElementIncludesAltText(): void
    {
        $result = $this->builder->buildImageElement('img_key', 'Alternative text');

        $expected = [
            'tag' => 'img',
            'img_key' => 'img_key',
            'alt' => [
                'content' => 'Alternative text',
                'tag' => 'plain_text',
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildImageElementIncludesTitle(): void
    {
        $result = $this->builder->buildImageElement('img_key', null, 'Image Title');

        $expected = [
            'tag' => 'img',
            'img_key' => 'img_key',
            'title' => [
                'content' => 'Image Title',
                'tag' => 'plain_text',
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildImageElementIncludesBothAltAndTitle(): void
    {
        $result = $this->builder->buildImageElement('img_key', 'Alt text', 'Title text');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('alt', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertSame('Alt text', $result['alt']['content']);
        $this->assertSame('Title text', $result['title']['content']);
    }

    public function testBuildDividerElementCreatesHorizontalRule(): void
    {
        $result = $this->builder->buildDividerElement();

        $expected = ['tag' => 'hr'];

        $this->assertSame($expected, $result);
    }

    public function testBuildInputElementCreatesBasicInput(): void
    {
        $result = $this->builder->buildInputElement('username', 'Enter username');

        $expected = [
            'tag' => 'input',
            'name' => 'username',
            'placeholder' => [
                'content' => 'Enter username',
                'tag' => 'plain_text',
            ],
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildInputElementIncludesDefaultValue(): void
    {
        $result = $this->builder->buildInputElement('email', 'Email address', 'user@example.com');

        $this->assertSame('user@example.com', $result['default_value']);
    }

    public function testBuildInputElementCreatesMultilineInput(): void
    {
        $result = $this->builder->buildInputElement('description', 'Enter description', null, true);

        $this->assertTrue($result['multiline']);
    }

    public function testBuildInputElementSetsMaxLength(): void
    {
        $result = $this->builder->buildInputElement('title', 'Title', null, false, 100);

        $this->assertSame(100, $result['max_length']);
    }

    public function testBuildInputElementWithAllOptions(): void
    {
        $result = $this->builder->buildInputElement(
            'bio',
            'Tell us about yourself',
            'Default bio content',
            true,
            500
        );

        $expected = [
            'tag' => 'input',
            'name' => 'bio',
            'placeholder' => [
                'content' => 'Tell us about yourself',
                'tag' => 'plain_text',
            ],
            'default_value' => 'Default bio content',
            'multiline' => true,
            'max_length' => 500,
        ];

        $this->assertSame($expected, $result);
    }

    public function testBuildInputElementWithoutMultilineDoesNotIncludeMultilineKey(): void
    {
        $result = $this->builder->buildInputElement('name', 'Name', null, false);

        $this->assertArrayNotHasKey('multiline', $result);
    }

    public function testBuildInputElementWithoutMaxLengthDoesNotIncludeMaxLengthKey(): void
    {
        $result = $this->builder->buildInputElement('name', 'Name', null, false, null);

        $this->assertArrayNotHasKey('max_length', $result);
    }

    public function testBuildInputElementWithoutDefaultValueDoesNotIncludeDefaultValueKey(): void
    {
        $result = $this->builder->buildInputElement('name', 'Name');

        $this->assertArrayNotHasKey('default_value', $result);
    }

    public function testBuildTextElement(): void
    {
        // 测试文本元素创建功能
        $result = $this->builder->buildTextElement('Test content', true);

        $this->assertSame('div', $result['tag']);
        $this->assertSame('Test content', $result['text']['content']);
        $this->assertSame('lark_md', $result['text']['tag']);

        // 测试普通文本
        $result = $this->builder->buildTextElement('Plain text');
        $this->assertSame('plain_text', $result['text']['tag']);
    }

    public function testBuildImageElement(): void
    {
        // 测试图片元素创建功能
        $result = $this->builder->buildImageElement('img_key_123', 'Alt text', 'Title text');

        $this->assertSame('img', $result['tag']);
        $this->assertSame('img_key_123', $result['img_key']);
        $this->assertSame('Alt text', $result['alt']['content']);
        $this->assertSame('plain_text', $result['alt']['tag']);
        $this->assertSame('Title text', $result['title']['content']);
        $this->assertSame('plain_text', $result['title']['tag']);
    }

    public function testBuildDividerElement(): void
    {
        // 测试分割线元素创建功能
        $result = $this->builder->buildDividerElement();

        $this->assertSame(['tag' => 'hr'], $result);
    }

    public function testBuildInputElement(): void
    {
        // 测试输入框元素创建功能
        $result = $this->builder->buildInputElement('email', 'Enter email', 'test@example.com', true, 100);

        $this->assertSame('input', $result['tag']);
        $this->assertSame('email', $result['name']);
        $this->assertSame('Enter email', $result['placeholder']['content']);
        $this->assertSame('plain_text', $result['placeholder']['tag']);
        $this->assertSame('test@example.com', $result['default_value']);
        $this->assertTrue($result['multiline']);
        $this->assertSame(100, $result['max_length']);
    }
}
