<?php
/* LegacyConfig module for Icingaweb2 - Copyright (c) 2019 NETWAYS GmbH <info@netways.de> */

namespace Icinga\Module\Legacyconfig\ProvidedHook\Director;

use Icinga\Application\Icinga;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Legacyconfig\Legacy\LegacyConfigParser;

class ImportSourceIcingaLegacy extends ImportSourceHook
{
    protected $objects;

    /** @var LegacyConfigParser */
    protected $parser;

    public static function addSettingsFormFields(QuickForm $form)
    {
        $types = static::getTypes();

        $form->addElement('select', 'object_type', [
            'label'        => $form->translate('Object Type'),
            'description'  => ($form->translate('Choose from the available types: ') . join(', ', $types)),
            'required'     => true,
            'multiOptions' => $form->optionalEnum($types)

        ]);

        return $form;
    }

    public static function getTypes()
    {
        return [
            'command'           => 'Commands',
            'host'              => 'Hosts',
            'hostgroup'         => 'Hostgroups',
            'service'           => 'Services',
            'servicegroup'      => 'Servicegroups',
            'servicedependency' => 'Servicedependency',
            'contact'           => 'Contacts',
            'contactgroup'      => 'Contactgroups',
            'timeperiod'        => 'Timeperiods',
        ];
    }

    /**
     * @return \Icinga\Application\Config
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function Config()
    {
        $module = Icinga::app()->getModuleManager()->getModule('legacyconfig');
        return $module->getConfig();
    }

    protected function loadData()
    {
        if ($this->objects === null) {
            $path = $this->Config()->get('base', 'config_path');
            $type = $this->getSetting('object_type');

            $this->parser = new LegacyConfigParser();
            $this->parser->parseFileTree($path);

            $this->objects = $this->parser->getObjects($type);
        }

        return $this->objects;
    }

    public function getName()
    {
        return mt('legacyconfig', 'Legacy Config Objects');
    }

    /**
     * Returns an array containing importable objects
     *
     * @return array
     */
    public function fetchData()
    {
        return $this->loadData();
    }

    /**
     * Returns a list of all available columns
     *
     * @return array
     */
    public function listColumns()
    {
        $columns = [];

        foreach ($this->loadData() as $object) {
            foreach ($object as $k => $v) {
                $columns[$k] = 1;
            }
        }

        return array_keys($columns);
    }
}
