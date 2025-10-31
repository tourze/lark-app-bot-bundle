# 发送消息内容结构
最后更新于 2025-06-12

> **官方文档：** [发送消息内容结构 - 飞书开放平台](https://open.feishu.cn/document/uAjLw4CM/ukTMukTMukTM/im-v1/message/create_json)


## 注意事项
1. 示例代码中的`receive_id`、`user_id`、`image_key`、`file_key`等参数均为示例数据，需替换为真实值。
2. 内容构造示例仅适用于**发送消息、回复消息、编辑消息接口**，不适用于批量发送消息接口和历史版本接口。
3. 不适用于自定义机器人，其使用方式需参考《自定义机器人使用指南》。


## 消息内容介绍
在发送消息、回复消息、编辑消息接口中，需传入`content`字段，不同`msg_type`对应不同结构。  
**文本类型示例**：
```json
{
  "receive_id": "ou_7d8a6e6df7621556ce0d21922b676706ccs",
  "content": "{\"text\":\" test content\"}",
  "msg_type": "text"
}
```
- **注意**：`content`为字符串类型，JSON结构需转义。可通过结构体序列化或第三方工具处理。


## 各类型消息内容JSON结构

### 1. 文本（text）
#### 内容示例
```json
{
  "text": "test content"
}
```

#### 参数说明
| 名称   | 类型   | 必填 | 描述         |
|--------|--------|------|--------------|
| text   | string | 是   | 文本内容     |
| 示例值 | "test content" | -    |              |

#### 请求体示例
```json
{
  "receive_id": "ou_7d8a6e6df7621556ce0d21922b67670xxxx",
  "content": "{\"text\":\"test content\"}",
  "msg_type": "text"
}
```

#### 高级功能
- **换行符**：使用`\n`，需转义。
  ```json
  {
    "content": "{\"text\":\"firstline \\n secondline \"}"
  }
  ```

- **@用户/所有人**：
    - @单个用户：`<at user_id="ou_xxxxxxx">用户名</at>`（需填有效ID）。
    - @所有人：`<at user_id="all"></at>`（需群开启@所有人功能）。  
      **示例**：
  ```json
  {
    "content": "{\"text\":\"<at user_id=\\\"ou_xxxxxxx\\\">Tom</at> text content\"}"
  }
  ```

- **样式标签**：支持`<b>`（加粗）、`<i>`（斜体）、`<u>`（下划线）、`<s>`（删除线），可嵌套。  
  **示例**：
  ```json
  {
    "content": "{\"text\":\"<b>bold content<i>, bold and italic content</i></b>\"}"
  }
  ```

- **超链接**：格式为`[文本](链接)`。  
  **示例**：
  ```json
  {
    "content": "{\"text\":\"[Feishu Open Platform](https://open.feishu.cn)\"}"
  }
  ```


### 2. 富文本（post）
支持文字、图片、视频、@、超链接等元素，需压缩为一行并转义。
#### 内容示例
```json
{
  "zh_cn": {
    "title": "我是一个标题",
    "content": [
      [{"tag":"text","text":"第一行:","style":["bold","underline"]}, ... ],
      [{"tag":"img","image_key":"img_xxx"}],
      ...
    ]
  }
}
```

#### 参数说明
| 名称     | 类型   | 必填 | 描述                 |
|----------|--------|------|----------------------|
| zh_cn/en_us | object | 是   | 多语言配置（至少一种） |
| title    | string | 否   | 标题                 |
| content  | array  | 是   | 内容段落（每个段落为node列表） |

#### 支持的标签和参数
- **文本（text）**：
  ```json
  {
    "tag": "text",
    "text": "内容",
    "style": ["bold", "italic"],
    "un_escape": false
  }
  ```

- **超链接（a）**：
  ```json
  {
    "tag": "a",
    "text": "链接文本",
    "href": "https://example.com",
    "style": ["underline"]
  }
  ```

- **@标签（at）**：
  ```json
  {
    "tag": "at",
    "user_id": "ou_xxx",
    "style": ["lineThrough"]
  }
  ```

- **图片（img）**：
  ```json
  {
    "tag": "img",
    "image_key": "img_xxx"
  }
  ```

- **Markdown（md）**：支持@、超链接、列表、代码块等，需独占段落。  
  **示例**：
  ```json
  {
    "tag": "md",
    "text": "**加粗** [链接](https://example.com)\n1. 列表项"
  }
  ```


### 3. 图片（image）
#### 内容示例
```json
{
  "image_key": "img_7ea74629-9191-4176-998c-2e603c9c5e8g"
}
```

#### 参数说明
| 名称       | 类型   | 必填 | 描述               |
|------------|--------|------|--------------------|
| image_key  | string | 是   | 图片Key（上传获取） |

#### 请求体示例
```json
{
  "receive_id": "oc_xxx",
  "content": "{\"image_key\": \"img_v2_xxx\"}",
  "msg_type": "image"
}
```


### 4. 卡片（interactive）
支持通过卡片实体ID、模板ID或JSON发送。
#### 方式一：卡片实体ID
```json
{
  "content": "{\"type\":\"card\",\"data\":{\"card_id\":\"7371713483664506900\"}}"
}
```

#### 方式二：模板ID
```json
{
  "content": "{\"type\":\"template\",\"data\":{
    \"template_id\":\"xxxxxxxxxxxx\",
    \"template_version_name\":\"1.0.0\",
    \"template_variable\":{\"key1\":\"value1\"}
  }}"
}
```

#### 方式三：卡片JSON
```json
{
  "content": "{\"schema\":\"2.0\",\"body\":{...}}" // 压缩转义后的JSON
}
```


### 5. 分享群名片（share_chat）
#### 内容示例
```json
{
  "chat_id": "oc_0dd200d32fda15216d2c2ef1ddb32f76"
}
```

#### 参数说明
| 名称     | 类型   | 必填 | 描述         |
|----------|--------|------|--------------|
| chat_id  | string | 是   | 群ID（需机器人在群内） |


### 6. 分享个人名片（share_user）
#### 内容示例
```json
{
  "user_id": "ou_0dd200d32fda15216d2c2ef1ddb32f76"
}
```

#### 参数说明
| 名称     | 类型   | 必填 | 描述               |
|----------|--------|------|--------------------|
| user_id  | string | 是   | 用户OpenID（需在可用范围） |


### 7. 语音（audio）
#### 内容示例
```json
{
  "file_key": "75235e0c-4f92-430a-a99b-8446610223cg"
}
```

#### 参数说明
| 名称     | 类型   | 必填 | 描述               |
|----------|--------|------|--------------------|
| file_key | string | 是   | 语音文件Key（上传获取） |


### 8. 视频（media）
#### 内容示例
```json
{
  "file_key": "file_xxx",
  "image_key": "img_xxx" // 可选封面
}
```


### 9. 文件（file）
#### 内容示例
```json
{
  "file_key": "75235e0c-4f92-430a-a99b-8446610223cg"
}
```


### 10. 表情包（sticker）
#### 内容示例
```json
{
  "file_key": "file_xxx" // 需通过接收消息事件获取
}
```


### 11. 系统消息（system）
#### 内容示例
```json
{
  "type": "divider",
  "params": {
    "divider_text": {
      "text": "新会话",
      "i18n_text": {"zh_CN": "新会话", "en_US": "New Session"}
    }
  },
  "options": {"need_rollup": true}
}
```

#### 参数说明
| 名称     | 类型   | 必填 | 描述               |
|----------|--------|------|--------------------|
| type     | string | 是   | 仅支持"divider"    |
| params   | object | 是   | 分割线内容         |
| options  | object | 否   | 滚动清屏配置（need_rollup） |


## 相关问题
遇到其他问题？问问 [开放平台智能助手](链接)
