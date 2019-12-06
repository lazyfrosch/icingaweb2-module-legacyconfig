<?php
/* NETWAYS modules for Icingaweb2 - Copyright (c) 2019 NETWAYS GmbH <info@netways.de> */

namespace Icinga\Module\Legacyconfig\Legacy;

use http\Exception\InvalidArgumentException;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Exception\NotFoundError;
use stdClass;

class LegacyConfigParser
{
    protected $files = [];

    protected $objects = [];

    protected $templates = [];

    protected $used = [];

    protected $templateUsage = [];

    public function parseFile($path)
    {
        $file = realpath($path);

        if (array_key_exists($file, $this->files)) {
            throw new InvalidArgumentException('File was already parsed: %s', $file);
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new NotFoundError('Could not read file contents: %s', $file);
        }

        $this->parseFileContent($content, $file);

        $this->files[$file] = 1;

        return $this;
    }

    public function parseFileTree($path)
    {
        $dh = @opendir($path);

        if ($dh === false) {
            throw new NotFoundError('Could not open directory for reading: %s', $path);
        }

        while ($file = readdir($dh)) {
            if (substr($file, 0, 1) === '.') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullPath)) {
                $this->parseFileTree($fullPath);
            } else {
                if (preg_match('~\.cfg$~', $file)) {
                    $this->parseFile($fullPath);
                }
            }
        }

        closedir($dh);

        return $this;
    }

    protected function parseFileContent($content, $file)
    {
        $lines = preg_split('~\r?\n~', $content);

        $inBlock = null;
        /** @var object|null $attr */
        $attr = null;
        $lineno = 0;

        foreach ($lines as $line) {
            $lineno++;

            if (preg_match('~^\s*$~', $line)) {
                // empty line
                continue;
            } elseif (preg_match('~^\s*[#;]~', $line)) {
                // comment only
                continue;
            }

            if ($inBlock !== null) {
                if (preg_match('~^\s*(\S+)\s+(.+)$~', $line, $m)) {
                    // key value
                    $value = trim($m[2]);
                    $value = preg_replace('~\s[#;].*~', '', $value);

                    $key = $m[1];
                    $value = trim($value);

                    if ($inBlock === 'timeperiod') {
                        // special handling for timeperiod to collect ranges
                        if (
                            substr($key, 0, 10) !== 'timeperiod'
                            && ! in_array($key, ['name', 'alias', 'use', 'exclude', 'include', 'register'])
                        ) {
                            if (! property_exists($attr, 'ranges')) {
                                $attr->ranges = [];
                            }
                            $attr->ranges[] = $key . ' ' . $value;
                        } else {
                            $attr->{$key} = $value;
                        }
                    } else {
                        $attr->{$key} = $value;
                    }
                } elseif (preg_match('~^\s*\}\s*$~', $line)) {
                    // end of block
                    $this->storeObject($inBlock, $attr);

                    $inBlock = null;
                    $attr = new stdClass;
                } else {
                    throw new InvalidPropertyException('Unexpected line at %d in %s: %s', $lineno, $file, $line);
                }
            } else {
                // outside of block
                if (preg_match('~^\s*define\s+(\w+)\s*(\{\s*)?$~', $line, $m)) {
                    // start of block
                    $inBlock = $m[1];
                    $attr = new stdClass;
                } else {
                    throw new InvalidPropertyException('Unexpected line at %d in %s: %s', $lineno, $file, $line);
                }
            }
        }
    }

    public static function splitList($string)
    {
        return preg_split('~\s*,\s*~', trim($string));
    }

    public static function splitCommand($string)
    {
        return preg_split('~\s*!\s*~', trim($string));
    }

    protected function storeObject($type, $attr)
    {
        // rebuild vars to an object
        $vars = [];
        foreach ($attr as $k => $v) {
            if (substr($k, 0, 1) === '_') {
                $vars[substr($k, 1)] = $v;
                unset($attr->{$k});
            }
        }
        if (! empty($vars)) {
            $attr->vars = (object) $vars;
        }

        // check for object referenced to be marked used
        $this->detectUsage($type, $attr);

        // Exceptions to detect templates based on attributes been set
        $nameAttr = null;
        switch ($type) {
            case 'host':
            case 'contact':
                $nameAttr = $type . '_name';
                break;
            case 'service':
                $nameAttr = 'service_description';
                break;
        }

        if (
            property_exists($attr, 'register') && $attr->register === '0'
            || ! empty ($nameAttr) && ! property_exists($attr, $nameAttr) // fallback that doesn't require register to be set
        ) {
            if (! array_key_exists($type, $this->templates)) {
                $this->templates[$type] = [];
            }

            if (! property_exists($attr, 'name') && ! empty($nameAttr) && property_exists($attr, $nameAttr)) {
                $attr->name = $attr->{$nameAttr};
            }

            $this->templates[$type][] = $attr;
        } else {
            if (! array_key_exists($type, $this->objects)) {
                $this->objects[$type] = [];
            }

            $this->objects[$type][] = $attr;
        }
    }

    protected function detectUsage($type, &$attr)
    {
        $nameAttr = $type . '_name';

        foreach ($attr as $k => $v) {
            switch ($k) {
                case 'use':
                    $this->markUsed($type, $v);

                    // Count which templates are used by what object
                    // Only for contacts currently
                    if ($type === 'contact') {
                        if (property_exists($attr, $nameAttr)) {
                            $this->markTemplateUsed($type, $v, $attr->$nameAttr);
                        } elseif (property_exists($attr, 'name')) {
                            $this->markTemplateUsed($type, $v, $attr->name);
                        }
                    }
                    break;
                case 'check_command':
                case 'event_handler':
                    $command = static::splitCommand($v);
                    $this->markUsed('command', $command[0]);
                    break;
                case 'host_notification_commands':
                case 'service_notification_commands':
                    foreach (static::splitList($v) as $command) {
                        $this->markUsed('command', $command);
                    }
                    break;
                case 'check_period':
                case 'notification_period':
                case 'host_notification_period':
                case 'service_notification_period':
                    $this->markUsed('timeperiod', $v);
                    break;
                case 'hostgroups':
                    foreach (static::splitList($v) as $group) {
                        $this->markUsed('hostgroup', $group);
                    }
                    break;
                case 'servicegroups':
                    foreach (static::splitList($v) as $group) {
                        $this->markUsed('servicegroup', $group);
                    }
                    break;
                case 'contact_groups':
                    foreach (static::splitList($v) as $group) {
                        $this->markUsed('contactgroup', $group);
                    }
                    break;
            }
        }

        if ($type === 'contactgroup') {
            if (property_exists($attr, 'members') && ! empty($attr->members)) {
                $memberType = substr($type, 0, -5);

                foreach (static::splitList($attr->members) as $member) {
                    $this->markUsed($memberType, $member);
                }
            }
        } elseif ($type === 'hostgroup' || $type === 'servicegroup') {
            if (property_exists($attr, 'members') && ! empty($attr->members)) {
                $this->markUsed($type, $attr->{$nameAttr});
            }
        }

        if ($type === 'hostgroup' || $type === 'servicegroup' || $type === 'contactgroup') {
            $groupMemberAttr = $type . '_members';
            if (property_exists($attr, $groupMemberAttr) && ! empty($attr->$groupMemberAttr)) {
                if ($type !== 'contactgroup') {
                    $this->markUsed($type, $attr->$nameAttr);
                }

                foreach (static::splitList($attr->{$groupMemberAttr}) as $member) {
                    $this->markUsed($type, $member);
                }
            }
        }
    }

    public function getFiles()
    {
        return array_keys($this->files);
    }

    public function getObjects($type = null)
    {
        if ($type !== null) {
            if (array_key_exists($type, $this->objects)) {
                return $this->objects[$type];
            } else {
                return [];
            }
        } else {
            return $this->objects;
        }
    }

    public function getTemplates($type = null)
    {
        if ($type !== null) {
            if (array_key_exists($type, $this->templates)) {
                return $this->templates[$type];
            } else {
                return [];
            }
        } else {
            return $this->templates;
        }
    }

    protected function markUsed($type, $name)
    {
        if (! array_key_exists($type, $this->used)) {
            $this->used[$type] = [];
        }

        $this->used[$type][$name] = $name;
        return $this;
    }

    public function getUsed($type = null)
    {
        if ($type !== null) {
            if (array_key_exists($type, $this->used)) {
                return $this->used[$type];
            } else {
                return [];
            }
        } else {
            return $this->used;
        }
    }

    protected function markTemplateUsed($type, $template, $name)
    {
        if (! array_key_exists($type, $this->templateUsage)) {
            $this->templateUsage[$type] = [];
        }
        if (! array_key_exists($template, $this->templateUsage[$type])) {
            $this->templateUsage[$type][$template] = [];
        }

        $this->templateUsage[$type][$template][$name] = $name;

        return $this;
    }

    public function getTemplateUsage($type, $template = null)
    {
        if (! array_key_exists($type, $this->templateUsage)) {
            return [];
        }

        if ($template !== null) {
            if (array_key_exists($template, $this->templateUsage[$type])) {
                return $this->templateUsage[$type][$template];
            } else {
                return [];
            }
        } else {
            return $this->templateUsage[$type];
        }
    }
}
