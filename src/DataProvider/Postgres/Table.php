<?php

namespace Azizoff\ModelGenerator\DataProvider\Postgres;

use Azizoff\ModelGenerator\DataProvider\ColumnInterface;
use Azizoff\ModelGenerator\DataProvider\TableInterface;
use Illuminate\Database\Connection;

class Table implements TableInterface
{
    /**
     * @var string
     */
    private $tableName;
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(string $tableName, Connection $connection)
    {
        $this->tableName = $tableName;
        $this->connection = $connection;
    }

    /**
     * @return ColumnInterface[]
     */
    public function getColumns(): array
    {
        static $columns;
        if (null === $columns) {
            $columnsData = $this->loadColumns();

            $columns = array_map(
                static function ($column) {
                    return new Column($column);
                },
                $columnsData
            );
        }

        return $columns;
    }

    /**
     * @return ColumnInterface[]
     */
    public function getPrimary(): array
    {
        static $primary;

        if (null === $primary) {
            $primaryColumns = $this->loadPrimaryColumnsNames();

            $primary = array_filter(
                $this->getColumns(),
                static function ($column) use ($primaryColumns) {
                    return in_array($column->getName(), $primaryColumns, true);
                }
            );
        }

        return $primary;
    }

    /**
     * @return array
     */
    private function loadPrimaryColumnsNames(): array
    {
        $query = <<<'SQL'
SELECT
    kcu.column_name
FROM
    information_schema.table_constraints tco
        INNER JOIN information_schema.key_column_usage kcu
                   ON kcu.constraint_name = tco.constraint_name
                       AND kcu.constraint_schema = tco.constraint_schema
                       AND kcu.constraint_name = tco.constraint_name
WHERE
      tco.table_name = :table_name
  AND tco.constraint_type = 'PRIMARY KEY'
SQL;
        $primaryData = $this->connection->select($query, ['table_name' => $this->tableName]);

        return array_map(
            static function ($key) {
                return $key->column_name;
            },
            $primaryData
        );
    }

    /**
     * @return array
     */
    private function loadColumns(): array
    {
        $query = <<<'SQL'
SELECT
    ordinal_position,
    column_name,
    is_nullable,
    data_type,
    udt_name,
    character_maximum_length,
    numeric_precision,
    numeric_precision_radix,
    numeric_scale,
    column_default
FROM
    information_schema.columns
WHERE
    table_name = :table_name
ORDER BY ordinal_position
SQL;
        return $this->connection->select($query, ['table_name' => $this->tableName]);
    }

    public function getName(): string
    {
        return $this->tableName;
    }
}
