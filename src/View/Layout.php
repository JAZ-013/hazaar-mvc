<?php
/**
 * @file        Hazaar/View/Layout.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View;

class Layout extends \Hazaar\View implements \ArrayAccess, \Iterator {

    private $_content = '';

    private $_rendered_views = null;

    private $_views   = array();

    protected $_priority = 0;

    protected $_links = array();

    protected $_requires = array();

    protected $_requires_param = array();

    protected $_postItems = array();

    private $cacheDir;

    public function __construct($view = null) {

        if(! $view)
            $view = 'application';

        parent::__construct($view, array('html', 'hazaar'));

        if ($this->application->config->has('view')) {

            if ($this->application->config->view->has('link')) {

                foreach ($this->application->config->view->link as $link)
                    $this->link($link);

            }

            if ($this->application->config->view->has('requires')) {

                foreach ($this->application->config->view->requires as $req)
                    $this->requires($req);

            }

        }

    }

    public function setContent($content) {

        $this->_content = $content;

    }

    public function prepare($merge_data = true){

        if($this->_rendered_views !== null)
            return false;

        $this->_rendered_views = '';

        foreach($this->_views as $view) {

            $view->addHelper($this->_helpers);

            $view->registerMethodHandler($this->_methodHandler);

            $view->extend($this->_data);

            $this->_rendered_views .= $view->render();

            if($merge_data)
                $this->extend($view->getData());

        }

        return true;

    }

    public function setImportPriority($priority) {

        $this->_priority = $priority;

    }

    public function import() {

        $out = '';

        $local = (string) $this->application->url();

        if (count($this->_links) > 0) {

            if ($this->_requires_param) {

                foreach ($this->_links as $priority => & $req) {

                    foreach ($req as $r) {

                        $uri = new \Hazaar\Http\Uri($r->parameters()->get('href'));

                        if (substr((string) $uri, 0, strlen($local)) != $local)
                            continue;

                        $uri->setParams($this->_requires_param);

                        $r->parameters()->set('href', $uri);

                    }

                }

            }

            krsort($this->_links);

            foreach ($this->_links as $link)
                $out .= implode("\n", $link) . "\n";

            $out .= "\n";

        }

        return $out;

    }

    public function post() {

        $out = '';

        if (count($this->_requires) > 0) {

            if ($this->_requires_param) {

                $local = (string) $this->application->url();

                foreach ($this->_requires as &$req) {

                    foreach ($req as $r) {

                        $uri = new \Hazaar\Http\Uri($r->parameters()->get('src'));

                        if (substr((string) $uri, 0, strlen($local)) != $local)
                            continue;

                        $uri->setParams($this->_requires_param);

                        $r->parameters()->set('src', $uri);
                    }
                }
            }

            krsort($this->_requires);

            foreach ($this->_requires as &$req)
                $out .= implode("\n", $req) . "\n";

            $out .= "\n";

        }

        foreach ($this->_postItems as $item) {

            if ($item instanceof \Hazaar\View)
                $out .= $item->render();

            elseif ($item instanceof \Hazaar\Html\Script)
                $out .= $item->renderObject();

        }

        $scripts = $this->_scripts;

        if(is_array($this->_views)){

            foreach($this->_views as $view)
                $scripts += $view->_scripts;

        }

        if (count($scripts) > 0)
            $out .= implode("\n", $scripts) . "\n";

        foreach ($this->_helpers as $helper) {

            if (method_exists($helper, 'post'))
                $out .= $helper->post();

        }

        return $out;

    }

    public function addPost($item) {

        $this->_postItems[] = $item;

    }

    /**
     * Render the layout with an optional prepare method
     */
    public function render() {

        if($this->application->config->view['prepare'] === true)
            $this->prepare();

        return parent::render();

    }

    public function requires($script, $charset = null, $cache_local = null) {

        if (is_array($script)) {

            foreach ($script as $s) $this->requires($s, $charset, $cache_local);

            return;

        }

        if (!$script instanceof \Hazaar\Html\Script) {

            if(is_string($script) && preg_match('/^(\w+):\/\/(.+)$/', $script, $matches)){

                if($cache_local === true || ($cache_local === null && $this->application->config->view->cache === true))
                    $script = $this->application->url('hazaar', 'view/js/' . $matches[1] . '/' . $matches[2])->encode();

            }elseif(!$script instanceof \Hazaar\Application\Url){

                if($this->_methodHandler instanceof \Hazaar\Controller && $this->_methodHandler->base_path)
                    $script = $this->_methodHandler->base_path . '/' . $this->_methodHandler->getName() . '/file/' . $script;
                elseif(is_string($script) && $script[0] !== '/')
                    $script = 'script/' . $script;

                $script = $this->application->url($script);

            }

            $script = (new \Hazaar\Html\Script())->src($script);

            if ($charset)
                $script->charset($charset);

        }

        $this->_requires[$this->_priority][] = $script;

    }

    public function link($href, $rel = null, $cache_local = null) {

        if (! $rel) {

            $info = pathinfo($href);

            if ((array_key_exists('extension', $info) && ( $info['extension'] == 'css' || $info['extension'] == 'less')) || ! array_key_exists('extension', $info)) {

                $rel = 'stylesheet';

            } elseif ($info['filename'] == 'favicon') {

                $rel = 'shortcut icon';

            }

        }

        $link = (new \Hazaar\Html\Inline('link'))->rel($rel);

        if(is_string($href) && preg_match('/^(\w+):\/\/(.+)$/', $href, $matches)){

            if($cache_local === true || ($cache_local === null && $this->application->config->view->cache === true))
                $href = $this->application->url('hazaar', 'view/css/' . $matches[1] . '/' . $matches[2])->encode();

        }elseif(!$href instanceof \Hazaar\Application\Url){

            switch ($rel) {
                case 'stylesheet':

                    if($this->_methodHandler instanceof \Hazaar\Controller
                    && $this->_methodHandler->base_path)
                        $href = $this->_methodHandler->base_path . '/'
                            . $this->_methodHandler->getName()
                            . '/file/' . $href;
                    else
                        $href = 'style/' . $href;

                    $href = $this->application->url($href);

                    break;

                case 'shortcut icon':

                    $link->id('favicon');

                    $href = $this->application->url($href);

                    break;

            }

        }

        $link->href($href);

        $this->_links[$this->_priority][] = $link;

        return $link;

    }

    /**
     * Render the views contained in this layout view
     *
     * @return string
     */
    public function layout() {

        $output = $this->_content;

        if($this->_rendered_views === null)
            $this->prepare(false); //Prepare the views now, but don't bother merging data back in

        $output .= $this->_rendered_views;

        return $output;

    }

    /**
     * Add a view to the layout
     *
     * This method will add a view based on the supplied argument.  If the argument is a string a new Hazaar\View object
     * is created using the view file named in the argument.  Alterntively, the argument can be a Hazaar\View object
     * which will simply then be added to the layout.
     *
     * @param $view mixed A string naming the view to load, or an existing Hazaar_View object.
     *
     * @param $key string Optional key to store the view as.  Allows direct referencing later.
     *
     * @return \Hazaar\View
     */
    public function add($view, $key = null) {

        if(! $view instanceof \Hazaar\View)
            $view = new \Hazaar\View($view, FALSE);

        if($key)
            $this->_views[$key] = $view;

        else
            $this->_views[] = $view;

        return $view;

    }

    public function remove($key) {

        if(array_key_exists($key, $this->_views))
            unset($this->_views[$key]);

    }

    /*
     * Array Access
     */

    public function offsetExists($offset) {

        return array_key_exists($offset, $this->_views);

    }

    public function offsetGet($offset) {

        return $this->_views[$offset];

    }

    public function offsetSet($offset, $value) {

        $this->add($value, $offset);

    }

    public function offsetUnset($offset) {

        $this->remove($offset);

    }

    /*
     * Iterator
     */
    public function current() {

        return current($this->_views);

    }

    public function key() {

        return key($this->_views);

    }

    public function next() {

        return next($this->_views);

    }

    public function rewind() {

        return rewind($this->_views);

    }

    public function valid() {

        return current($this->_views);

    }

    public function lib($type, $request){

        $app_url = (string)\Hazaar\Application::getInstance()->url();

        if(!substr($request->referer(), 0, strlen($app_url)) == $app_url)
            throw new \Exception('You are not allowed to access this resource!', 403);

        $this->application->config->app['compress'] = false;

        $this->cacheDir = new \Hazaar\File\Dir($this->application->runtimePath('viewcache/' . $type, true));

        $path = $request->getRawPath();

        $params = $request->getParams();

        $file = $this->cacheDir->get($path . ((count($params) > 0) ? '_' . md5(array_flatten($params)) : null));

        if(!$file->exists()){

            $parent = $file->parent();

            if(!$parent->exists())
                $parent->create(true);

            $pos = strpos($path, '/');

            $url = substr($path, 0, $pos) . '://' . substr($path, $pos + 1);

            if(count($params) > 0)
                $url .= '?' . http_build_query($params);

            if(!($content = @file_get_contents($url)))
                throw new \Exception('Unable to cache from source ' . $url, 502);

            $headers = array();

            foreach($http_response_header as $idx => $hdr){

                if($idx === 0)
                    continue;

                $pos = strpos($hdr, ':');

                $headers[strtolower(substr($hdr, 0, $pos))] = trim(substr($hdr, $pos + 2));


            }

            $file->set_meta($headers);

            $file->set_contents($content);

            $file->save();

        }

        $response = new \Hazaar\Controller\Response\Javascript($file);

        $response->setUnmodified($request->getHeader('If-Modified-Since'));

        if($meta = $file->get_meta())
            $response->setContentType(ake($meta, 'content-type'));

        return $response;

    }

}