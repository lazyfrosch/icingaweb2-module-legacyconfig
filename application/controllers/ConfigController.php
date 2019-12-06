<?php
/* NETWAYS modules for Icingaweb2 - Copyright (c) 2019 NETWAYS GmbH <info@netways.de> */

namespace Icinga\Module\Legacyconfig\Controllers;

use Icinga\Module\Legacyconfig\Forms\ConfigForm;
use Icinga\Web\Controller;

class ConfigController extends Controller
{
    public function init()
    {
        $this->assertPermission('config/modules');
        parent::init();
    }

    public function indexAction()
    {
        $form = new ConfigForm();
        $form
            ->setIniConfig($this->Config())
            ->handleRequest();
        $this->view->form = $form;

        $this->view->title = $this->translate('Config');
        $this->view->tabs = $this->Module()->getConfigTabs()->activate('config');
    }
}
