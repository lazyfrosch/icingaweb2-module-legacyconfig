<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbols

/* NETWAYS modules for Icingaweb2 - Copyright (c) 2019 NETWAYS GmbH <info@netways.de> */

/** @var \Icinga\Application\Modules\Module $this */

/** @var \Icinga\Application\Modules\MenuItemContainer $section */
$section = $this->menuSection('legacyconfig')
    ->setLabel('Legacy Config')
    ->setUrl('legacyconfig/overview')
    ->setIcon('sitemap')
    ->setPriority(90);

$section->add('overview', array(
    'url'      => 'legacyconfig/overview',
    'label'    => $this->translate('Overview'),
    'priority' => 10,
));

$section->add('hosts', array(
    'url'      => 'legacyconfig/overview/hosts',
    'label'    => $this->translate('Hosts'),
    'priority' => 20,
));

$this->provideConfigTab('config', array(
    'title' => $this->translate('Configure Module'),
    'label' => $this->translate('Config'),
    'url'   => 'config'
));
