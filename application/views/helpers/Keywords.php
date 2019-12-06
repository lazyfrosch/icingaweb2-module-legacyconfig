<?php

class Zend_View_Helper_Keywords extends Zend_View_Helper_Abstract
{
    public function keywords($string)
    {
        $string = preg_replace('~_+~', ' ', $string);
        return ucwords($string);
    }
}
