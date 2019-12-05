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

        $this->addScope('openid');

    }


    public function getProfile(){

        if(!$this->session->has('oauth2_data'))
            return null;

        if(!($this->session->oauth2_data instanceof \stdClass && property_exists($this->session->oauth2_data, 'id_token')))
            return null;

        $parts = explode('.', $this->session->oauth2_data->id_token);

        foreach($parts as &$part)
            $part = \base64url_decode($part);

        return json_decode($parts[1]);

    }

    public function logout($redirect_url = null){

        $endpoint = new \Hazaar\Http\Uri(ake($this->metadata, 'end_session_endpoint'));

        $endpoint->client_id = $this->client_id;

        $endpoint->id_token_hint = ake($this->session->oauth2_data, 'id_token');

        if($redirect_url)
            $endpoint->post_logout_redirect_uri = (string)$redirect_url;

        if(!$this->deauth())
            return false;

        return $endpoint;

    }

}
