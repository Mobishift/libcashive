<?php
/**
 * PHP SDK for cashive
 * 
 * @author Deckmon Yang <yanghb@msan.cn>
 */

namespace LibCashive;

class Response{
    
    public $http_code;
    
    public $response;
    
    public $is_success;
    
    function __construct($http_code, $response){
        $this->http_code = $http_code;
        $this->response = $response;
        $this->is_success = ($http_code >= 200 and $http_code < 300);
    }
    
    function get_response(){
        return $this->response;
    }
    
    function get_errormsg(){
        if($this->is_success){
            return "";
        }
        if($this->http_code == 403){
            return "签名错误";
        }elseif($this->http_code == 404){
            return "404不存在";
        }else{
            foreach($this->response as $key => $value){
                if($key == "detail"){
                    return $value;
                }
                if($key == "__all__"){
                    return $value[0];
                }
                if(is_array($value)){
                    return $key.":".$value[0];
                }else{
                    return (string) $value;
                }
            }
            return (string) $this->response;
        }
    }
    
}

/**
 * @author Deckmon
 * @version 1.0
 */
class Client {
    /**
     * @ignore
     */
    public $key;
    /**
     * @ignore
     */
    public $secret;
    /**
     * Contains the last HTTP status code returned. 
     *
     * @ignore
     */
    public $http_code;
    /**
     * Contains the last API call.
     *
     * @ignore
     */
    public $url;
    /**
     * Set up the API root URL.
     *
     * @ignore
     */
    public $host = "http://cashive.mobishift.com/api/";
    /**
     * Set timeout default.
     *
     * @ignore
     */
    public $timeout = 30;
    /**
     * Set connect timeout.
     *
     * @ignore
     */
    public $connecttimeout = 30;
	/**
	 * Verify SSL Cert.
	 *
	 * @ignore
	 */
	public $ssl_verifypeer = FALSE;
    /**
     * Contains the last HTTP headers returned.
     *
     * @ignore
     */
    public $http_info;
	/**
	 * Set the useragnet.
	 *
	 * @ignore
	 */
	public $useragent = 'Cashive PHP SDK v1.0';
	/**
	 * boundary of multipart
	 * @ignore
	 */
	public static $boundary = '';
    /**
     * print the debug info
     *
     * @ignore
     */
    public $debug = FALSE;
    /**
     * construct Cashive object
     */
    function __construct($key, $secret) {
        $this->key = $key;
        $this->secret = $secret;
    }

    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    function makeSign($parameters){
        ksort($parameters);
        $pre_str = '';
        foreach($parameters as $key => $value){
            $pre_str = $pre_str.$key.'='.$value.'&';
        }
        $pre_str = $pre_str.'secret='.$this->secret;
        $sign = md5($pre_str);
        return $sign;
    }
    
    function signParams($parameters){
        $new_params = array();
        foreach($parameters as $key => $value){
            if($value != ""){
                $new_params[$key] = $value;
            }
        }
        $new_params['rnd'] = $this->generateRandomString();
        $new_params['time'] = (string) time();
        $new_params['key'] = $this->key;
        $sign = $this->makeSign($new_params);
        $new_params['sign'] = $sign;
        return $new_params;
    }

    function request($method, $url, $parameters=array()){
        $url = "{$this->host}{$url}";
        $parameters = $this->signParams($parameters);
        $response = $this->MakeRequest($url, $method, $parameters);
        $res = new Response($this->http_code, json_decode($response, true));
        return $res;
    }

    /**
     * Format and sign an OAuth / API request
     *
     * @return string
     * @ignore
     */
    function MakeRequest($url, $method, $parameters, $multi = false) {
        switch ($method) {
            case 'GET':
                $url = $url . '?' . http_build_query($parameters);
                return $this->http($url, 'GET');
            default:
                $headers = array();
                if (!$multi && (is_array($parameters) || is_object($parameters)) ) {
                    $body = http_build_query($parameters);
                } else {
                    $body = self::build_http_query_multi($parameters);
                    $headers[] = "Content-Type: multipart/form-data; boundary=" . self::$boundary;
                }
                return $this->http($url, $method, $body, $headers);
        }
    }

    /**
     * Make an HTTP request
     *
     * @return string API results
     * @ignore
     */
    function http($url, $method, $postfields = NULL, $headers = array()) {
        $this->http_info = array();
        $ci = curl_init();
        /* Curl settings */
        curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ci, CURLOPT_USERAGENT, $this->useragent);
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
        curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ci, CURLOPT_ENCODING, "");
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
        if (version_compare(phpversion(), '5.4.0', '<')) {
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, 1);
        } else {
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, 2);
        }
        curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
        curl_setopt($ci, CURLOPT_HEADER, FALSE);

        switch ($method) {
            case 'POST':
                curl_setopt($ci, CURLOPT_POST, TRUE);
                if (!empty($postfields)) {
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
                    $this->postdata = $postfields;
                }
                break;
            case 'DELETE':
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if (!empty($postfields)) {
                    $url = "{$url}?{$postfields}";
                }
        }

        curl_setopt($ci, CURLOPT_URL, $url );
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers );
        curl_setopt($ci, CURLINFO_HEADER_OUT, TRUE );

        $response = curl_exec($ci);
        $this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
        $this->http_info = array_merge($this->http_info, curl_getinfo($ci));
        $this->url = $url;

        if ($this->debug) {
            echo "=====post data======\r\n";
            var_dump($postfields);

            echo "=====headers======\r\n";
            print_r($headers);

            echo '=====request info====='."\r\n";
            print_r( curl_getinfo($ci) );

            echo '=====response====='."\r\n";
            print_r( $response );
        }
        curl_close ($ci);
        return $response;
    }

    /**
     * Get the header info to store.
     *
     * @return int
     * @ignore
     */
    function getHeader($ch, $header) {
        $i = strpos($header, ':');
        if (!empty($i)) {
            $key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
            $value = trim(substr($header, $i + 2));
            $this->http_header[$key] = $value;
        }
        return strlen($header);
    }

    /**
     * @ignore
     */
    public static function build_http_query_multi($params) {
        if (!$params) return '';

        uksort($params, 'strcmp');

        $pairs = array();

        self::$boundary = $boundary = uniqid('------------------');
        $MPboundary = '--'.$boundary;
        $endMPboundary = $MPboundary. '--';
        $multipartbody = '';

        foreach ($params as $parameter => $value) {

            if( in_array($parameter, array('pic', 'image')) && $value{0} == '@' ) {
                $url = ltrim( $value, '@' );
                $content = file_get_contents( $url );
                $array = explode( '?', basename( $url ) );
                $filename = $array[0];

                $multipartbody .= $MPboundary . "\r\n";
                $multipartbody .= 'Content-Disposition: form-data; name="' . $parameter . '"; filename="' . $filename . '"'. "\r\n";
                $multipartbody .= "Content-Type: image/unknown\r\n\r\n";
                $multipartbody .= $content. "\r\n";
            } else {
                $multipartbody .= $MPboundary . "\r\n";
                $multipartbody .= 'content-disposition: form-data; name="' . $parameter . "\"\r\n\r\n";
                $multipartbody .= $value."\r\n";
            }

        }

        $multipartbody .= $endMPboundary;
        return $multipartbody;
    }
}