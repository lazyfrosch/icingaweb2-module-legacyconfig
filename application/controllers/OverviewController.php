<?php
/* NETWAYS modules for Icingaweb2 - Copyright (c) 2019 NETWAYS GmbH <info@netways.de> */

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

        $this->view->counts = $counts;
        $this->view->files = $files;
    }

    protected function handleObjectList($type, $keyColumn = null, $usage = true)
    {
        $parser = $this->parser();

        $objects = $parser->getObjects($type);
        $templates = $parser->getTemplates($type);
        $used = $parser->getUsed($type);

        if ($keyColumn === null) {
            $keyColumn = $type . '_name';
        }

        $this->view->objects = $objectTable = new LegacyObjectTable($objects, $keyColumn);
        if (! empty($templates)) {
            $this->view->templates = $templateTable = new LegacyObjectTable($templates);
            $templateTable->setObjectsUsed($used);
        }

        if ($usage) {
            $objectTable->setObjectsUsed($used);
        }

        $this->view->title = 'Legacy ' . ucfirst($type);

        $this->setViewScript('objects');
        return $parser;
    }

    public function commandsAction()
    {
        $this->handleObjectList('command');

        $this->view->objects->setEllipsesTable(false);
    }

    public function hostsAction()
    {
        $this->handleObjectList('host', null, false);
    }

    public function hostgroupsAction()
    {
        $this->handleObjectList('hostgroup');
    }

    public function servicesAction()
    {
        $this->handleObjectList('service', 'service_description', false);

        $this->view->objects->setPrioritiesColumns(['host_name', 'hostgroup_name']);
    }

    public function servicegroupsAction()
    {
        $this->handleObjectList('servicegroup');
    }

    public function servicedependencysAction()
    {
        $this->handleObjectList('servicedependency', 'service_description', false);

        $this->view->objects->setPrioritiesColumns(['host_name', 'hostgroup_name']);
    }

    public function contactsAction()
    {
        $parser = $this->handleObjectList('contact');

        $templateUsage = $parser->getTemplateUsage('contact');
        $templates = $parser->getTemplates('contact');
        $used = $parser->getUsed('contact');

        /** @var LegacyObjectTable $templateTable */
        $templateTable = $this->view->templates;

        // Detect templates where all objects are unused
        // This is specific for contacts only
        foreach ($templates as $template) {
            $name = $template->name;
            if (! array_key_exists($name, $templateUsage)) {
                $templateTable->unmarkObjectUsed($name);
            } else {
                $isUsed = false;
                foreach ($templateUsage[$name] as $usage) {
                    if (array_key_exists($usage, $used)) {
                        $isUsed = true;
                        break;
                    }
                }
                if ($isUsed === false) {
                    $templateTable->unmarkObjectUsed($name);
                }
            }
        }
    }

    public function contactgroupsAction()
    {
        $this->handleObjectList('contactgroup');
    }

    public function timeperiodsAction()
    {
        $this->handleObjectList('timeperiod');
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
