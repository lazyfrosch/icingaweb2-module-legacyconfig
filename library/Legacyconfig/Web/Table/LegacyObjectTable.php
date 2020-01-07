<?php
/* NETWAYS modules for Icingaweb2 - Copyright (c) 2019 NETWAYS GmbH <info@netways.de> */

namespace Icinga\Module\Legacyconfig\Web\Table;

use gipfl\IcingaWeb2\Table\SimpleQueryBasedTable;
use Icinga\Data\DataArray\ArrayDatasource;
use ipl\Html\Html;
use ipl\Html\Table;

class LegacyObjectTable extends SimpleQueryBasedTable
{
    /** @var \stdClass[] */
    protected $objects;

    protected $columns;

    /** @var ArrayDatasource */
    protected $ds;

    protected $keyColumn;

    protected $defaultAttributes = [
        'class'            => ['data-table'],
        'data-base-target' => '_next',
    ];

    protected $ignoredColumns = [
        'register',
    ];

    protected $protectVars = [
        'pass',
        'pw',
        'community',
    ];

    protected $prioritiesColumns;

    protected $objectsInUse;

    const IMPORT_ATTRIBUTE = 'use';

    protected $ellipsesTable = true;

    public function __construct($objects, $keyColumn = 'name')
    {
        $this->objects = $objects;
        $this->ds = new ArrayDatasource($objects);
        $this->keyColumn = $keyColumn;

        if ($this->keyColumn) {
            $this->ds->setKeyColumn($this->keyColumn);
        }
    }

    public function markObjectUsed($name)
    {
        if ($this->objectsInUse === null) {
            $this->objectsInUse = [];
        }

        $this->objectsInUse[$name] = $name;
        return $this;
    }

    public function unmarkObjectUsed($name)
    {
        if ($this->objectsInUse !== null) {
            if (array_key_exists($name, $this->objectsInUse)) {
                unset($this->objectsInUse[$name]);
            }
        }
        return $this;
    }

    public function setObjectsUsed($names)
    {
        $this->objectsInUse = $names;
        return $this;
    }

    public function getColumns()
    {
        if ($this->columns === null) {
            $this->ds->select()->getColumns();
            $columns = [
                $this->keyColumn => $this->keyColumn,
            ];

            $otherColumns = [];
            foreach ($this->objects as $object) {
                foreach (get_object_vars($object) as $k => $v) {
                    if (in_array($k, $this->ignoredColumns)) {
                        continue;
                    }
                    $otherColumns[$k] = $k;
                }
            }
            ksort($otherColumns);

            // pull specific attributes to the front
            foreach ([static::IMPORT_ATTRIBUTE] as $key) {
                if (array_key_exists($key, $otherColumns)) {
                    $columns[$key] = $key;
                    unset($otherColumns[$key]);
                }
            }

            if ($this->prioritiesColumns !== null) {
                foreach ($this->prioritiesColumns as $key) {
                    if (array_key_exists($key, $otherColumns)) {
                        $columns[$key] = $key;
                        unset($otherColumns[$key]);
                    }
                }
            }


            $this->columns = array_merge($columns, $otherColumns);
        }

        return $this->columns;
    }

    public function getColumnsToBeRendered()
    {
        return $this->getColumns();
    }

    public function prepareQuery()
    {
        $query = $this->ds->select();

        if ($this->keyColumn !== null) {
            $query->order($this->keyColumn);
        }

        return $query;
    }

    /**
     * @return array
     */
    public function getPrioritiesColumns()
    {
        return $this->prioritiesColumns;
    }

    /**
     * @param array $prioritiesColumns
     *
     * @return LegacyObjectTable
     */
    public function setPrioritiesColumns($prioritiesColumns)
    {
        $this->prioritiesColumns = $prioritiesColumns;
        return $this;
    }

    /**
     * @return bool
     */
    public function isEllipsesTable()
    {
        return $this->ellipsesTable;
    }

    /**
     * @param bool $ellipsesTable
     *
     * @return LegacyObjectTable
     */
    public function setEllipsesTable($ellipsesTable = true)
    {
        $this->ellipsesTable = $ellipsesTable;
        return $this;
    }

    public function getAttributes()
    {
        parent::getAttributes();

        $class = $this->attributes->get('class');
        $class->removeValue('cell-ellipsis');

        if ($this->isEllipsesTable()) {
            $class->addValue('cell-ellipsis');
        }

        return $this->attributes;
    }

    protected function renderObjectColumn($object)
    {
        $json = json_encode($object, JSON_PRETTY_PRINT);
        return Html::tag('pre', null, $json);
    }

    /** @noinspection PhpUnused */
    protected function renderColVars($value)
    {
        if (empty($value)) {
            return '';
        }

        $value = clone $value;

        // hide protected values
        foreach ($value as $k => $v) {
            $lower = strtolower($k);
            foreach ($this->protectVars as $protected) {
                if (strpos($lower, $protected) !== false) {
                    $value->{$k} = '***';
                    break;
                }
            }
        }

        $str = '';
        foreach ($value as $k => $v) {
            $str .= sprintf("%s: %s\n", $k, $v);
        }

        return Html::tag('pre', $str);
    }

    /** @noinspection PhpUnused */
    protected function renderColCommandLine($value)
    {
        return Html::tag('pre', null, $value);
    }

    protected function renderCol($key, $value)
    {
        $helperfunc = 'renderCol' . join('', array_map('ucwords', preg_split('~_~', $key)));
        if (method_exists($this, $helperfunc)) {
            return $this->$helperfunc($value);
        } elseif (is_object($value)) {
            return $this->renderObjectColumn($value);
        } elseif (is_array($value)) {
            return join(', ', $value);
        } else {
            return $value;
        }
    }

    protected function renderRow($row)
    {
        $tr = Table::tr();

        $unused = false;
        if ($this->objectsInUse !== null) {
            $key = $row->{$this->keyColumn};
            if (! array_key_exists($key, $this->objectsInUse)) {
                $unused = true;
            }
        }

        $colNo = 0;

        foreach ($this->getColumns() as $col) {
            $colNo++;
            $td = Table::td('');

            if ($colNo === 1 && $unused) {
                $td->setAttribute('class', 'unused');
            }

            if (property_exists($row, $col)) {
                $td->setContent($this->renderCol($col, $row->$col));
            }

            $tr->add($td);
        }

        return $tr;
    }
}
