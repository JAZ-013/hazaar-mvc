<?php

namespace Hazaar\Auth\Adapter;

/**
 * Hazaar MOJO Authentication Adapter
 *
 * This adapter is for authentication via the Hazaar MOJO OpenID platform.  The adapter automatically configures everything to 
 * successfully authenticate.  Only the $client_id and $client_secret parameters are required.
 *
 * @version 1.0
 * 
 * @author Jamie Carl <jamie@hazaar.io>
 */
class Mojo extends \Hazaar\Auth\Adapter\OpenID {

    function __construct($client_id, $client_secret, $grant_type = 'code', $cache_config = array(), $cache_backend = 'session') {

        parent::__construct($client_id, $client_secret, $grant_type, $cache_config, $cache_backend);

        $this->addScope('email');

        $this->addScope('profile');

        $uri_prefix = 'https://id.hazaar.io/oauth2/v1/';

        $this->setAuthURI($uri_prefix . 'authorize');

        $this->setTokenURI($uri_prefix . 'token');

        $this->setRegistrationURI($uri_prefix . 'clients');

        $this->setIntrospectURI($uri_prefix . 'introspect');

        $this->setRevokeURI($uri_prefix . 'revoke');

        $this->setLogoutURI($uri_prefix . 'logout');

        $this->setUserinfoURI($uri_prefix . 'userinfo');

    }

}
