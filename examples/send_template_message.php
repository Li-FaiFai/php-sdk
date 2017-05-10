<?php
require_once __DIR__ . '/wechat.php';

// @see https://mp.weixin.qq.com/wiki/5/6dde9eaa909f83354e0094dc3ad99e05.html

$data = [
    "first" => [
        "value" => "您好，您的随访已到时间。",
        "color" => "#173177"
    ],
    "keyword1" => [
        "value" => 'lifaifai',
    ],
    "keyword2" => [
        "value" => date('Y-m-d H:i'),
    ],
    "keyword3" => [
        "value" => '',
    ],
];
				
$wechat->sendTemplateMessage([
    'touser' => '',
    'template_id' => '',
    'url' => '',
    'topcolor' => '#FF0000',
    'data' => $data
]);