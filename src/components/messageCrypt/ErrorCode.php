<?php
namespace life2016\miniprogram\components\messageCrypt;

/**
 * error code 说明.
 * <ul>

 *    <li>-41001: encodingAesKey 非法</li>
 *    <li>-41003: aes 解密失败</li>
 *    <li>-41004: 解密后得到的buffer非法</li>
 *    <li>-41005: base64加密失败</li>
 *    <li>-41016: base64解密失败</li>
 * </ul>
 */
class ErrorCode
{
	public static $OK = 0;
	public static $IllegalAesKey = -41001;
	public static $IllegalIv = -41002;
	public static $IllegalBuffer = -41003;
	public static $DecodeBase64Error = -41004;

    /**
     * 根据错误码获取错误信息
     * @param $errorCode
     * @return string
     */
    public static function getErrorMsg($errorCode)
    {
        switch ($errorCode) {
            case '40029':
                $errorMsg = 'code 非法';
                break;
            case '-41001':
                $errorMsg = 'sessionKey 非法';
                break;
            case '-41002':
                $errorMsg = 'iv 非法';
                break;
            case '-41003':
                $errorMsg = '解密失败';
                break;
            case '41008':
                $errorMsg = '缺少code参数';
                break;
            case '40163':
                $errorMsg = 'code已使用';
                break;
            default:
                $errorMsg = '操作失败，错误码：' . $errorCode;
        }
        return $errorMsg;
    }
}

?>