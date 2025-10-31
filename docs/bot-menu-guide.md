# 机器人菜单使用指南
最后更新于 2025-03-31

> **官方文档：** [机器人菜单使用指南 - 飞书开放平台](https://open.feishu.cn/document/client-docs/bot-v3/bot-customized-menu)

本文介绍如何为飞书机器人配置自定义菜单，提供便捷的交互入口。

## 机器人菜单概述

机器人菜单是飞书应用机器人的一项重要功能，通过为机器人配置自定义菜单，可以将应用的常用功能固定在机器人的聊天输入框附近，用户可以通过点击菜单项快速触发相应功能。

### 菜单特点

- **便捷访问**：将常用功能以菜单形式展示，用户一键即达
- **界面友好**：可视化的操作界面，降低使用门槛
- **功能聚合**：将多个功能整合在一个入口，提升用户体验
- **响应式设计**：支持不同屏幕尺寸的设备

### 应用场景

1. **功能导航**：为复杂应用提供功能导航
2. **快捷操作**：常用操作的快速入口
3. **信息查询**：快速查询不同类型的信息
4. **工作流触发**：一键启动预定义的工作流程

## 菜单配置

### 配置入口

1. **进入开发者后台**
   - 访问 [飞书开放平台](https://open.feishu.cn/app)
   - 选择要配置菜单的应用

2. **找到菜单配置**
   - 在应用详情页，点击"应用功能" > "机器人"
   - 找到"机器人菜单"配置项

### 菜单结构

机器人菜单支持两级结构：

```
一级菜单
├── 二级菜单项1
├── 二级菜单项2
└── 二级菜单项3
```

### 菜单项配置

#### 一级菜单配置

```json
{
  "menu": {
    "list": [
      {
        "text": "数据查询",
        "sub_menu": {
          "list": [
            {
              "text": "销售数据",
              "value": "query_sales",
              "type": "text"
            },
            {
              "text": "用户统计",
              "value": "query_users", 
              "type": "text"
            }
          ]
        }
      },
      {
        "text": "系统设置",
        "value": "settings",
        "type": "text"
      }
    ]
  }
}
```

#### 配置参数说明

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| text | string | 是 | 菜单显示文本，最多8个字符 |
| value | string | 否 | 菜单值，点击时发送给机器人 |
| type | string | 是 | 菜单类型，目前仅支持 "text" |
| sub_menu | object | 否 | 二级菜单配置 |

### 菜单限制

- **一级菜单**：最多3个
- **二级菜单**：每个一级菜单下最多5个二级菜单
- **文本长度**：菜单文本最多8个字符
- **菜单类型**：目前仅支持文本类型

## 菜单事件处理

### 事件订阅

在开发者后台的"事件订阅"页面，订阅以下事件：

```
application.bot.menu_v6
```

### 事件数据结构

当用户点击菜单时，机器人将收到以下格式的事件：

```json
{
  "schema": "2.0",
  "header": {
    "event_id": "xxx",
    "event_type": "application.bot.menu_v6",
    "create_time": "1609073151000",
    "token": "xxx",
    "app_id": "xxx",
    "tenant_key": "xxx"
  },
  "event": {
    "operator": {
      "operator_id": {
        "union_id": "on_xxx",
        "user_id": "xxx", 
        "open_id": "ou_xxx"
      },
      "operator_type": "user"
    },
    "event_key": "query_sales",
    "timestamp": "1609073151"
  }
}
```

### 事件字段说明

| 字段名 | 类型 | 描述 |
|--------|------|------|
| operator | object | 操作者信息 |
| event_key | string | 菜单项的value值 |
| timestamp | string | 事件发生时间戳 |

## 开发实现

### 1. 处理菜单事件

```python
from flask import Flask, request, jsonify

app = Flask(__name__)

@app.route('/webhook', methods=['POST'])
def handle_webhook():
    data = request.json
    
    # 处理菜单事件
    if data.get('header', {}).get('event_type') == 'application.bot.menu_v6':
        return handle_menu_event(data)
    
    return jsonify({"code": 0})

def handle_menu_event(data):
    event = data.get('event', {})
    event_key = event.get('event_key')
    operator = event.get('operator', {})
    user_id = operator.get('operator_id', {}).get('open_id')
    
    # 根据菜单项执行相应操作
    if event_key == 'query_sales':
        return handle_sales_query(user_id)
    elif event_key == 'query_users':
        return handle_user_query(user_id)
    elif event_key == 'settings':
        return handle_settings(user_id)
    
    return jsonify({"code": 0})
```

### 2. 响应菜单操作

```python
import requests

def handle_sales_query(user_id):
    # 查询销售数据
    sales_data = get_sales_data()
    
    # 发送回复消息
    send_message(user_id, {
        "msg_type": "post",
        "content": {
            "post": {
                "zh_cn": {
                    "title": "销售数据查询结果",
                    "content": [
                        [
                            {"tag": "text", "text": "今日销售额：", "style": ["bold"]},
                            {"tag": "text", "text": f"¥{sales_data['today']:,.2f}"}
                        ],
                        [
                            {"tag": "text", "text": "本月销售额：", "style": ["bold"]},
                            {"tag": "text", "text": f"¥{sales_data['month']:,.2f}"}
                        ]
                    ]
                }
            }
        }
    })
    
    return jsonify({"code": 0})

def send_message(user_id, content):
    url = "https://open.feishu.cn/open-apis/im/v1/messages"
    headers = {
        "Authorization": f"Bearer {get_tenant_access_token()}",
        "Content-Type": "application/json"
    }
    data = {
        "receive_id": user_id,
        "receive_id_type": "open_id",
        **content
    }
    
    response = requests.post(url, headers=headers, json=data)
    return response.json()
```

### 3. 动态菜单更新

```python
def update_bot_menu(new_menu_config):
    """更新机器人菜单配置"""
    url = "https://open.feishu.cn/open-apis/application/v6/application/app_menu"
    headers = {
        "Authorization": f"Bearer {get_tenant_access_token()}",
        "Content-Type": "application/json"
    }
    
    response = requests.patch(url, headers=headers, json=new_menu_config)
    return response.json()

# 示例：根据用户权限动态调整菜单
def customize_menu_for_user(user_id):
    user_role = get_user_role(user_id)
    
    if user_role == 'admin':
        menu_config = {
            "menu": {
                "list": [
                    {
                        "text": "数据查询",
                        "sub_menu": {
                            "list": [
                                {"text": "销售数据", "value": "query_sales", "type": "text"},
                                {"text": "用户统计", "value": "query_users", "type": "text"},
                                {"text": "系统监控", "value": "query_system", "type": "text"}
                            ]
                        }
                    },
                    {
                        "text": "系统管理",
                        "sub_menu": {
                            "list": [
                                {"text": "用户管理", "value": "manage_users", "type": "text"},
                                {"text": "权限设置", "value": "manage_permissions", "type": "text"}
                            ]
                        }
                    }
                ]
            }
        }
    else:
        menu_config = {
            "menu": {
                "list": [
                    {
                        "text": "数据查询",
                        "sub_menu": {
                            "list": [
                                {"text": "个人数据", "value": "query_personal", "type": "text"},
                                {"text": "团队数据", "value": "query_team", "type": "text"}
                            ]
                        }
                    }
                ]
            }
        }
    
    return update_bot_menu(menu_config)
```

## 用户体验优化

### 1. 菜单设计原则

**简洁明了**：
- 菜单文字简短易懂
- 功能分类清晰
- 避免功能重复

**层次分明**：
- 相关功能归类到同一个一级菜单
- 常用功能放在显眼位置
- 避免菜单层级过深

### 2. 交互优化

**即时反馈**：
```python
def handle_menu_event_with_feedback(data):
    # 立即发送确认消息
    send_immediate_feedback(user_id, "正在处理您的请求...")
    
    # 处理具体业务逻辑
    result = process_business_logic(event_key)
    
    # 发送最终结果
    send_final_result(user_id, result)
```

**错误处理**：
```python
def handle_menu_event_safely(data):
    try:
        return process_menu_event(data)
    except Exception as e:
        logger.error(f"Menu event error: {e}")
        send_error_message(user_id, "抱歉，处理过程中发生错误，请稍后重试")
        return jsonify({"code": 0})
```

### 3. 个性化配置

**用户偏好**：
- 记录用户常用功能
- 根据使用频率调整菜单顺序
- 提供个性化设置选项

**上下文感知**：
- 根据对话历史调整菜单
- 基于用户角色显示不同菜单
- 考虑时间和场景因素

## 最佳实践

### 1. 菜单规划

**功能分析**：
1. 列出机器人所有功能
2. 按使用频率和重要性排序
3. 将相关功能归类
4. 设计合理的菜单结构

**用户调研**：
- 了解用户的使用习惯
- 收集用户反馈意见
- 持续优化菜单设计

### 2. 开发建议

**代码组织**：
```python
class BotMenuHandler:
    def __init__(self):
        self.menu_handlers = {
            'query_sales': self.handle_sales_query,
            'query_users': self.handle_user_query,
            'settings': self.handle_settings,
        }
    
    def handle_menu_event(self, event_key, user_id):
        handler = self.menu_handlers.get(event_key)
        if handler:
            return handler(user_id)
        else:
            return self.handle_unknown_menu(event_key, user_id)
```

**配置管理**：
- 将菜单配置存储在配置文件中
- 支持热更新菜单配置
- 提供菜单配置的版本管理

### 3. 监控与分析

**使用统计**：
- 记录菜单点击次数
- 分析用户使用偏好
- 识别低使用率的功能

**性能监控**：
- 监控菜单响应时间
- 追踪错误率
- 优化响应速度

## 常见问题

### Q: 菜单不显示怎么办？

A: 检查以下几点：
- 确认菜单配置格式正确
- 检查应用是否已发布
- 验证用户是否在应用可用范围内
- 确认机器人能力已开启

### Q: 如何实现多语言菜单？

A: 目前菜单配置暂不支持多语言，建议：
- 在应用中检测用户语言偏好
- 动态更新菜单配置
- 或在菜单点击后根据用户语言返回相应内容

### Q: 菜单事件处理失败如何调试？

A: 调试方法：
- 检查事件订阅配置
- 验证Webhook地址可访问性
- 查看应用日志
- 使用开发者工具测试

### Q: 可以动态修改菜单吗？

A: 可以通过API动态更新菜单配置，但需要注意：
- 频繁更新可能影响用户体验
- 建议在用户首次使用时初始化菜单
- 重大更新时通知用户

## 相关链接

- [机器人概述](./bot-overview.md)
- [快速入门](./quick-start.md)
- [机器人使用指南](./bot-usage-guide.md)
- [飞书开放平台 API 文档](https://open.feishu.cn/document/server-docs/api-call-guide/calling-process/overview)