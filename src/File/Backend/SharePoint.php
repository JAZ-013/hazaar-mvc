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

    private $root = null;

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
            'root'          => 'Shared Documents',
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

        $this->root = new \stdClass;

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

    private function _post($path, $suffix = null, &$response = null){

        return $this->_query('POST', $url, $response);

    }

    private function _get($path, $suffix = null, &$response = null){

        return $this->_query('GET', $path, $suffix, $response);

    }

    private function _query($method, $path, $suffix = null, &$response = null){

        $url = $this->makeODataURL($path) . (($suffix !== null) ? '/' . $suffix : '');

        $request = new Request($url, $method, 'application/json; OData=verbose');

        $request->setHeader('Accept', 'application/json; OData=verbose');

        $response = $this->send($request);

        if($response->status !== 200){

            $error = ake($response->body(), 'error');

            throw new \Exception('Invalid response from SharePoint: code=' . $error->code . ' message=' . $error->message->value);

        }

        return $response->body();

    }

    private function makeODataURL($path){

        $url = $this->options['webURL'] . '/_api/Web';

        $parts = explode('/', trim($this->options['root'] . '/' . ltrim($path, ' /'), '/'));

        foreach($parts as $part)
            $url .= "/folders/getbyurl('" . rawurlencode($part) . "')";

        return $url;

    }

    private function &info($path){

        $folder =& $this->root;

        $parts = explode('/', trim($path, ' /'));
        
        //If there's no item, we're loading the root, so make some stuff up.
        if(!($item = array_pop($parts)) && !property_exists($folder, 'Name'))
            $folder = ake($this->_get('/'), 'd');

        foreach($parts as $part){

            if(!property_exists($folder, 'items'))
                $folder->items = array();

            if(!array_key_exists($part, $folder->items))
                $folder->items[$part] = new \stdClass;

            $folder =& $folder->items[$part];

        }

        if(!$item)
            return $folder;

        if(!property_exists($folder, 'items'))
            $folder->items = $this->load(implode('/', $parts));

        foreach($folder->items as $f){

            if($f->Name === $item)
                return $f;

        }
        
        $null = null;

        return $null;

    }

    private function load($path){
        
        $folders = ake($this->_get($path, 'folders'), 'd.results');
                
        $files = ake($this->_get($path, 'files'), 'd.results');

        $sort = function($a, $b){
            if ($a->Name === $b->Name) return 0;
            return ($a->Name < $b->Name) ? -1 : 1;
        };

        usort($folders, $sort);

        usort($files, $sort);

        return array_merge($folders, $files);

    }

    //Get a directory listing
    public function scandir($path, $regex_filter = NULL, $show_hidden = FALSE) {

        $info =& $this->info($path);

        if(!isset($info->items) || $info->ItemCount !== count($info->items))
            $info->items = $this->load($path);

        $files = array();

        foreach($info->items as $item)
            $files[] = $item->Name;

         return $files;

    }

    //Check if file/path exists
    public function exists($path) {

        if(!($info = $this->info($path)))
            return false;

        return $info->Exists;

    }

    public function realpath($path) {

        if(!($info = $this->info($path)))
            return null;

        return $info->ServerRelativeUrl;

    }

    public function is_readable($path) {

        if($info = $this->info($path))
            return true;

        return false;

    }

    public function is_writable($path) {

        if($info = $this->info($path))
            return true;

        return false;

    }

    //TRUE if path is a directory
    public function is_dir($path) {

        if(!($info = $this->info($path)))
            return false;

        return $info->__metadata->type === 'SP.Folder';

    }

    //TRUE if path is a symlink
    public function is_link($path) {

        return false;

    }

    //TRUE if path is a normal file
    public function is_file($path) {

        if(!($info = $this->info($path)))
            return false;

        return $info->__metadata->type === 'SP.File';

    }

    //Returns the file type
    public function filetype($path) {

        if(!($info = $this->info($path)))
            return false;

            dump($info);

        return false;

    }

    //Returns the file modification time
    public function filectime($path) {

        if(!($info = $this->info($path)))
            return null;

        return strtotime($info->TimeCreated);
        
    }

    //Returns the file modification time
    public function filemtime($path) {

        if(!($info = $this->info($path)))
            return null;

        return strtotime($info->TimeLastModified);

    }

    //Returns the file modification time
    public function fileatime($path) {

        return null;

    }

    public function filesize($path) {

        if(!($info = $this->info($path)))
            return false;
        
        return intval(ake($info, 'Length', 0));

    }

    public function fileperms($path) {

        die(__METHOD__);
        
        return false;

    }

    public function chmod($path, $mode) {

        die(__METHOD__);
        
        return false;

    }

    public function chown($path, $user) {

        die(__METHOD__);
        
        return false;

    }

    public function chgrp($path, $group) {

        die(__METHOD__);
        
        return false;

    }

    public function unlink($path) {

        die(__METHOD__);
        
        return false;

    }

    public function mime_content_type($path) {

        die(__METHOD__);
        
        return false;

    }

    public function md5Checksum($path) {

        die(__METHOD__);
        
        return false;

    }

    public function thumbnail($path, $params = array()) {

        die(__METHOD__);
        
        return FALSE;

    }

    //Create a directory
    public function mkdir($path) {

        die(__METHOD__);
        
        return false;

    }

    public function rmdir($path, $recurse = false) {

        die(__METHOD__);
        
        return false;

    }

    //Copy a file from src to dst
    public function copy($src, $dst, $recursive = FALSE) {

        die(__METHOD__);
        
        return false;

    }

    public function link($src, $dst) {

        die(__METHOD__);
        
        return false;

    }

    //Move a file from src to dst
    public function move($src, $dst) {

        die(__METHOD__);
        
        return false;

    }

    //Read the contents of a file
    public function read($path) {

        die(__METHOD__);
        
        return false;

    }

    //Write the contents of a file
    public function write($file, $data, $content_type, $overwrite = FALSE) {

        die(__METHOD__);
        
        return false;

    }

    public function upload($path, $file, $overwrite = TRUE) {

        die(__METHOD__);
        
        return false;

    }

    public function set_meta($path, $values) {

        return false;

    }

    public function get_meta($path, $key = NULL) {

        return false;

    }

    public function preview_uri($path) {

        return null;

    }

    public function direct_uri($path) {

        if(!($info = $this->info($path)))
            return false;

        if($info->__metadata->type === 'SP.Folder')
            return null;
            
        return $info->LinkingUri;

    }

}
