<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder\Card;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * TestCardElement 测试.
 *
 * @internal
 */
#[CoversClass(TestCardElement::class)]
final class TestCardElementTest extends TestCase
{
    public function testGetTag(): void
    {
        $element = new TestCardElement();
        $this->assertSame('test_element', $element->toArray()['tag']);
    }

    public function testSetTestData(): void
    {
        $element = new TestCardElement();
        $result = $element->withTestData('key', 'value');

        $this->assertInstanceOf(TestCardElement::class, $result);
        $this->assertSame('value', $element->getTestData('key'));
    }

    public function testWithTestData(): void
    {
        $element = new TestCardElement();
        $result = $element->withTestData('foo', 'bar');

        $this->assertInstanceOf(TestCardElement::class, $result);
    }

    public function testGetTestData(): void
    {
        $element = new TestCardElement();
        $element->withTestData('test', 'data');

        $this->assertSame('data', $element->getTestData('test'));
        $this->assertNull($element->getTestData('nonexistent'));
    }
}
