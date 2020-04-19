<?php

namespace Azizoff\ModelGenerator\DataProvider;

interface DataProviderInterface
{
    /**
     * @param string $table
     *
     * @return TableInterface
     */
    public function getTable(string $table): TableInterface;

}
