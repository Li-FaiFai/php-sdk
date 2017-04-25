<?php
require_once __DIR__ . '/../autoload.php';

use wechat\Wechat;

// 操作 - 初始化对象。
$wechat = new Wechat([
	'appId'          => '',
	'appSecret'      => '',
	'token'          => '',
	'encodingAesKey' => '',
]);