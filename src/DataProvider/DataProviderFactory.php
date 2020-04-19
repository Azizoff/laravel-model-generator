<?php

namespace Azizoff\ModelGenerator\DataProvider;

use Azizoff\ModelGenerator\DataProvider\Postgres\DataProvider as PostgresDataProvider;
use Exception;
use Illuminate\Database\Connection;
use PDO;

class DataProviderFactory
{
    /**
     * @param Connection $connection
     *
     * @return DataProviderInterface
     * @throws Exception
     */
    public static function make(Connection $connection): DataProviderInterface
    {
        $driver = (string)$connection->getPDO()->getAttribute(PDO::ATTR_DRIVER_NAME);
        switch ($driver) {
            case 'pgsql':
                return new PostgresDataProvider($connection);
        }

        throw new Exception('Unknown driver: %s', $driver);
    }
}
