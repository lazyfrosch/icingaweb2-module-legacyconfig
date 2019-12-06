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
}
