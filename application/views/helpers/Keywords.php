<?php
/* NETWAYS modules for Icingaweb2 - Copyright (c) 2019 NETWAYS GmbH <info@netways.de> */

class Zend_View_Helper_Keywords extends Zend_View_Helper_Abstract
{
    public function keywords($string)
    {
        $string = preg_replace('~_+~', ' ', $string);
        return ucwords($string);
    }
}
