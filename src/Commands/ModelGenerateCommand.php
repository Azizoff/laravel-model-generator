<?php

namespace Azizoff\ModelGenerator\Commands;

use Azizoff\ModelGenerator\DataProvider\DataProviderFactory;
use Azizoff\ModelGenerator\DataProvider\DataProviderInterface;
use Exception;
use Illuminate\Config\Repository;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Filesystem\Filesystem;
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
    protected $description = 'Create a new Eloquent model class based on database table';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Model';
    /**
     * @var string
     */
    private $defaultNamespace;
    /**
     * @var DataProviderInterface
     */
    private $dataProvider;

    /**
     * ModelGenerateCommand constructor.
     *
     * @param ConnectionResolverInterface $db
     * @param Filesystem $files
     * @param Repository $config
     *
     * @throws Exception
     */
    public function __construct(ConnectionResolverInterface $db, Filesystem $files, Repository $config)
    {
        parent::__construct($files);
        $this->defaultNamespace = $config->get('model-generator.default_namespace');
        /** @var Connection $connection */
        $connection = $db->connection($config->get('model-generator.connection'));
        $this->dataProvider = DataProviderFactory::make($connection);
    }

    /**
     * @inheritDoc
     */
    protected function getStub(): string
    {
        return __DIR__ . '/stubs/model.stub';
    }

    private function replaceProperties($stub): string
    {
        $primary = $this->getPrimary();
        $columns = $this->getColumns();

        $stub = str_replace(
            [
                'PropertiesDocBlockPart',
                'PrimaryPropertyPart',
                'TableNamePropertyPart',
                'NoTimestampsPropertyPart',
                'SoftDeletesImportPart',
                'SoftDeletesTraitPart',
                'CastsPropertyPart',
                'IncrementingKeyPart',
                'PrimaryKeyTypePart',
            ],
            [
                $this->generatePropertyDocBlock($columns),
                $this->generatePrimaryPropertyPart($primary),
                $this->generateTableNamePropertyPart($this->getTableName()),
                $this->generateNoTimestampsPropertyPart($columns),
                $this->generateSoftDeletesImportPart($columns),
                $this->generateSoftDeletesTraitPart($columns),
                $this->generateCastsPropertyPart($columns),
                $this->generateNoIncrementingKeyPropertyPart($primary, $columns),
                $this->generatePrimaryKeyTypeAttributePart($primary, $columns),

            ],
            $stub
        );

        return $stub;
    }

    private function getColumns()
    {
        static $columns;

        if (null === $columns) {
            $columns = $this->dataProvider->getColumns($this->getTableName());
        }

        return $columns;
    }

    private function getPrimary()
    {
        static $primary;

        if (null === $primary) {
            $primary = $this->dataProvider->getPrimary($this->getTableName());
        }

        return $primary;
    }

    protected function buildClass($name)
    {
        return $this->cleanEmptyLines($this->replaceProperties(parent::buildClass($name)));
    }

    private function generatePropertyDocBlock(array $columns): string
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
                    $columns
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

    private function generatePrimaryPropertyPart(array $primary): string
    {
        if (count($primary) === 1) {
            return sprintf('protected $primaryKey = \'%s\';', $primary[0]->column_name);
        }
        return 'protected $primaryKey = \'\'; // Unknown key';
    }

    private function generateTableNamePropertyPart(string $table): string
    {
        return sprintf('protected $table = \'%s\';', $table);
    }

    private function generateNoTimestampsPropertyPart(array $columns): string
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
    private function getTableName(): string
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

    private function generateSoftDeletesImportPart(array $columns): string
    {
        return
            $this->isSoftDeletes($columns)
                ? 'use Illuminate\Database\Eloquent\SoftDeletes;' . PHP_EOL
                : '';
    }

    private function generateSoftDeletesTraitPart(array $columns): string
    {
        return $this->isSoftDeletes($columns) ? 'use SoftDeletes;' : '';
    }

    private function generateCastsPropertyPart(array $columns): string
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

    private function generateNoIncrementingKeyPropertyPart(array $primary, array $columns): string
    {
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

    private function generatePrimaryKeyTypeAttributePart(array $primary, array $columns): string
    {
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

    private function cleanEmptyLines(string $stub): string
    {
        return preg_replace('#^\n\n#m', PHP_EOL, preg_replace('#^ {4}\n#m', '', $stub));
    }

    protected function getNameInput(): string
    {
        if ($this->hasOption('model') && !empty($this->option('model'))) {
            return $this->option('model');
        }
        return Str::ucfirst(Str::camel($this->getTableName()));
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . $this->defaultNamespace;
    }

    protected function getArguments(): array
    {
        return [
            ['table', InputArgument::REQUIRED, 'The name of the table'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the model already exists'],
            ['model', null, InputOption::VALUE_REQUIRED, 'Model class name'],
        ];
    }
}
