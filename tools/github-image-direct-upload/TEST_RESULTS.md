# 测试记录

## 已通过

- 目标仓库公开状态、默认分支 `main` 和现有示例文件路径已通过 GitHub API 核实。
- 示例 Raw 直链 `https://raw.githubusercontent.com/lulu3121/mcp-transfer-station/main/images/thank-you-original.webp` 未带登录令牌请求，HTTP **200**，`Content-Type: image/webp`，大小 **5,578 bytes**。
- 下载的示例文件由 Pillow 解码为 **320 × 460 WEBP**。
- HTML 中内嵌 QR 库和页面脚本通过 Node.js `--check` 语法检查。
- 内嵌二维码生成器在 L、M、Q、H 四种纠错等级，以及 512 和 1024 像素输出尺寸下共 **8/8** 由 ZXing 精确解码为实际示例 Raw URL。
- 新 HTML 未包含 GitHub PAT、旧宝塔接口地址或旧图床口令；PAT 仅通过 PHP 服务端配置读取。

## 尚未完成

- 此环境没有 PHP CLI，因此没有运行 PHP lint；需在 PHP 5.6 兼容环境或宝塔站点部署后执行。
- 尚未配置服务器端 Fine-grained PAT、独立上传密钥和站点外上传日志路径。
- 尚未通过新 PHP 代理完成真实文件上传，因此还没有新路径的 Raw 文件案例。
- 尚未做连续多文件真实上传、手机扫码或不同运营商网络验收。
- 原有服务器接口保持不变；本次只验证现存公开 Raw 示例，不改动旧站点。
