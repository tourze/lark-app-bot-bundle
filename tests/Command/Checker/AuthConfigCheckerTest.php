<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Checker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Checker\AuthConfigChecker;
use Tourze\LarkAppBotBundle\Service\Authentication\TokenProviderInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AuthConfigChecker::class)]
#[RunTestsInSeparateProcesses]
final class AuthConfigCheckerTest extends AbstractIntegrationTestCase
{
    private AuthConfigChecker $checker;

    private MockObject&TokenProviderInterface $mockTokenProvider;

    private MockObject&SymfonyStyle $mockIo;

    public function testConstructor(): void
    {
        $this->assertInstanceOf(AuthConfigChecker::class, $this->checker);
    }

    public function testGetName(): void
    {
        $name = $this->checker->getName();
        $this->assertSame('认证配置', $name);
    }

    public function testCheckWithValidToken(): void
    {
        $this->mockTokenProvider
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('valid_token_123')
        ;

        $this->mockIo->expects($this->once())
            ->method('success')
            ->with('成功获取Access Token')
        ;

        $this->mockIo->expects($this->atLeastOnce())
            ->method('comment')
        ;

        $result = $this->checker->check($this->mockIo);
        $this->assertFalse($result);
    }

    public function testCheckWithTokenException(): void
    {
        $this->mockTokenProvider
            ->expects($this->once())
            ->method('getToken')
            ->willThrowException(new \Exception('Token获取失败'))
        ;

        $this->mockIo->expects($this->once())
            ->method('error')
            ->with(self::stringContains('认证检查失败'))
        ;

        $result = $this->checker->check($this->mockIo);
        $this->assertTrue($result);
    }

    public function testCheckWithTokenVisibilityShowToken(): void
    {
        $this->mockTokenProvider
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('valid_token_123')
        ;

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $this->mockTokenProvider
            ->expects($this->once())
            ->method('getExpiresAt')
            ->willReturn($expiresAt)
        ;

        $this->mockIo->expects($this->once())
            ->method('success')
            ->with('成功获取Access Token')
        ;

        $this->mockIo->expects($this->exactly(3))
            ->method('comment')
        ;

        $result = $this->checker->checkWithTokenVisibility($this->mockIo, false, true);
        $this->assertFalse($result);
    }

    public function testCheckWithTokenVisibilityHideToken(): void
    {
        $this->mockTokenProvider
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('valid_token_123')
        ;

        $this->mockIo->expects($this->once())
            ->method('success')
            ->with('成功获取Access Token')
        ;

        $this->mockIo->expects($this->exactly(2))
            ->method('comment')
        ;

        $result = $this->checker->checkWithTokenVisibility($this->mockIo, false, false);
        $this->assertFalse($result);
    }

    public function testCheckWithFixAndSuccessfulRefresh(): void
    {
        $this->mockTokenProvider
            ->expects($this->once())
            ->method('getToken')
            ->willThrowException(new \Exception('Token过期'))
        ;

        $this->mockTokenProvider
            ->expects($this->once())
            ->method('refresh')
        ;

        $this->mockIo->expects($this->once())
            ->method('error')
            ->with(self::stringContains('认证检查失败'))
        ;

        $this->mockIo->expects($this->exactly(2))
            ->method('comment')
        ;

        $this->mockIo->expects($this->once())
            ->method('success')
            ->with('Token刷新成功')
        ;

        $result = $this->checker->check($this->mockIo, true);
        $this->assertFalse($result);
    }

    public function testCheckWithFixAndFailedRefresh(): void
    {
        $this->mockTokenProvider
            ->expects($this->once())
            ->method('getToken')
            ->willThrowException(new \Exception('Token过期'))
        ;

        $this->mockTokenProvider
            ->expects($this->once())
            ->method('refresh')
            ->willThrowException(new \Exception('刷新失败'))
        ;

        $this->mockIo->expects($this->exactly(2))
            ->method('error')
        ;

        $result = $this->checker->check($this->mockIo, true);
        $this->assertTrue($result);
    }

    protected function onSetUp(): void
    {
        $this->mockTokenProvider = $this->createMock(TokenProviderInterface::class);
        $this->mockIo = $this->createMock(SymfonyStyle::class);

        // 设置服务容器的Mock服务
        self::getContainer()->set(TokenProviderInterface::class, $this->mockTokenProvider);

        // 从容器中获取服务实例
        $this->checker = self::getService(AuthConfigChecker::class);
    }
}
