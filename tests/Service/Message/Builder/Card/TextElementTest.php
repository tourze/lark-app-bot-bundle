<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder\Card;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\Message\Builder\Card\TextElement;

/**
 * @internal
 */
#[CoversClass(TextElement::class)]
final class TextElementTest extends TestCase
{
    public function testTextElementCreationWithPlainText(): void
    {
        $element = new TextElement('Hello World');

        $array = $element->toArray();
        $this->assertSame('div', $array['tag']);
        $this->assertSame('Hello World', $array['text']['content']);
        $this->assertSame('plain_text', $array['text']['tag']);
    }

    public function testTextElementCreationWithMarkdown(): void
    {
        $element = new TextElement('**Bold Text**', true);

        $array = $element->toArray();
        $this->assertSame('div', $array['tag']);
        $this->assertSame('**Bold Text**', $array['text']['content']);
        $this->assertSame('lark_md', $array['text']['tag']);
    }

    public function testSetContent(): void
    {
        $element = new TextElement('Original Content');
        $element->setContent('New Content');
        $array = $element->toArray();
        $this->assertSame('New Content', $array['text']['content']);
    }

    public function testAsMarkdown(): void
    {
        $element = new TextElement('Plain Text');
        $element->asMarkdown();
        $array = $element->toArray();
        $this->assertSame('lark_md', $array['text']['tag']);
    }

    public function testAsPlainText(): void
    {
        $element = new TextElement('**Bold Text**', true);
        $element->asPlainText();
        $array = $element->toArray();
        $this->assertSame('plain_text', $array['text']['tag']);
    }

    public function testFluentInterface(): void
    {
        $element = new TextElement('Original');
        $element->setContent('New Content');
        $element->asMarkdown();

        $array = $element->toArray();
        $this->assertSame('New Content', $array['text']['content']);
        $this->assertSame('lark_md', $array['text']['tag']);
    }

    public function testToggleBetweenFormats(): void
    {
        $element = new TextElement('Test Content');

        // Start as plain text
        $array = $element->toArray();
        $this->assertSame('plain_text', $array['text']['tag']);

        // Change to markdown
        $element->asMarkdown();
        $array = $element->toArray();
        $this->assertSame('lark_md', $array['text']['tag']);

        // Change back to plain text
        $element->asPlainText();
        $array = $element->toArray();
        $this->assertSame('plain_text', $array['text']['tag']);
    }
}
