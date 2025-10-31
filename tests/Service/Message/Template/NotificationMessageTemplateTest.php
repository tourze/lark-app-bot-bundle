<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\Message\Template\NotificationMessageTemplate;

/**
 * @internal
 */
#[CoversClass(NotificationMessageTemplate::class)]
final class NotificationMessageTemplateTest extends TestCase
{
    private NotificationMessageTemplate $template;

    protected function setUp(): void
    {
        $this->template = new NotificationMessageTemplate();
    }

    public function testGetName(): void
    {
        $this->assertSame('notification_message', $this->template->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertSame('系统通知消息模板，支持不同级别的通知', $this->template->getDescription());
    }

    public function testGetRequiredVariables(): void
    {
        $variables = $this->template->getRequiredVariables();

        $this->assertIsArray($variables);
        $this->assertArrayHasKey('title', $variables);
        $this->assertArrayHasKey('content', $variables);
        $this->assertCount(2, $variables);
    }

    public function testRenderWithMinimalVariables(): void
    {
        $variables = [
            'title' => '系统通知',
            'content' => '这是一条系统通知消息',
        ];

        $builder = $this->template->render($variables);
        $result = $builder->build();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('zh_cn', $result);
        $this->assertSame('ℹ️ 系统通知', $result['zh_cn']['title']);

        $contentStr = json_encode($result['zh_cn']['content'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->assertIsString($contentStr);
        $this->assertNotNull($contentStr, 'JSON encoding should succeed');
        $this->assertStringContainsString('这是一条系统通知消息', $contentStr);
        $this->assertStringContainsString('通知时间', $contentStr);
    }

    public function testRenderWithDifferentLevels(): void
    {
        $levels = [
            NotificationMessageTemplate::LEVEL_INFO => 'ℹ️',
            NotificationMessageTemplate::LEVEL_SUCCESS => '✅',
            NotificationMessageTemplate::LEVEL_WARNING => '⚠️',
            NotificationMessageTemplate::LEVEL_ERROR => '❌',
        ];

        foreach ($levels as $level => $icon) {
            $variables = [
                'title' => '测试通知',
                'content' => '测试内容',
                'level' => $level,
            ];

            $builder = $this->template->render($variables);
            $result = $builder->build();

            $this->assertStringContainsString($icon, $result['zh_cn']['title']);
        }
    }

    public function testRenderWithMentions(): void
    {
        $variables = [
            'title' => '任务分配',
            'content' => '新任务已分配',
            'mentions' => [
                ['user_id' => 'user123', 'user_name' => '张三'],
                ['user_id' => 'user456', 'user_name' => '李四'],
            ],
        ];

        $builder = $this->template->render($variables);
        $result = $builder->build();

        $contentStr = json_encode($result['zh_cn']['content'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->assertIsString($contentStr);
        $this->assertNotNull($contentStr, 'JSON encoding should succeed');
        $this->assertStringContainsString('相关人员', $contentStr);
        $this->assertStringContainsString('user123', $contentStr);
        $this->assertStringContainsString('张三', $contentStr);
        $this->assertStringContainsString('user456', $contentStr);
        $this->assertStringContainsString('李四', $contentStr);
    }

    public function testRenderWithActions(): void
    {
        $variables = [
            'title' => '审批请求',
            'content' => '有新的审批需要处理',
            'actions' => [
                ['text' => '查看详情', 'url' => 'https://example.com/approval/123'],
                ['text' => '立即审批', 'url' => 'https://example.com/approve/123'],
                ['text' => '拒绝'],
            ],
        ];

        $builder = $this->template->render($variables);
        $result = $builder->build();

        $contentStr = json_encode($result['zh_cn']['content'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($contentStr, 'JSON encoding should not fail');
        $this->assertStringContainsString('可执行操作', $contentStr);
        $this->assertStringContainsString('查看详情', $contentStr);
        $this->assertStringContainsString('https://example.com/approval/123', $contentStr);
        $this->assertStringContainsString('立即审批', $contentStr);
        $this->assertStringContainsString('拒绝', $contentStr);
    }

    public function testRenderWithCustomTime(): void
    {
        $customTime = new \DateTime('2024-01-15 15:30:00');

        $variables = [
            'title' => '定时通知',
            'content' => '这是一条定时发送的通知',
            'time' => $customTime,
        ];

        $builder = $this->template->render($variables);
        $result = $builder->build();

        $contentStr = json_encode($result['zh_cn']['content'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($contentStr, 'JSON encoding should not fail');
        $this->assertStringContainsString('2024-01-15 15:30:00', $contentStr);
    }

    public function testLevelSpecificTips(): void
    {
        // 测试错误级别的提示
        $variables = [
            'title' => '系统错误',
            'content' => '数据库连接失败',
            'level' => NotificationMessageTemplate::LEVEL_ERROR,
        ];

        $builder = $this->template->render($variables);
        $result = $builder->build();

        $contentStr = json_encode($result['zh_cn']['content'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($contentStr, 'JSON encoding should not fail');
        $this->assertStringContainsString('请立即处理此错误', $contentStr);

        // 测试警告级别的提示
        $variables['level'] = NotificationMessageTemplate::LEVEL_WARNING;
        $builder = $this->template->render($variables);
        $result = $builder->build();

        $contentStr = json_encode($result['zh_cn']['content'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($contentStr, 'JSON encoding should not fail');
        $this->assertStringContainsString('请注意关注此警告信息', $contentStr);
    }

    public function testCompleteNotification(): void
    {
        $variables = [
            'title' => '部署完成',
            'content' => '应用 v2.0.1 已成功部署到生产环境',
            'level' => NotificationMessageTemplate::LEVEL_SUCCESS,
            'time' => new \DateTime('2024-01-15 16:45:00'),
            'mentions' => [
                ['user_id' => 'dev001', 'user_name' => '开发负责人'],
                ['user_id' => 'ops001', 'user_name' => '运维负责人'],
            ],
            'actions' => [
                ['text' => '查看部署日志', 'url' => 'https://deploy.example.com/logs/12345'],
                ['text' => '访问生产环境', 'url' => 'https://app.example.com'],
                ['text' => '回滚版本', 'url' => 'https://deploy.example.com/rollback/12345'],
            ],
        ];

        $builder = $this->template->render($variables);
        $result = $builder->build();

        $this->assertSame('✅ 部署完成', $result['zh_cn']['title']);

        $contentStr = json_encode($result['zh_cn']['content'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($contentStr, 'JSON encoding should not fail');
        $this->assertStringContainsString('应用 v2.0.1 已成功部署到生产环境', $contentStr);
        $this->assertStringContainsString('dev001', $contentStr);
        $this->assertStringContainsString('ops001', $contentStr);
        $this->assertStringContainsString('查看部署日志', $contentStr);
        $this->assertStringContainsString('2024-01-15 16:45:00', $contentStr);
    }
}
