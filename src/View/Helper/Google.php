<?php
/**
 * @file        Hazaar/View/Helper/Google.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

define('GMAP_TYPE_STREET', 'ROADMAP');

define('GMAP_TYPE_HYBRID', 'HYBRID');

class Google extends \Hazaar\View\Helper {

    private $settings;

    public function import() {

        $this->requires('html');

        $this->settings = new \Hazaar\Map( array(
            'api_key' => null,
            'gmaps_source' => "http://maps.googleapis.com/maps/api/js?sensor=false"
        ), $this->args);

    }

    public function map($name, $location, $settings = array(), $args = array()) {

        $settings = new \Hazaar\Map($settings);

        $settings->extend(array(
            'zoom' => 16,
            'type' => GMAP_TYPE_STREET
        ), $this->settings);

        $args = new \Hazaar\Map( array('id' => $name), $args);

        if(!$location instanceof \Hazaar\Google\Location) {

            $location = new \Hazaar\Google\Location($location);

        }

        if($settings->api_key == null)
            throw new \Hazaar\Exception('You MUST supply your Google Services API key to use Google Maps.  For more information see: ' . $this->html->link('https://developers.google.com/maps/documentation/javascript/tutorial#api_key'));

        $canvas = $this->html->block('div', null, $args->toArray(true));

        $code = "function init_$name(){
            var mapOptions = {
              center: new google.maps.LatLng(" . $location->lat() . ", " . $location->lng() . "),
              zoom: " . $settings->zoom . ",
              mapTypeId: google.maps.MapTypeId." . $settings->type . "
            };
            map = new google.maps.Map(document.getElementById('$name'), mapOptions);
        }";

        $init = $this->html->block('script', $code, array('type' => 'text/javascript'));

        $loadArgs = array(
            'type' => 'text/javascript',
            'src' => $settings->gmaps_source . '&key=' . $settings->api_key . '&callback=init_' . $name
        );

        $load = $this->html->block('script', null, $loadArgs);

        return $canvas . "\n" . $init . "\n" . $load;

    }

    public function analytics($tracking_id) {

        $code = "
            var _gaq = _gaq || [];
            _gaq.push(['_setAccount', '$tracking_id']);
            _gaq.push(['_trackPageview']);

            (function() {
                var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
                ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
                var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
            })();
        ";

        return $this->html->block('script', $code, array('type' => 'text/javascript'));

    }

}

