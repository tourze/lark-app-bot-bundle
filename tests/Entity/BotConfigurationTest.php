<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Entity\BotConfiguration;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(BotConfiguration::class)]
final class BotConfigurationTest extends AbstractEntityTestCase
{
    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['appId', 'app_123456'],
            ['name', 'Test Configuration'],
            ['configKey', 'test.config.key'],
            ['configValue', 'test_value'],
            ['description', 'Test description'],
            ['isActive', false],
        ];
    }

    public function testBotConfigurationCanBeCreatedWithDefaultValues(): void
    {
        $config = new BotConfiguration();

        self::assertNull($config->getId());
        self::assertSame('', $config->getAppId());
        self::assertSame('', $config->getName());
        self::assertSame('', $config->getConfigKey());
        self::assertSame('', $config->getConfigValue());
        self::assertNull($config->getDescription());
        self::assertTrue($config->getIsActive());
    }

    public function testBotConfigurationSettersAndGetters(): void
    {
        $config = new BotConfiguration();

        $config->setAppId('app_test123');
        $config->setName('My Config');
        $config->setConfigKey('api.endpoint');
        $config->setConfigValue('https://api.example.com');
        $config->setDescription('API endpoint configuration');
        $config->setIsActive(false);

        self::assertSame('app_test123', $config->getAppId());
        self::assertSame('My Config', $config->getName());
        self::assertSame('api.endpoint', $config->getConfigKey());
        self::assertSame('https://api.example.com', $config->getConfigValue());
        self::assertSame('API endpoint configuration', $config->getDescription());
        self::assertFalse($config->getIsActive());
    }

    public function testToStringMethod(): void
    {
        $config = new BotConfiguration();
        $config->setName('Test Config');
        $config->setConfigKey('test.key');

        self::assertSame('配置 Test Config (test.key)', (string) $config);
    }

    public function testDescriptionCanBeNull(): void
    {
        $config = new BotConfiguration();
        $config->setDescription(null);

        self::assertNull($config->getDescription());
    }

    public function testIsActiveToggle(): void
    {
        $config = new BotConfiguration();

        // Default is true
        self::assertTrue($config->getIsActive());

        $config->setIsActive(false);
        self::assertFalse($config->getIsActive());

        $config->setIsActive(true);
        self::assertTrue($config->getIsActive());
    }

    protected function createEntity(): object
    {
        return new BotConfiguration();
    }
}
