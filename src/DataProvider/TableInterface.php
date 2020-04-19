<?php

namespace Azizoff\ModelGenerator\DataProvider;

interface TableInterface
{
    /**
     * @return ColumnInterface[]
     */
    public function getPrimary(): array;

    /**
     * @return ColumnInterface[]
     */
    public function getColumns(): array;

    public function getName(): string;
}
