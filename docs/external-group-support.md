# 机器人支持外部群和外部用户单聊
最后更新于 2025-03-31

> **官方文档：** [机器人支持外部群和外部用户单聊 - 飞书开放平台](https://open.feishu.cn/document/develop-robots/add-bot-to-external-group)

本文介绍如何配置机器人支持外部群聊和与外部用户单聊的功能。

## 功能概述

飞书机器人的外部群和外部用户支持功能，允许企业内的机器人与外部组织的用户进行交互，实现跨组织的协作和沟通。

### 主要特性

- **外部群聊**：机器人可以被添加到包含外部用户的群组中
- **外部单聊**：机器人可以与外部用户进行一对一对话
- **权限控制**：精细化的权限管理，确保数据安全
- **身份识别**：自动识别内部用户和外部用户

### 应用场景

1. **客户服务**：为外部客户提供智能客服支持
2. **合作伙伴协作**：与合作伙伴进行项目协作和信息同步
3. **供应商管理**：与供应商进行订单、物流等信息交互
4. **跨组织会议**：在包含外部参与者的会议群中提供助手服务

## 前提条件

### 企业认证要求

只有通过飞书认证的企业才能使用外部群和外部用户功能：

1. **企业实名认证**：完成企业实名认证流程
2. **飞书认证**：获得飞书官方认证标识
3. **管理员授权**：企业管理员开启外部协作功能

### 技术要求

- **应用类型**：必须是企业自建应用
- **机器人能力**：已开启机器人功能
- **权限申请**：申请相应的外部交互权限

## 配置步骤

### 步骤一：开启外部协作

1. **企业管理后台配置**
   - 登录飞书企业管理后台
   - 进入"安全与合规" > "外部协作"
   - 开启"允许外部群聊"和"允许外部单聊"

2. **设置外部用户权限**
   - 配置外部用户可访问的功能范围
   - 设置数据访问限制
   - 定义交互规则

### 步骤二：应用配置

1. **进入开发者后台**
   - 访问 [飞书开放平台](https://open.feishu.cn/app)
   - 选择要配置的应用

2. **开启外部功能**
   - 在"应用功能" > "机器人"页面
   - 找到"外部群和外部用户"配置项
   - 开启相应功能

3. **权限申请**
   在"权限管理"页面申请以下权限：
   
   ```
   - 获取与发送单聊、群组消息（外部）
   - 以应用的身份发送消息（外部）
   - 获取用户基本信息（外部）
   ```

### 步骤三：发布应用

1. **创建版本**
   - 在"版本管理与发布"页面创建新版本
   - 说明外部功能的变更内容

2. **设置可用范围**
   - 配置应用的可用范围
   - 可选择"全员可用"或"部分成员可用"

3. **提交审核**
   - 提交应用审核
   - 等待管理员审核通过

## 开发实现

### 识别用户类型

```python
def identify_user_type(sender_info):
    """识别用户是内部用户还是外部用户"""
    tenant_key = sender_info.get('tenant_key')
    current_tenant = get_current_tenant_key()
    
    if tenant_key == current_tenant:
        return 'internal'
    else:
        return 'external'

def handle_message_event(data):
    event = data.get('event', {})
    sender = event.get('sender', {})
    message = event.get('message', {})
    
    user_type = identify_user_type(sender)
    
    if user_type == 'external':
        return handle_external_user_message(sender, message)
    else:
        return handle_internal_user_message(sender, message)
```

### 处理外部用户消息

```python
def handle_external_user_message(sender, message):
    """处理外部用户发送的消息"""
    user_id = sender.get('sender_id', {}).get('open_id')
    content = message.get('content')
    chat_id = message.get('chat_id')
    
    # 记录外部用户交互日志
    log_external_interaction(user_id, content, chat_id)
    
    # 应用外部用户专用的处理逻辑
    if is_customer_service_request(content):
        return handle_customer_service(user_id, content)
    elif is_collaboration_request(content):
        return handle_collaboration(user_id, content)
    else:
        return send_default_external_response(user_id)

def send_default_external_response(user_id):
    """向外部用户发送默认回复"""
    response = {
        "msg_type": "post",
        "content": {
            "post": {
                "zh_cn": {
                    "title": "欢迎使用智能助手",
                    "content": [
                        [
                            {"tag": "text", "text": "您好！我是企业智能助手。"}
                        ],
                        [
                            {"tag": "text", "text": "可以为您提供以下服务："},
                        ],
                        [
                            {"tag": "text", "text": "• 产品咨询"}
                        ],
                        [
                            {"tag": "text", "text": "• 技术支持"}
                        ],
                        [
                            {"tag": "text", "text": "• 合作洽谈"}
                        ]
                    ]
                }
            }
        }
    }
    
    return send_message(user_id, response)
```

### 外部群组处理

```python
def handle_external_group_message(chat_id, sender, message):
    """处理外部群组中的消息"""
    user_type = identify_user_type(sender)
    content = message.get('content')
    
    # 检查是否@了机器人
    if is_bot_mentioned(content):
        if user_type == 'external':
            return handle_external_user_in_group(chat_id, sender, message)
        else:
            return handle_internal_user_in_group(chat_id, sender, message)
    
    # 如果没有@机器人，根据群组设置决定是否响应
    group_settings = get_group_settings(chat_id)
    if group_settings.get('auto_respond_external', False):
        return provide_general_assistance(chat_id, content)

def handle_external_user_in_group(chat_id, sender, message):
    """处理外部用户在群组中@机器人的消息"""
    user_id = sender.get('sender_id', {}).get('open_id')
    
    # 特殊处理外部用户的请求
    response = {
        "msg_type": "text", 
        "content": {
            "text": f"<at user_id=\"{user_id}\"></at> 感谢您的咨询，我来为您提供帮助。"
        }
    }
    
    return send_group_message(chat_id, response)
```

### 权限和数据保护

```python
def check_external_user_permissions(user_id, requested_action):
    """检查外部用户权限"""
    external_permissions = get_external_user_permissions(user_id)
    
    # 定义外部用户允许的操作
    allowed_actions = [
        'basic_query',
        'product_info',
        'customer_service',
        'public_document_access'
    ]
    
    if requested_action not in allowed_actions:
        return False
    
    # 检查特定权限
    if requested_action in external_permissions:
        return True
    
    return False

def filter_sensitive_data(data, user_type):
    """根据用户类型过滤敏感数据"""
    if user_type == 'external':
        # 移除外部用户不应看到的敏感信息
        filtered_data = {
            key: value for key, value in data.items() 
            if key not in ['internal_id', 'employee_info', 'confidential_data']
        }
        return filtered_data
    
    return data

def send_safe_message_to_external(user_id, data):
    """安全地向外部用户发送消息"""
    # 过滤敏感数据
    safe_data = filter_sensitive_data(data, 'external')
    
    # 添加外部用户标识
    safe_data['external_user'] = True
    
    # 记录发送日志
    log_external_message(user_id, safe_data)
    
    return send_message(user_id, safe_data)
```

## 安全配置

### 数据保护策略

1. **数据分类**
   ```python
   DATA_CLASSIFICATION = {
       'public': ['product_info', 'company_intro', 'contact_info'],
       'internal': ['employee_data', 'financial_info'],
       'confidential': ['trade_secrets', 'customer_private_data']
   }
   
   def get_allowed_data_for_external(data_type):
       return data_type in DATA_CLASSIFICATION['public']
   ```

2. **访问控制**
   ```python
   def apply_external_access_control(user_id, request):
       # 检查用户是否在外部用户白名单中
       if not is_external_user_whitelisted(user_id):
           return deny_access("用户未在授权列表中")
       
       # 检查请求频率限制
       if exceeds_rate_limit(user_id):
           return deny_access("请求过于频繁，请稍后再试")
       
       # 检查请求内容合规性
       if not is_content_compliant(request):
           return deny_access("请求内容不符合规范")
       
       return allow_access()
   ```

### 审计和监控

```python
def log_external_interaction(user_id, action, content, timestamp=None):
    """记录外部用户交互日志"""
    if timestamp is None:
        timestamp = datetime.now()
    
    log_entry = {
        'user_id': user_id,
        'user_type': 'external',
        'action': action,
        'content': content,
        'timestamp': timestamp,
        'ip_address': get_user_ip(user_id),
        'user_agent': get_user_agent(user_id)
    }
    
    # 保存到审计日志
    save_audit_log(log_entry)
    
    # 如果是敏感操作，立即发送告警
    if is_sensitive_action(action):
        send_security_alert(log_entry)

def generate_external_usage_report():
    """生成外部用户使用报告"""
    report = {
        'total_external_users': count_external_users(),
        'total_interactions': count_external_interactions(),
        'top_requested_features': get_top_external_features(),
        'security_incidents': get_security_incidents(),
        'compliance_status': check_compliance_status()
    }
    
    return report
```

## 最佳实践

### 1. 用户体验优化

**差异化服务**：
- 为外部用户提供专门的欢迎消息
- 简化外部用户的操作流程
- 提供清晰的功能说明

**响应速度**：
- 优先处理外部用户的请求
- 设置合理的超时时间
- 提供进度反馈

### 2. 安全最佳实践

**最小权限原则**：
- 只授予外部用户必要的权限
- 定期审查和调整权限设置
- 实施严格的数据访问控制

**监控和告警**：
- 实时监控外部用户活动
- 设置异常行为告警
- 定期进行安全审计

### 3. 合规管理

**数据保护**：
- 遵循相关数据保护法规
- 实施数据加密和脱敏
- 建立数据删除机制

**用户同意**：
- 获得用户明确同意
- 提供隐私政策说明
- 支持用户撤回同意

## 常见问题

### Q: 外部用户无法与机器人交互？

A: 检查以下配置：
- 企业是否已通过飞书认证
- 外部协作功能是否已开启
- 应用权限是否申请并审核通过
- 用户是否在允许的范围内

### Q: 如何区分内部用户和外部用户？

A: 可以通过以下方式识别：
- 检查`tenant_key`是否与当前企业一致
- 使用用户信息API获取用户所属组织
- 通过用户ID前缀判断（内部用户和外部用户的ID格式可能不同）

### Q: 外部用户能访问哪些数据？

A: 外部用户的数据访问范围取决于：
- 企业的外部协作配置
- 应用的权限设置
- 具体的业务逻辑实现
- 建议只允许访问公开和授权的数据

### Q: 如何确保外部交互的安全性？

A: 建议采取以下措施：
- 实施严格的权限控制
- 加强数据过滤和脱敏
- 记录详细的审计日志
- 定期进行安全评估
- 建立异常监控机制

## 相关链接

- [机器人概述](./bot-overview.md)
- [快速入门](./quick-start.md)
- [机器人使用指南](./bot-usage-guide.md)
- [飞书开放平台安全指南](https://open.feishu.cn/document/home/security-guidelines)