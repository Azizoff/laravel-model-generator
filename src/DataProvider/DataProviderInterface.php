<?php

namespace Azizoff\ModelGenerator\DataProvider;

interface DataProviderInterface
{
    /**
     * @param string $table
     *
     * @return ColumnInterface[]
     */
    public function getColumns(string $table): array;

    /**
     * @param string $table
     *
     * @return PrimaryInterface[]
     */
    public function getPrimary(string $table): array;
}
