<?php

namespace Azizoff\ModelGenerator\Commands;

use Azizoff\ModelGenerator\DataProvider\ColumnInterface;
use Azizoff\ModelGenerator\DataProvider\DataProviderFactory;
use Azizoff\ModelGenerator\DataProvider\DataProviderInterface;
use Azizoff\ModelGenerator\DataProvider\PrimaryInterface;
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

    /**
     * @param string $stub
     *
     * @return string
     */
    private function replaceProperties(string $stub): string
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

    /**
     * @return ColumnInterface[]
     */
    private function getColumns()
    {
        static $columns;

        if (null === $columns) {
            $columns = $this->dataProvider->getColumns($this->getTableName());
        }

        return $columns;
    }

    /**
     * @return PrimaryInterface[]
     */
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

    /**
     * @param ColumnInterface[] $columns
     *
     * @return string
     */
    private function generatePropertyDocBlock(array $columns): string
    {
        return
            '/**'
            . PHP_EOL
            . implode(
                PHP_EOL,
                array_map(
                    function ($column) {
                        return vsprintf(
                            " * @property %s%s $%s",
                            [
                                $column->getPHPType(),
                                $column->isNullable() ? '|null' : '',
                                $column->getName(),
                            ]
                        );
                    },
                    $columns
                )
            )
            . PHP_EOL
            . ' */';
    }

    /**
     * @param PrimaryInterface[] $primary
     *
     * @return string
     */
    private function generatePrimaryPropertyPart(array $primary): string
    {
        if (count($primary) === 1) {
            return sprintf('protected $primaryKey = \'%s\';', $primary[0]->getColumnName());
        }
        return 'protected $primaryKey = \'\'; // Unknown key';
    }

    private function generateTableNamePropertyPart(string $table): string
    {
        return sprintf('protected $table = \'%s\';', $table);
    }

    /**
     * @param ColumnInterface[] $columns
     *
     * @return string
     */
    private function generateNoTimestampsPropertyPart(array $columns): string
    {
        $names = array_map(
            static function ($column) {
                return $column->getName();
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

    /**
     * @param ColumnInterface[] $columns
     *
     * @return bool
     */
    private function isSoftDeletes(array $columns): bool
    {
        $names = array_map(
            static function ($column) {
                return $column->getName();
            },
            $columns
        );
        $date_columns = array_intersect(['deleted_at'], $names);
        return 1 === count($date_columns);
    }

    /**
     * @param ColumnInterface[] $columns
     *
     * @return string
     */
    private function generateSoftDeletesImportPart(array $columns): string
    {
        return
            $this->isSoftDeletes($columns)
                ? 'use Illuminate\Database\Eloquent\SoftDeletes;' . PHP_EOL
                : '';
    }

    /**
     * @param ColumnInterface[] $columns
     *
     * @return string
     */
    private function generateSoftDeletesTraitPart(array $columns): string
    {
        return $this->isSoftDeletes($columns) ? 'use SoftDeletes;' : '';
    }

    /**
     * @param ColumnInterface[] $columns
     *
     * @return string
     */
    private function generateCastsPropertyPart(array $columns): string
    {
        $toArrayCasts = array_filter(
            $columns,
            static function ($column) {
                return in_array($column->getType(), ['json', 'jsonb'], true);
            }
        );

        $casts = [];

        foreach ($toArrayCasts as $column) {
            $casts[] = str_repeat(' ', 8) . sprintf("'%s' => 'json',", $column->getName());
        }

        if (count($casts) > 0) {
            return 'protected $casts = [' . PHP_EOL .
                implode(PHP_EOL, $casts)
                . PHP_EOL . '    ];';
        }

        return '';
    }

    /**
     * @param PrimaryInterface[] $primary
     * @param ColumnInterface[] $columns
     *
     * @return string
     */
    private function generateNoIncrementingKeyPropertyPart(array $primary, array $columns): string
    {
        if (1 === count($primary)) {
            $key = array_filter(
                $columns,
                static function ($column) use ($primary) {
                    return $column->getName() === $primary[0]->getColumnName();
                }
            );
            if (1 === count($key)) {
                if (mb_stripos($key[0]->getDefaultValue(), 'nextval') === false) {
                    return 'public $incrementing = false;';
                }
            }
        }

        return '';
    }

    /**
     * @param PrimaryInterface[] $primary
     * @param ColumnInterface[] $columns
     *
     * @return string
     */
    private function generatePrimaryKeyTypeAttributePart(array $primary, array $columns): string
    {
        if (1 === count($primary)) {
            $key = array_filter(
                $columns,
                static function ($column) use ($primary) {
                    return $column->getName() === $primary[0]->getColumnName();
                }
            );

            if (1 === count($key)) {
                $type = $key[0]->getPHPType();
                if (in_array($type, ['string'], true)) {
                    return 'protected $keyType = \'' . $type . '\';';
                }
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
