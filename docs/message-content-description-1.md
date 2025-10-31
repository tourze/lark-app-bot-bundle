# 接收消息内容结构
最后更新于 2025-06-14

> **官方文档：** [接收消息内容结构 - 飞书开放平台](https://open.feishu.cn/document/uAjLw4CM/ukTMukTMukTM/reference/im-v1/message/list)

本文介绍调用消息查询接口（如获取会话历史消息、获取指定消息内容等）时，返回结果中可能包含的消息类型与内容。

## 示例
获取指定消息内容的返回结果示例：
```json
{
  "code": 0,
  "data": {
    "items": [
      {
        "body": {
          "content": "{\"text\":\"test content\"}"
        },
        "chat_id": "oc_c7af75456b3475e72fd349b954d5xxxx",
        "create_time": "1722238025751",
        "deleted": false,
        "message_id": "om_84586909cde1d551d10532a83524xxxx",
        "msg_type": "text",
        "sender": {
          "id": "cli_a61e4f821889xxxx",
          "id_type": "app_id",
          "sender_type": "app",
          "tenant_key": "1709bdxxxx"
        },
        "update_time": "1722238025751",
        "updated": false
      }
    ]
  },
  "msg": "success"
}
```  
- `msg_type`：消息类型（string），如 `text` 表示文本消息。
- `content`：消息内容（string），为 JSON 结构。


## 各类型消息 JSON 结构

### 1. 文本（text）
```json
{
  "text": "@_user_1 文本消息"
}
```  
#### 内容说明
- 超链接格式：
    - 普通链接：`[超链接文本](超链接地址)`，如 `[飞书开放平台](https://open.feishu.cn)`。
    - 邮箱链接：`[邮箱文本](mailto:邮箱地址)`。
- @ 用户会被替换为 `@_user_X` 形式（X 为序号），具体用户信息可通过响应字段 `mentions` 获取。
- 粗体、下划线等文本样式会被忽略，仅显示纯文本。


### 2. 富文本（post）
**注意**：获取的富文本内容与发送时不完全一致，MD 标签不返回，系统会转换为其他标签，引用、列表等会简化为文本标签。
```json
{
  "title": "我是一个标题",
  "content": [
    [
      {
        "tag": "text",
        "text": "第一行 :",
        "style": ["bold", "underline"]
      },
      {
        "tag": "a",
        "href": "http://www.feishu.cn",
        "text": "超链接",
        "style": ["bold", "italic"]
      },
      {
        "tag": "at",
        "user_id": "@_user_1",
        "user_name": "",
        "style": []
      }
    ],
    // 其他标签内容（图片、视频、表情等）
  ]
}
```  
#### 标签（tag）及参数说明
| 标签   | 字段       | 类型     | 描述                                                                 |
|--------|------------|----------|----------------------------------------------------------------------|
| `text` | `text`     | string   | 文本内容                                                             |
|        | `un_escape`| boolean  | 是否为 unescape 解码（默认 false）                                   |
|        | `style`    | []string | 文本样式（`bold`/`underline`/`lineThrough`/`italic`）                |
| `a`    | `text`     | string   | 超链接显示文本                                                       |
|        | `href`     | string   | 链接地址                                                             |
|        | `style`    | []string | 文本样式                                                             |
| `at`   | `user_id`  | string   | 被@用户序号（如 `@_user_3`），详情通过 `mentions` 字段获取            |
|        | `user_name`| string   | 用户姓名                                                             |
|        | `style`    | []string | 文本样式                                                             |
| `img`  | `image_key`| string   | 图片唯一标识（支持机器人上传的图片下载）                             |
| `media`| `file_key` | string   | 视频文件唯一标识（支持机器人上传的文件下载）                         |
|        | `image_key`| string   | 视频封面图片唯一标识                                                 |
| `emotion`| `emoji_type` | string | 表情类型（如 `SMILE`）                                               |
| `code_block` | `language` | string | 代码语言（如 `GO`/`PYTHON` 等）                                      |
|        | `text`     | string   | 代码内容                                                             |
| `hr`   | -          | -        | 分割线（无参数）                                                     |


### 3. 图片（image）
```json
{
  "image_key": "img_4adb3cc3-902b-4187-b0f1-842f67fd017g"
}
```  
- `image_key`：图片唯一标识（机器人上传的图片支持下载）。


### 4. 文件（file）
```json
{
  "file_key": "75235e0c-4f92-430a-a99b-8446610223cg",
  "file_name": "test.txt"
}
```  
- `file_key`：文件唯一标识（机器人上传的文件支持下载）。
- `file_name`：文件名。


### 5. 文件夹（folder）
```json
{
  "file_key": "75235e0c-4f92-430a-a99b-8446610223cg",
  "file_name": "folder"
}
```  
- `file_key`：文件夹唯一标识（仅支持客户端上传/下载，API 无法下载）。
- `file_name`：文件夹名。


### 6. 音频（audio）
```json
{
  "file_key": "75235e0c-4f92-430a-a99b-8446610223cg",
  "duration": 2000
}
```  
- `file_key`：音频文件唯一标识（机器人上传的文件支持下载）。
- `duration`：音频时长（毫秒）。


### 7. 视频（media）
```json
{
  "file_key": "75235e0c-4f92-430a-a99b-8446610223cg",
  "image_key": "img_xxxxxx",
  "file_name": "测试视频.mp4",
  "duration": 2000
}
```  
- `file_key`：视频文件唯一标识（机器人上传的文件支持下载）。
- `image_key`：视频封面图片唯一标识。
- `file_name`：文件名。
- `duration`：视频时长（毫秒）。


### 8. 表情包（sticker）
```json
{
  "file_key": "75235e0c-4f92-430a-a99b-8446610223cg"
}
```  
- `file_key`：表情包唯一标识（支持通过 `file_key` 发送消息，但不支持下载）。


### 9. 卡片（interactive）
**注意**：返回结构与原始卡片 JSON 不一致，暂不支持返回原始 JSON 及 JSON 2.0 结构。
```json
{
  "title": "卡片标题",
  "elements": [
    // 按钮、超链接、文本、图片等元素数组
  ]
}
```  


### 10. 红包（hongbao）
```json
{
  "text": "[红包]"
}
```  
- 仅返回固定文本 `[红包]`。


### 11. 日程相关卡片
#### （1）日程分享卡片（share_calendar_event）
```json
{
  "summary": "日程分享测试",
  "start_time": "1608265395000",
  "end_time": "1608267015000"
}
```  
- `summary`：日程标题。
- `start_time`/`end_time`：日程开始/结束时间（毫秒级时间戳）。

#### （2）日程邀请卡片（calendar）
```json
{
  "summary": "日程邀请测试",
  "start_time": "1608265395000",
  "end_time": "1608267015000"
}
```  
字段说明同上。

#### （3）日程转让/附言/切换日历卡片（general_calendar）
```json
{
  "summary": "日程转让测试",
  "start_time": "1608265395000",
  "end_time": "1608267015000"
}
```  
字段说明同上。


### 12. 群名片（share_chat）
```json
{
  "chat_id": "oc_0dd200d32fdaxxxxxxxx32f76"
}
```  
- `chat_id`：群 ID（可通过接口查询群信息）。


### 13. 个人名片（share_user）
```json
{
  "user_id": "ou_0dd200d32xxxxx6d2c2ef1ddb32f76"
}
```  
- `user_id`：用户 open_id（可通过接口查询用户信息）。


### 14. 系统消息（system）
#### （1）拉用户入群消息
```json
{
  "template": "{from_user} invited {to_chatters} to this chat.",
  "from_user": ["botName"],
  "to_chatters": ["小明", "小王", "小红"]
}
```  
- `template`：消息模板。
- `from_user`：发起者列表。
- `to_chatters`：被拉用户列表。

#### （2）分割线消息
```json
{
  "template": "{divider_text}",
  "from_user": [],
  "to_chatters": [],
  "divider_text": {
    "text": "新会话",
    "i18n_text": {
      "zh_cn": "新话题",
      "en_us": "New Session"
    }
  }
}
```  
- `divider_text`：分割线内容（支持多语言配置）。


### 15. 位置（location）
```json
{
  "name": "xx省xx市",
  "longitude": "xxx.xxx",
  "latitude": "xxx.xxx"
}
```  
- `name`：位置名称。
- `longitude`/`latitude`：经度/纬度。


### 16. 视频通话（video_chat）
```json
{
  "topic": "视频通话消息",
  "start_time": "1623124523829"
}
```  
- `topic`：通话标题。
- `start_time`：通话开始时间（毫秒级时间戳）。


### 17. 任务（todo）
```json
{
  "task_id": "acd096a5-a157-4b9d-80e2-5b317456f005",
  "summary": {
    "title": "",
    "content": [[{"tag": "text", "text": "多吃水果，多运动，健康生活，快乐工作。"}]]
  },
  "due_time": "1623124318000"
}
```  
- `task_id`：任务 ID（用于操作任务）。
- `summary`：富文本格式的任务标题（结构同富文本 `post`）。
- `due_time`：任务截止时间（毫秒级时间戳）。


### 18. 投票（vote）
```json
{
  "topic": "投票测试",
  "options": ["选项1", "选项2", "选项3"]
}
```  
- `topic`：投票主题。
- `options`：投票选项列表。


### 19. 合并转发（merge_forward）
```json
{
  "content": "Merged and Forwarded Message"
}
```  
- 内容固定为 `Merged and Forwarded Message`，子消息需通过接口单独获取。


## 相关问题
遇到其他问题？问问 [开放平台智能助手](链接)
