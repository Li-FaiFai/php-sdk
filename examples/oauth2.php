<?php
require_once __DIR__ . '/wechat.php';

// @see https://mp.weixin.qq.com/wiki/4/9ac2e7b1f1d22e9e57260f6553822520.html 

// 判断 - 是否来源微信浏览器
if ( strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'micromessenger') !== false )
{
    if (!isset($_GET["code"])) {
    	// 操作 - 第一步：用户同意授权，获取code
        $redirectUrl = 'http://'.$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $strLoginUrl = $wechat->getOauth2AuthorizeUrl($redirectUrl,"authorize","snsapi_userinfo");
        header("Location: $strLoginUrl");
        exit;
    } else {
    	// 操作 - 第二步：通过code换取网页授权access_token
        $arrToken = $wechat->getOauth2AccessToken($_GET['code']);
        if (!empty($arrToken['openid']) && !empty($arrToken['access_token'])) {
        	// 操作 - 第四步：拉取用户信息(需scope为 snsapi_userinfo)
		    $arrUserInfo = $wechat->getSnsUserInfo($arrToken['openid'], $arrToken['access_token'], 'zh_CN');
        }
    }
}