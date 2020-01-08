<?php
/* LegacyConfig module for Icingaweb2 - Copyright (c) 2019 NETWAYS GmbH <info@netways.de> */

/** @var \Icinga\Application\Modules\Module $this */

$prefix = '\\Icinga\\Module\\Legacyconfig\\';

$this->provideHook('director/ImportSource', $prefix . 'ProvidedHook\\Director\\ImportSourceIcingaLegacy');
