<?php

namespace Azizoff\ModelGenerator\DataProvider\Postgres;

use Azizoff\ModelGenerator\DataProvider\DataProviderInterface;
use Azizoff\ModelGenerator\DataProvider\TableInterface;
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

    public function getTable(string $table): TableInterface
    {
        return new Table($table, $this->connection);
    }
}
