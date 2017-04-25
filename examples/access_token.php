<?php
require_once __DIR__ . '/wechat.php';

// @see https://mp.weixin.qq.com/wiki/14/9f9c82c1af308e3b14ba9b973f99a8ba.html

// 获取 - access token
$access_token = $wechat->getAccessToken();