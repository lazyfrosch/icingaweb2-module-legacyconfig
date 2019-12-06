<?php

namespace Icinga\Module\Legacyconfig\Controllers;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Legacyconfig\Legacy\LegacyConfigParser;
use Icinga\Module\Legacyconfig\Web\Controller;
use Icinga\Module\Legacyconfig\Web\Table\LegacyObjectTable;

class OverviewController extends Controller
{
    public function init()
    {
        $tabs = $this->getTabs();

        $tabs->add('index', [
            'title' => 'Index',
            'url'   => 'legacyconfig/overview',
        ]);

        $types = [
            'commands'           => 'Commands',
            'hosts'              => 'Hosts',
            'hostgroups'         => 'Hostgroups',
            'services'           => 'Services',
            'servicegroups'      => 'Servicegroups',
            'servicedependencys' => 'Servicedependency',
            'contacts'           => 'Contacts',
            'contactgroups'      => 'Contactgroups',
            'timeperiods'        => 'Timeperiods',
        ];

        foreach ($types as $id => $title) {
            $tabs->add($id, [
                'title' => $title,
                'url'   => 'legacyconfig/overview/' . $id,
            ]);
        }

        $action = $this->getRequest()->getActionName();
        if ($tabs->get($action)) {
            $tabs->activate($action);
        }
    }

    public function indexAction()
    {
        $parser = $this->parser();

        $files = $parser->getFiles();
        sort($files);
        $objects = $parser->getObjects();
        $templates = $parser->getTemplates();

        $counts = [];
        foreach (array_keys($objects) as $type) {
            $counts[$type] = [
                'objects' => count($objects[$type]),
            ];
            if (array_key_exists($type, $templates)) {
                $counts[$type]['templates'] = count($templates[$type]);
            }

            if ($type === 'service') {
                $toHosts = 0;
                $toHostgroups = 0;

                foreach ($objects['service'] as $object) {
                    if (property_exists($object, 'host_name')) {
                        $toHosts++;
                    }
                    if (property_exists($object, 'hostgroup_name')) {
                        $toHostgroups++;
                    }
                }

                $counts[$type]['to_hosts'] = $toHosts;
                $counts[$type]['to_hostgroups'] = $toHostgroups;
            }
        }

        $this->view->title = 'Legacy Overall';

        $this->view->counts = json_encode($counts, JSON_PRETTY_PRINT);
        $this->view->files = $files;
    }

    public function commandsAction()
    {
        $parser = $this->parser();

        $objects = $parser->getObjects('command');

        $this->view->title = 'Legacy Commands';

        $this->view->objects = new LegacyObjectTable($objects, 'command_name');

        // TODO: can be determine used commands here?

        $this->setViewScript('objects');
    }

    public function hostsAction()
    {
        $parser = $this->parser();

        $templates = $parser->getTemplates('host');
        $objects = $parser->getObjects('host');

        $this->view->title = 'Legacy Hosts';

        $this->view->templates = $templateTable = new LegacyObjectTable($templates);
        $this->view->objects = new LegacyObjectTable($objects, 'host_name');

        // Mark used templates in table
        // TODO: implement indexing into LegacyConfigParser
        foreach ([$templates, $objects] as $arr) {
            foreach ($arr as $object) {
                if (property_exists($object, 'use')) {
                    $templateTable->markObjectUsed($object->use);
                }
            }
        }

        $this->setViewScript('objects');
    }

    public function hostgroupsAction()
    {
        $parser = $this->parser();

        $objects = $parser->getObjects('hostgroup');

        $this->view->title = 'Legacy Hostgroups';

        $this->view->objects = new LegacyObjectTable($objects, 'hostgroup_name');

        // TODO: unused

        $this->setViewScript('objects');
    }

    public function servicesAction()
    {
        $parser = $this->parser();

        $templates = $parser->getTemplates('service');
        $objects = $parser->getObjects('service');

        $this->view->title = 'Legacy Services';

        $this->view->templates = $templateTable = new LegacyObjectTable($templates);
        // TODO: duplicate service_descriptions!
        $this->view->objects = $objectsTable = new LegacyObjectTable($objects, 'service_description');

        $objectsTable->setPrioritiesColumns(['host_name', 'hostgroup_name']);

        // TODO: implement unsed
        $this->setViewScript('objects');
    }

    public function servicegroupsAction()
    {
        $parser = $this->parser();

        $objects = $parser->getObjects('servicegroup');

        $this->view->title = 'Legacy Contactgroups';

        $this->view->objects = new LegacyObjectTable($objects, 'servicegroup_name');

        // TODO: unused

        $this->setViewScript('objects');
    }

    public function servicedependencysAction()
    {
        $parser = $this->parser();

        $objects = $parser->getObjects('servicedependency');

        $this->view->title = 'Legacy Servicedependency';

        $this->view->objects = $objectsTable = new LegacyObjectTable($objects, 'service_description');

        $objectsTable->setPrioritiesColumns(['host_name', 'hostgroup_name']);

        // TODO: unused

        $this->setViewScript('objects');
    }

    public function contactsAction()
    {
        $parser = $this->parser();

        $templates = $parser->getTemplates('contact');
        $objects = $parser->getObjects('contact');

        $this->view->title = 'Legacy Contacts';

        $this->view->templates = new LegacyObjectTable($templates);
        $this->view->objects = new LegacyObjectTable($objects, 'contact_name');

        // TODO: implement unsed
        $this->setViewScript('objects');
    }

    public function contactgroupsAction()
    {
        $parser = $this->parser();

        $objects = $parser->getObjects('contactgroup');

        $this->view->title = 'Legacy Contactgroups';

        $this->view->objects = new LegacyObjectTable($objects, 'contactgroup_name');

        // TODO: unused

        $this->setViewScript('objects');
    }

    public function timeperiodsAction()
    {
        $parser = $this->parser();

        $objects = $parser->getObjects('timeperiod');

        $this->view->title = 'Legacy Timeperiods';

        $this->view->objects = new LegacyObjectTable($objects, 'timeperiod_name');

        // TODO: unused

        $this->setViewScript('objects');
    }

    protected function parser()
    {
        $parser = new LegacyConfigParser();

        $config = $this->Config();
        $path = $config->get('base', 'config_path');

        if (! $path) {
            throw new InvalidPropertyException('Please configure the base config path for the module!');
        }

        $parser->parseFileTree($path);

        return $parser;
    }
}
