<?php

namespace Azizoff\ModelGenerator\DataProvider\Postgres;

use Azizoff\ModelGenerator\DataProvider\ColumnInterface;

class Column implements ColumnInterface
{
    private $object;

    public function __construct($object)
    {
        $this->object = $object;
    }

    public function getType()
    {
        return $this->object->data_type;
    }

    public function isNullable(): bool
    {
        return $this->object->is_nullable === 'YES';
    }

    public function getName()
    {
        return $this->object->column_name;
    }

    public function getDefaultValue()
    {
        return $this->object->column_default;
    }
}
