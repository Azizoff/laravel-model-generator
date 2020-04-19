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

    public function getPrimary(string $table);
}
