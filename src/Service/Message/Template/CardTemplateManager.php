<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Template;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Service\Message\Builder\CardMessageBuilder;

/**
 * 卡片模板管理器
 * 提供预定义的卡片模板
 */
#[Autoconfigure(public: true)]
final class CardTemplateManager
{
    /**
     * 预定义模板
     *
     * @var array<string, callable>
     */
    private array $templates = [];

    public function __construct()
    {
        $this->registerDefaultTemplates();
    }

    /**
     * 注册模板
     *
     * @param string   $name     模板名称
     * @param callable $template 模板构建函数
     */
    public function registerTemplate(string $name, callable $template): void
    {
        $this->templates[$name] = $template;
    }

    /**
     * 获取模板
     *
     * @param string $name 模板名称
     */
    public function getTemplate(string $name): ?callable
    {
        return $this->templates[$name] ?? null;
    }

    /**
     * 应用模板
     *
     * @param CardMessageBuilder  $builder 卡片构建器
     * @param string              $name    模板名称
     * @param array<string,mixed> $data    模板数据
     *
     * @return bool 是否成功应用模板
     */
    public function applyTemplate(CardMessageBuilder $builder, string $name, array $data): bool
    {
        $template = $this->getTemplate($name);
        if (null === $template) {
            return false;
        }

        $template($builder, $data);

        return true;
    }

    /**
     * 获取所有模板名称.
     *
     * @return array<string>
     */
    public function getTemplateNames(): array
    {
        return array_keys($this->templates);
    }

    /**
     * 检查模板是否存在.
     *
     * @param string $name 模板名称
     */
    public function hasTemplate(string $name): bool
    {
        return isset($this->templates[$name]);
    }

    /**
     * 注册默认模板
     */
    private function registerDefaultTemplates(): void
    {
        $this->registerNotificationTemplate();
        $this->registerApprovalTemplate();
        $this->registerTaskTemplate();
        $this->registerWelcomeTemplate();
        $this->registerReportTemplate();
        $this->registerAlertTemplate();
        $this->registerEventInvitationTemplate();
        $this->registerPollTemplate();
    }

    /**
     * 注册通知模板
     */
    private function registerNotificationTemplate(): void
    {
        $this->registerTemplate('notification', function (CardMessageBuilder $builder, array $data): void {
            $builder->setHeader($data['title'] ?? '通知', 'blue')
                ->addText($data['content'] ?? '')
                ->addDivider()
                ->addNote([$data['note'] ?? '系统通知'])
            ;
        });
    }

    /**
     * 注册审批模板
     */
    private function registerApprovalTemplate(): void
    {
        $this->registerTemplate('approval', function (CardMessageBuilder $builder, array $data): void {
            $builder->setHeader($data['title'] ?? '审批请求', 'orange')
                ->addText($data['description'] ?? '')
                ->addDivider()
                ->addFields($data['fields'] ?? [], true)
                ->addDivider()
                ->addActions([
                    ['text' => '同意', 'type' => 'primary', 'value' => ['action' => 'approve', 'id' => $data['id'] ?? '']],
                    ['text' => '拒绝', 'type' => 'danger', 'value' => ['action' => 'reject', 'id' => $data['id'] ?? '']],
                ])
            ;
        });
    }

    /**
     * 注册任务模板
     */
    private function registerTaskTemplate(): void
    {
        $this->registerTemplate('task', function (CardMessageBuilder $builder, array $data): void {
            $builder->setHeader($data['title'] ?? '任务', 'turquoise')
                ->addText(\sprintf('**任务描述**\n%s', $data['description'] ?? ''), true)
                ->addDivider()
                ->addFields([
                    ['name' => '负责人', 'value' => $data['assignee'] ?? '未分配'],
                    ['name' => '截止时间', 'value' => $data['due_date'] ?? '无'],
                    ['name' => '优先级', 'value' => $data['priority'] ?? '普通'],
                    ['name' => '状态', 'value' => $data['status'] ?? '待处理'],
                ], true)
                ->addDivider()
                ->addActions([
                    ['text' => '接受任务', 'type' => 'primary', 'value' => ['action' => 'accept', 'task_id' => $data['task_id'] ?? '']],
                    ['text' => '查看详情', 'type' => 'default', 'url' => $data['detail_url'] ?? ''],
                ])
            ;
        });
    }

    /**
     * 注册欢迎模板
     */
    private function registerWelcomeTemplate(): void
    {
        $this->registerTemplate('welcome', function (CardMessageBuilder $builder, array $data): void {
            $builder->setHeader('欢迎加入', 'green')
                ->addText(\sprintf('Hi %s，欢迎加入我们！', $data['name'] ?? ''))
                ->addDivider()
                ->addText($data['introduction'] ?? '这里是一个充满活力的团队，期待与你共同成长。')
                ->addDivider()
                ->addActions([
                    ['text' => '了解更多', 'type' => 'primary', 'url' => $data['learn_more_url'] ?? ''],
                    ['text' => '查看指南', 'type' => 'default', 'url' => $data['guide_url'] ?? ''],
                ])
            ;
        });
    }

    /**
     * 注册报告模板
     */
    private function registerReportTemplate(): void
    {
        $this->registerTemplate('report', function (CardMessageBuilder $builder, array $data): void {
            $builder->setHeader($data['title'] ?? '数据报告', 'purple')
                ->addText(\sprintf('**报告时间**：%s', $data['report_time'] ?? date('Y-m-d H:i:s')), true)
                ->addDivider()
            ;

            // 添加数据字段
            if (isset($data['metrics']) && \is_array($data['metrics'])) {
                $fields = [];
                foreach ($data['metrics'] as $metric) {
                    \assert(\is_array($metric));
                    $name = $metric['name'] ?? '';
                    $value = $metric['value'] ?? '';
                    \assert(\is_string($name));
                    \assert(\is_string($value));
                    $fields[] = ['name' => $name, 'value' => $value];
                }
                /** @var array<array<string, string>> $fields */
                $builder->addFields($fields, true);
            }

            // 添加图表（如果有）
            if (isset($data['chart_image'])) {
                $builder->addDivider()->addImage($data['chart_image'], '数据图表');
            }

            $builder->addDivider()
                ->addActions([
                    ['text' => '查看完整报告', 'type' => 'primary', 'url' => $data['full_report_url'] ?? ''],
                    ['text' => '下载报告', 'type' => 'default', 'url' => $data['download_url'] ?? ''],
                ])
            ;
        });
    }

    /**
     * 注册告警模板
     */
    private function registerAlertTemplate(): void
    {
        $this->registerTemplate('alert', function (CardMessageBuilder $builder, array $data): void {
            $builder->setHeader('🚨 ' . ($data['title'] ?? '系统告警'), 'red')
                ->addText(\sprintf('**告警级别**：%s', $data['level'] ?? '高'), true)
                ->addText(\sprintf('**告警时间**：%s', $data['time'] ?? date('Y-m-d H:i:s')), true)
                ->addDivider()
                ->addText($data['message'] ?? '系统检测到异常情况')
                ->addDivider()
                ->addFields([
                    ['name' => '影响范围', 'value' => $data['impact'] ?? '未知'],
                    ['name' => '建议操作', 'value' => $data['suggestion'] ?? '请立即处理'],
                ], false)
                ->addDivider()
                ->addActions([
                    ['text' => '立即处理', 'type' => 'danger', 'url' => $data['handle_url'] ?? ''],
                    ['text' => '查看详情', 'type' => 'default', 'url' => $data['detail_url'] ?? ''],
                ])
            ;
        });
    }

    /**
     * 注册活动邀请模板
     */
    private function registerEventInvitationTemplate(): void
    {
        $this->registerTemplate('event_invitation', function (CardMessageBuilder $builder, array $data): void {
            $builder->setHeader($data['title'] ?? '活动邀请', 'indigo');

            if (isset($data['banner_image'])) {
                $builder->addImage($data['banner_image'], $data['title'] ?? '活动海报');
            }

            $builder->addText($data['description'] ?? '')
                ->addDivider()
                ->addFields([
                    ['name' => '📅 时间', 'value' => $data['time'] ?? ''],
                    ['name' => '📍 地点', 'value' => $data['location'] ?? ''],
                    ['name' => '👥 参与人数', 'value' => $data['participants'] ?? ''],
                    ['name' => '🎫 费用', 'value' => $data['fee'] ?? '免费'],
                ], true)
                ->addDivider()
                ->addActions([
                    ['text' => '立即报名', 'type' => 'primary', 'value' => ['action' => 'register', 'event_id' => $data['event_id'] ?? '']],
                    ['text' => '查看详情', 'type' => 'default', 'url' => $data['detail_url'] ?? ''],
                ])
            ;
        });
    }

    /**
     * 注册投票模板
     */
    private function registerPollTemplate(): void
    {
        $this->registerTemplate('poll', function (CardMessageBuilder $builder, array $data): void {
            $builder->setHeader($data['title'] ?? '投票', 'wathet')
                ->addText($data['question'] ?? '')
                ->addDivider()
            ;

            // 添加选项按钮
            if (isset($data['options']) && \is_array($data['options'])) {
                $actions = [];
                foreach ($data['options'] as $index => $option) {
                    $actions[] = [
                        'text' => $option,
                        'type' => 'default',
                        'value' => ['action' => 'vote', 'poll_id' => $data['poll_id'] ?? '', 'option' => $index],
                    ];
                }
                $builder->addActions($actions);
            }

            $builder->addDivider()
                ->addNote([\sprintf('投票截止时间：%s', $data['deadline'] ?? '无限制')])
            ;
        });
    }
}
