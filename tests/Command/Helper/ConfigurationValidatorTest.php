<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Command\Helper\ConfigurationValidator;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ConfigurationValidator::class)]
#[RunTestsInSeparateProcesses]
final class ConfigurationValidatorTest extends AbstractIntegrationTestCase
{
    public function testCheckRequiredConfigKeys(): void
    {
        // 使用已在 services_test.yaml 中注册的有效配置变体，确保必填项校验通过
        $validator = self::getContainer()->get('MockConfigurationValidatorValid');
        $io = $this->createDummyIo();
        $this->assertFalse($validator->checkRequiredConfigKeys($io));
    }

    public function testCheckSingleConfigKey(): void
    {
        $validator = self::getService(ConfigurationValidator::class);
        $io = $this->createDummyIo();
        $this->assertTrue($validator->checkSingleConfigKey($io, 'lark_app_bot.non_exist_key'));
    }

    public function testCheckApiDomainConfig(): void
    {
        // 使用有效配置的 Validator，验证 API 域名检查通过
        $validator = self::getContainer()->get('MockConfigurationValidatorValid');
        $io = $this->createDummyIo();
        $this->assertFalse($validator->checkApiDomainConfig($io));
    }

    public function testInvalidDomainConfig(): void
    {
        $validator = self::getContainer()->get('MockConfigurationValidatorInvalidDomain');
        $this->assertInstanceOf(ConfigurationValidator::class, $validator);
        $io = $this->createDummyIo();
        $this->assertTrue($validator->checkApiDomainConfig($io));
    }

    private function createDummyIo(): \Symfony\Component\Console\Style\SymfonyStyle
    {
        $input = new \Symfony\Component\Console\Input\ArrayInput([]);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        return new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);
    }

    protected function onSetUp(): void
    {
    }
}
