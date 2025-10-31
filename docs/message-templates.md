# 消息模板使用指南

## 概述

飞书应用机器人Bundle提供了一套完整的消息模板系统，帮助开发者快速构建标准化的消息内容。

## 内置模板

### 1. 欢迎消息模板 (WelcomeMessageTemplate)

用于新用户加入群组或初次使用时的欢迎消息。

#### 必需变量
- `user_name` - 用户名称
- `user_id` - 用户ID

#### 可选变量
- `group_name` - 群组名称
- `rules` - 群规则数组
- `tips` - 新手提示数组

#### 使用示例

```php
use Tourze\LarkAppBotBundle\Message\MessageService;
use Tourze\LarkAppBotBundle\Message\Template\WelcomeMessageTemplate;

$template = new WelcomeMessageTemplate();
$builder = $template->render([
    'user_name' => '张三',
    'user_id' => 'ou_123456',
    'group_name' => '技术讨论组',
    'rules' => [
        '保持友善和尊重',
        '禁止发送广告',
        '技术讨论为主'
    ],
    'tips' => [
        '可以使用 @all 通知所有人',
        '支持发送图片和文件'
    ]
]);

$messageService->sendWithBuilder('chat_123456', $builder, MessageService::RECEIVE_ID_TYPE_CHAT_ID);
```

### 2. 通知消息模板 (NotificationMessageTemplate)

用于系统通知、提醒等场景，支持不同级别的通知。

#### 必需变量
- `title` - 通知标题
- `content` - 通知内容

#### 可选变量
- `level` - 通知级别 (info/success/warning/error)
- `time` - 通知时间
- `actions` - 操作按钮数组
- `mentions` - 需要@的用户数组

#### 使用示例

```php
use Tourze\LarkAppBotBundle\Message\Template\NotificationMessageTemplate;

$template = new NotificationMessageTemplate();
$builder = $template->render([
    'title' => '部署成功',
    'content' => '您的应用已成功部署到生产环境',
    'level' => NotificationMessageTemplate::LEVEL_SUCCESS,
    'time' => new \DateTime(),
    'actions' => [
        ['text' => '查看详情', 'url' => 'https://example.com/deploy/123'],
        ['text' => '回滚', 'url' => 'https://example.com/rollback/123']
    ],
    'mentions' => [
        ['user_id' => 'ou_123', 'user_name' => '张三'],
        ['user_id' => 'ou_456', 'user_name' => '李四']
    ]
]);

$messageService->sendWithBuilder('chat_123456', $builder, MessageService::RECEIVE_ID_TYPE_CHAT_ID);
```

## 自定义模板

### 创建自定义模板

实现 `MessageTemplateInterface` 接口或继承 `AbstractMessageTemplate` 类：

```php
use Tourze\LarkAppBotBundle\Message\Template\AbstractMessageTemplate;
use Tourze\LarkAppBotBundle\Message\Builder\MessageBuilderInterface;
use Tourze\LarkAppBotBundle\Message\Builder\RichTextBuilder;

class TaskAssignmentTemplate extends AbstractMessageTemplate
{
    public function getName(): string
    {
        return 'task_assignment';
    }

    public function getDescription(): string
    {
        return '任务分配通知模板';
    }

    public function render(array $variables = []): MessageBuilderInterface
    {
        $taskTitle = $this->getVariable($variables, 'task_title');
        $assigneeId = $this->getVariable($variables, 'assignee_id');
        $assigneeName = $this->getVariable($variables, 'assignee_name');
        $dueDate = $this->getVariable($variables, 'due_date');
        $priority = $this->getVariable($variables, 'priority', 'normal');
        
        $builder = RichTextBuilder::create()
            ->setTitle('📋 新任务分配');
            
        // 根据优先级添加不同的标记
        if ($priority === 'high') {
            $builder->addBold('🔴 高优先级任务')
                    ->newParagraph();
        }
        
        $builder->addText('任务：')
                ->addBold($taskTitle)
                ->newParagraph()
                ->addText('负责人：')
                ->atUser($assigneeId, $assigneeName)
                ->newParagraph()
                ->addText('截止日期：')
                ->addUnderline($this->formatTime($dueDate, 'Y年m月d日'))
                ->newParagraph()
                ->addLineBreak()
                ->addText('请及时处理，如有问题请联系项目经理。');
                
        return $builder;
    }

    public function getRequiredVariables(): array
    {
        return [
            'task_title' => '任务标题',
            'assignee_id' => '负责人ID',
            'assignee_name' => '负责人名称',
            'due_date' => '截止日期',
        ];
    }
}
```

### 注册自定义模板

在Symfony服务配置中注册：

```yaml
services:
    App\Message\Template\TaskAssignmentTemplate:
        tags:
            - { name: lark.message_template }
```

### 使用模板管理器

```php
use Tourze\LarkAppBotBundle\Message\Template\TemplateManager;

// 获取模板管理器
$templateManager = $container->get(TemplateManager::class);

// 注册自定义模板
$templateManager->registerTemplate(new TaskAssignmentTemplate());

// 获取并使用模板
$template = $templateManager->getTemplate('task_assignment');
$builder = $template->render([
    'task_title' => '完成用户注册功能',
    'assignee_id' => 'ou_789',
    'assignee_name' => '王五',
    'due_date' => new \DateTime('+3 days'),
    'priority' => 'high'
]);
```

## 模板最佳实践

### 1. 变量验证

始终验证必需的变量是否存在：

```php
protected function render(array $variables = []): MessageBuilderInterface
{
    // 使用 getVariable 方法自动验证
    $userName = $this->getVariable($variables, 'user_name');
    
    // 对于可选变量，提供默认值
    $level = $this->getVariable($variables, 'level', 'info');
}
```

### 2. 时间格式化

使用内置的时间格式化方法：

```php
$formattedTime = $this->formatTime($variables['created_at'], 'Y-m-d H:i:s');
```

### 3. 文本处理

使用内置的文本处理方法：

```php
// 截断长文本
$summary = $this->truncateText($longText, 100);

// 转义HTML字符
$safeText = $this->escapeHtml($userInput);
```

### 4. 多语言支持

对于需要支持多语言的模板：

```php
public function render(array $variables = []): MessageBuilderInterface
{
    $locale = $this->getVariable($variables, 'locale', 'zh_cn');
    
    $builder = RichTextBuilder::create();
    $builder->setLocale($locale);
    
    if ($locale === 'zh_cn') {
        $builder->setTitle('新任务');
    } else {
        $builder->setTitle('New Task');
    }
    
    return $builder;
}
```

## 常见问题

### Q: 如何在模板中添加图片？

```php
$builder->addImage($imageKey, 300, 200); // 宽度和高度可选
```

### Q: 如何创建复杂的消息结构？

使用 `newParagraph()` 方法组织内容：

```php
$builder->addBold('第一部分')
        ->newParagraph()
        ->addText('详细内容...')
        ->newParagraph()
        ->addBold('第二部分')
        ->newParagraph()
        ->addText('更多内容...');
```

### Q: 如何处理模板渲染错误？

```php
try {
    $builder = $template->render($variables);
} catch (ValidationException $e) {
    // 处理缺少必需变量的情况
    $this->logger->error('模板渲染失败', [
        'template' => $template->getName(),
        'error' => $e->getMessage()
    ]);
}
```

## 总结

消息模板系统提供了：

1. **标准化** - 确保消息格式的一致性
2. **复用性** - 避免重复编写相似的消息构建代码
3. **可维护性** - 集中管理消息格式，便于统一修改
4. **类型安全** - 通过变量验证确保数据完整性

通过使用消息模板，您可以更高效地构建和管理飞书消息，提供更好的用户体验。