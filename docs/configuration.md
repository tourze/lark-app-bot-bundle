# 配置指南

## 环境变量配置

本Bundle使用环境变量进行配置。在你的 `.env` 文件中添加以下配置：

```bash
# 必填：飞书应用凭证
LARK_APP_ID=cli_xxxxxxxxxxxxx
LARK_APP_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# 可选：事件订阅配置
LARK_VERIFICATION_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
LARK_ENCRYPT_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# 可选：缓存目录（默认使用系统临时目录）
LARK_CACHE_DIR=/var/cache/lark_app_bot
```

## 服务配置

Bundle自动注册了以下服务：

### TokenProviderInterface

用于获取和管理飞书应用的访问令牌：

```php
use Tourze\LarkAppBotBundle\Authentication\TokenProviderInterface;

class MyService
{
    public function __construct(
        private TokenProviderInterface $tokenProvider
    ) {
    }

    public function doSomething(): void
    {
        $token = $this->tokenProvider->getToken();
        // 使用token调用飞书API
    }
}
```

### 使用自定义缓存

如果需要使用Redis或其他缓存系统，可以在服务配置中覆盖默认的缓存服务：

```yaml
# config/services.yaml
services:
    lark_app_bot.cache:
        class: Symfony\Component\Cache\Adapter\RedisAdapter
        arguments:
            - '@redis.connection'

    redis.connection:
        class: Redis
        calls:
            - connect:
                - '%env(REDIS_HOST)%'
                - '%env(int:REDIS_PORT)%'
```

## 日志配置

Bundle使用标准的Symfony日志系统。要启用日志，确保在Monolog配置中添加了相应的通道：

```yaml
# config/packages/monolog.yaml
monolog:
    channels:
        - lark_app_bot
    handlers:
        lark:
            type: stream
            path: '%kernel.logs_dir%/lark.log'
            level: debug
            channels: ['lark_app_bot']
```

## 示例：完整的应用配置

```bash
# .env.local
LARK_APP_ID=cli_a1234567890abcdef
LARK_APP_SECRET=1234567890abcdef1234567890abcdef
LARK_CACHE_DIR=%kernel.cache_dir%/lark_app_bot
```

然后在控制器或服务中使用：

```php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Tourze\LarkAppBotBundle\Authentication\TokenProviderInterface;

class TestController extends AbstractController
{
    public function __construct(
        private TokenProviderInterface $tokenProvider
    ) {
    }

    public function index(): Response
    {
        $token = $this->tokenProvider->getToken();
        
        return new Response('Token: ' . $token);
    }
}
```