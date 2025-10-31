<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Checker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Checker\WebhookConfigChecker;
use Tourze\LarkAppBotBundle\Tests\TestCase\CheckerFactory;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(WebhookConfigChecker::class)]
#[RunTestsInSeparateProcesses]
final class WebhookConfigCheckerTest extends AbstractIntegrationTestCase
{
    private WebhookConfigChecker $checker;

    private MockObject&SymfonyStyle $mockIo;

    public function testConstructor(): void
    {
        $this->assertInstanceOf(WebhookConfigChecker::class, $this->checker);
    }

    public function testGetName(): void
    {
        $name = $this->checker->getName();
        $this->assertSame('Webhook配置', $name);
    }

    public function testCheckWithCompleteWebhookConfig(): void
    {
        $config = [
            'webhook' => [
                'encrypt_key' => 'test_encrypt_key',
                'verification_token' => 'test_verification_token',
                'enabled_events' => [
                    'im.message.receive_v1',
                    'im.chat.member.user.added_v1',
                ],
            ],
        ];

        $checker = $this->createCheckerWithConfig($config);

        $this->mockIo->expects($this->exactly(3))
            ->method('success') // 3个成功消息：加密密钥、验证令牌、事件订阅
        ;

        $this->mockIo->expects($this->once())
            ->method('section')
            ->with('Webhook配置信息')
        ;

        $this->mockIo->expects($this->atLeastOnce())
            ->method('text')
        ;

        $this->mockIo->expects($this->once())
            ->method('definitionList')
        ;

        $result = $checker->check($this->mockIo);
        $this->assertFalse($result);
    }

    public function testCheckWithMissingWebhookConfig(): void
    {
        $checker = $this->createCheckerWithConfig([]);

        $this->mockIo->expects($this->exactly(2))
            ->method('warning')
        ;

        $this->mockIo->expects($this->once())
            ->method('comment')
            ->with('未配置特定的事件订阅，将接收所有事件')
        ;

        $result = $checker->check($this->mockIo);
        $this->assertFalse($result);
    }

    public function testCheckWithPartialWebhookConfig(): void
    {
        $config = [
            'webhook' => [
                'encrypt_key' => 'test_encrypt_key',
                // 缺少 verification_token
                'enabled_events' => ['im.message.receive_v1'],
            ],
        ];

        $checker = $this->createCheckerWithConfig($config);

        $this->mockIo->expects($this->exactly(2))
            ->method('success') // 加密密钥 + 事件订阅
        ;

        $this->mockIo->expects($this->once())
            ->method('warning')
            ->with('未配置Webhook验证令牌，某些事件可能无法验证')
        ;

        $result = $checker->check($this->mockIo);
        $this->assertFalse($result);
    }

    public function testCheckWithEmptyEvents(): void
    {
        $config = [
            'webhook' => [
                'encrypt_key' => 'test_key',
                'verification_token' => 'test_token',
                'enabled_events' => [],
            ],
        ];

        $checker = $this->createCheckerWithConfig($config);

        $this->mockIo->expects($this->once())
            ->method('comment')
            ->with('未配置特定的事件订阅，将接收所有事件')
        ;

        $result = $checker->check($this->mockIo);
        $this->assertFalse($result);
    }

    public function testCheckWithInvalidWebhookConfig(): void
    {
        $config = [
            'webhook' => 'invalid_string',
        ];

        $checker = $this->createCheckerWithConfig($config);

        $this->mockIo->expects($this->exactly(2))
            ->method('warning')
        ;

        $result = $checker->check($this->mockIo);
        $this->assertFalse($result);
    }

    public function testCheckWithVerboseMode(): void
    {
        $checker = self::getService(WebhookConfigChecker::class);

        $this->mockIo->expects($this->once())
            ->method('isVerbose')
            ->willReturn(true)
        ;

        $this->mockIo->expects($this->once())
            ->method('listing')
            ->with([
                'im.message.receive_v1',
                'im.chat.member.user.added_v1',
            ])
        ;

        $result = $checker->check($this->mockIo);
        $this->assertFalse($result);
    }

    public function testCheckWithNonVerboseMode(): void
    {
        $checker = self::getService(WebhookConfigChecker::class);

        $this->mockIo->expects($this->once())
            ->method('isVerbose')
            ->willReturn(false)
        ;

        $this->mockIo->expects($this->never())
            ->method('listing')
        ;

        $result = $checker->check($this->mockIo);
        $this->assertFalse($result);
    }

    protected function onSetUp(): void
    {
        $this->mockIo = $this->createMock(SymfonyStyle::class);
        $this->checker = self::getService(WebhookConfigChecker::class);
    }

    /** @param array<string, mixed> $config */
    private function createCheckerWithConfig(array $config): WebhookConfigChecker
    {
        // 使用独立工厂类创建实例，工厂类不被 CoversClass 覆盖
        return CheckerFactory::createWebhookConfigChecker($config);
    }
}
