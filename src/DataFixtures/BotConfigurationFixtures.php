<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\LarkAppBotBundle\Entity\BotConfiguration;

/**
 * 飞书机器人配置测试数据夹具.
 *
 * 创建不同类型的机器人配置，用于演示和测试配置管理功能
 */
class BotConfigurationFixtures extends Fixture
{
    // 引用常量定义
    public const BOT_CONFIG_WEBHOOK_REFERENCE = 'bot-config-webhook';
    public const BOT_CONFIG_TOKEN_REFERENCE = 'bot-config-token';
    public const BOT_CONFIG_INACTIVE_REFERENCE = 'bot-config-inactive';

    public function load(ObjectManager $manager): void
    {
        // 创建Webhook配置
        $config1 = new BotConfiguration();
        $config1->setAppId('cli_a1b2c3d4e5f6g7h8');
        $config1->setName('Webhook URL配置');
        $config1->setConfigKey('webhook_url');
        $config1->setConfigValue('https://your-domain.com/webhook/lark');
        $config1->setDescription('飞书机器人接收事件的回调地址');
        $config1->setIsActive(true);

        $manager->persist($config1);

        // 创建验证Token配置
        $config2 = new BotConfiguration();
        $config2->setAppId('cli_a1b2c3d4e5f6g7h8');
        $config2->setName('验证Token');
        $config2->setConfigKey('verification_token');
        $config2->setConfigValue('v1234567890abcdef');
        $config2->setDescription('用于验证飞书事件回调的安全token');
        $config2->setIsActive(true);

        $manager->persist($config2);

        // 创建加密密钥配置
        $config3 = new BotConfiguration();
        $config3->setAppId('cli_a1b2c3d4e5f6g7h8');
        $config3->setName('加密密钥');
        $config3->setConfigKey('encrypt_key');
        $config3->setConfigValue('abcdef1234567890abcdef1234567890');
        $config3->setDescription('用于解密飞书事件数据的密钥');
        $config3->setIsActive(true);

        $manager->persist($config3);

        // 创建App ID配置
        $config4 = new BotConfiguration();
        $config4->setAppId('cli_a1b2c3d4e5f6g7h8');
        $config4->setName('应用ID');
        $config4->setConfigKey('app_id');
        $config4->setConfigValue('cli_a1b2c3d4e5f6g7h8');
        $config4->setDescription('飞书应用的唯一标识符');
        $config4->setIsActive(true);

        $manager->persist($config4);

        // 创建App Secret配置
        $config5 = new BotConfiguration();
        $config5->setAppId('cli_a1b2c3d4e5f6g7h8');
        $config5->setName('应用密钥');
        $config5->setConfigKey('app_secret');
        $config5->setConfigValue('abc123def456ghi789jkl012mno345pqr678');
        $config5->setDescription('飞书应用的密钥，用于获取访问令牌');
        $config5->setIsActive(true);

        $manager->persist($config5);

        // 创建第二个应用的配置
        $config6 = new BotConfiguration();
        $config6->setAppId('cli_b2c3d4e5f6g7h8i9');
        $config6->setName('测试环境Webhook');
        $config6->setConfigKey('webhook_url');
        $config6->setConfigValue('https://test-domain.com/webhook/lark');
        $config6->setDescription('测试环境的webhook地址');
        $config6->setIsActive(true);

        $manager->persist($config6);

        // 创建消息模板配置
        $config7 = new BotConfiguration();
        $config7->setAppId('cli_a1b2c3d4e5f6g7h8');
        $config7->setName('欢迎消息模板');
        $config7->setConfigKey('welcome_message_template');
        $config7->setConfigValue('欢迎加入我们的群组！请查看群公告了解更多信息。');
        $config7->setDescription('新用户加入群组时的欢迎消息模板');
        $config7->setIsActive(true);

        $manager->persist($config7);

        // 创建未激活的配置
        $config8 = new BotConfiguration();
        $config8->setAppId('cli_a1b2c3d4e5f6g7h8');
        $config8->setName('已弃用的配置');
        $config8->setConfigKey('deprecated_setting');
        $config8->setConfigValue('old_value');
        $config8->setDescription('这是一个已经不再使用的配置项');
        $config8->setIsActive(false);

        $manager->persist($config8);

        // 创建API限流配置
        $config9 = new BotConfiguration();
        $config9->setAppId('cli_a1b2c3d4e5f6g7h8');
        $config9->setName('API限流配置');
        $config9->setConfigKey('api_rate_limit');
        $config9->setConfigValue('{"requests_per_minute": 60, "burst_limit": 10}');
        $config9->setDescription('API调用频率限制配置（JSON格式）');
        $config9->setIsActive(true);

        $manager->persist($config9);

        // 创建超时配置
        $config10 = new BotConfiguration();
        $config10->setAppId('cli_a1b2c3d4e5f6g7h8');
        $config10->setName('API超时时间');
        $config10->setConfigKey('api_timeout');
        $config10->setConfigValue('30');
        $config10->setDescription('API调用超时时间（秒）');
        $config10->setIsActive(true);

        $manager->persist($config10);

        $manager->flush();

        // 设置引用，供其他 Fixtures 使用
        $this->addReference(self::BOT_CONFIG_WEBHOOK_REFERENCE, $config1);
        $this->addReference(self::BOT_CONFIG_TOKEN_REFERENCE, $config2);
        $this->addReference(self::BOT_CONFIG_INACTIVE_REFERENCE, $config8);
    }
}
