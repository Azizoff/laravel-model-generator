<?php

namespace Azizoff\ModelGenerator\DataProvider;

interface DataProviderInterface
{
    public function getColumns(string $table);

    public function getPrimary(string $table);
}
