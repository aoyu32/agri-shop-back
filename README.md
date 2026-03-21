# 农产品交易平台后端 API

基于 ThinkPHP 6.1 开发的农产品交易平台后端接口

## 环境要求

- PHP >= 7.2.5
- MySQL >= 5.7
- Composer

## 安装步骤

1. 安装依赖
```bash
composer install
```

2. 配置数据库
复制 `.env.example` 为 `.env`，修改数据库配置：
```
DATABASE = agri_shop
USERNAME = root
PASSWORD = your_password
```

3. 导入数据库
执行 `agri-shop-front/database/` 目录下的 SQL 文件：
```bash
mysql -u root -p agri_shop < ../agri-shop-front/database/schema.sql
```

4. 启动服务
```bash
php think run -p 8080
```

默认访问地址：http://localhost:8080

## API 接口文档

### 基础信息

- 接口前缀：`/api`
- 返回格式：JSON
- 字符编码：UTF-8

### 响应格式

成功响应：
```json
{
  "code": 200,
  "message": "操作成功",
  "data": {},
  "timestamp": 1234567890
}
```

失败响应：
```json
{
  "code": 400,
  "message": "错误信息",
  "data": {},
  "timestamp": 1234567890
}
```

### 用户认证接口

#### 1. 用户登录

**接口地址：** `POST /api/auth/login`

**请求参数：**
```json
{
  "username": "consumer123",  // 用户名或手机号
  "password": "123456",       // 密码
  "remember": false           // 是否记住密码（可选）
}
```

**响应示例：**
```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "abc123...",
    "user": {
      "id": 1,
      "username": "consumer123",
      "phone": "13800138000",
      "avatar": "https://...",
      "nickname": "张三",
      "role": "consumer"
    }
  },
  "timestamp": 1234567890
}
```

#### 2. 用户注册

**接口地址：** `POST /api/auth/register`

**请求参数：**
```json
{
  "username": "newuser",
  "password": "123456",
  "confirm_password": "123456",
  "phone": "13800138000",
  "code": "123456",
  "role": "consumer"  // consumer-消费者, merchant-商户
}
```

**响应示例：**
```json
{
  "code": 200,
  "message": "注册成功",
  "data": {
    "user": {
      "id": 2,
      "username": "newuser",
      "phone": "13800138000",
      "role": "consumer"
    }
  },
  "timestamp": 1234567890
}
```

#### 3. 发送验证码

**接口地址：** `POST /api/auth/send-code`

**请求参数：**
```json
{
  "phone": "13800138000"
}
```

**响应示例：**
```json
{
  "code": 200,
  "message": "验证码发送成功",
  "data": {
    "message": "验证码已发送",
    "code": "123456"  // 仅开发环境返回
  },
  "timestamp": 1234567890
}
```

#### 4. 重置密码

**接口地址：** `POST /api/auth/reset-password`

**请求参数：**
```json
{
  "phone": "13800138000",
  "code": "123456",
  "password": "newpassword",
  "confirm_password": "newpassword"
}
```

**响应示例：**
```json
{
  "code": 200,
  "message": "密码重置成功",
  "data": {},
  "timestamp": 1234567890
}
```

#### 5. 退出登录

**接口地址：** `POST /api/auth/logout`

**请求头：**
```
Authorization: Bearer {token}
```

**响应示例：**
```json
{
  "code": 200,
  "message": "退出成功",
  "data": {},
  "timestamp": 1234567890
}
```

#### 6. 获取用户信息

**接口地址：** `GET /api/auth/user-info`

**请求头：**
```
Authorization: Bearer {token}
```

**响应示例：**
```json
{
  "code": 200,
  "message": "操作成功",
  "data": {
    "user": {
      "id": 1,
      "username": "consumer123",
      "phone": "13800138000",
      "avatar": "https://...",
      "nickname": "张三",
      "role": "consumer",
      "gender": 1
    }
  },
  "timestamp": 1234567890
}
```

## 错误码说明

| 错误码 | 说明 |
|--------|------|
| 200 | 成功 |
| 400 | 请求错误 |
| 401 | 未授权 |
| 403 | 禁止访问 |
| 404 | 资源不存在 |
| 422 | 验证失败 |
| 500 | 服务器错误 |

## 开发说明

### 目录结构

```
agri-shop-back/
├── app/
│   ├── controller/        # 控制器
│   │   └── AuthController.php
│   ├── model/            # 模型
│   │   └── User.php
│   ├── validate/         # 验证器
│   │   └── UserValidate.php
│   ├── common/           # 公共类
│   │   └── Response.php
│   └── BaseController.php
├── config/               # 配置文件
├── route/                # 路由
│   └── app.php
├── public/               # 入口文件
└── .env                  # 环境配置
```

### 注意事项

1. 验证码功能：当前为开发模式，验证码会在响应中返回。生产环境需要集成短信服务商API。
2. Token管理：当前使用简单的缓存方式存储token，建议生产环境使用JWT。
3. 密码加密：使用PHP的`password_hash()`函数进行加密。
4. 跨域配置：如需前后端分离，需要配置CORS中间件。

## 测试账号

### 消费者账号
- 用户名：consumer123
- 密码：123456

### 商户账号
- 用户名：farmer123
- 密码：123456

## License

Apache-2.0
