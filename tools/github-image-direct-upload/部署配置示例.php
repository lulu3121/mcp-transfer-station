<?php
// 将此文件放在网站目录之外，并让 GITHUB_UPLOAD_CONFIG 指向它。
// 不要把真实令牌提交到 GitHub，也不要把它粘贴到聊天中。
return array(
    'owner' => 'lulu3121',
    'repo' => 'mcp-transfer-station',
    'branch' => 'main',
    'root' => 'uploads',
    'token' => '在服务器上填写你的 GitHub Fine-grained PAT',
    'upload_key' => '单独的接口密钥',
    'upload_log' => 'C:/private/github-image-upload.jsonl',
    'cors_origin' => '*',
    'max_bytes' => 6291456
);
