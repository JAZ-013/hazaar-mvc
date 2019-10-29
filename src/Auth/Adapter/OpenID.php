<?php

namespace Hazaar\Auth\Adapter;

/**
 * OAuth short summary.
 *
 * OAuth description.
 *
 * @version 1.0
 * @author jamiec
 */
class OpenID extends \Hazaar\Auth\Adapter\OAuth2 {

    function __construct($client_id, $client_secret, $grant_type = 'code', $cache_config = array(), $cache_backend = 'session') {

        parent::__construct($client_id, $client_secret, $grant_type, $cache_config, $cache_backend);

        $this->addScope('openid', 'profile', 'email');

    }


    public function getProfile(){

        if(!$this->session->has('oauth2_data'))
            return null;

        $parts = explode('.', $this->session->oauth2_data->id_token);

        foreach($parts as &$part)
            $part = \base64url_decode($part);

        return json_decode($parts[1]);

    }

}
