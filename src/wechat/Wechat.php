<?php
namespace wechat;

class Wechat
{
	/**
     * 微信接口基本地址
     */
    const WECHAT_BASE_URL = 'https://api.weixin.qq.com';
    /**
     * 数据缓存前缀
     * @var string
     */
    public $cachePrefix = 'cache_wechat_sdk_mp';
    /**
     * 公众号appId
     * @var string
     */
    public $appId;
    /**
     * 公众号appSecret
     * @var string
     */
    public $appSecret;
    /**
     * 公众号接口验证token,可由您来设定. 并填写在微信公众平台->开发者中心
     * @var string
     */
    public $token;
    /**
     * 公众号消息加密键值
     * @var string
     */
    public $encodingAesKey;
    /**
     * 公众号access_token
     * @var array
     */
    private $_accessToken;
    /**
     * 缓存位置
     * @var string
     */
    static public $cachepath;

    /**
     * 构造方法
     * @param array $params
     */
    public function __construct($params = []) 
    {
        $this->token = isset($params['token']) ? $params['token'] : '';
        $this->appId = isset($params['appId']) ? $params['appId'] : '';
        $this->appSecret = isset($params['appSecret']) ? $params['appSecret'] : '';
        $this->encodingAesKey = isset($params['encodingaeskey']) ? $params['encodingaeskey'] : '';
    }

    /**
     * 微信服务器请求签名检测
     * @param string $signature 微信加密签名，signature结合了开发者填写的token参数和请求中的timestamp参数、nonce参数。
     * @param string $timestamp 时间戳
     * @param string $nonce 随机数
     * @return bool
     */
    public function checkSignature($signature = null, $timestamp = null, $nonce = null)
    {
        $signature === null && isset($_GET['signature']) && $signature = $_GET['signature'];
        $timestamp === null && isset($_GET['timestamp']) && $timestamp = $_GET['timestamp'];
        $nonce === null && isset($_GET['nonce']) && $nonce = $_GET['nonce'];
        $tmpArr = [$this->token, $timestamp, $nonce];
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        return sha1($tmpStr) == $signature;
    }

    /**
     * 获取AccessToken
     * 超时后会自动重新获取AccessToken并触发self::EVENT_AFTER_ACCESS_TOKEN_UPDATE事件
     * @param bool $force 是否强制获取
     * @return mixed
     * @throws HttpException
     */
    public function getAccessToken($force = false)
    {
        $time = time(); // 为了更精确控制.取当前时间计算
        if ($this->_accessToken === null || $this->_accessToken['expire'] < $time || $force) {
            $result = $this->_accessToken === null && !$force ? $this->getCache('access_token') : false;
            if (empty($result)) {
                if (!($result = $this->requestAccessToken())) {
                    throw new \Exception("Fail to get access_token from wechat server.");
                }
                $result['expire'] = $time + $result['expires_in'];
                $this->setCache('access_token', $result);
            }
            $this->setAccessToken($result);
        }
        return $this->_accessToken['access_token'];
    }

    /**
     * 设置AccessToken
     * @param array $accessToken
     * @throws InvalidParamException
     */
    public function setAccessToken(array $accessToken)
    {
        if (!isset($accessToken['access_token'])) {
            throw new \Exception('The wechat access_token must be set.');
        } elseif(!isset($accessToken['expire'])) {
            throw new \Exception('Wechat access_token expire time must be set.');
        }
        $this->_accessToken = $accessToken;
    }

    /**
     * access token获取
     */
    const WECHAT_ACCESS_TOKEN_PREFIX = '/cgi-bin/token';
    /**
     * 请求服务器access_token
     * @param string $grantType
     * @return array|bool
     */
    protected function requestAccessToken($grantType = 'client_credential')
    {
        $result = $this->httpGet(self::WECHAT_ACCESS_TOKEN_PREFIX, [
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'grant_type' => $grantType
        ]);
        return isset($result['access_token']) ? $result : false;
    }

    /**
     * 获取用户基本信息(UnionID机制)
     */
    const WECHAT_USER_INFO_GET = '/cgi-bin/user/info';
    /**
     * 获取用户基本信息(UnionID机制)
     * @param $openId
     * @param string $lang
     * @return bool|mixed
     * @throws \yii\web\HttpException
     */
    public function getUserInfo($openId, $lang = 'zh_CN')
    {
        $result = $this->httpGet(self::WECHAT_USER_INFO_GET, [
            'access_token' => $this->getAccessToken(),
            'openid' => $openId,
            'lang' => $lang
        ]);
        return !array_key_exists('errcode', $result) ? $result : false;
    }

    /**
     * 用户同意授权，获取code
     */
    const WECHAT_OAUTH2_AUTHORIZE_URL = 'https://open.weixin.qq.com/connect/oauth2/authorize';
    /**
     * 用户同意授权，获取code:第一步
     * 通过此函数生成授权url
     * @param $redirectUrl 授权后重定向的回调链接地址，请使用urlencode对链接进行处理
     * @param string $state 重定向后会带上state参数，开发者可以填写a-zA-Z0-9的参数值
     * @param string $scope 应用授权作用域，snsapi_base （不弹出授权页面，直接跳转，只能获取用户openid），
     * snsapi_userinfo （弹出授权页面，可通过openid拿到昵称、性别、所在地。并且，即使在未关注的情况下，只要用户授权，也能获取其信息）
     * @return string
     */
    public function getOauth2AuthorizeUrl($redirectUrl, $state = 'authorize', $scope = 'snsapi_base')
    {
        return $this->httpBuildQuery(self::WECHAT_OAUTH2_AUTHORIZE_URL, [
            'appid' => $this->appId,
            'redirect_uri' => $redirectUrl,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
        ]) . '#wechat_redirect';
    }

    /**
     * 通过code换取网页授权access_token
     */
    const WECHAT_OAUTH2_ACCESS_TOKEN_PREFIX = '/sns/oauth2/access_token';
    /**
     * 通过code换取网页授权access_token:第二步
     * 通过跳转到getOauth2AuthorizeUrl返回的授权code获取用户资料 (该函数和getAccessToken函数作用不同.请参考文档)
     * @param $code
     * @param string $grantType
     * @return array
     */
    public function getOauth2AccessToken($code, $grantType = 'authorization_code')
    {
        $result = $this->httpGet(self::WECHAT_OAUTH2_ACCESS_TOKEN_PREFIX, [
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'code' => $code,
            'grant_type' => $grantType
        ]);
        return !array_key_exists('errcode', $result) ? $result : false;
    }

    /**
     * 刷新access_token
     */
    const WECHAT_OAUTH2_ACCESS_TOKEN_REFRESH_PREFIX = '/sns/oauth2/refresh_token';
    /**
     * 刷新access_token:第三步(非必须)
     * 由于access_token拥有较短的有效期，当access_token超时后，可以使用refresh_token进行刷新
     * refresh_token拥有较长的有效期（7天、30天、60天、90天），当refresh_token失效的后，需要用户重新授权。
     * @param $refreshToken
     * @param string $grantType
     * @return array|bool
     */
    public function refreshOauth2AccessToken($refreshToken, $grantType = 'refresh_token')
    {
        $result = $this->httpGet(self::WECHAT_OAUTH2_ACCESS_TOKEN_REFRESH_PREFIX, [
            'appid' => $this->appId,
            'grant_type' => $grantType,
            'refresh_token' => $refreshToken
        ]);
        return !array_key_exists('errcode', $result) ? $result : false;
    }

    /**
     * 拉取用户信息(需scope为 snsapi_userinfo)
     */
    const WEHCAT_SNS_USER_INFO_PREFIX = '/sns/userinfo';
    /**
     * 拉取用户信息(需scope为 snsapi_userinfo):第四步
     * @param $openId
     * @param string $oauth2AccessToken
     * @param string $lang
     * @return array|bool
     */
    public function getSnsUserInfo($openId, $oauth2AccessToken, $lang = 'zh_CN')
    {
        $result = $this->httpGet(self::WEHCAT_SNS_USER_INFO_PREFIX, [
            'access_token' => $oauth2AccessToken,
            'openid' => $openId,
            'lang' => $lang
        ]);
        return !array_key_exists('errcode', $result) ? $result : false;
    }

    /**
     * 检验授权凭证（access_token）是否有效
     */
    const WECHAT_SNS_AUTH_PREFIX = '/sns/auth';
    /**
     * 检验授权凭证（access_token）是否有效
     * @param $accessToken
     * @param $openId
     * @return bool
     */
    public function checkOauth2AccessToken($accessToken, $openId)
    {
        $result = $this->httpGet(self::WECHAT_SNS_AUTH_PREFIX, [
            'access_token' => $accessToken,
            'openid' => $openId
        ]);
        return isset($result['errmsg']) && $result['errmsg'] == 'ok';
    }

    /**
     * 发送模板消息
     */
    const WECHAT_TEMPLATE_MESSAGE_SEND_PREFIX = '/cgi-bin/message/template/send';
    /**
     * 发送模板消息
     * @param array $data 模板需要的数据
     * @return int|bool
     */
    public function sendTemplateMessage(array $data)
    {
        $result = $this->httpRaw(self::WECHAT_TEMPLATE_MESSAGE_SEND_PREFIX, array_merge([
            'url' => null,
            'topcolor' => '#FF0000'
        ], $data), [
            'access_token' => $this->getAccessToken()
        ]);
        echo "<Pre>";
        var_dump($result);
        return isset($result['msgid']) ? $result['msgid'] : false;
    }

    /**
     * Http Raw数据 Post 请求
     * @param $url
     * @param $postOptions
     * @param array $options
     * @return mixed
     */
    public function httpRaw($url, $postOptions, array $options = [])
    {
        return $this->parseHttpRequest(function($url, $postOptions) {
            return $this->http($url, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => is_array($postOptions) ? json_encode($postOptions, JSON_UNESCAPED_UNICODE) : $postOptions
            ]);
        }, $this->httpBuildQuery($url, $options), $postOptions);
    }

    /**
     * Api url 组装
     * @param $url
     * @param array $options
     * @return string
     */
    protected function httpBuildQuery($url, array $options)
    {
    	if (stripos($url, 'http://') === false && stripos($url, 'https://') === false) {
            $url = self::WECHAT_BASE_URL . $url;
        }

        if (!empty($options)) {
            $url .= (stripos($url, '?') === null ? '&' : '?') . http_build_query($options);
        }
        return $url;
    }

    /**
     * Http Get 请求
     * @param $url
     * @param array $options
     * @return mixed
     */
    public function httpGet($url, array $options = [])
    {
        return $this->parseHttpRequest(function($url) {
            return $this->http($url);
        }, $this->httpBuildQuery($url, $options));
    }

    /**
     * Http Post 请求
     * @param $url
     * @param array $postOptions
     * @param array $options
     * @return mixed
     */
    public function httpPost($url, array $postOptions, array $options = [])
    {
        return $this->parseHttpRequest(function($url, $postOptions) {
            return $this->http($url, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postOptions
            ]);
        }, $this->httpBuildQuery($url, $options), $postOptions);
    }

    /**
     * @inheritdoc
     * @param bool $force 是否强制获取access_token, 该设置会在access_token使用错误时, 是否再获取一次access_token并再重新提交请求
     */
    public function parseHttpRequest(callable $callable, $url, $postOptions = null, $force = true)
    {
        $result = call_user_func_array($callable, [$url, $postOptions]);
        if (isset($result['errcode']) && $result['errcode']) {
            $this->lastError = $result;
            switch ($result ['errcode']) {
                case 40001: //access_token 失效,强制更新access_token, 并更新地址重新执行请求
                    if ($force) {
                        $url = preg_replace_callback("/access_token=([^&]*)/i", function(){
                            return 'access_token=' . $this->getAccessToken(true);
                        }, $url);
                        $result = $this->parseHttpRequest($callable, $url, $postOptions, false); // 仅重新获取一次,否则容易死循环
                    }
                    break;
            }
        }
        return $result;
    }

    /**
     * Http基础库 使用该库请求微信服务器
     * @param $url
     * @param array $options
     * @return bool|mixed
     */
    protected function http($url, $options = [])
    {
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
        ] + (stripos($url, "https://") !== false ? [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1 // 微信官方屏蔽了ssl2和ssl3, 启用更高级的ssl
        ] : []) + $options;

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $content = curl_exec($curl);
        $status = curl_getinfo($curl);
        curl_close($curl);
        if (isset($status['http_code']) && $status['http_code'] == 200) {
            return json_decode($content, true) ?: false; // 正常加载应该是只返回json字符串
        }
        return false;
    }

    /**
     * 检查缓存目录
     * @return bool
     */
    static protected function checkPath() 
    {
        self::$cachepath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
        self::$cachepath = rtrim(self::$cachepath, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir(self::$cachepath) && !mkdir(self::$cachepath, 0755, TRUE)) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * 设置缓存
     * @param string $name
     * @param array $result
     * @param int $expired
     * @return mixed
     */
    protected function setCache($name, $result) 
    {
        $data = serialize($result);
        return self::checkPath() && file_put_contents(self::$cachepath . $name, $data);
    }

    /**
     * 读取缓存
     * @param string $name
     * @return mixed
     */
    protected function getCache($name) 
    {
        if (self::checkPath() && ($file = self::$cachepath .$name) && file_exists($file) && ($data = file_get_contents($file)) && !empty($data)) {
            $data = unserialize($data);
            return $data;
        }
        return null;
    }
}