<?php
/**
 * @file        Hazaar/Session.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar;

/**
 * @brief       Session class
 *
 * @detail      Sessions make use of the Hazaar\Cache class but they create a unique session ID so stored data is not
 *              shared between user sessions.  If you want to store data that can be shared then use the Hazaar\Cache
 *              classes directly.
 *
 * @since       2.0.1
 */
class Session extends \Hazaar\Cache {

    static public  $SESSION_NAME = 'hazaar-session';

    static private $session_id;

    public function __construct($options = array(), $backend = 'session') {

        if(!$options instanceof \Hazaar\Map){

            if(!is_array($options))
                $options = array();

            $options = new \Hazaar\Map($options);

        }

        if($options->has('session_id'))
            Session::$session_id = $options->session_id;
        else
            Session::$session_id = ake($_COOKIE, Session::$SESSION_NAME, Session::$session_id);

        if(! Session::$session_id) {

            Session::$session_id = md5(uniqid());

            setcookie(Session::$SESSION_NAME, Session::$session_id, 0, APPLICATION_BASE);

        }

        $options->use_pragma = false;

        $options->keepalive = true;

        //If there is no backend requested, and none configured, use SESSION
        if ($backend === NULL
            && ($app = \Hazaar\Application::getInstance()) instanceof \Hazaar\Application
            && !$app->config->cache->has('backend'))
            $backend = array('apc', 'session');

        parent::__construct($backend, $options, Session::$session_id);

        if(!$this->backend->can('keepalive'))
            throw new \Exception('The currently selected cache backend, ' . get_class($this->backend) . ', does not support the keepalive feature which is required by the ' . __CLASS__ . ' class.  Please choose a caching backend that supports the keepalive feature.');

    }

}