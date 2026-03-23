<?php

require_once 'includes/Utils/Utils.php';
require_once 'includes/Utils/HttpHelper.php';
require_once 'includes/Config/ATPConfig.php';

// 测试配置
$test_config = array(
    'X-MERCHANT-ID' => 'YOUR_MERCHANT_ID', // 替换为实际商户ID
    'X-PRIVATE-KEY' => 'YOUR_PRIVATE_KEY', // 替换为实际私钥
    'X-ADDON-PLATFORM' => 'easy-digital-downloads',
    'X-ADDON-VERSION' => '1.0.0'
);

// 测试域名获取
echo "=== 测试域名获取 ===\n";
$domain = Utils::getDomain();
echo "获取到的域名: $domain\n\n";

// 测试私钥加载
echo "=== 测试私钥加载 ===\n";
try {
    $privateKey = $test_config['X-PRIVATE-KEY'];
    $keyResource = Utils::loadPrivateKey($privateKey);
    if ($keyResource !== false) {
        echo "私钥加载成功\n";
    } else {
        echo "私钥加载失败\n";
    }
} catch (Exception $e) {
    echo "私钥加载错误: " . $e->getMessage() . "\n";
}
echo "\n";

// 测试头部构建
echo "=== 测试头部构建 ===\n";
try {
    $header = Utils::buildGetHeader($test_config);
    echo "头部构建成功:\n";
    print_r($header);
} catch (Exception $e) {
    echo "头部构建错误: " . $e->getMessage() . "\n";
}
echo "\n";

// 测试签名生成
echo "=== 测试签名生成 ===\n";
try {
    $test_data = 'test data';
    $signature = Utils::rasSign($test_data, $test_config['X-PRIVATE-KEY']);
    echo "签名生成成功: $signature\n";
} catch (Exception $e) {
    echo "签名生成错误: " . $e->getMessage() . "\n";
}
echo "\n";

// 测试HTTP请求
echo "=== 测试HTTP请求 ===\n";
try {
    $config = new ATPConfig($test_config, 'sandbox');
    $iframe_url = $config->getIframeUrl();
    $header = Utils::buildGetHeader($test_config);
    echo "请求URL: $iframe_url\n";
    echo "请求头部:\n";
    print_r($header);
    // 这里不实际发送请求，只是测试构建
    echo "HTTP请求构建成功\n";
} catch (Exception $e) {
    echo "HTTP请求构建错误: " . $e->getMessage() . "\n";
}

echo "\n测试完成!";
?>