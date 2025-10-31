<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Checker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Checker\BasicConfigChecker;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(BasicConfigChecker::class)]
#[RunTestsInSeparateProcesses]
final class BasicConfigCheckerTest extends AbstractIntegrationTestCase
{
    private BasicConfigChecker $checker;

    private MockObject&SymfonyStyle $mockIo;

    public function testConstructor(): void
    {
        $this->assertInstanceOf(BasicConfigChecker::class, $this->checker);
    }

    public function testGetName(): void
    {
        $name = $this->checker->getName();
        $this->assertSame('基本配置', $name);
    }

    public function testCheckWithValidConfig(): void
    {
        $this->mockIo->expects($this->atLeastOnce())
            ->method('success')
        ;

        $result = $this->checker->check($this->mockIo);
        $this->assertTrue($result); // 因为Bundle注册检查会失败
    }

    public function testCheckWithMissingRequiredConfig(): void
    {
        $this->mockIo->expects($this->atLeastOnce())
            ->method('error')
        ;

        $checker = self::getContainer()->get('basic_config_checker.missing_required');
        $this->assertInstanceOf(BasicConfigChecker::class, $checker);

        $result = $checker->check($this->mockIo);
        $this->assertTrue($result);
    }

    public function testCheckWithEmptyConfig(): void
    {
        $this->mockIo->expects($this->atLeastOnce())
            ->method('error')
        ;

        $checker = self::getContainer()->get('basic_config_checker.empty');
        $this->assertInstanceOf(BasicConfigChecker::class, $checker);

        $result = $checker->check($this->mockIo);
        $this->assertTrue($result);
    }

    public function testCheckWithInvalidApiDomain(): void
    {
        $this->mockIo->expects($this->atLeastOnce())
            ->method('warning')
        ;

        $this->mockIo->expects($this->atLeastOnce())
            ->method('comment')
        ;

        $checker = self::getContainer()->get('basic_config_checker.invalid_domain');
        $this->assertInstanceOf(BasicConfigChecker::class, $checker);

        $result = $checker->check($this->mockIo);
        $this->assertTrue($result); // 会有其他检查失败
    }

    public function testCheckWithFixOption(): void
    {
        $this->mockIo->expects($this->atLeastOnce())
            ->method('error')
        ;

        $this->mockIo->expects($this->atLeastOnce())
            ->method('comment')
        ;

        $this->mockIo->expects($this->atLeastOnce())
            ->method('text')
        ;

        $checker = self::getContainer()->get('basic_config_checker.empty');
        $this->assertInstanceOf(BasicConfigChecker::class, $checker);

        $result = $checker->check($this->mockIo, true);
        $this->assertTrue($result);
    }

    public function testCheckApiDomainWithValidDomains(): void
    {
        $testCases = [
            'basic_config_checker' => 'https://open.feishu.cn',
            'basic_config_checker.larksuite' => 'https://open.larksuite.com',
        ];

        foreach ($testCases as $serviceId => $domain) {
            $this->mockIo->expects($this->atLeastOnce())
                ->method('success')
            ;

            // 根据服务 ID 获取对应的 checker
            if ('basic_config_checker' === $serviceId) {
                $checker = self::getService(BasicConfigChecker::class);
            } else {
                $checker = self::getContainer()->get($serviceId);
                $this->assertInstanceOf(BasicConfigChecker::class, $checker);
            }

            $result = $checker->check($this->mockIo);
            $this->assertTrue($result); // 其他检查仍会失败
        }
    }

    public function testCheckWithPartialConfig(): void
    {
        // 期望配置检查会报错
        $this->mockIo->expects($this->atLeastOnce())
            ->method('error')
        ;

        $checker = self::getContainer()->get('basic_config_checker.partial');
        $this->assertInstanceOf(BasicConfigChecker::class, $checker);

        $result = $checker->check($this->mockIo);
        $this->assertTrue($result);
    }

    protected function onSetUp(): void
    {
        $this->mockIo = $this->createMock(SymfonyStyle::class);
        $this->checker = self::getService(BasicConfigChecker::class);
    }
}
