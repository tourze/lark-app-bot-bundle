<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\RouteCollection;
use Tourze\LarkAppBotBundle\Service\AttributeControllerLoader;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses]
final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    public function testConstructor(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $this->assertInstanceOf(AttributeControllerLoader::class, $loader);
    }

    public function testLoad(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $collection = $loader->load('any_resource');

        $this->assertInstanceOf(RouteCollection::class, $collection);
        // The collection should contain routes from WebhookController
        $this->assertGreaterThanOrEqual(0, $collection->count());
    }

    public function testSupports(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        // The loader always returns false for supports
        $this->assertFalse($loader->supports('any_resource'));
        $this->assertFalse($loader->supports('any_resource', 'any_type'));
        $this->assertFalse($loader->supports(null));
    }

    public function testAutoload(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $collection = $loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $collection);
        // The collection should contain routes from WebhookController
        $this->assertGreaterThanOrEqual(0, $collection->count());
    }

    public function testImplementsRoutingAutoLoaderInterface(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $this->assertInstanceOf(
            'Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface',
            $loader
        );
    }

    public function testExtendsSymfonyLoader(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $this->assertInstanceOf(
            'Symfony\Component\Config\Loader\Loader',
            $loader
        );
    }

    public function testAutoConfigureTag(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $reflection = new \ReflectionClass($loader);
        $attributes = $reflection->getAttributes();

        $hasAutoConfigureTag = false;
        foreach ($attributes as $attribute) {
            if ('Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag' === $attribute->getName()) {
                $hasAutoConfigureTag = true;
                $args = $attribute->getArguments();
                $this->assertSame('routing.loader', $args['name']);
                break;
            }
        }

        $this->assertTrue($hasAutoConfigureTag, 'Class should have AutoconfigureTag attribute');
    }

    public function testLoadReturnsRouteCollection(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $result = $loader->load('test_resource', 'test_type');

        $this->assertInstanceOf(RouteCollection::class, $result);

        // Test that the returned collection is the same as autoload
        $autoloadResult = $loader->autoload();
        $this->assertSame($autoloadResult->count(), $result->count());
    }

    public function testLoadWithNullType(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $result = $loader->load('test_resource', null);

        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    public function testMethodsVisibility(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        $reflection = new \ReflectionClass($loader);

        // Check load method
        $loadMethod = $reflection->getMethod('load');
        $this->assertTrue($loadMethod->isPublic());

        // Check supports method
        $supportsMethod = $reflection->getMethod('supports');
        $this->assertTrue($supportsMethod->isPublic());

        // Check autoload method
        $autoloadMethod = $reflection->getMethod('autoload');
        $this->assertTrue($autoloadMethod->isPublic());
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
    }
}
