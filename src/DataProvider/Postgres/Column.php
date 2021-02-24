<?php

namespace Azizoff\ModelGenerator\DataProvider\Postgres;

use Azizoff\ModelGenerator\DataProvider\ColumnInterface;
use Azizoff\ModelGenerator\DataProvider\ConstantAwareInterface;

class Column implements ColumnInterface, ConstantAwareInterface
{
    private $object;
    /**
     * @var ColumnsConstraints
     */
    private $constraints;

    public function __construct($object, ColumnsConstraints $constraints)
    {
        $this->object = $object;
        $this->constraints = $constraints;
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

    public function getPHPType(): string
    {
        static $map = [
            'bigint'                      => 'int',
            'boolean'                     => 'bool',
            'character varying'           => 'string',
            'integer'                     => 'int',
            'json'                        => 'array',
            'jsonb'                       => 'array',
            'smallint'                    => 'int',
            'time with time zone'         => 'string',
            'time without time zone'      => 'string',
            'timestamp with time zone'    => 'string',
            'timestamp without time zone' => 'string',
        ];

        $type = $this->getType();

        return $map[$type] ?? 'string';
    }

    public function isIncremental(): bool
    {
        return mb_stripos($this->getDefaultValue(), 'nextval') !== false;
    }

    public function getSchema(): bool
    {
        return $this->object->table_schema;
    }

    public function getConstants(): array
    {
        $result = [];
        $constraints = $this->constraints->getConstraints($this);

        foreach ($constraints as $constraint) {
            $values = $constraint->getValues();
            foreach ($values as $value) {
                $result[] = $value;
            }
        }

        return $result;
    }
}
