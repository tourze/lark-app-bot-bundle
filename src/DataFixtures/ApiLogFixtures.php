<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\LarkAppBotBundle\Entity\ApiLog;

/**
 * 飞书API调用日志测试数据夹具.
 *
 * 创建不同类型和状态的API调用日志，用于演示和测试API日志管理功能
 */
class ApiLogFixtures extends Fixture
{
    // 引用常量定义
    public const API_LOG_SUCCESS_REFERENCE = 'api-log-success';
    public const API_LOG_ERROR_REFERENCE = 'api-log-error';
    public const API_LOG_SLOW_REFERENCE = 'api-log-slow';

    public function load(ObjectManager $manager): void
    {
        // 创建成功的API调用日志
        $log1 = new ApiLog();
        $log1->setEndpoint('/api/v1/messages/send');
        $log1->setMethod('POST');
        $log1->setStatusCode(200);
        $log1->setRequestData([
            'chat_id' => 'oc_xxx123',
            'msg_type' => 'text',
            'content' => ['text' => '测试消息'],
        ]);
        $log1->setResponseData([
            'code' => 0,
            'msg' => 'success',
            'data' => ['message_id' => 'om_xxx456'],
        ]);
        $log1->setResponseTime(150);
        $log1->setUserId('ou_user001');

        $manager->persist($log1);

        // 创建另一个成功的API调用
        $log2 = new ApiLog();
        $log2->setEndpoint('/api/v1/users/info');
        $log2->setMethod('GET');
        $log2->setStatusCode(200);
        $log2->setRequestData(['user_id' => 'ou_user002']);
        $log2->setResponseData([
            'code' => 0,
            'data' => [
                'user_id' => 'ou_user002',
                'name' => '张三',
                'email' => 'zhangsan@example.com',
            ],
        ]);
        $log2->setResponseTime(80);
        $log2->setUserId('ou_user002');

        $manager->persist($log2);

        // 创建客户端错误的API调用
        $log3 = new ApiLog();
        $log3->setEndpoint('/api/v1/groups/create');
        $log3->setMethod('POST');
        $log3->setStatusCode(400);
        $log3->setRequestData([
            'name' => '',  // 空名称导致错误
            'description' => '测试群组',
        ]);
        $log3->setResponseData([
            'code' => 400,
            'msg' => 'Invalid request: name cannot be empty',
        ]);
        $log3->setResponseTime(45);
        $log3->setUserId('ou_user003');

        $manager->persist($log3);

        // 创建未找到资源的API调用
        $log4 = new ApiLog();
        $log4->setEndpoint('/api/v1/messages/get');
        $log4->setMethod('GET');
        $log4->setStatusCode(404);
        $log4->setRequestData(['message_id' => 'om_notfound']);
        $log4->setResponseData([
            'code' => 404,
            'msg' => 'Message not found',
        ]);
        $log4->setResponseTime(120);
        $log4->setUserId('ou_user004');

        $manager->persist($log4);

        // 创建服务器错误的API调用
        $log5 = new ApiLog();
        $log5->setEndpoint('/api/v1/files/upload');
        $log5->setMethod('POST');
        $log5->setStatusCode(500);
        $log5->setRequestData(['file_type' => 'image']);
        $log5->setResponseData([
            'code' => 500,
            'msg' => 'Internal server error',
        ]);
        $log5->setResponseTime(3000);
        $log5->setUserId('ou_user005');

        $manager->persist($log5);

        // 创建慢响应的API调用
        $log6 = new ApiLog();
        $log6->setEndpoint('/api/v1/chats/members');
        $log6->setMethod('GET');
        $log6->setStatusCode(200);
        $log6->setRequestData(['chat_id' => 'oc_biggroup']);
        $log6->setResponseData([
            'code' => 0,
            'data' => [
                'members' => array_fill(0, 100, ['user_id' => 'ou_userXXX']),
            ],
        ]);
        $log6->setResponseTime(2500);  // 慢响应
        $log6->setUserId('ou_user006');

        $manager->persist($log6);

        // 创建PUT请求日志
        $log7 = new ApiLog();
        $log7->setEndpoint('/api/v1/groups/update');
        $log7->setMethod('PUT');
        $log7->setStatusCode(200);
        $log7->setRequestData([
            'chat_id' => 'oc_update123',
            'name' => '更新后的群组名称',
        ]);
        $log7->setResponseData([
            'code' => 0,
            'msg' => 'success',
        ]);
        $log7->setResponseTime(200);
        $log7->setUserId('ou_user007');

        $manager->persist($log7);

        // 创建DELETE请求日志
        $log8 = new ApiLog();
        $log8->setEndpoint('/api/v1/messages/delete');
        $log8->setMethod('DELETE');
        $log8->setStatusCode(204);
        $log8->setRequestData(['message_id' => 'om_delete123']);
        $log8->setResponseData(null);
        $log8->setResponseTime(95);
        $log8->setUserId('ou_user008');

        $manager->persist($log8);

        $manager->flush();

        // 设置引用，供其他 Fixtures 使用
        $this->addReference(self::API_LOG_SUCCESS_REFERENCE, $log1);
        $this->addReference(self::API_LOG_ERROR_REFERENCE, $log3);
        $this->addReference(self::API_LOG_SLOW_REFERENCE, $log6);
    }
}
