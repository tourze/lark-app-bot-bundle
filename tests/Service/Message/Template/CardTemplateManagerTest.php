<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\Message\Builder\CardMessageBuilder;
use Tourze\LarkAppBotBundle\Service\Message\Template\CardTemplateManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CardTemplateManager::class)]
#[RunTestsInSeparateProcesses]
final class CardTemplateManagerTest extends AbstractIntegrationTestCase
{
    public function testConstructor(): void
    {
        $manager = self::getService(CardTemplateManager::class);
        $this->assertInstanceOf(CardTemplateManager::class, $manager);
        // 验证默认模板已注册
        $this->assertTrue($manager->hasTemplate('notification'));
        $this->assertTrue($manager->hasTemplate('approval'));
        $this->assertTrue($manager->hasTemplate('task'));
        $this->assertTrue($manager->hasTemplate('welcome'));
        $this->assertTrue($manager->hasTemplate('report'));
        $this->assertTrue($manager->hasTemplate('alert'));
        $this->assertTrue($manager->hasTemplate('event_invitation'));
        $this->assertTrue($manager->hasTemplate('poll'));
    }

    public function testRegisterTemplate(): void
    {
        $manager = self::getService(CardTemplateManager::class);
        $templateName = 'custom_template';
        $template = function (CardMessageBuilder $builder, array $data): void {
            $builder->setHeader($data['title'] ?? 'Custom');
        };

        $manager->registerTemplate($templateName, $template);

        $this->assertTrue($manager->hasTemplate($templateName));
        $this->assertSame($template, $manager->getTemplate($templateName));
    }

    public function testGetTemplate(): void
    {
        $manager = self::getService(CardTemplateManager::class);
        $template = $manager->getTemplate('notification');
        $this->assertIsCallable($template);

        $nonExistent = $manager->getTemplate('non_existent');
        $this->assertNull($nonExistent);
    }

    public function testApplyTemplateWithValidTemplate(): void
    {
        /*
         * 使用具体类 CardMessageBuilder 创建 Mock 对象的原因：
         * 1. CardMessageBuilder 是一个卡片消息构建器类，没有对应的接口定义
         * 2. 该类提供了流式 API 设计，需要 mock 链式调用的行为
         * 3. 这是测试模板管理器如何调用构建器方法的单元测试，使用 mock 是合理的
         * 4. 未来可考虑为 CardMessageBuilder 创建接口以提高可测试性
         */
        $builder = $this->createMock(CardMessageBuilder::class);
        $builder->expects($this->once())
            ->method('setHeader')
            ->with('测试通知', 'blue')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addText')
            ->with('测试内容')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addDivider')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addNote')
            ->with(['测试备注'])
            ->willReturn($builder)
        ;

        $data = [
            'title' => '测试通知',
            'content' => '测试内容',
            'note' => '测试备注',
        ];

        $manager = self::getService(CardTemplateManager::class);
        $result = $manager->applyTemplate($builder, 'notification', $data);
        $this->assertTrue($result);
    }

    public function testApplyTemplateWithInvalidTemplate(): void
    {
        /*
         * 使用具体类 CardMessageBuilder 创建 Mock 对象的原因：
         * 1. CardMessageBuilder 是具体的构建器类，没有抽象接口
         * 2. 测试无效模板时，构建器不应被调用，因此 mock 不会有实际方法调用
         * 3. 这种设计符合测试需求，验证了无效模板不会触发构建器操作
         */
        $builder = $this->createMock(CardMessageBuilder::class);
        $manager = self::getService(CardTemplateManager::class);
        $result = $manager->applyTemplate($builder, 'non_existent', []);
        $this->assertFalse($result);
    }

    public function testGetTemplateNames(): void
    {
        $manager = self::getService(CardTemplateManager::class);
        $names = $manager->getTemplateNames();
        $this->assertIsArray($names);
        $this->assertContains('notification', $names);
        $this->assertContains('approval', $names);
        $this->assertContains('task', $names);
        $this->assertContains('welcome', $names);
        $this->assertContains('report', $names);
        $this->assertContains('alert', $names);
        $this->assertContains('event_invitation', $names);
        $this->assertContains('poll', $names);
    }

    public function testHasTemplate(): void
    {
        $manager = self::getService(CardTemplateManager::class);
        $this->assertTrue($manager->hasTemplate('notification'));
        $this->assertFalse($manager->hasTemplate('non_existent'));
    }

    public function testApprovalTemplate(): void
    {
        /*
         * 使用具体类 CardMessageBuilder 创建 Mock 对象的原因：
         * 1. CardMessageBuilder 是卡片构建器的具体实现，提供了丰富的卡片构建方法
         * 2. 测试需要验证审批模板正确调用了构建器的各个方法（setHeader、addText、addDivider等）
         * 3. 通过 mock 可以精确验证方法调用次数和参数，确保模板行为符合预期
         * 4. 这是标准的单元测试实践，隔离了外部依赖
         */
        $builder = $this->createMock(CardMessageBuilder::class);
        $builder->expects($this->once())
            ->method('setHeader')
            ->with('审批请求', 'orange')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addText')
            ->with('需要审批')
            ->willReturn($builder)
        ;
        $builder->expects($this->exactly(2))
            ->method('addDivider')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addFields')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addActions')
            ->willReturn($builder)
        ;

        $data = [
            'title' => '审批请求',
            'description' => '需要审批',
            'fields' => [['name' => '申请人', 'value' => '张三']],
            'id' => '12345',
        ];

        $manager = self::getService(CardTemplateManager::class);
        $manager->applyTemplate($builder, 'approval', $data);
    }

    public function testTaskTemplate(): void
    {
        /*
         * 使用具体类 CardMessageBuilder 创建 Mock 对象的原因：
         * 1. CardMessageBuilder 负责构建飞书卡片消息，是具体实现类而非接口
         * 2. 任务模板测试需要验证构建器方法的调用顺序和参数
         * 3. Mock 对象允许我们验证模板是否正确配置了任务卡片的各个组件
         * 4. 这种测试方式确保了模板逻辑的正确性，不依赖实际的卡片构建实现
         */
        $builder = $this->createMock(CardMessageBuilder::class);
        $builder->expects($this->once())
            ->method('setHeader')
            ->with('新任务', 'turquoise')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addText')
            ->willReturn($builder)
        ;
        $builder->expects($this->exactly(2))
            ->method('addDivider')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addFields')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addActions')
            ->willReturn($builder)
        ;

        $data = [
            'title' => '新任务',
            'description' => '完成报告',
            'assignee' => '李四',
            'due_date' => '2024-12-31',
            'priority' => '高',
            'status' => '进行中',
            'task_id' => 'TASK-001',
            'detail_url' => 'https://example.com/task/001', ];

        $manager = self::getService(CardTemplateManager::class);
        $manager->applyTemplate($builder, 'task', $data);
    }

    public function testReportTemplateWithMetricsAndChart(): void
    {
        /*
         * 使用具体类 CardMessageBuilder 创建 Mock 对象的原因：
         * 1. CardMessageBuilder 是飞书卡片消息的构建器实现，包含特定的卡片元素方法
         * 2. 报告模板测试需要验证复杂的卡片结构，包括指标字段和图表
         * 3. 通过 mock 可以验证 addFields、addImage 等方法的正确调用
         * 4. 这种方式隔离了测试，专注于模板逻辑而非卡片构建细节
         */
        $builder = $this->createMock(CardMessageBuilder::class);
        $builder->expects($this->once())
            ->method('setHeader')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addText')
            ->willReturn($builder)
        ;
        $builder->expects($this->exactly(3))
            ->method('addDivider')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addFields')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addImage')
            ->with('https://example.com/chart.png', '数据图表')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addActions')
            ->willReturn($builder)
        ;

        $data = [
            'title' => '月度报告',
            'report_time' => '2024-01-31 23:59:59',
            'metrics' => [
                ['name' => '销售额', 'value' => '100万'],
                ['name' => '增长率', 'value' => '15%'],
            ],
            'chart_image' => 'https://example.com/chart.png',
            'full_report_url' => 'https://example.com/report',
            'download_url' => 'https://example.com/report.pdf',
        ];

        $manager = self::getService(CardTemplateManager::class);
        $manager->applyTemplate($builder, 'report', $data);
    }

    public function testPollTemplateWithOptions(): void
    {
        /*
         * 使用具体类 CardMessageBuilder 创建 Mock 对象的原因：
         * 1. CardMessageBuilder 实现了飞书卡片的构建逻辑，没有抽象出接口层
         * 2. 投票模板需要动态生成选项按钮，测试需验证 addActions 方法的参数
         * 3. 使用 mock 和 callback 验证可以确保投票选项被正确转换为按钮
         * 4. 这是合理的测试策略，聚焦于模板的行为验证
         */
        $builder = $this->createMock(CardMessageBuilder::class);
        $builder->expects($this->once())
            ->method('setHeader')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addText')
            ->willReturn($builder)
        ;
        $builder->expects($this->exactly(2))
            ->method('addDivider')
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addActions')
            ->with(self::callback(function ($actions) {
                return \is_array($actions) && 3 === \count($actions);
            }))
            ->willReturn($builder)
        ;
        $builder->expects($this->once())
            ->method('addNote')
            ->willReturn($builder)
        ;

        $data = [
            'title' => '团建投票',
            'question' => '你想去哪里团建？',
            'options' => ['海边', '山区', '城市'],
            'poll_id' => 'POLL-001',
            'deadline' => '2024-02-01 18:00:00',
        ];

        $manager = self::getService(CardTemplateManager::class);
        $manager->applyTemplate($builder, 'poll', $data);
    }

    protected function onSetUp(): void
    {// 无需特殊初始化
    }
}
