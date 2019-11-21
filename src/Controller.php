<?php
/**
 * @file        Controller/Controller.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar;

/**
 * Base Controller class
 * 
 * All controller classes extend this class.  Normally this class would only be extended by the controller classes
 * provided by Hazaar MVC, as how a controller actually behaves and the functionality it provides is actually defined
 * by the controller itself.  This controller does nothing, but will still initialise and run, but will output nothing.
 */
abstract class Controller {

    public $url_default_action_name = 'index';

    protected $application;

    protected $name;

    protected $request;

    protected $statusCode;

    protected $base_path;    //Optional base_path for controller relative url() calls.

    /**
     * Base controller constructor
     * 
     * @param string $name The name of the controller.  This is the name used when generating URLs.
     * 
     * @param \Hazaar\Application $application An application instance.
     */
    public function __construct($name, \Hazaar\Application $application) {

        $this->name = strtolower($name);

        $this->application = $application;

    }

    /**
     * Controller shutdown method
     * 
     * This method is called when a controller is being shut down.  It will call the extending controllers
     * shutdown method if it exists, otherwise it will silently carry on.
     */
    public function __shutdown() {

        if(method_exists($this, 'shutdown'))
            $this->shutdown();

    }

    /**
     * Controller initialisation method
     * 
     * This should be called by all extending controllers and is simply responsible for storing the calling request.
     * 
     * @param \Hazaar\Application\Request $request The application request object.
     */
    public function __initialize(\Hazaar\Application\Request $request){

        $this->request = $request;

    }

    /**
     * Convert the controller object into a string
     */
    public function __tostring() {

        return get_class($this);

    }

    /**
     * Default run method.
     * 
     * The run method is where the controller does all it's work.  This default one does nothing.
     */
    public function __run(){

        return false;

    }

    /**
     * Get the name of the controller
     */
    public function getName() {

        return $this->name;

    }

    /**
     * Set the default return status code
     * 
     * @param integer $code The default status code that will used on responses.
     */
    public function setStatus($code = null) {

        $this->statusCode = $code;

    }

    public function getStatus(){

        return $this->statusCode;
        
    }

    public function getBasePath(){
        
        return $this->base_path;

    }

    public function setBasePath($path){

        $this->base_path = $path;
        
    }

    /**
     * Initiate a redirect response to the client
     */
    public function redirect($location, $args = array(), $save_url = TRUE) {

        return $this->application->redirect($location, $args, $save_url);

    }

    /**
     * Redirect back to a URL saved during redirection
     * 
     * This mechanism is used with the $save_url parameter of `Hazaar\Application::redirect()` so save the current URL into the session
     * so that once we're done processing the request somewhere else we can come back to where we were. This is useful for when
     * a user requests a page but isn't authenticated, we can redirect them to a login page and then that page can call this
     * `Hazaar\Controller::redirectBack()` method to redirect the user back to the page they were originally looking for.
     */
    public function redirectBack(){

        return $this->application->redirectBack();

    }

    /**
     * Generate a URL relative to the controller.
     *
     * This is the controller relative method for generating URLs in your application.  URLs generated from
     * here are relative to the controller.  For URLs that are relative to the current application see
     * `Hazaar\Application::url()`.
     *
     * Parameters are dynamic and depend on what you are trying to generate.
     *
     * For examples see: [Generating URLs](/basics/urls.md)
     *
     */
    public function url() {

        $url = new Application\Url();

        $parts = func_get_args();

        if(count($parts) === 1 && strtolower(trim($parts[0])) === $this->url_default_action_name)
            $parts = array();

        $this_parts = explode('/', $this->name);

        if(count($parts) === 0 && $this_parts[count($this_parts)-1] === $this->url_default_action_name)
            array_pop($this_parts);

        call_user_func_array(array($url, '__construct'), array_merge($this_parts, $parts));

        return $url;

    }

    /**
     * Test if a URL is active, relative to this controller.
     *
     * Parameters are simply a list of URL 'parts' that will be combined to test against the current URL to see if it is active.  Essentially
     * the argument list is the same as `Hazaar\Controller::url()` except that parameter arrays are not supported.
     * 
     * * Example
     * ```php
     * if($controller->active('index')){
     * ```
     *
     * If the current URL has more parts than the function argument list, this will mean that only a portion of the URL is tested
     * against.  This allows an action to be tested without looking at it's argument list URL parts.  This also means that it is
     * possible to call the `active()` method without any arguments to test if the controller itself is active, which if you are
     * calling it from within the controller, should always return `TRUE`.
     * 
     * @return boolean True if the supplied URL is active as the current URL.
     */
    public function active() {

        $parts = func_get_args();

        return call_user_func_array(array($this->application, 'active'), array_merge(array($this->name), $parts));

    }

}
