<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Builder\Card;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\Message\Builder\Card\CardElement;

/**
 * CardElement 核心功能测试.
 *
 * @internal
 */
#[CoversClass(CardElement::class)]
final class CardElementCoreTest extends TestCase
{
    public function testCardElementCreation(): void
    {
        $element = new TestCardElement();

        $array = $element->toArray();
        $this->assertSame('test_element', $array['tag']);
    }

    public function testToArray(): void
    {
        $element = new TestCardElement();
        $element->withTestData('key1', 'value1');
        $element->withTestData('key2', ['nested' => 'value']);

        $array = $element->toArray();
        $this->assertSame('test_element', $array['tag']);
        $this->assertSame('value1', $array['key1']);
        $this->assertSame(['nested' => 'value'], $array['key2']);
    }

    public function testSetAndGetData(): void
    {
        $element = new TestCardElement();
        $element->withTestData('test_key', 'test_value');

        $this->assertSame('test_value', $element->getTestData('test_key'));
    }

    public function testGetDataWithNonExistentKey(): void
    {
        $element = new TestCardElement();

        $this->assertNull($element->getTestData('nonexistent'));
    }

    public function testSetDataReturnsself(): void
    {
        $element = new TestCardElement();
        $result = $element->withTestData('key', 'value');

        $this->assertSame($element, $result);
    }
}
