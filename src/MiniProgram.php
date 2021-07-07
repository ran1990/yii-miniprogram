<?php

namespace life2016\miniprogram;

use life2016\miniprogram\components\BaseWechat;
use life2016\miniprogram\components\messageCrypt\WXBizDataCrypt;
use Yii;
use yii\base\InvalidConfigException;


/**
 * 微信小程序sdk
 */
class MiniProgram extends BaseWechat
{
    /**
     * 微信接口基本地址
     */
    const WECHAT_BASE_URL = 'https://api.weixin.qq.com';
    /**
     * @see https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/access-token/auth.getAccessToken.html
     */
    const ACCESS_TOKEN_PATH = '/cgi-bin/token';
    /**
     * @see https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/login/auth.code2Session.html
     */
    const JSCODE_2_SESSSION_PATH = '/sns/jscode2session';
    /**
     * @see https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/qr-code/wxacode.createQRCode.html
     */
    const WXAQRCODE_PATH = '/cgi-bin/wxaapp/createwxaqrcode';
    /**
     * @see https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/qr-code/wxacode.get.html
     */
    const WXACODE_PATH = '/wxa/getwxacode';
    /**
     * @see  https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/qr-code/wxacode.getUnlimited.html
     */
    const WXACODE_UNLIMIT_PATH = '/wxa/getwxacodeunlimit';

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
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        if ($this->appId === null) {
            throw new InvalidConfigException('The "appId" property must be set.');
        } elseif ($this->appSecret === null) {
            throw new InvalidConfigException('The "appSecret" property must be set.');
        }
    }

    /**
     * 获取缓存键值
     * @param $name
     * @return string
     */
    protected function getCacheKey($name)
    {
        return $this->cachePrefix . '_' . $this->appId . '_' . $name;
    }

    /**
     * 增加微信基本链接
     * @inheritdoc
     */
    protected function httpBuildQuery($url, array $options)
    {
        if (stripos($url, 'http://') === false && stripos($url, 'https://') === false) {
            $url = self::WECHAT_BASE_URL . $url;
        }
        return parent::httpBuildQuery($url, $options);
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
            Yii::warning([
                'url' => $url,
                'result' => $result,
                'postOptions' => $postOptions
            ], __METHOD__);
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

    /* =================== 基础接口 =================== */

    /**
     * 请求服务器access_token
     * @param string $grantType
     * @return array|bool
     */
    protected function requestAccessToken($grantType = 'client_credential')
    {
        $result = $this->httpGet(self::ACCESS_TOKEN_PATH, [
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'grant_type' => $grantType
        ]);
        return isset($result['access_token']) ? $result : false;
    }


    /**
     * 获取openid、sessionKey
     * @param $code
     * @return array|bool|mixed
     */
    public function getJscode2Sessio($code)
    {
        $result = $this->httpGet(self::JSCODE_2_SESSSION_PATH, [
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'js_code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        return !array_key_exists('errcode', $result) ? $result : false;
    }

    /**
     * 获取小程序二维码
     * @param array $data
     * @return mixed
     */
    public function createWXAQRCode(array $data)
    {
        $result = $this->httpPost(self::WXAQRCODE_PATH, $data, [
            'access_token' => $this->getAccessToken(),
        ]);

        return !array_key_exists('errcode', $result) ? $result : false;
    }

    /**
     * 获取有数量限制的小程序码
     * @param array $data
     * @return mixed
     */
    public function getWXACode(array $data)
    {
        $result = $this->httpPost(self::WXACODE_PATH, $data, [
            'access_token' => $this->getAccessToken(),
        ]);

        return !array_key_exists('errcode', $result) ? $result : false;
    }

    /**
     * 获取无数量限制的小程序码
     * @param array $data
     * @return mixed
     */
    public function getWxacodeUnlimit(array $data)
    {
        $result = $this->httpPost(self::WXACODE_UNLIMIT_PATH, $data, [
            'access_token' => $this->getAccessToken(),
        ]);

        return !array_key_exists('errcode', $result) ? $result : false;

    }

    /**
     * 解密微信用户的加密数据包
     * @param array $params
     * @return mixed
     */
    public function decryptData(array $params)
    {
        if (empty($params['session_key'])) {
            $this->lastError = ['errcode' => -102, 'errmsg' => 'session Key不能为空'];
            return false;
        }
        //验证签名
        if (!empty($params['rawData']) && !empty($params['signature'])) {
            $sign = sha1($params['rawData'] . $params['session_key']);
            if ($sign !== $params['signature']) {
                $this->lastError = ['errcode' => -102, 'errmsg' => '签名不匹配'];
                return false;
            }
        }

        // 使用sessionKey解密加密数据包
        $pc = new WXBizDataCrypt($this->appId, $params['session_key']);
        $errCode = $pc->decryptData($params['encryptedData'], $params['iv'], $data);
        if (!empty($errCode)) {
            $this->lastError = ['errcode' => -103, 'errmsg' => '解密失败，错误码:'. $errCode];
            return false;
        }

        return  json_decode($data, true);
    }



}