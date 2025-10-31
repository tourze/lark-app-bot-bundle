<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Message\Template\WelcomeMessageTemplate;

/**
 * @internal
 */
#[CoversClass(WelcomeMessageTemplate::class)]
final class WelcomeMessageTemplateTest extends TestCase
{
    private WelcomeMessageTemplate $template;

    protected function setUp(): void
    {
        $this->template = new WelcomeMessageTemplate();
    }

    public function testGetName(): void
    {
        $this->assertSame('welcome_message', $this->template->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertSame('新用户/新成员欢迎消息模板', $this->template->getDescription());
    }

    public function testGetRequiredVariables(): void
    {
        $variables = $this->template->getRequiredVariables();

        $this->assertIsArray($variables);
        $this->assertArrayHasKey('user_name', $variables);
        $this->assertArrayHasKey('user_id', $variables);
    }

    public function testRenderWithMinimalVariables(): void
    {
        $variables = [
            'user_name' => '张三',
            'user_id' => 'user123',
        ];

        $builder = $this->template->render($variables);
        $result = $builder->build();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('zh_cn', $result);
        $this->assertSame('欢迎', $result['zh_cn']['title']);

        // 验证内容包含用户信息
        $contentStr = json_encode($result['zh_cn']['content'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($contentStr, 'JSON encoding should not fail');
        $this->assertStringContainsString('user123', $contentStr);
        $this->assertStringContainsString('张三', $contentStr);
    }

    public function testRenderWithGroupInfo(): void
    {
        $variables = [
            'user_name' => '李四',
            'user_id' => 'user456',
            'group_name' => '技术讨论组',
        ];

        $builder = $this->template->render($variables);
        $result = $builder->build();

        $this->assertSame('欢迎加入 技术讨论组', $result['zh_cn']['title']);

        $contentStr = json_encode($result['zh_cn']['content'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($contentStr, 'JSON encoding should not fail');
        $this->assertStringContainsString('技术讨论组', $contentStr);
    }

    public function testRenderWithRulesAndTips(): void
    {
        $variables = [
            'user_name' => '王五',
            'user_id' => 'user789',
            'group_name' => '项目组',
            'rules' => [
                '保持友善和尊重',
                '禁止发送广告',
                '技术讨论为主',
            ],
            'tips' => [
                '可以使用 @all 通知所有人',
                '支持发送图片和文件',
            ],
        ];

        $builder = $this->template->render($variables);
        $result = $builder->build();

        $contentStr = json_encode($result['zh_cn']['content'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($contentStr, 'JSON encoding should not fail');

        // 验证规则
        $this->assertStringContainsString('群规则', $contentStr);
        $this->assertStringContainsString('保持友善和尊重', $contentStr);
        $this->assertStringContainsString('禁止发送广告', $contentStr);

        // 验证提示
        $this->assertStringContainsString('新手提示', $contentStr);
        $this->assertStringContainsString('@all', $contentStr);
    }

    public function testValidateVariables(): void
    {
        // 有效的变量
        $validVariables = [
            'user_name' => '测试用户',
            'user_id' => 'test123',
        ];
        $this->assertTrue($this->template->validateVariables($validVariables));

        // 缺少必需的变量
        $invalidVariables1 = [
            'user_name' => '测试用户',
        ];
        $this->assertFalse($this->template->validateVariables($invalidVariables1));

        // 空值
        $invalidVariables2 = [
            'user_name' => '',
            'user_id' => 'test123',
        ];
        $this->assertFalse($this->template->validateVariables($invalidVariables2));
    }

    public function testRenderWithMissingVariableThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $variables = [
            'user_name' => '测试用户',
            // 缺少 user_id
        ];

        $this->template->render($variables);
    }
}
