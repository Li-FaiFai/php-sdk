<?php
require_once __DIR__ . '/wechat.php';

// @see https://mp.weixin.qq.com/wiki/8/f9a0b8382e0b77d87b3bcc1ce6fbc104.html

// 验证 - 服务器地址的有效性
if ($wechat->checkSignature()) {
	echo $_GET["echostr"];
}