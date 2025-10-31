<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\Message\Builder\CardMessageBuilder;
use Tourze\LarkAppBotBundle\Service\Message\Template\CardTemplateManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * CardTemplateManager 集成测试.
 *
 * @internal
 */
#[CoversClass(CardTemplateManager::class)]
#[RunTestsInSeparateProcesses]
final class CardTemplateManagerIntegrationTest extends AbstractIntegrationTestCase
{
    private CardTemplateManager $templateManager;

    public function testCardTemplate(): void
    {
        // 测试通知模板
        $builder = new CardMessageBuilder();
        $this->templateManager->applyTemplate($builder, 'notification', [
            'title' => '系统通知',
            'content' => '您有一条新消息',
            'note' => '发送时间：' . date('Y-m-d H:i:s'),
        ]);

        $cardData = $builder->build();
        $this->assertSame('系统通知', $cardData['header']['title']['content']);
        $this->assertSame('blue', $cardData['header']['template']);

        // 测试审批模板
        $builder = new CardMessageBuilder();
        $this->templateManager->applyTemplate($builder, 'approval', [
            'title' => '请假申请',
            'description' => '张三申请请假一天',
            'fields' => [
                ['name' => '申请人', 'value' => '张三'],
                ['name' => '请假时间', 'value' => '2024-01-01'],
                ['name' => '请假原因', 'value' => '个人事务'],
            ],
            'id' => 'approval_123',
        ]);

        $cardData = $builder->build();
        $this->assertSame('请假申请', $cardData['header']['title']['content']);
        $this->assertSame('orange', $cardData['header']['template']);

        // 验证按钮
        $actions = end($cardData['elements'])['actions'];
        $this->assertIsArray($actions);
        $this->assertCount(2, $actions);
        $this->assertSame('同意', $actions[0]['text']['content']);
        $this->assertSame('拒绝', $actions[1]['text']['content']);
    }

    public function testCustomTemplate(): void
    {
        // 注册自定义模板
        $this->templateManager->registerTemplate('custom_test', function (CardMessageBuilder $builder, array $data): void {
            $builder->setHeader($data['title'] ?? '自定义模板', 'green');
            $builder
                ->addText('自定义内容：' . ($data['content'] ?? ''))
                ->addActions([
                    ['text' => '自定义按钮', 'type' => 'primary', 'url' => $data['url'] ?? '#'],
                ])
            ;
        });

        // 使用自定义模板
        $builder = new CardMessageBuilder();
        $this->templateManager->applyTemplate($builder, 'custom_test', [
            'title' => '测试标题',
            'content' => '测试内容',
            'url' => 'https://example.com',
        ]);

        $cardData = $builder->build();
        $this->assertSame('测试标题', $cardData['header']['title']['content']);
        $this->assertStringContainsString('自定义内容：测试内容', $cardData['elements'][0]['text']['content']);
    }

    public function testApplyTemplate(): void
    {
        // 测试应用预定义模板
        $builder = new CardMessageBuilder();
        $result = $this->templateManager->applyTemplate($builder, 'welcome', [
            'name' => '新用户',
            'introduction' => '欢迎来到我们的团队！',
            'learn_more_url' => 'https://example.com/learn',
            'guide_url' => 'https://example.com/guide',
        ]);

        $this->assertTrue($result);

        $cardData = $builder->build();
        $this->assertSame('欢迎加入', $cardData['header']['title']['content']);
        $this->assertSame('green', $cardData['header']['template']);
        $this->assertStringContainsString('Hi 新用户，欢迎加入我们！', $cardData['elements'][0]['text']['content']);

        // 测试应用不存在的模板
        $builder2 = new CardMessageBuilder();
        $result2 = $this->templateManager->applyTemplate($builder2, 'non_existent_template', []);
        $this->assertFalse($result2);
    }

    public function testRegisterTemplate(): void
    {
        // 测试注册新模板
        $templateName = 'test_dynamic_template';
        $templateData = ['message' => '动态模板内容'];

        $this->templateManager->registerTemplate($templateName, function (CardMessageBuilder $builder, array $data): void {
            $builder->setHeader('动态模板', 'purple');
            $builder->addText($data['message'] ?? '默认消息');
        });

        // 验证模板已注册
        $this->assertNotNull($this->templateManager->getTemplate($templateName));
        $this->assertTrue($this->templateManager->hasTemplate($templateName));

        // 使用新注册的模板
        $builder = new CardMessageBuilder();
        $result = $this->templateManager->applyTemplate($builder, $templateName, $templateData);

        $this->assertTrue($result);

        $cardData = $builder->build();
        $this->assertSame('动态模板', $cardData['header']['title']['content']);
        $this->assertSame('purple', $cardData['header']['template']);
        $this->assertSame('动态模板内容', $cardData['elements'][0]['text']['content']);

        // 测试获取所有模板名称
        $templateNames = $this->templateManager->getTemplateNames();
        $this->assertContains($templateName, $templateNames);
        $this->assertContains('notification', $templateNames);
        $this->assertContains('approval', $templateNames);
    }

    protected function onSetUp(): void
    {
        $this->templateManager = self::getService(CardTemplateManager::class);
    }
}
