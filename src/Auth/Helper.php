<?php

namespace Hazaar\Auth;

class Helper extends Adapter {

    function __construct($cache_config = array(), $cache_backend = 'session'){

        parent::__construct($cache_config, $cache_backend);

        $this->identity = $this->session->hazaar_auth_identity;

    }

    public function queryAuth($identity, $credential = null, $extra = array()) {

        /*
         * Helper does not support queryAuth as it doesn't know how to look up credentials
         */

        return false;

    }

}
