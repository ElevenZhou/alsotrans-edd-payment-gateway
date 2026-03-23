# Alsotrans Payment Gateway for Easy Digital Downloads - Version 1.1.7

## 项目概述

本项目是一个基于 WordPress 插件 **Easy Digital Downloads (EDD)** 的支付网关，用于集成 **Alsotrans Global** 支付服务。

项目从现有的 WooCommerce 版本（`pay_woo`）移植而来，采用 iframe 集成方式，支持信用卡支付。

---

## 项目结构

```
Pay_edd_new/
├── alsotrans-edd-payment/          # 主插件目录
│   ├── alsotrans_edd.php           # 主插件文件（插件入口）
│   ├── class-alsotrans-edd-gateway.php  # 支付网关核心类
│   ├── assets/
│   │   └── js/
│   │       └── alsotrans_edd.js   # 前端JavaScript脚本
│   ├── includes/
│   │   ├── Config/
│   │   │   └── ATPConfig.php       # 配置管理类
│   │   ├── Utils/
│   │   │   ├── Utils.php           # 工具类（RSA签名、域名获取等）
│   │   │   └── HttpHelper.php      # HTTP请求帮助类
│   │   ├── Model/
│   │   │   ├── GatewayRequest.php  # 网关请求模型
│   │   │   ├── GatewayResponse.php # 网关响应模型
│   │   │   └── CallbackData.php   # 回调数据模型
│   │   ├── Payment/
│   │   │   └── Iframe.php          # Iframe支付处理类
│   │   └── Callback/
│   │       └── CallbackService.php # 回调处理服务
│   └── languages/                  # 语言文件
│
├── debug.log                       # 调试日志
├── test_alsotrans.php             # 测试脚本
└── test_domain_issue.php          # 域名问题测试脚本

参考目录:
├── Pay_woo/                       # WooCommerce版本（参考）
│   └── woo-alsotrans-payment-v3.0.0/
│
├── Pay_edd/                       # EDD支付网关参考案例
│   ├── Custom-Payment-Gateway-for-Easy-Digital-Downloads/
│   ├── edd-authorize-net/
│   └── paystack-easy-digital-downloads/
│
└── Doc/                          # 文档
    ├── apidoc.txt                # API文档地址
    └── alsotrans-woocommerce安装文档.pdf
```

---

## 项目进度

### ✅ 已完成

1. **基础架构搭建**
   - 插件结构创建
   - EDD支付网关注册
   - 设置页面集成

2. **核心功能实现**
   - RSA签名生成（支持PKCS#8私钥格式）
   - HTTP请求处理（GET/POST）
   - Token获取流程
   - 支付创建流程
   - 回调处理

3. **问题修复**
   - PKCS#8私钥格式支持
   - HTTP请求参数顺序修复
   - 地址数组转字符串警告修复
   - 域名自动获取逻辑增强
   - 修复 frontend `acceptedCards` 参数名空格问题（1.1.0）
   - 改进站点域名解析策略，优先设置值、去掉自动添加 `www.`（1.1.0）
   - cURL 记录日志路径兼容 Windows（1.1.0）
   - 修复 `ATP is not defined` 错误：调整脚本加载顺序，添加 ATP 库加载完成检查（1.1.0）
   - 尝试直连模式（DIRECT）：修改支付模式从 EMBED 改为 DIRECT（1.1.0）
   - 修复支付表单语言问题：将language从"default"改为"en"（1.1.1）
   - 移除无效的acceptedCards参数，卡片类型需在Alsotrans后台配置（1.1.1）
   - 添加ATP库调试信息，便于排查表单验证问题（1.1.1）
   - 恢复EMBED模式：根据用户反馈改回EMBED模式（1.1.2）
   - 修复异步token获取问题：正确等待ATP.confirmPay()完成再提交表单（1.1.3）
   - 移除重复的事件处理，避免token获取冲突（1.1.3）
   - 添加域名获取调试信息，便于排查X-SITE-DOMAIN验证问题（1.1.4）
   - 添加详细的JavaScript和PHP调试信息，排查token传递问题（1.1.5）
   - 添加双重事件监听器和增强调试，解决JavaScript执行问题（1.1.6）
   - 添加基于Paystack实现的正确表单提交监听器（1.1.7）
### 🔄 待解决

1. **X-SITE-DOMAIN 验证问题**
   - 状态：API返回 "X-SITE-DOMAIN is empty or incorrect"
   - 原因：Alsotrans服务器端域名验证失败
   - 解决方案：等待Alsotrans客服确认正确的域名配置

2. **cardToken is empty or incorrect**
   - 状态：正在调试中
   - 问题：JavaScript没有成功将生成的token设置到表单字段
   - 最新进展：添加了基于Paystack实现的正确表单提交监听器
   - 调试步骤：
     1. 检查浏览器控制台是否有新的调试信息
     2. 确认ATP.confirmPay()是否被调用
     3. 验证token是否成功设置到表单字段

---

## 安装与使用

## 安装与使用

### 1. 安装插件

1. 将 `alsotrans-edd-payment` 目录复制到 WordPress 插件目录：
   ```
   wp-content/plugins/
   ```

2. 在 WordPress 后台启用插件

### 2. 配置插件

1. 进入 **下载(EDD) → 设置 → 支付网关**
2. 找到 **Alsotrans** 设置区域
3. 配置以下内容：
   - **Enable/Disable**: 启用
   - **Title**: 支付方式标题
   - **Merchant ID**: 商户ID
   - **Private Key**: 私钥（PKCS#8格式）
   - **MD5 Key**: MD5密钥（用于回调验证）
   - **Environment**: 环境选择（Live/Sandbox）
   - **Site Domain**: 网站域名（用于API验证）

### 3. 测试支付流程

1. 在前端选择一个付费下载产品
2. 结账时选择 "Credit Card (Alsotrans)"
3. 完成支付

---

## API 参考

### 文档地址

官方API文档：`https://docs.alsotransglobal.com/`

主要接口：
- [请求签名规则](https://docs.alsotransglobal.com/signature.html)
- [环境配置](https://docs.alsotransglobal.com/environment.html)
- [Iframe信用卡支付](https://docs.alsotransglobal.com/iframeCredit.html)
- [获取Iframe Token](https://docs.alsotransglobal.com/getIframeToken.html)
- [支付回调通知](https://docs.alsotransglobal.com/notify.html)

### API 端点

| 环境 | 基础URL | Token接口 |
|------|---------|-----------|
| 正式 | `https://api.alsotransglobal.com` | `/v3/merchants/token` |
| 沙箱 | `https://stage-api.alsotransglobal.com` | `/v3/merchants/token` |

### 请求头要求

```
X-MERCHANT-ID: 商户ID
X-SITE-DOMAIN: 网站域名
X-TIMESTAMP: 时间戳(毫秒)
X-SIGNATURE: RSA签名
X-ADDON-PLATFORM: easy-digital-downloads
X-ADDON-VERSION: 插件版本
X-ADDON-TYPE: iframe
```

---

## 参考项目

### 官方示例
- [WooCommerce版本](file:///i:/Dev/Pay/Pay_woo/woo-alsotrans-payment-v3.0.0/) - 主要参考

### EDD支付网关案例
- [Coinsnap for EDD](https://github.com/Coinsnap/Coinsnap-for-EasyDigitalDownloads)
- [SSLCommerz for EDD](https://github.com/TanvirIsraq/wp-edd-sslcommerz-gateway)
- [Novalnet for EDD](https://github.com/Novalnet-AG/easydigitaldownloads-payment-integration-novalnet)
- [Pronamic Pay for EDD](https://github.com/pronamic/wp-pronamic-pay-easy-digital-downloads)
- [Zarinpal for EDD](https://github.com/alireza1219/integrate-zarinpal-edd)
- [Payoneer for EDD](https://github.com/bukunmilab/payoneer-payment-gateway-for-easy-digital-downloads)
- [CoinPayments for EDD](https://github.com/CoinPaymentsNet/easy-digital-downloads-coinpayments-gateway)

### EDD开发文档
- [官方支付网关文档](https://easydigitaldownloads.com/docs/payment-gateways/)
- [开发者文档](https://easydigitaldownloads.com/categories/docs/developer-docs/)

---

## 可能存在的问题

### 1. X-SITE-DOMAIN 验证失败 🔴

**问题描述**：
API返回错误 `{"code":"4001","describe":"X-SITE-DOMAIN is empty or incorrect"}`

**可能原因**：
- Alsotrans后台未正确配置网站域名
- 域名格式不匹配（如带www vs 不带www）
- 商户ID与域名绑定错误

**解决方案**：
- 联系Alsotrans客服确认正确域名
- 在插件设置中填写正确域名

### 2. 私钥格式问题 🟡

**问题描述**：
`Invalid private key: error:1E08010C:DECODER routines::unsupported`

**解决方案**：
- 确保私钥为PKCS#8格式
- 插件已内置PKCS#8支持

### 3. 回调验证失败 🟡

**问题描述**：
支付成功后回调无法验证

**解决方案**：
- 确认MD5 Key配置正确
- 检查回调URL可访问性

### 5. 卡片类型支持问题 🟡

**问题描述**：
支付表单只支持Visa卡，输入MasterCard卡号提示不支持

**原因分析**：
- Alsotrans的iframe支付中，卡片类型支持是在商户后台配置的，不是通过前端代码参数控制
- `acceptedCards`参数在ATP库中不存在，前端无法控制支持的卡片类型

**解决方案**：
- 联系Alsotrans客服，在商户后台启用所需卡片类型的支持
- 目前支持的卡片类型需要在Alsotrans系统中配置

### 7. 文件版本同步问题 🟡

**问题描述**：
服务器上的插件版本与本地开发版本不一致，导致修复无法生效

**解决方案**：
- 重新上传整个插件文件夹到服务器
- 清除WordPress缓存和CDN缓存
- 确认插件版本显示为1.1.3

---

## 开发相关

### 调试日志

插件会在以下位置生成调试日志：
- WordPress debug.log（需开启WP_DEBUG）
- 插件目录下的debug.log

### 本地测试

可以使用 `test_alsotrans.php` 进行本地测试（需修改配置）。

---

## 版本信息

- **当前版本**: 1.1.5
- **创建日期**: 2026-03-21
- **支持EDD版本**: 3.0+
- **支持PHP版本**: 7.4+

---

## 许可证

本项目仅供开发参考使用，遵守Alsotrans服务条款。
