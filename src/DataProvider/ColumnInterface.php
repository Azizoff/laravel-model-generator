<?php

namespace Azizoff\ModelGenerator\DataProvider;

interface ColumnInterface
{
    public function getType();

    public function isNullable(): bool;

    public function getName();

    public function getDefaultValue();
}
