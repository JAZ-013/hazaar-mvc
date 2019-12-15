<?php

namespace Hazaar\Template\Smarty;

class Modifier {

    static private $modifiers = array(
        'capitalize',
        'cat',
        'count_characters',
        'count_paragraphs',
        'count_sentences',
        'count_words',
        'date_format',
        'default',
        'escape',
        'indent',
        'lower',
        'nl2br',
        'number_format',
        'regex_replace',
        'replace',
        'spacify',
        'string_format',
        'strip',
        'strip_tags',
        'truncate',
        'upper',
        'wordwrap',

        //Custom Hazaar MVC Modifiers
        'implode'
    );

    static public function has_function($func){

        return in_array($func, Modifier::$modifiers);

    }

    public function capitalize($string){

        return ucwords($string);

    }

    public function cat(){

        return implode('', func_get_args());

    }

    public function count_characters($string, $include_whitespace = false){

        if($include_whitespace === false)
            $string = str_replace(' ', '', $string);

        return strlen($string);

    }

    public function count_paragraphs($string){

        return substr_count(trim($string, "\n\n"), "\n\n") + 1;

    }

    public function count_sentences($string){

        if(!preg_match_all('/[\.\!\?]+[\s\n]?/', $string, $matches))
            return strlen($string) > 0;

        $count = count($matches[0]);

        $final = substr(trim($string), -1);

        if($final != '.' && $final != '!' && $final != '?')
            $count++;

        return $count;

    }

    public function count_words($string){

        return str_word_count($string);

    }

    public function date_format($item, $format){

        if(!$item instanceof \Hazaar\Date)
            $item = new \Hazaar\Date($item);

        return strftime($format, $item->getTimestamp());

    }

    public function default($value, $default = null){

        if($value) return $value;

        return $default;

    }

    public function escape($string, $format = 'html', $character_encoding = 'ISO-8859-1'){

        if($format == 'html')
            $string = htmlspecialchars($string, ENT_COMPAT, $character_encoding);
        elseif($format = 'url')
            $string = urlencode($string);
        elseif($format = 'quotes')
            $string = addcslashes($string, "'");

        return $string;

    }

    public function indent($string, $length = 4, $pad_string = ' '){

        return str_pad($string, $length, $pad_string, STR_PAD_LEFT);

    }

    public function lower($string){

        return strtolower($string);

    }

    public function nl2br($string){

        return str_replace("\n", '<br />', $string);

    }

    public function number_format($string, $decimals = 0, $dec_point = '.', $thousands_sep = ','){

        return number_format($string, $decimals, $dec_point, $thousands_sep);

    }

    public function regex_replace($string, $pattern = '//', $replacement = ''){

        return preg_replace($pattern, $replacement, $string);

    }

    public function replace($string, $search, $replace){

        return str_replace($search, $replace, $string);

    }

    public function spacify($string, $replacement = ' '){

        return implode($replacement, preg_split('//', $string));

    }

    public function string_format($string, $format){

        return sprintf($format, $string);

    }

    public function strip($string, $replacement = ' '){

        return preg_replace('/[\s\n\t]+/', $replacement, $string);

    }

    public function strip_tags($string){

        return preg_replace('/<[^>]+>/', '', $string);

    }

    public function truncate($string, $chars = 80, $text = '...', $cut = false, $middle = false){

        if(strlen($string) > ($chars -= strlen($text))) {

            if($middle === true){

                $string = substr($string, 0, $chars / 2) . $text . substr($string, -($chars / 2));

            }else{

                $string = substr($string, 0, $chars);

                if($cut === false)
                    $string = substr($string, 0, strrpos($string ,' '));

                $string = $string . $text;

            }

        }

        return $string;

    }

    public function upper($string){

        return strtoupper($string);

    }

    public function wordwrap($string, $width = 80, $break = "\n", $cut = true){

        return wordwrap($string, $width, $break, $cut);

    }

    public function implode($array, $glue = ''){

        if(is_array($array))
            return implode($glue, $array);

        return $array;

    }

}
