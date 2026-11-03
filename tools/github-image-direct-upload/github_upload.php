<?php
// GitHub image direct-link upload proxy, PHP 5.6 compatible.
// Keep the GitHub token in server environment or in a local config file outside the web root.

$config = array(
    'owner' => getenv('GITHUB_OWNER') ? getenv('GITHUB_OWNER') : 'lulu3121',
    'repo' => getenv('GITHUB_REPO') ? getenv('GITHUB_REPO') : 'mcp-transfer-station',
    'branch' => getenv('GITHUB_BRANCH') ? getenv('GITHUB_BRANCH') : 'main',
    'root' => getenv('GITHUB_UPLOAD_ROOT') ? getenv('GITHUB_UPLOAD_ROOT') : 'uploads',
    'token' => getenv('GITHUB_TOKEN') ? getenv('GITHUB_TOKEN') : '',
    'api_url' => getenv('GITHUB_API_URL') ? getenv('GITHUB_API_URL') : 'https://api.github.com',
    'upload_key' => getenv('UPLOAD_API_KEY') ? getenv('UPLOAD_API_KEY') : '',
    'upload_log' => getenv('GITHUB_UPLOAD_LOG') ? getenv('GITHUB_UPLOAD_LOG') : '',
    'cors_origin' => getenv('CORS_ORIGIN') ? getenv('CORS_ORIGIN') : '*',
    'max_bytes' => getenv('MAX_UPLOAD_BYTES') ? (int) getenv('MAX_UPLOAD_BYTES') : 6 * 1024 * 1024
);

// Optional secret config path. Set GITHUB_UPLOAD_CONFIG to a file outside the public site.
$configPath = getenv('GITHUB_UPLOAD_CONFIG');
if ($configPath && is_file($configPath)) {
    $local = require $configPath;
    if (is_array($local)) {
        $config = array_merge($config, $local);
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . $config['cors_origin']);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Upload-Key');

function github_upload_out($success, $message, $extra = array(), $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(array('success' => $success, 'message' => $message), $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    github_upload_out(false, '只允许 POST', array('error' => 'method_not_allowed'), 405);
}

if ($config['token'] === '') {
    github_upload_out(false, '服务端尚未配置 GitHub 令牌', array('error' => 'github_token_missing'), 503);
}

if ($config['upload_key'] === '') {
    github_upload_out(false, '服务端尚未配置独立上传接口密钥', array('error' => 'upload_key_missing'), 503);
}
$providedKey = isset($_SERVER['HTTP_X_UPLOAD_KEY']) ? (string) $_SERVER['HTTP_X_UPLOAD_KEY'] : '';
if ($providedKey === '' && isset($_POST['api_key'])) {
    $providedKey = (string) $_POST['api_key'];
}
if (!hash_equals($config['upload_key'], $providedKey)) {
    github_upload_out(false, '上传接口密钥错误', array('error' => 'upload_key_invalid'), 401);
}

if ($config['upload_log'] === '') {
    github_upload_out(false, '服务端尚未配置站点外上传日志路径', array('error' => 'upload_log_missing'), 503);
}
$uploadLogDirectory = dirname($config['upload_log']);
if (!is_dir($uploadLogDirectory) || !is_writable($uploadLogDirectory)) {
    github_upload_out(false, '上传日志目录不存在或 PHP 无写入权限', array('error' => 'upload_log_unavailable'), 503);
}

if (!isset($_FILES['file'])) {
    github_upload_out(false, '没有收到文件', array('error' => 'file_missing'), 400);
}

$file = $_FILES['file'];
if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    $code = isset($file['error']) ? (int) $file['error'] : -1;
    github_upload_out(false, '上传失败，错误码：' . $code, array('error' => 'upload_error', 'upload_error_code' => $code), 400);
}

if (!isset($file['size']) || $file['size'] <= 0 || $file['size'] > $config['max_bytes']) {
    github_upload_out(false, '文件大小超过服务端限制（当前上限 ' . $config['max_bytes'] . ' 字节）', array('error' => 'file_too_large'), 413);
}

if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    github_upload_out(false, '上传文件无效', array('error' => 'invalid_upload'), 400);
}

$allowed = array(
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    'image/gif' => 'gif'
);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
if ($finfo) {
    finfo_close($finfo);
}
if (!isset($allowed[$mime])) {
    github_upload_out(false, '不支持的文件类型：' . ($mime ? $mime : 'unknown'), array('error' => 'unsupported_type'), 415);
}

$binary = file_get_contents($file['tmp_name']);
if ($binary === false || strlen($binary) === 0) {
    github_upload_out(false, '读取上传文件失败', array('error' => 'file_read_failed'), 400);
}

$originalName = isset($file['name']) ? (string) $file['name'] : 'image';
$base = pathinfo($originalName, PATHINFO_FILENAME);
$base = preg_replace('/[^a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]+/u', '-', $base);
$base = trim($base, '-_');
if ($base === '') {
    $base = 'image';
}
$unique = date('Ymd-His') . '-' . substr(sha1(uniqid((string) mt_rand(), true)), 0, 10);
$relativePath = trim($config['root'], '/\\') . '/' . date('Y') . '/' . date('m') . '/' . $base . '-' . $unique . '.' . $allowed[$mime];
$apiUrl = rtrim($config['api_url'], '/') . '/repos/' . rawurlencode($config['owner']) . '/' . rawurlencode($config['repo']) . '/contents/' . str_replace('%2F', '/', rawurlencode($relativePath));
$payload = json_encode(array(
    'message' => 'Upload image ' . basename($relativePath),
    'content' => base64_encode($binary),
    'branch' => $config['branch']
));

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 45);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: application/vnd.github+json',
    'Authorization: Bearer ' . $config['token'],
    'Content-Type: application/json',
    'User-Agent: github-image-direct-upload/1.0',
    'X-GitHub-Api-Version: 2022-11-28'
));
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $curlError !== '') {
    github_upload_out(false, '连接 GitHub API 失败：' . $curlError, array('error' => 'github_network_error'), 502);
}

$decoded = json_decode($response, true);
if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
    $detail = is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : 'GitHub 返回异常响应';
    $error = ($httpCode === 401 || $httpCode === 403) ? 'github_authorization_failed' : 'github_api_error';
    github_upload_out(false, $detail, array('error' => $error, 'github_status' => $httpCode), 502);
}

$rawUrl = 'https://raw.githubusercontent.com/' . rawurlencode($config['owner']) . '/' . rawurlencode($config['repo']) . '/' . rawurlencode($config['branch']) . '/' . str_replace('%2F', '/', rawurlencode($relativePath));
$logRecorded = false;
if ($config['upload_log'] !== '') {
    $logEntry = json_encode(array(
        'time' => gmdate('c'),
        'filename' => basename($relativePath),
        'path' => $relativePath,
        'imageUrl' => $rawUrl,
        'mime' => $mime,
        'size' => strlen($binary),
        'status' => 'success'
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $logRecorded = file_put_contents($config['upload_log'], $logEntry, FILE_APPEND | LOCK_EX) !== false;
}
github_upload_out(true, '上传到 GitHub 成功', array(
    'filename' => basename($relativePath),
    'path' => $relativePath,
    'imageUrl' => $rawUrl,
    'url' => $rawUrl,
    'mime' => $mime,
    'size' => strlen($binary),
    'githubStatus' => $httpCode,
    'logRecorded' => $logRecorded
));

