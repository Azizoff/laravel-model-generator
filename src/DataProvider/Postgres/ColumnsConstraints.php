<?php

namespace Azizoff\ModelGenerator\DataProvider\Postgres;

class ColumnsConstraints
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param Column $column
     *
     * @return Constraint[]
     */
    public function getConstraints(Column $column): array
    {
        $result = [];
        foreach ($this->data as $constraint) {
            if ($constraint->table_schema === $column->getSchema() && $constraint->column_name === $column->getName()) {
                $result[] = new Constraint($constraint);
            }
        }

        return $result;
    }
}
