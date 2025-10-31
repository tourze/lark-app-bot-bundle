<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\LarkAppBotBundle\Entity\MessageRecord;

/**
 * 飞书消息记录测试数据夹具.
 *
 * 创建不同类型的消息记录，用于演示和测试消息管理功能
 */
class MessageRecordFixtures extends Fixture
{
    // 引用常量定义
    public const MESSAGE_RECORD_TEXT_REFERENCE = 'message-record-text';
    public const MESSAGE_RECORD_BOT_REFERENCE = 'message-record-bot';
    public const MESSAGE_RECORD_CARD_REFERENCE = 'message-record-card';

    public function load(ObjectManager $manager): void
    {
        // 创建文本消息记录
        $message1 = new MessageRecord();
        $message1->setMessageId('om_text_message_001');
        $message1->setChatId('oc_internal_team_001');
        $message1->setChatType('group');
        $message1->setSenderId('ou_user_001');
        $message1->setSenderType('user');
        $message1->setMessageType('text');
        $message1->setContent([
            'text' => '大家好，今天的项目进度会议安排在下午3点，请大家准时参加。',
        ]);

        $manager->persist($message1);

        // 创建机器人消息记录
        $message2 = new MessageRecord();
        $message2->setMessageId('om_bot_message_001');
        $message2->setChatId('oc_internal_team_001');
        $message2->setChatType('group');
        $message2->setSenderId('ou_bot_001');
        $message2->setSenderType('bot');
        $message2->setMessageType('text');
        $message2->setContent([
            'text' => '提醒：明天是项目截止日期，请确保所有任务都已完成。',
        ]);

        $manager->persist($message2);

        // 创建图片消息记录
        $message3 = new MessageRecord();
        $message3->setMessageId('om_image_message_001');
        $message3->setChatId('oc_tech_discussion_001');
        $message3->setChatType('group');
        $message3->setSenderId('ou_user_002');
        $message3->setSenderType('user');
        $message3->setMessageType('image');
        $message3->setContent([
            'image_key' => 'img_v2_041b28e3-f2c9-4f2e-b5a8-8b0b5c5e2d1e',
            'width' => 1920,
            'height' => 1080,
        ]);

        $manager->persist($message3);

        // 创建文件消息记录
        $message4 = new MessageRecord();
        $message4->setMessageId('om_file_message_001');
        $message4->setChatId('oc_project_alpha_001');
        $message4->setChatType('group');
        $message4->setSenderId('ou_user_003');
        $message4->setSenderType('user');
        $message4->setMessageType('file');
        $message4->setContent([
            'file_key' => 'file_v2_041b28e3-f2c9-4f2e-b5a8-8b0b5c5e2d1e',
            'file_name' => '项目需求文档.pdf',
            'file_size' => 2048576,
            'file_type' => 'pdf',
        ]);

        $manager->persist($message4);

        // 创建卡片消息记录
        $message5 = new MessageRecord();
        $message5->setMessageId('om_card_message_001');
        $message5->setChatId('oc_customer_service_001');
        $message5->setChatType('group');
        $message5->setSenderId('ou_bot_002');
        $message5->setSenderType('bot');
        $message5->setMessageType('card');
        $message5->setContent([
            'config' => [
                'wide_screen_mode' => true,
            ],
            'header' => [
                'title' => [
                    'tag' => 'plain_text',
                    'content' => '工单提醒',
                ],
                'template' => 'blue',
            ],
            'elements' => [
                [
                    'tag' => 'div',
                    'text' => [
                        'tag' => 'lark_md',
                        'content' => '**工单编号：** TK-2025-001\n**优先级：** 高\n**状态：** 待处理',
                    ],
                ],
            ],
        ]);

        $manager->persist($message5);

        // 创建私聊文本消息
        $message6 = new MessageRecord();
        $message6->setMessageId('om_p2p_text_001');
        $message6->setChatId('p2p_chat_001');
        $message6->setChatType('p2p');
        $message6->setSenderId('ou_user_004');
        $message6->setSenderType('user');
        $message6->setMessageType('text');
        $message6->setContent([
            'text' => '你好，关于明天的会议议程，我想和你确认一下。',
        ]);

        $manager->persist($message6);

        // 创建音频消息记录
        $message7 = new MessageRecord();
        $message7->setMessageId('om_audio_message_001');
        $message7->setChatId('oc_cross_department_001');
        $message7->setChatType('group');
        $message7->setSenderId('ou_user_005');
        $message7->setSenderType('user');
        $message7->setMessageType('audio');
        $message7->setContent([
            'file_key' => 'audio_v2_041b28e3-f2c9-4f2e-b5a8-8b0b5c5e2d1e',
            'duration' => 45,
        ]);

        $manager->persist($message7);

        // 创建视频消息记录
        $message8 = new MessageRecord();
        $message8->setMessageId('om_video_message_001');
        $message8->setChatId('oc_external_partner_001');
        $message8->setChatType('group');
        $message8->setSenderId('ou_user_006');
        $message8->setSenderType('user');
        $message8->setMessageType('video');
        $message8->setContent([
            'file_key' => 'video_v2_041b28e3-f2c9-4f2e-b5a8-8b0b5c5e2d1e',
            'duration' => 120,
            'width' => 1280,
            'height' => 720,
        ]);

        $manager->persist($message8);

        // 创建表情包消息记录
        $message9 = new MessageRecord();
        $message9->setMessageId('om_sticker_message_001');
        $message9->setChatId('oc_small_test_group');
        $message9->setChatType('group');
        $message9->setSenderId('ou_user_007');
        $message9->setSenderType('user');
        $message9->setMessageType('sticker');
        $message9->setContent([
            'file_key' => 'sticker_v2_041b28e3-f2c9-4f2e-b5a8-8b0b5c5e2d1e',
        ]);

        $manager->persist($message9);

        // 创建富文本消息记录
        $message10 = new MessageRecord();
        $message10->setMessageId('om_post_message_001');
        $message10->setChatId('oc_company_all_staff');
        $message10->setChatType('group');
        $message10->setSenderId('ou_user_008');
        $message10->setSenderType('user');
        $message10->setMessageType('post');
        $message10->setContent([
            'zh_cn' => [
                'title' => '月度总结报告',
                'content' => [
                    [
                        [
                            'tag' => 'text',
                            'text' => '本月工作总结：',
                        ],
                    ],
                    [
                        [
                            'tag' => 'text',
                            'text' => '1. 完成了产品功能开发',
                        ],
                    ],
                    [
                        [
                            'tag' => 'text',
                            'text' => '2. 修复了关键bug',
                        ],
                    ],
                ],
            ],
        ]);

        $manager->persist($message10);

        $manager->flush();

        // 设置引用，供其他 Fixtures 使用
        $this->addReference(self::MESSAGE_RECORD_TEXT_REFERENCE, $message1);
        $this->addReference(self::MESSAGE_RECORD_BOT_REFERENCE, $message2);
        $this->addReference(self::MESSAGE_RECORD_CARD_REFERENCE, $message5);
    }
}
