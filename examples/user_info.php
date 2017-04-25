<?php
require_once __DIR__ . '/wechat.php';

// @see https://mp.weixin.qq.com/wiki/1/8a5ce6257f1d3b2afb20f83e72b72ce9.html

// 获取 - 用户基本信息（包括UnionID机制）
$openId = '';
$wechat->getUserInfo($openId);