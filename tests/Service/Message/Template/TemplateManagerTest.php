<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Exception\ConfigurationException;
use Tourze\LarkAppBotBundle\Service\Message\Template\MessageTemplateInterface;
use Tourze\LarkAppBotBundle\Service\Message\Template\NotificationMessageTemplate;
use Tourze\LarkAppBotBundle\Service\Message\Template\TemplateManager;
use Tourze\LarkAppBotBundle\Service\Message\Template\WelcomeMessageTemplate;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(TemplateManager::class)]
#[RunTestsInSeparateProcesses]
final class TemplateManagerTest extends AbstractIntegrationTestCase
{
    private TemplateManager $manager;

    public function testRegisterTemplate(): void
    {
        $template = $this->createMock(MessageTemplateInterface::class);
        $template->method('getName')->willReturn('test_template');

        $result = $this->manager->registerTemplate($template);

        $this->assertSame($this->manager, $result);
        $this->assertTrue($this->manager->hasTemplate('test_template'));
    }

    public function testGetTemplate(): void
    {
        $template = $this->createMock(MessageTemplateInterface::class);
        $template->method('getName')->willReturn('test_template');

        $this->manager->registerTemplate($template);

        $retrievedTemplate = $this->manager->getTemplate('test_template');
        $this->assertSame($template, $retrievedTemplate);
    }

    public function testGetTemplateThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('模板 "non_existent" 未找到');

        $this->manager->getTemplate('non_existent');
    }

    public function testHasTemplate(): void
    {
        $this->assertFalse($this->manager->hasTemplate('test_template'));

        $template = $this->createMock(MessageTemplateInterface::class);
        $template->method('getName')->willReturn('test_template');
        $this->manager->registerTemplate($template);

        $this->assertTrue($this->manager->hasTemplate('test_template'));
    }

    public function testGetAllTemplates(): void
    {
        // 记录初始模板数量（集成测试环境可能有预注册的模板）
        $initialCount = \count($this->manager->getAllTemplates());

        $template1 = $this->createMock(MessageTemplateInterface::class);
        $template1->method('getName')->willReturn('template1');

        $template2 = $this->createMock(MessageTemplateInterface::class);
        $template2->method('getName')->willReturn('template2');

        $this->manager->registerTemplate($template1);
        $this->manager->registerTemplate($template2);

        $templates = $this->manager->getAllTemplates();

        // 验证新增了2个模板
        $this->assertIsArray($templates);
        $this->assertCount($initialCount + 2, $templates);
        $this->assertArrayHasKey('template1', $templates);
        $this->assertArrayHasKey('template2', $templates);
    }

    public function testGetTemplatesInfo(): void
    {
        $template = $this->createMock(MessageTemplateInterface::class);
        $template->method('getName')->willReturn('test_template');
        $template->method('getDescription')->willReturn('Test template description');
        $template->method('getRequiredVariables')->willReturn([
            'var1' => 'Variable 1',
            'var2' => 'Variable 2',
        ]);

        $this->manager->registerTemplate($template);

        $info = $this->manager->getTemplatesInfo();

        $this->assertIsArray($info);
        $this->assertArrayHasKey('test_template', $info);
        $this->assertSame('test_template', $info['test_template']['name']);
        $this->assertSame('Test template description', $info['test_template']['description']);
        $this->assertSame([
            'var1' => 'Variable 1',
            'var2' => 'Variable 2',
        ], $info['test_template']['variables']);
    }

    public function testRemoveTemplate(): void
    {
        $template = $this->createMock(MessageTemplateInterface::class);
        $template->method('getName')->willReturn('test_template');

        $this->manager->registerTemplate($template);
        $this->assertTrue($this->manager->hasTemplate('test_template'));

        $result = $this->manager->removeTemplate('test_template');

        $this->assertSame($this->manager, $result);
        $this->assertFalse($this->manager->hasTemplate('test_template'));
    }

    public function testClearTemplates(): void
    {
        $template1 = $this->createMock(MessageTemplateInterface::class);
        $template1->method('getName')->willReturn('template1');

        $template2 = $this->createMock(MessageTemplateInterface::class);
        $template2->method('getName')->willReturn('template2');

        $this->manager->registerTemplate($template1);
        $this->manager->registerTemplate($template2);

        $result = $this->manager->clearTemplates();

        $this->assertSame($this->manager, $result);
        $this->assertIsArray($this);
        $this->assertCount(0, $this->manager->getAllTemplates());
    }

    public function testRegisterTemplates(): void
    {
        $template1 = $this->createMock(MessageTemplateInterface::class);
        $template1->method('getName')->willReturn('template1');

        $template2 = $this->createMock(MessageTemplateInterface::class);
        $template2->method('getName')->willReturn('template2');

        $result = $this->manager->registerTemplates([$template1, $template2]);

        $this->assertSame($this->manager, $result);
        $this->assertTrue($this->manager->hasTemplate('template1'));
        $this->assertTrue($this->manager->hasTemplate('template2'));
    }

    public function testCreateDefault(): void
    {
        $manager = TemplateManager::createDefault();

        $this->assertTrue($manager->hasTemplate('welcome_message'));
        $this->assertTrue($manager->hasTemplate('notification_message'));

        $welcomeTemplate = $manager->getTemplate('welcome_message');
        $this->assertInstanceOf(WelcomeMessageTemplate::class, $welcomeTemplate);

        $notificationTemplate = $manager->getTemplate('notification_message');
        $this->assertInstanceOf(NotificationMessageTemplate::class, $notificationTemplate);
    }

    public function testTemplateOverride(): void
    {
        $template1 = $this->createMock(MessageTemplateInterface::class);
        $template1->method('getName')->willReturn('test_template');
        $template1->method('getDescription')->willReturn('First template');

        $template2 = $this->createMock(MessageTemplateInterface::class);
        $template2->method('getName')->willReturn('test_template');
        $template2->method('getDescription')->willReturn('Second template');

        $this->manager->registerTemplate($template1);
        $this->manager->registerTemplate($template2);

        $retrievedTemplate = $this->manager->getTemplate('test_template');
        $this->assertSame($template2, $retrievedTemplate);
    }

    protected function onSetUp(): void
    {
        // 从容器获取 TemplateManager 服务
        $manager = self::getContainer()->get(TemplateManager::class);
        self::assertInstanceOf(TemplateManager::class, $manager);
        $this->manager = $manager;
    }
}
