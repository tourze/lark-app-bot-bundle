# 获取机器人信息
最后更新于 2022-09-27

> **官方文档：** [获取机器人信息 - 飞书开放平台](https://open.feishu.cn/document/ukTMukTMukTM/uAjMxEjLwITMx4CMyETM)

## 本文内容
获取机器人的基本信息。

需要启用机器人能力（前往开发者后台 - 选择你要获取信息的应用 - 导航栏点击应用功能 - 机器人，开启机器人能力并发布后即可。）


## 请求
### 基本
- **HTTP URL**：https://open.feishu.cn/open-apis/bot/v3/info
- **HTTP Method**：GET
- **权限要求**：无

### 请求头
|名称|类型|必填|描述|
|----|----|----|----|
|Authorization|string|是|tenant_access_token<br>值格式："Bearer access_token"<br>示例值："Bearer t-7f1bcd13fc57d46bac21793a18e560"<br>[了解更多：获取与使用access_token](需补充具体链接)|


## 响应
### 响应体
|名称|类型|描述|
|----|----|----|
|code|int|错误码，非 0 表示失败|
|msg|string|错误描述|
|bot|bot_info|机器人信息|
|├─ activate_status|int|app 当前状态<br>0: 初始化，租户待安装<br>1: 租户停用<br>2: 租户启用<br>3: 安装后待启用<br>4: 升级待启用<br>5: license过期停用<br>6: Lark套餐到期或降级停用|
|├─ app_name|string|app 名称|
|├─ avatar_url|string|app 图像地址|
|├─ ip_white_list|string[]|app 的 IP 白名单地址|
|└─ open_id|string|机器人的open_id|

### 响应体示例
```json
{
  "code":0,
  "msg":"ok",
  "bot":{
    "activate_status":2,
    "app_name":"name",
    "avatar_url":"https://s1-imfile.feishucdn.com/static-resource/v1/da5xxxx14b16113",
    "ip_white_list":[],
    "open_id":"ou_e6e14f667cfe239d7b129b521dce0569"
  }
}
```

## 相关问题
遇到其他问题？问问 [开放平台智能助手](需补充具体链接)
