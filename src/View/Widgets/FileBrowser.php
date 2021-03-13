<?php

namespace Hazaar\View\Widgets;

/**
 * @detail          Basic button widget.
 *
 * @since           1.1
 */
class FileBrowser {

    private $name;

    private $div;

    private $settings;

    private $jquery;

    /**
     * @detail      Initialise a file browser widget
     *
     * @param       string $name The ID of the file browser element to create.
     *
     * @param       string $connect The URL to connect to
     */
    function __construct($name, $settings, $params = array()) {

        $this->div = new \Hazaar\Html\Div(NULL, $params);

        $this->div->id($this->name = $name);

        $this->settings = new \Hazaar\Map([
            'connect' => (string) new \Hazaar\Application\Url('media')
        ], $settings);

        $this->jquery = \Hazaar\Html\jQuery::getInstance();

    }

    /**
     * @detail      Output the browser as HTML
     */
    public function toString() {

        $args = $this->settings->toJson();

        $this->jquery->exec("$('#" . $this->name . "').fileBrowser($args);");

        return $this->div->renderObject();

    }

    /**
     * @detail      Magic method to output the browser as HTML
     */
    public function __toString(){

        return $this->toString();

    }

    /**
     * @detail      Set an optional parameter
     * 
     * @param       string $key The name of the parameter.
     * 
     * @param       mixed $value The value of the parameter.
     * 
     * @return      FileBrowser
     */
    public function set($key, $value) {

        $this->settings[$key] = $value;

        return $this;

    }

    /**
     * @detail      Set the URL to connect to for directory data
     *
     * @param       string $url The URL to connect to.
     *
     * @return      FileBrowser
     */
    public function connect($url = TRUE) {

        return $this->set('connect', (string)$url);

    }

    /**
     * @detail      Set the display title for the filebrowser
     * 
     * @param       string $title The title to display
     * 
     * @return      FileBrowser
     */
    public function title($title) {

        return $this->set('title', $title);

    }

    /**
     * @detail      Set the root path to browse
     * 
     * @param       string $source The name of the media source
     * 
     * @param       string $path The path on the media source.  Defaults to '/';
     * 
     * @return      FileBrowser
     */
    public function root($source, $path = '/'){

        return $this->set('root', [$source, $path]);

    }

    /**
     * @detail      Set whether folders should be listed in the detail panel
     *
     * @param       boolean $value True/false value.  Default is true.
     *
     * @return      FileBrowser
     */
    public function listfolders($value = TRUE) {

        return $this->set('listfolders', $value);

    }

    /**
     * @detail      Set the width of the browser
     * 
     * @param       mixed $width A valid HTML/CSS dimension.  eg: 500px, 100%, 8em, etc.
     * 
     * @return      FileBrowser
     */
    public function width($width) {

        return $this->set('width', $width);

    }

    /**
     * @detail      Set the height of the browser
     * 
     * @param       mixed $height A valid HTML/CSS dimension.  eg: 500px, 100%, 8em, etc.
     * 
     * @return      FileBrowser
     */
    public function height($height) {

        return $this->set('height', $height);

    }

    /**
     * @detail      Enable/Disable folder tree auto expansion.
     * 
     * @param       boolean $value TRUE/FALSE to ENABLE/DISABLE.  
     * 
     * @return      FileBrowser
     */
    public function autoexpand($value) {

        return $this->set('autoexpand', $value);

    }

    /**
     * @detail      Show the file info panel on the right of the browser to display detailed file information.
     * 
     * @param       boolean $value TRUE/FALSE to ENABLE/DISABLE.  
     * 
     * @return      FileBrowser
     */
    public function showinfo($value = TRUE) {

        return $this->set('showinfo', $value);

    }

    /**
     * @detail      Toggle multiple file selection behaviour.
     * 
     * @param       boolean $value TRUE/FALSE to ENABLE/DISABLE.  
     * 
     * @return      FileBrowser
     */
    public function allowmultiple($value = TRUE) {

        return $this->set('allowmultiple', $value);

    }

    /**
     * @detail      Set the width and height of the file previews
     * 
     * @param       mixed $width A valid HTML/CSS dimension.  eg: 500px, 100%, 8em, etc.
     * 
     * @param       mixed $height A valid HTML/CSS dimension.  eg: 500px, 100%, 8em, etc.
     * 
     * @return      FileBrowser
     */
    public function previewsize($width = NULL, $height = NULL) {

        return $this->set('previewsize', array('w' => $width, 'h' => $height));

    }

    /**
     * @detail      Toggles if file metadata should be used
     * 
     * @param       boolean $value TRUE/FALSE to ENABLE/DISABLE.
     * 
     * @return      FileBrowser
     */
    public function useMeta($value = TRUE) {

        return $this->set('useMeta', $value);

    }

}
