# 本地部署说明

> 本项目仅用于本地教学实验，请勿部署到公网。

## 1. 环境要求

| 组件 | 要求 |
| --- | --- |
| Web 服务器 | phpStudy（Apache 或 Nginx）或任意 PHP 环境 |
| PHP | 7.4 及以上（代码使用了 `declare(strict_types=1)` 与类型声明） |
| MySQL | 5.7 / 8.0 均可，本项目在 MySQL 8 上验证 |
| 浏览器 | Chrome / Edge 等现代浏览器 |

需要开启的 PHP 扩展：`pdo_mysql`、`mbstring`（phpStudy 默认已开启）。

## 2. 放置项目

把 `campus-order-security` 整个目录放到网站根目录，例如：

```
D:\phpstudy_pro\WWW\campus_order_security
```

对应访问地址：`http://localhost/campus_order_security/`

## 3. 配置数据库

### 方式一：直接改配置文件

编辑 `config/config.php` 中的 `db` 部分：

```php
'db' => [
    'host' => '127.0.0.1',
    'port' => '3307',
    'name' => 'campus_order_security',
    'user' => 'root',
    'pass' => 'root',
    'charset' => 'utf8mb4',
],
```

### 方式二：用环境变量覆盖（推荐）

不改代码，设置环境变量即可：

```powershell
$env:DB_HOST = "127.0.0.1"
$env:DB_PORT = "3306"
$env:DB_NAME = "campus_order_security"
$env:DB_USER = "root"
$env:DB_PASS = "your_password"
```

> 注意：phpStudy 的 Apache 模块方式可能读不到用户环境变量，此时请用方式一。

## 4. 初始化数据库

浏览器访问：

```
http://localhost/campus_order_security/setup.php
```

点击「创建数据库并导入示例数据」。该操作会执行 `database/schema.sql`，创建 11 张表并写入示例数据。

也可以手动导入：

```bash
mysql -u root -p < database/schema.sql
```

## 5. 登录

| 入口 | 地址 | 账号 |
| --- | --- | --- |
| 管理员 | `/admin/` | `admin` / `admin123` |
| 学生 | `/register.php` | 手机号 + 学号（示例数据见 `schema.sql`） |

> 默认口令仅用于本地演示，真实环境请立即修改。

## 6. DoS 实验准备

1. 确认项目根目录存在 `dos_demo.php`。
2. 浏览器打开 `http://localhost/campus_order_security/dos_demo.php`。
3. 脚本在 `tools/dos_local_test.py`，运行：

```powershell
cd tools
python .\dos_local_test.py --manual --concurrency 5 --interval 0.1
```

4. 按提示输入 `start` 开始、`stop` 停止、`quit` 退出。

脚本内置白名单，只允许 `localhost`、`127.0.0.1`、`::1`，访问其他地址会直接退出。

## 7. 常见问题

| 现象 | 原因 | 处理 |
| --- | --- | --- |
| 启动报错「无法连接数据库」 | 端口或账号不对 | 确认 MySQL 端口（3306/3307）与 `config/config.php` 一致 |
| MySQL 启动失败 | 系统 `MySQL80` 服务占用 3306 | 停止系统服务，或用 phpStudy 的 3307 实例 |
| 页面显示源码 | PHP 未解析 | 确认访问的是 `http://` 而非直接打开文件 |
| `500` 错误 | PDO 扩展未开或配置错误 | 开启 `pdo_mysql`，检查 `config/config.php` |
| DoS 页面点击按钮无反应 | `dos_defense_state.txt` 无写权限 | 给项目目录写权限，或手动创建该文件 |
| 登录提示 CSRF 失效 | 会话过期 | 重新打开登录页刷新 Token |

## 8. 目录权限

Linux 环境下需保证 Web 用户对项目根目录有写权限（`dos_demo.php` 需要写 `dos_defense_state.txt`）：

```bash
chmod -R 755 .
chmod 777 .
```

Windows + phpStudy 一般无需额外设置。

## 9. 清理与重置

重新导入数据：

```bash
mysql -u root -p < database/schema.sql
```

或再次访问 `setup.php` 点击初始化按钮。
