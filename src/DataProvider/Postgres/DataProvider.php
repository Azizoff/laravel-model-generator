<?php

namespace Azizoff\ModelGenerator\DataProvider\Postgres;

use Azizoff\ModelGenerator\DataProvider\DataProviderInterface;
use Illuminate\Database\Connection;

class DataProvider implements DataProviderInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getColumns(string $table)
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
        return $this->connection->select($query, ['table_name' => $table]);
    }

    public function getPrimary(string $table)
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
        return $this->connection->select($query, ['table_name' => $table]);
    }
}
