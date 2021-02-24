<?php

namespace Azizoff\ModelGenerator\DataProvider\Postgres;

class Constraint
{
    private $object;

    public function __construct($object)
    {
        $this->object = $object;
    }

    public function isEnum(): bool
    {
        $templates = [
            sprintf("#\(\(%s\)::text = ANY \(\(ARRAY\[.*\]\)::text\[\]\)\)#", $this->object->column_name),
        ];

        foreach ($templates as $template) {
            if (preg_match($template, $this->object->defenition)) {
                return true;
            }
        }

        return false;
    }

    public function getValues(): array
    {
        $templates = [
            sprintf("#\(\(%s\)::text = ANY \(\(ARRAY\[(?<values>.*)\]\)::text\[\]\)\)#", $this->object->column_name),
        ];

        foreach ($templates as $template) {
            $values = [];
            if (preg_match($template, $this->object->definition, $values)) {
                $parts = explode(', ', $values['values']);

                $parts = array_map(
                    static function ($item) {
                        if (preg_match('#^\'(?<name>.+)\'::character varying$#', $item, $matches)) {
                            return $matches['name'];
                        }

                        return '';
                    },
                    $parts
                );

                return $parts;
            }
        }


        return [];
    }
}
