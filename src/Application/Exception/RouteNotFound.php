<?php

namespace Hazaar\Application\Exception;

class RouteNotFound extends \Hazaar\Exception {

    protected $name = 'Route Not Found';

    function __construct($path) {

        parent::__construct("No route found to handle '$path'.", 404);

    }

}
