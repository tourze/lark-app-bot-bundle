<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Checker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Checker\PermissionsChecker;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(PermissionsChecker::class)]
#[RunTestsInSeparateProcesses]
final class PermissionsCheckerTest extends AbstractIntegrationTestCase
{
    private PermissionsChecker $checker;

    private MockObject&SymfonyStyle $mockIo;

    public function testConstructor(): void
    {
        $this->assertInstanceOf(PermissionsChecker::class, $this->checker);
    }

    public function testGetName(): void
    {
        $name = $this->checker->getName();
        $this->assertSame('权限检查', $name);
    }

    public function testCheck(): void
    {
        $this->mockIo->expects($this->once())
            ->method('comment')
            ->with('建议配置以下权限范围：')
        ;

        $this->mockIo->expects($this->once())
            ->method('table')
            ->with(
                ['权限范围', '说明'],
                self::callback(function (array $rows): bool {
                    $expectedRows = [
                        ['im:message', '发送消息'],
                        ['im:chat', '群组管理'],
                        ['contact:user.base:readonly', '读取用户基本信息'],
                        ['contact:user.email:readonly', '读取用户邮箱'],
                        ['contact:user.phone:readonly', '读取用户手机号'],
                    ];

                    return $rows === $expectedRows;
                })
            )
        ;

        $this->mockIo->expects($this->once())
            ->method('note')
            ->with('请在飞书开放平台的"权限管理"中配置这些权限')
        ;

        $result = $this->checker->check($this->mockIo);
        $this->assertFalse($result); // 权限检查只是建议，不算错误
    }

    public function testCheckWithFixOption(): void
    {
        $this->mockIo->expects($this->once())
            ->method('comment')
        ;

        $this->mockIo->expects($this->once())
            ->method('table')
        ;

        $this->mockIo->expects($this->once())
            ->method('note')
        ;

        $result = $this->checker->check($this->mockIo, true);
        $this->assertFalse($result); // fix 选项不影响结果
    }

    protected function onSetUp(): void
    {
        $this->mockIo = $this->createMock(SymfonyStyle::class);
        $this->checker = self::getService(PermissionsChecker::class);
    }
}
