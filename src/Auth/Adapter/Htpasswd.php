<?php

namespace Hazaar\Auth\Adapter;

class Htpasswd extends \Hazaar\Auth\Adapter implements _Interface {

    private $passwd = CONFIG_PATH . DIRECTORY_SEPARATOR . '.passwd';

    private $user_hash = '$apr1$';

    function __construct($cache_config = array(), $cache_backend = 'session'){

        if(!file_exists($this->passwd))
            die('Hazaar admin console is currently disabled!');

        parent::__construct($cache_config, $cache_backend);
        
    }

    /*
     * We must provide a queryAuth method for the auth base class to use to look up details
     */
    public function queryAuth($identity, $extras = array()) {

        $users = array();

        $lines = explode("\n", trim(file_get_contents($this->passwd)));

        foreach($lines as $line){

            if(!$line)
                continue;

            list($user_identity, $userhash) = explode(':', $line);

            $users[$user_identity] = $userhash;

        }

        $this->user_hash = trim(ake($users, $identity));

        if(strlen($this->user_hash) > 0)
            return array('identity' => $identity, 'credential' => $this->user_hash);

        return false;

    }

    public function getCredential($credential = NULL) {

        if($credential === null)
            $credential = $this->credential;

        $hash = '';

        if(substr($this->user_hash, 0, 6) == '$apr1$'){                      //APR1-MD5

            $BASE64_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

            $APRMD5_ALPHABET = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

            $parts = explode('$', $this->user_hash);

            $salt = substr($parts[2], 0, 8);

            $max = strlen($credential);

            $context = $credential . '$apr1$' . $salt;

            $binary = pack('H32', md5($credential . $salt . $credential));

            for($i=$max; $i>0; $i-=16)
                $context .= substr($binary, 0, min(16, $i));

            for($i=$max; $i>0; $i>>=1)
                $context .= ($i & 1) ? chr(0) : $credential[0];

            $binary = pack('H32', md5($context));

            for($i=0; $i<1000; $i++) {

                $new = ($i & 1) ? $credential : $binary;

                if($i % 3) $new .= $salt;

                if($i % 7) $new .= $credential;

                $new .= ($i & 1) ? $binary : $credential;

                $binary = pack('H32', md5($new));

            }

            $hash = '';

            for ($i = 0; $i < 5; $i++) {

                $k = $i + 6;

                $j = $i + 12;

                if($j == 16) $j = 5;

                $hash = $binary[$i] . $binary[$k] . $binary[$j] . $hash;

            }

            $hash = chr(0) . chr(0) . $binary[11] . $hash;

            $hash = strtr(strrev(substr(base64_encode($hash), 2)), $BASE64_ALPHABET, $APRMD5_ALPHABET);

            $hash = '$apr1$' . $salt . '$' . $hash;

        }elseif(substr($this->user_hash, 0, 5) == '{SHA}'){                  //SHA1

            $hash = '{SHA}' . base64_encode(sha1($credential, TRUE));

        }elseif(substr($this->user_hash, 0, 4) == '$2y$'){                   //Blowfish

            $hash = crypt($credential, substr($this->user_hash, 0, 29));       

        }else{

            throw new \Hazaar\Exception('Unsupported password encryption algorithm.');

        }

        return $hash;

    }

}
