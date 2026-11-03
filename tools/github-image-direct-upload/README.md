# GitHub 图片直链上传与二维码工具

这版把图片上传目标从宝塔本地 `uploads/` 切换到公开仓库 `lulu3121/mcp-transfer-station`，上传成功后返回：

```text
https://raw.githubusercontent.com/lulu3121/mcp-transfer-station/main/uploads/YYYY/MM/唯一文件名.png
```

二维码只编码这个 Raw HTTPS 图片直链。手机扫码后直接请求图片，不经过 GitHub `/blob/` 页面、宝塔 IP 页面或第三方中转页。

## 文件

- `github-direct-upload.html`：本地打开的网页工具；支持选择图片、文字生成图片、上传、复制 Raw 直链、生成和保存二维码。
- `github_upload.php`：服务端上传代理，PHP 5.6 兼容；GitHub 令牌只从服务端环境读取。
- `.env.example`：配置模板，不含任何真实令牌。

## 部署

1. 在服务器网站目录之外保存 PHP 配置文件（可复制并改名 `部署配置示例.php`），在其中填写 `GITHUB_TOKEN` 和独立 `upload_key`；令牌使用 fine-grained PAT，只授权 `lulu3121/mcp-transfer-station` 的 Contents 读写权限。配置文件路径通过 PHP 环境变量 `GITHUB_UPLOAD_CONFIG` 指向。
2. 把 `github_upload.php` 放在独立图床站点的 PHP 目录，使用 HTTPS 域名访问它。
3. 必须设置一个单独的 `UPLOAD_API_KEY`，把它手动填入网页；它与 GitHub 令牌不是一回事。服务端未配置独立密钥时会拒绝上传，避免公开接口被滥用。
4. 在网页中把“上传接口地址”填成 `https://你的域名/github_upload.php`。
5. 选择图片或先生成文字图片，点击上传。成功后页面会用返回的 `imageUrl` 自动生成二维码。
6. 配置 `GITHUB_UPLOAD_LOG` 指向网站根目录之外、PHP 可追加写入的 JSONL 文件，并预先建立其父目录；未配置或不可写时接口会拒绝上传。日志记录成功上传的时间、文件名、仓库路径、直链、类型和状态。

## 安全边界

- GitHub 令牌不放入 HTML、公开仓库、localStorage 或二维码。
- 文件类型按服务端真实 MIME 检查，文件名只保留安全字符，并自动写入 `uploads/YYYY/MM/`。
- 默认单文件上限为 6 MiB，可通过 `MAX_UPLOAD_BYTES` 调整。PHP 的 `upload_max_filesize` 和 `post_max_size` 也必须高于这个值；建议分别至少设为 `7M` 和 `8M`。
- 本目录不能直接替换现有 `upload.php`；旧宝塔接口继续保留。

## 当前状态

GitHub 仓库和现有 Raw 示例文件已实际核对。由于当前会话没有可用的服务器环境变量和 GitHub 令牌，服务端脚本已完成但尚未部署到宝塔；部署后还需做一次真实上传、Raw 直链 HTTP 200、图片 MIME 和二维码解码验收。

详细检查见 [`TEST_RESULTS.md`](TEST_RESULTS.md)。
