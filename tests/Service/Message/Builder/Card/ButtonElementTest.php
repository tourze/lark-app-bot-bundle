<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder\Card;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\Message\Builder\Card\ButtonElement;

/**
 * @internal
 */
#[CoversClass(ButtonElement::class)]
final class ButtonElementTest extends TestCase
{
    public function testButtonElementCreation(): void
    {
        $button = new ButtonElement('Test Button');

        $array = $button->toArray();
        $this->assertSame('button', $array['tag']);
        $this->assertSame('Test Button', $array['text']['content']);
        $this->assertSame('plain_text', $array['text']['tag']);
        $this->assertSame(ButtonElement::TYPE_DEFAULT, $array['type']);
    }

    public function testSetText(): void
    {
        $button = new ButtonElement('Original');
        $button->setText('New Text');

        $array = $button->toArray();
        $this->assertSame('New Text', $array['text']['content']);
    }

    public function testSetType(): void
    {
        $button = new ButtonElement('Test');
        $button->setType(ButtonElement::TYPE_PRIMARY);

        $array = $button->toArray();
        $this->assertSame(ButtonElement::TYPE_PRIMARY, $array['type']);
    }

    public function testAsPrimary(): void
    {
        $button = new ButtonElement('Test');
        $button->asPrimary();

        $array = $button->toArray();
        $this->assertSame(ButtonElement::TYPE_PRIMARY, $array['type']);
    }

    public function testAsDefault(): void
    {
        $button = new ButtonElement('Test');
        $button->asPrimary(); // Change from default
        $button->asDefault();
        $array = $button->toArray();
        $this->assertSame(ButtonElement::TYPE_DEFAULT, $array['type']);
    }

    public function testAsDanger(): void
    {
        $button = new ButtonElement('Test');
        $button->asDanger();
        $array = $button->toArray();
        $this->assertSame(ButtonElement::TYPE_DANGER, $array['type']);
    }

    public function testSetUrl(): void
    {
        $button = new ButtonElement('Test');
        $button->setUrl('https://example.com');
        $array = $button->toArray();
        $this->assertSame('https://example.com', $array['url']);
    }

    public function testSetValueWithString(): void
    {
        $button = new ButtonElement('Test');
        $button->setValue('test_value');

        $array = $button->toArray();
        $this->assertSame('test_value', $array['value']);
    }

    public function testSetValueWithArray(): void
    {
        $button = new ButtonElement('Test');
        $value = ['key' => 'value', 'action' => 'test'];
        $button->setValue($value);

        $array = $button->toArray();
        $this->assertSame($value, $array['value']);
    }

    public function testSetMultiUrl(): void
    {
        $button = new ButtonElement('Test');
        $urls = [
            'pc' => 'https://example.com/pc',
            'mobile' => 'https://example.com/mobile',
        ];
        $button->setMultiUrl($urls);

        $array = $button->toArray();
        $this->assertSame($urls, $array['multi_url']);
    }

    public function testSetConfirm(): void
    {
        $button = new ButtonElement('Test');
        $button->setConfirm('Confirm Title', 'Confirm Message');

        $array = $button->toArray();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('confirm', $array);
        $this->assertSame('Confirm Title', $array['confirm']['title']['content']);
        $this->assertSame('plain_text', $array['confirm']['title']['tag']);
        $this->assertSame('Confirm Message', $array['confirm']['text']['content']);
        $this->assertSame('plain_text', $array['confirm']['text']['tag']);
    }

    public function testButtonConstants(): void
    {
        $this->assertSame('primary', ButtonElement::TYPE_PRIMARY);
        $this->assertSame('default', ButtonElement::TYPE_DEFAULT);
        $this->assertSame('danger', ButtonElement::TYPE_DANGER);
    }

    public function testFluentInterface(): void
    {
        $button = new ButtonElement('Test');
        $button->setText('New Text');
        $button->asPrimary();
        $button->setUrl('https://example.com');
        $button->setValue('test_value');
        $button->setConfirm('Title', 'Message');

        $array = $button->toArray();
        $this->assertSame('New Text', $array['text']['content']);
        $this->assertSame(ButtonElement::TYPE_PRIMARY, $array['type']);
        $this->assertSame('https://example.com', $array['url']);
        $this->assertSame('test_value', $array['value']);
        $this->assertIsArray($array);
        $this->assertArrayHasKey('confirm', $array);
    }
}
