<?php

namespace Azizoff\ModelGenerator\DataProvider\Postgres;

use Azizoff\ModelGenerator\DataProvider\PrimaryInterface;

class Primary implements PrimaryInterface
{
    private $object;

    public function __construct($object)
    {
        $this->object = $object;
    }

    public function getColumnName()
    {
        return $this->object->column_name;
    }
}
