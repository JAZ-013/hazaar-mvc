<?php

/**
 * @file        Auth/Adapter.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

/**
 * User authentication namespace
 */
namespace Hazaar\Auth;

/**
 * Abstract authentication adapter
 *
 * This class is the base class for all of the supplied authentication adapters.  This class takes care
 * of the password hash generation and session management, including the autologin function.
 *
 * Available options are:
 *
 * # encryption.hash - default: sha256
 * The hash algorithm to use to encrypt passwords.  This can be a a single algorith, such
 * as sha256, sha1 or any other algorithm supported by the PHP hash() function.  You can use the hash_algos()
 * function to get a list of available algorithms.  Any unsupported algorithms are silently ignored.
 *
 * This option can also be an array of algorithms.  In which case each one will be applied in the order
 * specified.  During each iteration the hash will be appended with the original password (this helps prevent
 * hash collisions) along with any salt value (see below) before being hashed with the next algorithm.
 *
 * The default is sha256 for security.  Please note that this breaks backwards compatibility with the 1.0
 * version of this module.
 *
 * # Configuration Directives
 *
 * ## encryption.count - default: 1
 * For extra obfuscation, it's possible to "hash the hash" this many times.  This is the old method we used
 * to add extra security to the hash, except we now also append the original password to the hash before
 * hashing it. (too much hash?).  In the case where the encryption.hash is a list of algorithms, each one
 * of these will be applied as above for each count.  So for example, if you have a list of 3 algorithms
 * and the count is 3, your password will be hashed 9 times.
 *
 * ## encryption.salt - default: null
 * For more security a salt value can be set which will be appended to each password when being hashed.  If
 * the password is being hashed multiple times then the salt is appended to the hash + password.
 *
 * ## autologin.period - default: 1
 * This is the period in which the autologin cookie will remain active (ie: will expire after this many
 * days).  The default is one day.
 *
 * # autologin.hash - default: md5
 * This is the hash algorithm used to encrypt the token placed in the cookie in the user's browser
 * session.  This data is hashed to ensure that it can not be manipulated by the user.
 *
 * ## token.hash - default: md5
 * The token hash is the value stored in the session cache and is used to confirm that a user
 * account is authenticated.  As an added security measure we apply a hash to this value so that plain
 * test passwords will never be stored in the session cache, even if there is no password encryption chain.
 *
 * ## timeout - default: 3600
 * For a standard login, this is the session expirey timeout.  Basically this is the maximum time in which
 * a session will ever be active.  If autologin is being used, then it is quite common to set this to a low
 * value to allow the user to be re-authenticated with the autologin token periodically.
 *
 * This is now more often used as a cache timeout value because on logon, certain data is obtained for a user
 * and stored in cache.  Sometimes obtaining this data can be processor intensive so we don't want to do it
 * on every page load.  Instead we do it, cache it, and then only do it again once this time passes.
 *
 * # Example Config (application.json)
 *
 * ```
 * {
 *     "development": {
 *         "cache": {
 *             "encryption": {
 *                 "hash": [ "md5", "sha1", "sha256" ],
 *                 "salt": "mysupersecretsalt"
 *             },
 *             "autologin": {
 *                 "period": 365,
 *                 "hash": "sha1"
 *             },
 *             "timeout": 28800
 *         }
 *     }
 * }
 * ```
 */
abstract class Adapter implements Adapter\_Interface, \ArrayAccess {

    protected $session;

    protected $options;

    private $identity;

    private $credential;

    // Extra data fields to store from the user record
    private $extra = array();

    function __construct($cache_config = array(), $cache_backend = null) {

        $this->options = new \Hazaar\Map(array(
            'encryption' => array(
                'hash' => 'sha1',
                'count' => 1,
                'salt' => '',
                'use_identity' => false
            ),
            'autologin' => array(
                'cookie' => 'hazaar-auth-autologin',
                'period' => 1,
                'hash'  => 'sha1'
            ),
            'token' => array(
                'hash' => 'sha1'
            ),
            'timeout' => 3600,
            'cache' => array(
                'backend' => 'session',
                'cookie' => 'hazaar-auth'
            )
        ), \Hazaar\Application::getInstance()->config['auth']);

        $cache_config = new \Hazaar\Map(array(
            'use_pragma' => FALSE,
            'lifetime' => $this->options->timeout,
            'session_name' => $this->options->cache['cookie']
        ), $cache_config);

        if($cache_backend instanceof \Hazaar\Cache){

            $cache_backend->configure($cache_config);

            $this->session = $cache_backend;

        }elseif($this->options->cache['backend'] === 'session'){

            $this->session = new \Hazaar\Session($cache_config);
        
        }else{

            $this->session = new \Hazaar\Cache($this->options->cache['backend'], $cache_config);

        }
        
        if($this->session->has('hazaar_auth_identity')
            && $this->session->has('hazaar_auth_token')
            && hash($this->options->token['hash'], $this->session->hazaar_auth_identity) === $this->session->hazaar_auth_token)
            $this->identity = $this->session->hazaar_auth_identity;

        if($this->session->has('hazaar_auth_identity') && $this->session->has('hazaar_auth_token')){

            if(hash($this->options->token['hash'], $this->getIdentifier($this->session->hazaar_auth_identity)) !== $this->session->hazaar_auth_token)
                $this->deauth();
            
        }

    }

    public function setIdentity($identity) {

        $this->identity = $identity;

    }

    public function setCredential($credential) {

        $this->credential = $credential;

    }

    public function getIdentity() {

        return $this->identity;

    }

    /**
     * Get the encrypted hash of a credential/password
     *
     * This method uses the "encryption" options from the application configuration to generate
     * a password hash based on the supplied password.  If no password is supplied then the
     * currently set credential is used.
     *
     * NOTE: Keep in mind that if no credential is set, or it's null, or an empty string, this
     * will still return a valid hash of that empty value using the defined encryption hash chain.
     *
     * @param mixed $credential
     * @return \null|string
     */
    public function getCredential($credential = NULL) {

        if($credential === null)
            $credential = $this->credential;

        if(!$credential)
            return $credential;

        $hash = false;

        if($this->options->encryption['use_identity'] === true)
            $credential =  $this->identity . ':' . $credential;

        $count = $this->options->encryption['count'];

        $algos = $this->options->encryption['hash'];

        if(!\Hazaar\Map::is_array($algos))
            $algos = array($algos);

        $salt = $this->options->encryption['salt'];

        $hash_algos = hash_algos();

        if(!is_string($salt))
            $salt = '';

        for($i = 1; $i <= $count; $i++){

            foreach($algos as $algo){

                if(!in_array($algo, $hash_algos))
                    continue;

                $hash = hash($algo, $hash . $credential . $salt);

            }

        }

        return $hash;

    }

    protected function getIdentifier($identity){

        return hash('sha1', $identity)
            . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '')
            . (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '')
            . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown-ua');

    }

    protected function setDataFields(array $fields) {

        $this->extra = $fields;

    }

    public function &get($key) {

        return $this->session->get($key);

    }

    public function &__get($key) {

        return $this->get($key);

    }

    public function __set($key, $value) {

        return $this->set($key, $value);

    }

    public function __isset($key) {

        return $this->has($key);

    }

    public function set($key, $value) {

        $this->session->set($key, $value);

    }

    public function has($key) {

        return $this->session->has($key);

    }

    public function authenticate($identity = NULL, $credential = NULL, $autologin = FALSE) {

        /*
         * Save the authentication data
         */
        if($identity)
            $this->setIdentity($identity);

        if($credential)
            $this->setCredential($credential);

        $auth = $this->queryAuth($this->getIdentity(), $this->extra);

        /*
         * First, make sure the identity is correct.
         */
        if($auth && is_array($auth) && ake($auth, 'identity') === $this->getIdentity()) {

            /*
             * Check the credentials
             */
            if(ake($auth, 'credential') === $this->getCredential()) {

                if(array_key_exists('data', $auth))
                    $this->session->setValues($auth['data']);

                $this->session->hazaar_auth_identity = $identity;

                $this->session->hazaar_auth_token = hash($this->options->token['hash'], $this->getIdentifier($identity));

                if(boolify($autologin) && $this->options->autologin['period'] > 0) {

                    /*
                     * $credential should be encrypted, as stored in the datasource (ie: database), so we
                     * md5 that to totally obscure it. If it is not encrypted then it is not being
                     * stored encrypted and the developer should re-think their auth strategy but
                     * we offer some minor protection from that stupidity here.
                     */
                    $data = base64_encode(http_build_query(array(
                        'identity' => $identity,
                        'hash' => hash($this->options->autologin['hash'], $this->getIdentifier($auth['credential'] . $identity))
                    )));

                    $cookie = $this->getAutologinCookieName();

                    $timeout = (86400 * $this->options->autologin['period']);

                    setcookie($cookie, $data, time() + $timeout, \Hazaar\Application::path());

                }

                return TRUE;

            }

        }

        return FALSE;

    }

    public function getUserData() {

        return $this->session->toArray();

    }

    public function authenticated() {

        if($this->session->has('hazaar_auth_identity')
            && $this->session->has('hazaar_auth_token')
            && hash($this->options->token['hash'], $this->getIdentifier($this->session->hazaar_auth_identity)) === $this->session->hazaar_auth_token) {

            return true;

        }

        $headers = hazaar_request_headers();

        if($authorization = ake($headers, 'Authorization')){

            list($method, $code) = explode(' ', $authorization);

            if(strtolower($method) != 'basic')
                throw new \Exception('Unsupported authorization method: ' . $method);

            list($identity, $credential) = explode(':', base64_decode($code));

            return $this->authenticate($identity, $credential);

        }elseif($this->canAutoLogin()) {

            /*
             * If we've got a cookie set, use the identity to look up credentials
             */
            $cookie_name = $this->getAutologinCookieName();

            parse_str(base64_decode(ake($_COOKIE, $cookie_name, '')), $cookie);

            if($cookie){

                if($identity = urldecode(ake($cookie, 'identity')))
                    $this->setIdentity($identity);

                if($auth = $this->queryAuth($identity, $this->extra)) {

                    $hash = hash($this->options->autologin['hash'], $this->getIdentifier($auth['credential'] . $identity));

                    /*
                    * Check the cookie credentials against the ones we just got from the adapter
                    */
                    if($identity === $auth['identity']
                        && $hash === ake($cookie, 'hash')) {

                        if(array_key_exists('data', $auth))
                            $this->session->setValues($auth['data']);

                        $this->session->hazaar_auth_identity = $identity;

                        $this->session->hazaar_auth_token = hash($this->options->token['hash'], $this->getIdentifier($identity));

                        return TRUE;

                    }else $this->deauth();

                }

            }

        }

        return FALSE;

    }

    /**
     * Check that the supplied password is correct for the current identity.
     *
     * This is useful for checking an account password before allowing something important to be updated.
     * This does the same steps as authenticate() but doesn't actually do the authentication.
     *
     * @param mixed $credential
     * @return boolean
     */
    public function check($credential) {

        $auth = $this->queryAuth($this->getIdentity(), $this->extra);

        /*
         * First, make sure the identity is correct.
         */
        if($auth && $auth['identity'] == $this->getIdentity()) {

            /*
             * Check the credentials
             */
            if($auth['credential'] == $this->getCredential($credential))
                return TRUE;

        }

        return FALSE;

    }

    public function deauth() {

        $this->session->clear(true);

        $cookie = $this->getAutologinCookieName();

        if(isset($_COOKIE[$cookie])) {

            unset($_COOKIE[$cookie]);

            setcookie($cookie, NULL, time() - 3600, \Hazaar\Application::path());

        }

        return TRUE;

    }

    /**
     * Helper method that sets the basic auth header and throws an unauthorised exception
     *
     * @throws \Exception
     */
    public function unauthorised(){

        header('WWW-Authenticate: Basic');

        throw new \Exception('Unauthorised!', 401);

    }

    protected function canAutoLogin() {

        $cookie = $this->getAutologinCookieName();

        return ($this->options['autologin']['period'] > 0 && isset($_COOKIE[$cookie]));

    }

    protected function getAutologinCookieName() {

        return $this->options['autologin']['cookie'];

    }

    public function getToken(){

        return $this->session->hazaar_auth_token;

    }

    public function getTokenType(){

        return 'Basic';

    }

    /**
     * Array Access Methods
     *
     * These methods allows accessing user data as array attributes of the auth object. These methods do not allow this
     * data to be modified in any way.
     */
    public function offsetExists($key) {

        return $this->session->has($key);

    }

    public function &offsetGet($key) {

        if($this->session->has($key))
            return $this->session->get($key);

        $result = NULL; // Required to return variables by reference

        return $result;

    }

    public function offsetSet($key, $value) {

        $this->session->set($key, $value);

    }

    public function offsetUnset($key) {

        $this->session->unset($key);

    }

}

