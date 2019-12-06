<?php
/* NETWAYS modules for Icingaweb2 - Copyright (c) 2019 NETWAYS GmbH <info@netways.de> */

namespace Icinga\Module\Legacyconfig\Web;

use Icinga\Web\Controller as IcingaController;

class Controller extends IcingaController
{
    protected function setViewScript($name, $noController = false)
    {
        $this->_helper->viewRenderer->setNoController($noController);
        $this->_helper->viewRenderer->setScriptAction($name);
    }
}
