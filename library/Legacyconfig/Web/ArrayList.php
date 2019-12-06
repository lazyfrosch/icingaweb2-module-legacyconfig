<?php

namespace Icinga\Module\Legacyconfig\Web;

use ipl\Html\Html;

class ArrayList extends Html
{
    protected $array;

    public function __construct($array)
    {
        $this->array = $array;
    }

    protected function renderArray($array)
    {
        $ul = static::tag('ul');

        foreach($array as $key => $value) {
            if (is_array($value)) {
                $ul->add($this->renderArray($value));
            } else {
                $content = $value;
                if (is_string($key)) {
                    $content = $key . ': ' . $content;
                }
                $li = static::tag('li', $content);
                $ul->add($li);
            }
        }

        var_export($ul);
        return $ul;
    }

    public function __toString()
    {
        return (string) $this->renderArray($this->array);
    }
}
