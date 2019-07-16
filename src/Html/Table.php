<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML table class.
 *
 * @detail      Displays an HTML &lt;table&gt; element.
 *
 * @since       1.1
 */
class Table extends Block {

    private $thead;

    private $tbody;

    private $tfoot;

    /**
     * @detail      The HTML table constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($rows = null, $parameters = array()) {

        parent::__construct('table', null, $parameters);

        if($rows)
            $this->addRows($rows);

    }

    /**
     * @detail      Add multiple headers at a time.  Takes an array of row arrays.
     *
     * @since       1.2
     *
     * @param       array $rows An array of row array data
     */
    public function addHeaders($rows) {

        if(is_array($rows)) {

            foreach($rows as $row) {

                $this->addHeader($row);

            }

        }

        return $this;

    }

    /**
     * @detail      Add a header to the table.  If a header was previously set, it will be overwritten as only a single
     *              header is allowed.
     *
     * @since       1.2
     *
     * @param       array $fields An array of fields to add to the row.  This can be any element.  If an element is a
     *              \Hazaar\Html\Th object then it will be added as-is.
     */
    public function addHeader($fields) {

        if(is_array($fields)) {

            if(!$this->thead instanceof Thead)
                parent::prepend($this->thead = new Thead());

            $this->thead->setRow($fields);

        }

        return $this;

    }

    /**
     * @detail      Add multiple rows at a time.  Takes an array of row arrays.
     *
     * @since       1.2
     *
     * @param       array $rows An array of row array data
     */
    public function addRows($rows) {

        if($rows instanceof Thead){

            parent::add($this->thead = $rows);

        }elseif($rows instanceof Tbody){

            parent::add($this->tbody = $rows);

        }elseif(is_array($rows)) {

            foreach($rows as $row)
                $this->addRow($row);

        }

        return $this;

    }

    /**
     * @detail      Add a row to the table.
     *
     * @since       1.2
     *
     * @param       array $fields An array of fields to add to the row.  This can be any element.  If an element is a
     *              \Hazaar\Html\Th object then it will be added as-is.
     */
    public function addRow($fields) {

        if(is_array($fields) || $fields instanceof Tr) {

            if(!$this->tbody instanceof Tbody)
                parent::add($this->tbody = new Tbody());

            $this->tbody->addRow($fields);

        }

        return $this;

    }

    /**
     * @detail      Add multiple headers at a time.  Takes an array of row arrays.
     *
     * @since       1.2
     *
     * @param       array $rows An array of row array data
     */
    public function addFooters($rows) {

        if(is_array($rows)) {

            foreach($rows as $row) {

                $this->addFooter($row);

            }

        }

        return $this;

    }

    /**
     * @detail      Add a header to the table.  If a header was previously set, it will be overwritten as only a single
     *              header is allowed.
     *
     * @since       1.2
     *
     * @param       array $fields An array of fields to add to the row.  This can be any element.  If an element is a
     *              \Hazaar\Html\Th object then it will be added as-is.
     */
    public function addFooter($fields) {

        if(is_array($fields)) {

            if(!$this->tfoot instanceof Tfoot)
                parent::add($this->tfoot = new Tfoot());

            $this->tfoot->setRow($fields);

        }

        return $this;

    }


}
