<?php

namespace Hazaar\Application\Exception;

class RouteNotFound extends \Hazaar\Exception {

    protected $name = 'Route Not Found';
    
    function __construct($controller) {

        parent::__construct("No route found for '$controller'.", 404);

    }

}
