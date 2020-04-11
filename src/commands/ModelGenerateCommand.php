<?php

namespace Azizoff\ModelGenerator\commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ModelGenerateCommand extends GeneratorCommand
{
    protected $name = "model:generate";
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Eloquent model class table based';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Model';

    /**
     * @inheritDoc
     */
    protected function getStub()
    {
        return __DIR__ . '/stubs/model.stub';
    }

    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the model already exists'],
        ];
    }

    private function replaceProperties($stub)
    {
        $stub = str_replace(
            [
                'DummyProperties',
                'DummyPrimary',
                'DummyTableName',
                'DummyTimestamps',
                'SoftDeletesImport',
                'SoftDeletesTrait',
                'CastsPart',
                'IncrementingKeyPart',
                'PrimaryKeyTypePart',
            ],
            [
                $this->buildPropertyDocBlock($this->getColumns()),
                $this->buildPrimary($this->getPrimary()),
                $this->buildTable($this->getTable()),
                $this->buildTimestampsPart($this->getColumns()),
                $this->softDeletesImportPart($this->getColumns()),
                $this->softDeletesTraitPart($this->getColumns()),
                $this->castsPart($this->getColumns()),
                $this->incrementingKeyPart($this->getColumns()),
                $this->primaryKeyTypePart($this->getColumns()),

            ],
            $stub
        );

        return $stub;
    }

    private function getColumns()
    {
        static $columns;

        if (null === $columns) {
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
FROM information_schema.columns
WHERE table_name = :table_name
ORDER BY ordinal_position
SQL;
            $columns = DB::select($query, ['table_name' => $this->getTable()]);
        }
        return $columns;
    }

    private function getPrimary()
    {
        static $primary;
        if (null === $primary) {
            $query = <<<'SQL'
select kcu.column_name
from information_schema.table_constraints tco
         join information_schema.key_column_usage kcu
              on kcu.constraint_name = tco.constraint_name
                  and kcu.constraint_schema = tco.constraint_schema
                  and kcu.constraint_name = tco.constraint_name
where tco.table_name = :table_name
  and tco.constraint_type = 'PRIMARY KEY'
SQL;
            $primary = DB::select($query, ['table_name' => $this->getTable()]);
        }
        return $primary;
    }


    protected function buildClass($name)
    {
        return $this->cleanEmptyLines($this->replaceProperties(parent::buildClass($name)));
    }

    private function buildPropertyDocBlock(array $properties)
    {
        return
            '/**'
            . PHP_EOL
            . implode(
                PHP_EOL,
                array_map(
                    function ($property) {
                        return vsprintf(
                            " * @property %s%s $%s",
                            [
                                $this->normalizeType($property->data_type),
                                $property->is_nullable === 'YES' ? '|null' : '',
                                $property->column_name,
                            ]
                        );
                    },
                    $properties
                )
            )
            . PHP_EOL
            . ' */';
    }

    private function normalizeType($data_type): string
    {
        $map = $this->getTypes();

        return $map[$data_type] ?? 'string';
    }

    /**
     * @return array
     */
    private function getTypes(): array
    {
        return [
            'bigint'                      => 'int',
            'boolean'                     => 'bool',
            'character varying'           => 'string',
            'integer'                     => 'int',
            'json'                        => 'array',
            'jsonb'                       => 'array',
            'smallint'                    => 'int',
            'time with time zone'         => 'string',
            'time without time zone'      => 'string',
            'timestamp with time zone'    => 'string',
            'timestamp without time zone' => 'string',
        ];
    }

    private function buildPrimary(array $primary): string
    {
        if (count($primary) === 1) {
            return sprintf('protected $primaryKey = \'%s\';', $primary[0]->column_name);
        }
        return 'protected $primaryKey = \'\'; // Unknown key';
    }

    private function buildTable(string $table): string
    {
        return sprintf('protected $table = \'%s\';', $table);
    }

    private function buildTimestampsPart(array $columns)
    {
        $names = array_map(
            static function ($item) {
                return $item->column_name;
            },
            $columns
        );
        $date_columns = array_intersect(['created_at', 'updated_at'], $names);
        return (count($date_columns) !== 2) ? 'protected $timestamps = false;' : '';
    }

    /**
     * @return string
     */
    private function getTable(): string
    {
        return trim($this->argument('table'));
    }


    private function isSoftDeletes(array $columns): bool
    {
        $names = array_map(
            static function ($item) {
                return $item->column_name;
            },
            $columns
        );
        $date_columns = array_intersect(['deleted_at'], $names);
        return 1 === count($date_columns);
    }

    private function softDeletesImportPart(array $columns): string
    {
        return
            $this->isSoftDeletes($this->getColumns())
                ? 'use Illuminate\Database\Eloquent\SoftDeletes;' . PHP_EOL
                : '';
    }

    private function softDeletesTraitPart(array $columns): string
    {
        return $this->isSoftDeletes($this->getColumns()) ? 'use SoftDeletes;' : '';
    }

    private function castsPart(array $columns)
    {
        $toArrayCasts = array_filter(
            $columns,
            static function ($item) {
                return in_array($item->data_type, ['json', 'jsonb'], true);
            }
        );

        $casts = [];

        foreach ($toArrayCasts as $item) {
            $casts[] = str_repeat(' ', 8) . sprintf("'%s' => 'json',", $item->column_name);
        }

        if (count($casts) > 0) {
            return 'protected $casts = [' . PHP_EOL .
                implode(PHP_EOL, $casts)
                . PHP_EOL . '    ];';
        }

        return '';
    }

    private function incrementingKeyPart(array $columns): string
    {
        $primary = $this->getPrimary();
        if (1 === count($primary)) {
            $key = array_filter(
                $columns,
                static function ($item) use ($primary) {
                    return $item->column_name === $primary[0]->column_name;
                }
            );
            if (mb_stripos($key[0]->column_default, 'nextval') === false) {
                return 'public $incrementing = false;';
            }
        }

        return '';
    }

    private function primaryKeyTypePart(array $columns)
    {
        $primary = $this->getPrimary();
        if (1 === count($primary)) {
            $key = array_filter(
                $columns,
                static function ($item) use ($primary) {
                    return $item->column_name === $primary[0]->column_name;
                }
            );

            $type = $this->normalizeType($key[0]->data_type);
            if (in_array($type, ['string'], true)) {
                return 'protected $keyType = \'' . $type . '\';';
            }
        }

        return '';
    }

    private function cleanEmptyLines(string $stub)
    {
        return preg_replace('#^    \n#m', '', $stub);
    }

    protected function getNameInput()
    {
        return Str::ucfirst(Str::camel($this->getTable()));
    }

    protected function getArguments()
    {
        return [
            ['table', InputArgument::REQUIRED, 'The name of the table'],
        ];
    }
}
