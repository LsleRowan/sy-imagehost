# ImageHost

私人云端图片托管服务

## 功能

- 管理后台：仪表盘、批量上传、图片管理、文件夹管理、系统设置
- REST API：公开查询接口 + Token 认证上传/删除接口
- 安全机制：登录锁定、CSRF 防护、密码哈希、文件类型验证
- 响应式界面：PC 和手机端适配

## 环境要求

- PHP 7.4+（推荐 8.0+）
- MySQL 5.7+ 或 MariaDB 10.3+
- Apache + mod_rewrite 或 nginx（无需伪静态规则）

## 安装

1. 上传所有文件到 Web 服务器
2. 编辑 `config/config.php` 填写数据库信息
3. 确保 `uploads/` 目录可写（`chmod 755`）
4. 浏览器访问 `install.php`，按向导完成安装
5. **安装完成后务必删除 `install.php`**

## 配置

数据库配置：`config/config.php`

站点配置（Base URL / CORS / API Token / 上传限制 / 登录安全）：安装完成后在后台「设置」页面修改

## API

| 接口 | 方法 | 认证 | 说明 |
|------|------|------|------|
| /api/folders.php | GET | 无需 | 获取文件夹列表 |
| /api/images.php | GET | 无需 | 获取图片列表 |
| /api/upload.php | POST | Bearer Token | 上传图片 |
| /api/delete.php | POST/DELETE | Bearer Token | 删除图片 |

## 目录结构

```
sy-imagehost/
├── admin/          # 管理后台
├── api/            # REST API 接口
├── assets/         # 静态资源（CSS/JS）
├── config/         # 配置文件
├── includes/       # 核心逻辑
├── uploads/        # 图片存储目录
├── index.php       # 首页
├── install.php     # 安装向导
└── .htaccess       # URL 重写与安全规则
```

## 安全

- 登录失败 5 次后锁定 30 分钟，次数/时长/会话有效期可在后台「登录安全」调整
- 所有密码使用 bcrypt 哈希
- API 使用 timing-safe Token 比对
- 上传文件经过扩展名、MIME 类型、图片格式三重验证
- uploads/ 目录禁止执行 PHP

## License

MIT
