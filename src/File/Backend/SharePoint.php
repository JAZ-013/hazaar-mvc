<?php

namespace Hazaar\File\Backend;

use \Hazaar\Http\Request;

class SharePoint extends \Hazaar\Http\Client implements _Interface {

    public  $separator  = '/';

    private $options;

    private $cache;

    private static $STSAuthURL = 'https://login.microsoftonline.com/extSTS.srf';

    private static $signInURL = '/_forms/default.aspx?wa=wsignin1.0';

    private $auth_cookies = null;

    static public function label(){

        return "Microsoft SharePoint";

    }

    public function __construct($options) {

        parent::__construct();

        $this->disableRedirect();

        $this->options = new \Hazaar\Map(array(
            'webURL'        => '',
            'username'      => '',
            'password'      => '',
            'root'          => '/',
            'cache_backend' => 'file'
        ), $options);

        if($this->options->isEmpty('webURL') || $this->options->isEmpty('username') || $this->options->isEmpty('password'))
            throw new Exception\DropboxError('SharePoint filesystem backend requires a webURL, username and password.');

        $cache_options = array(
            'use_pragma' => FALSE,
            'namespace' => 'sharepoint_' . md5($this->options->username . ':' . $this->options->password . '@' . $this->options->webURL)
        );

        $this->cache = new \Hazaar\Cache($this->options['cache_backend'], $cache_options);

        $this->uncacheCookie($this->cache);

    }

    public function __destruct() {

    }

    public function reload() {

    }

    public function reset() {

    }

    public function authorise($redirect_uri = NULL) {

        if($this->authorised())
            return true;

        if(!($token = $this->getSecurityToken($this->options['username'], $this->options['password'])))
            throw new \Exception('Unable to get SharePoint security token!');

        return $this->getAuthenticationCookies($token);

    }

    private function getSecurityToken($username, $password){

        $xmlFile = __DIR__ . DIRECTORY_SEPARATOR . 'XML' . DIRECTORY_SEPARATOR . 'SAML.xml';

        if(!file_exists($xmlFile))
            throw new \Exception('SAML XML authorisation template is missing!');

        $request = new Request(self::$STSAuthURL, 'POST');

        $request->setHeader('Accept', 'application/json; odata=verbose');

        $template = file_get_contents($xmlFile);

        $template = str_replace('{username}', $this->options['username'], $template);

        $template = str_replace('{password}', $this->options['password'], $template);

        $template = str_replace('{address}', $this->options['webURL'], $template);

        $request->setBody($template);

        $response = $this->send($request);

        if($response->status !== 200)
            throw new \Exception('Invalid response requesting security token.');

        $xml = new \DOMDocument();

        $xml->loadXML($response->body());

        if(!$xml instanceof \DOMDocument)
            throw new \Exception('Invalid response authenticating SharePoint access.');

        $xpath = new \DOMXPath($xml);

        if ($xpath->query("//wsse:BinarySecurityToken")->length > 0){

            $nodeToken = $xpath->query("//wsse:BinarySecurityToken")->item(0);

            if (!empty($nodeToken))
                return $nodeToken->nodeValue;

        }

        if ($xpath->query("//S:Fault")->length > 0)
            throw new \RuntimeException($xpath->query("//S:Fault")->item(0)->nodeValue);

        return false;

    }

    private function getAuthenticationCookies($token){

        $url_info = parse_url($this->options['webURL']);

        $url =  $url_info['scheme'] . '://' . $url_info['host'] . self::$signInURL;

        $request = new Request($url, 'POST');

        $request->setBody($token);

        $response = $this->send($request, false);

        if($response->status !== 302)
            throw new \Exception('Invalid response requesting auth cookies: ' . $response->status);

        $this->deleteCookie('fpc');

        $this->deleteCookie('x-ms-gateway-slice');

        $this->deleteCookie('stsservicecookie');

        $this->deleteCookie('RpsContextCookie');

        $this->cacheCookie($this->cache);

        return true;

    }

    public function authorised() {

        return ($this->hasCookie('FedAuth') && $this->hasCookie('rtFa'));

    }

    public function refresh($reset = FALSE) {

    }

    //Get a directory listing
    public function scandir($path, $regex_filter = NULL, $show_hidden = FALSE) {

        $url = $this->options['webURL'] . '/_api/Web'  . $this->makeODataPath($this->options['root'] . '/' . ltrim($path, '/')) . '/files';

        $request = new Request($url, 'GET', 'application/json; OData=verbose');

        $request->setHeader('Accept', 'application/json; OData=verbose');

        $this->applyCookies($request);

        $response = $this->send($request);

        dump($response->body());

    }

    private function makeODataPath($raw_path){

        $path = '';

        $parts = explode('/', trim($raw_path, ' /'));

        foreach($parts as $part)
            $path .= "/folders/getbyurl('" . rawurlencode($part) . "')";

        return $path;

    }

    //Check if file/path exists
    public function exists($path) {

        return FALSE;

    }

    public function realpath($path) {

        return false;

    }

    public function is_readable($path) {

        return false;

    }

    public function is_writable($path) {

        return false;

    }

    //TRUE if path is a directory
    public function is_dir($path) {

        return false;

    }

    //TRUE if path is a symlink
    public function is_link($path) {

        return false;

    }

    //TRUE if path is a normal file
    public function is_file($path) {

        return false;

    }

    //Returns the file type
    public function filetype($path) {

        return false;

    }

    //Returns the file modification time
    public function filectime($path) {

        return false;

    }

    //Returns the file modification time
    public function filemtime($path) {

        return false;

    }

    //Returns the file modification time
    public function fileatime($path) {

        return false;

    }

    public function filesize($path) {

        return false;

    }

    public function fileperms($path) {

        return false;

    }

    public function chmod($path, $mode) {

        return false;

    }

    public function chown($path, $user) {

        return false;

    }

    public function chgrp($path, $group) {

        return false;

    }

    public function unlink($path) {

        return false;

    }

    public function mime_content_type($path) {

        return false;

    }

    public function md5Checksum($path) {

        return false;

    }

    public function thumbnail($path, $params = array()) {

        return FALSE;

    }

    //Create a directory
    public function mkdir($path) {

        return false;

    }

    public function rmdir($path, $recurse = false) {

        return false;

    }

    //Copy a file from src to dst
    public function copy($src, $dst, $recursive = FALSE) {

        return false;

    }

    public function link($src, $dst) {

        return false;

    }

    //Move a file from src to dst
    public function move($src, $dst) {

        return false;

    }

    //Read the contents of a file
    public function read($path) {

        return false;

    }

    //Write the contents of a file
    public function write($file, $data, $content_type, $overwrite = FALSE) {

        return false;

    }

    public function upload($path, $file, $overwrite = TRUE) {

        return false;

    }

    public function set_meta($path, $values) {

        return false;

    }

    public function get_meta($path, $key = NULL) {

        return false;

    }

    public function preview_uri($path) {

        return false;

    }

    public function direct_uri($path) {

        return false;

    }

}
