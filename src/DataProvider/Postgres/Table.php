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
            $constraintsData = $this->loadConstraints();

            $columns = array_map(
                static function ($column) use ($constraintsData) {
                    return new Column($column, $constraintsData);
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
    column_default,
    table_schema
FROM
    information_schema.columns
WHERE
    table_name = :table_name
ORDER BY ordinal_position
SQL;

        return $this->connection->select($query, ['table_name' => $this->tableName]);
    }

    /**
     * @return ColumnsConstraints
     */
    private function loadConstraints(): ColumnsConstraints
    {
        $query = <<<'SQL'
SELECT pgc.conname AS constraint_name,
       ccu.table_schema AS table_schema,
       ccu.column_name,
       pgc.consrc AS definition
FROM pg_constraint pgc
JOIN pg_namespace nsp ON nsp.oid = pgc.connamespace
JOIN pg_class  cls ON pgc.conrelid = cls.oid
LEFT JOIN information_schema.constraint_column_usage ccu
          ON pgc.conname = ccu.constraint_name
          AND nsp.nspname = ccu.constraint_schema
WHERE contype ='c'
AND table_name = :table_name
ORDER BY pgc.conname;
SQL;

        $exists =
            $this
            ->connection
            ->selectOne(
                'select 1 as "exists" from pg_proc where proname = \'pg_get_constraintdef\' and pronargs = 1 limit 1'
            );

        if ($exists && (int)$exists->exists === 1) {
            $query = <<<'SQL'
SELECT pgc.conname AS constraint_name,
       ccu.table_schema AS table_schema,
       ccu.column_name,
       pg_get_constraintdef(pgc.oid) AS definition
FROM pg_constraint pgc
JOIN pg_namespace nsp ON nsp.oid = pgc.connamespace
JOIN pg_class  cls ON pgc.conrelid = cls.oid
LEFT JOIN information_schema.constraint_column_usage ccu
          ON pgc.conname = ccu.constraint_name
          AND nsp.nspname = ccu.constraint_schema
WHERE contype ='c'
AND table_name = :table_name
ORDER BY pgc.conname;
SQL;
        }


        return new ColumnsConstraints($this->connection->select($query, ['table_name' => $this->tableName]));
    }

    public function getName(): string
    {
        return $this->tableName;
    }
}
