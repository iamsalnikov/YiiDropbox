<?php
/**
 * Yii Extension provide Dropbox API
 *
 * @author Alexey Salnikov <http://iamsalnikov.ru/>
 * @author Joe Constant <http://joeconstant.me/>
 */
class YiiDropbox extends CApplicationComponent {

    public $appKey;
    public $appSecret;
    public $root = 'dropbox';

    const URL_REQUEST_TOKEN = 'https://api.dropbox.com/1/oauth/request_token';
    const URL_ACCESS_TOKEN =  'https://api.dropbox.com/1/oauth/access_token';
    const URL_AUTHORIZE = 'https://www.dropbox.com/1/oauth/authorize';
    const URL_API = 'https://api.dropbox.com/1/';
    const API_CONTENT_URL = 'https://api-content.dropbox.com/1/';

    /**
     * @var OAuth $_oauth
     */
    protected $_oauth;

    protected $_oauthToken;
    protected $_oauthTokenSecret;

    public function init() {
        $this->_oauth = new OAuth($this->appKey, $this->appSecret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
        $this->_oauth->enableDebug();
    }

    public function getRequestToken() {
        $tokens = $this->_oauth->getRequestToken(self::URL_REQUEST_TOKEN);
        $this->setToken($tokens);
        return $tokens;
    }

    public function setToken($oauthTokenSecret, $oauthToken = false) {
        if ($oauthToken) {
            $this->_oauthToken = $oauthToken;
            $this->_oauthTokenSecret = $oauthTokenSecret;
        }
        else {
            $this->_oauthToken = $oauthTokenSecret['oauth_token'];
            $this->_oauthTokenSecret = $oauthTokenSecret['oauth_token_secret'];
        }
        $this->_oauth->setToken($this->_oauthToken, $this->_oauthTokenSecret);
    }

    public function getAccessToken() {

        $tokens = $this->_oauth->getAccessToken(self::URL_ACCESS_TOKEN);
        $this->setToken($tokens);
        return $tokens;

    }

    public function getAuthorizeLink($callBack = false) {
        $uri = self::URL_AUTHORIZE . '?oauth_token=' . $this->_oauthToken;
        if ($callBack) $uri.='&oauth_callback=' . $callBack;
        return $uri;
    }

    /**
     * Получаем информацию об аккаунте
     * @return mixed
     */
    public function getAccountInfo() {
        $data = $this->fetch(self::URL_API . 'account/info');
        return json_decode($data['body'],true);
    }

    /**
     * Получаем содержимое файла
     *
     * @param string $path path
     * @param string $root Use this to override the default root path (sandbox/dropbox)
     * @return string
     */
    public function getFile($path = '', $root = false) {

        if (!$root) $root = $this->root;
        $path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
        $result = $this->fetch(self::API_CONTENT_URL . 'files/' . $root . '/' . ltrim($path,'/'));
        return $result['body'];

    }

    /**
     * Передаем файл в дропбокс
     * @param $path
     * @param $file
     * @param null $root
     * @return bool
     * @throws CException
     */
    public function putFile($path, $file, $root = null) {

        if (dirname($path) == '\\') {
            $directory = "/";
        } else {
            $directory = dirname($path);
        }
        $filename = basename($path);

        if($directory==='.') $directory = '';
        $directory = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($directory));
        $filename = str_replace('~', '%7E', rawurlencode($filename));
        if (is_null($root)) $root = $this->root;

        if (is_string($file)) {

            $file = fopen($file,'rb');

        } elseif (!is_resource($file)) {
            throw new CException('File must be a file-resource or a string');
        }
        $result=$this->multipartFetch(self::API_CONTENT_URL . 'files/' .
            $root . '/' . trim($directory,'/'), $file, $filename);

        if(!isset($result["httpStatus"]) || $result["httpStatus"] != 200)
            throw new CException("Uploading file to Dropbox failed");

        return true;
    }

    /**
     * Копируем файл
     * @param $from
     * @param $to
     * @param null $root
     * @return mixed
     */
    public function copy($from, $to, $root = null) {

        if (is_null($root)) $root = $this->root;
        $response = $this->fetch(self::URL_API . 'fileops/copy', array('from_path' => $from, 'to_path' => $to, 'root' => $root));

        return json_decode($response['body'],true);

    }

    /**
     * Создаем папку
     * @param $path
     * @param null $root
     * @return mixed
     */
    public function createFolder($path, $root = null) {

        if (is_null($root)) $root = $this->root;
        $path = '/' . ltrim($path,'/');

        $response = $this->fetch(self::URL_API . 'fileops/create_folder', array('path' => $path, 'root' => $root),'POST');
        return json_decode($response['body'],true);

    }

    /**
     * Удаляем файл
     * @param $path
     * @param null $root
     * @return mixed
     */
    public function delete($path, $root = null) {

        if (is_null($root)) $root = $this->root;
        $response = $this->fetch(self::URL_API . 'fileops/delete', array('path' => $path, 'root' => $root));
        return json_decode($response['body']);

    }

    /**
     * Перемещаем файл
     * @param $from
     * @param $to
     * @param null $root
     * @return mixed
     */
    public function move($from, $to, $root = null) {

        if (is_null($root)) $root = $this->root;
        $response = $this->fetch(self::URL_API . 'fileops/move', array('from_path' => rawurldecode($from), 'to_path' => rawurldecode($to), 'root' => $root));

        return json_decode($response['body'],true);

    }

    /**
     * Получаем метаданные файла
     *
     * @param $path
     * @param bool $list
     * @param null $hash
     * @param null $fileLimit
     * @param null $root
     * @return bool|mixed
     */
    public function getMetaData($path, $list = true, $hash = null, $fileLimit = null, $root = null) {

        if (is_null($root)) $root = $this->root;

        $args = array(
            'list' => $list,
        );

        if (!is_null($hash)) $args['hash'] = $hash;
        if (!is_null($fileLimit)) $args['file_limit'] = $fileLimit;

        $path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
        $response = $this->fetch(self::URL_API . 'metadata/' . $root . '/' . ltrim($path,'/'), $args);

        /* 304 is not modified */
        if ($response['httpStatus']==304) {
            return true;
        } else {
            return json_decode($response['body'],true);
        }

    }

    public function delta($cursor) {

        $arg['cursor'] = $cursor;

        $response = $this->fetch(self::URL_API . 'delta', $arg, 'POST');
        return json_decode($response['body'],true);

    }

    /**
     * Получаем превьюшки
     *
     * @param $path
     * @param string $size
     * @param null $root
     * @return mixed
     */
    public function getThumbnail($path, $size = 'small', $root = null) {

        if (is_null($root)) $root = $this->root;
        $path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
        $response = $this->fetch(self::URL_API . 'thumbnails/' . $root . '/' . ltrim($path,'/'),array('size' => $size));

        return $response['body'];

    }

    /**
     * Поиск
     *
     * Возвращает метаданные всех файлов и директорий, совпадающих с запросом
     *
     * @param string $query
     * @param string $root
     * @param string $path
     * @return array
     */
    public function search($query = '', $root = null, $path = ''){
        if (is_null($root)) $root = $this->root;
        if(!empty($path)){
            $path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
        }
        $response = $this->fetch(self::URL_API . 'search/' . $root . '/' . ltrim($path,'/'),array('query' => $query));
        return json_decode($response['body'],true);
    }

    /**
     * Создает и возвращает ссылку на расшаренные файлы
     *
     * @param type $path
     * @param type $root
     * @return type
     */
    public function share($path, $root = null) {
        if (is_null($root)) $root = $this->root;
        $path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
        $response = $this->fetch($this->api_url.  'shares/'. $root . '/' . ltrim($path, '/'), array(), 'POST');
        return json_decode($response['body'],true);

    }

    /**
     * Возвращает ссылку непосредственно на файл
     *
     * @param type $path
     * @param type $root
     * @return type
     */
    public function media($path, $root = null) {

        if (is_null($root)) $root = $this->root;
        $path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
        $response = $this->fetch(self::URL_API.  'media/'. $root . '/' . ltrim($path, '/'), array(), 'POST');
        return json_decode($response['body'],true);

    }

    /**
     * Создает и возвращает copy_ref. Эта ссылка строка может быть использована для копирования файла в
     * Dropbox другого пользователя, передав ее в качестве параметра на from_copy_ref / fileops / копия.
     *
     * @param type $path
     * @param type $root
     * @return type
     */
    public function copy_ref($path, $root = null) {

        if (is_null($root)) $root = $this->root;
        $path = str_replace(array('%2F','~'), array('/','%7E'), rawurlencode($path));
        $response = $this->fetch(self::URL_API.  'copy_ref/'. $root . '/' . ltrim($path, '/'));
        return json_decode($response['body'],true);

    }

    /**
     * Метод используется для создания составного POST запроса для отправки файлов
     * @param $uri
     * @param $file
     * @param $filename
     * @return array
     */
    protected function multipartFetch($uri, $file, $filename) {

        $boundary = 'R50hrfBj5JYyfR3vF3wR96GPCC9Fd2q2pVMERvEaOE3D8LZTgLLbRpNwXek3';

        $headers = array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        );

        $body="--" . $boundary . "\r\n";
        $body.="Content-Disposition: form-data; name=file; filename=".rawurldecode($filename)."\r\n";
        $body.="Content-type: application/octet-stream\r\n";
        $body.="\r\n";
        $body.=stream_get_contents($file);
        $body.="\r\n";
        $body.="--" . $boundary . "--";

        $uri.='?file=' . $filename;

        return $this->fetch($uri, $body, 'POST', $headers);

    }

    /**
     * Разбираем URL и возвращаем тело ответа
     * @param String $uri
     * @param array $arguments
     * @param string $method
     * @param array $httpHeaders
     * @return array
     * @throws CException
     */
    public function fetch($uri, $arguments = array(), $method = 'GET', $httpHeaders = array()) {
        try {
            $this->_oauth->fetch($uri, $arguments, $method, $httpHeaders);
            $result = $this->_oauth->getLastResponse();
            $lastResponseInfo = $this->_oauth->getLastResponseInfo();
            return array(
                'httpStatus' => $lastResponseInfo['http_code'],
                'body'       => $result,
            );
        } catch (CException $e) {

            $lastResponseInfo = $this->_oauth->getLastResponseInfo();
            switch($lastResponseInfo['http_code']) {

                case 304 :
                    return array(
                        'httpStatus' => 304,
                        'body'       => null,
                    );
                    break;
                case 400 :
                    throw new CException('Forbidden. Bad input parameter. Error message should indicate which one and why.');
                case 401 :
                    throw new CException('Forbidden. Bad or expired token. This can happen if the user or Dropbox revoked or expired an access token. To fix, you should re-authenticate the user.');
                case 403 :
                    throw new CException('Forbidden. This could mean a bad OAuth request, or a file or folder already existing at the target location.');
                case 404 :
                    throw new CException('Resource at uri: ' . $uri . ' could not be found');
                case 405 :
                    throw new CException('Forbidden. Request method not expected (generally should be GET or POST).');
                case 500 :
                    throw new CException('Server error. ' . $e->getMessage());
                case 503 :
                    throw new CException('Forbidden. Your app is making too many requests and is being rate limited. 503s can trigger on a per-app or per-user basis.');
                case 507 :
                    throw new CException('This dropbox is full');
                default:
                    // rethrowing
                    throw $e;
            }

        }

    }

}