<?php

namespace Hazaar\Html;

/**
 * @brief      This class is for rendering PDFs as HTML.
 * 
 * @since       1.2
 */
class PDF {

    private $source_file;

    private $page_top = 0;

    function __construct($source) {

        if(!$this->setSource($source)) {

            throw new \Hazaar\Exception("File not found while trying to render PDF '$source'", 404);

        }

    }

    public function setSource($source) {

        if(!file_exists($source)) {

            return false;

        }

        $this->source_file = realpath($source);

        return true;

    }

    public function __tostring() {

        return (string)$this->render();

    }

    public function render() {

        if(!($cmd = \Hazaar\Loader::getFilePath(FILE_PATH_SUPPORT, 'pdftohtml'))) {

            $cmd = 'pdftohtml';

        }

        if(!$xml = shell_exec($cmd . ' -xml -stdout -hidden -nomerge ' . $this->source_file . ' /tmp/' . uniqid())) {

            return 'Conversion of PDF to HTML failed for file ' . $this->source_file . '.  Is pdftohtml installed?';

        }

        $pdf = new \SimpleXMLElement($xml);

        $this->page_top = 0;

        $fonts = array();

        $document = new Block('div', null, array('class' => 'document'));

        $style = new Style();

        $style->select('span.text')->set(array(
            'position' => 'absolute',
            'white-space' => 'nowrap'
        ));

        $style->select('div.image')->set(array(
            'position' => 'absolute',
            'background-color' => 'transparent',
            'background-repeat' => 'no-repeat',
            'background-size' => '100%'
        ));

        $document->add($style);

        foreach($pdf->page as $page) {

            $document->add($this->renderPage($page, $fonts));

        }

        return $document;

    }

    private function renderPage($xml, &$fontspec = null) {

        /*
         * Update the fontspec with any new fonts
         */
        if(array_key_exists('fontspec', $xml)) {

            foreach($xml->fontspec as $font) {

                $fonts[(int)$font['id']] = $font;

            }

        }

        /*
         * Set up the page output style
         */
        $page_style = new Style();

        $page_style->set(array(
            'position' => $xml['position'],
            'top' => $this->page_top . 'px',
            'width' => $xml['width'] . 'px',
            'height' => $xml['height'] . 'px'
        ));

        $this->page_top += $xml['height'];

        $page = new Block('div', null, array(
            'class' => 'page',
            'style' => $page_style
        ));

        foreach($xml as $element) {

            $page->add($this->renderElement($element, $fontspec));

        }

        return $page;

    }

    private function renderElement($xml, &$fontspec = null) {

        $element = '';

        $style = new Style();

        $style->set('top', $xml['top'] . 'px');

        $style->set('left', $xml['left'] . 'px');

        $style->set('width', $xml['width'] . 'px');

        if($fontspec && $xml['font']) {

            $font = $fontspec[(int)$xml['font']];

            $style->set('font', $font['size'] . 'px \'' . $font['family'] . '\', sans-serif ');

            $style->set('color', (string)$font['color']);

        }

        $elem_type = $xml->getName();

        switch($elem_type) {

            case 'image' :
                $style->set('height', $xml['height'] . 'px');

                $content = new \Hazaar\File($xml['src']);

                $style->set('background-image', 'url(data:' . $content->getMimeType() . ';base64,' . $content->asBase64() . ')');

                $element = new Block('div', null, array(
                    'class' => 'image',
                    'style' => $style
                ));

                break;

            case 'text' :
                $style->set('line-height', $xml['height'] . 'px');

                $attr = array(
                    'class' => 'text',
                    'style' => $style
                );

                if($xml->count() == 0) {

                    $element = new Block('span', (string)$xml, $attr);

                } else {

                    $element = new Block('span', null, $attr);

                    foreach($xml->children() as $child) {

                        $element->add($child->asXML());

                    }

                }

                break;

            case 'fontspec' :
                $fontspec[(int)$xml['id']] = $xml;

                break;
        }

        return $element;

    }

}
