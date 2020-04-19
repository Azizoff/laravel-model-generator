<?php

namespace Azizoff\ModelGenerator\Commands;

use Azizoff\ModelGenerator\DataProvider\ColumnInterface;
use Azizoff\ModelGenerator\DataProvider\DataProviderFactory;
use Azizoff\ModelGenerator\DataProvider\DataProviderInterface;
use Azizoff\ModelGenerator\DataProvider\TableInterface;
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
        $tableName = $this->getTableName();
        $table = $this->dataProvider->getTable($tableName);

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
                $this->generatePropertyDocBlock($table),
                $this->generatePrimaryPropertyPart($table),
                $this->generateTableNamePropertyPart($table),
                $this->generateNoTimestampsPropertyPart($table),
                $this->generateSoftDeletesImportPart($table),
                $this->generateSoftDeletesTraitPart($table),
                $this->generateCastsPropertyPart($table),
                $this->generateNoIncrementingKeyPropertyPart($table),
                $this->generatePrimaryKeyTypeAttributePart($table),

            ],
            $stub
        );

        return $stub;
    }


    protected function buildClass($name)
    {
        return $this->cleanEmptyLines($this->replaceProperties(parent::buildClass($name)));
    }

    /**
     * @param TableInterface $table
     *
     * @return string
     */
    private function generatePropertyDocBlock(TableInterface $table): string
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
                    $table->getColumns()
                )
            )
            . PHP_EOL
            . ' */';
    }

    /**
     * @param TableInterface $table
     *
     * @return string
     */
    private function generatePrimaryPropertyPart(TableInterface $table): string
    {
        if (count($table->getPrimary()) === 1) {
            return sprintf('protected $primaryKey = \'%s\';', $table->getPrimary()[0]->getName());
        }
        return 'protected $primaryKey = \'\'; // Unknown key';
    }

    private function generateTableNamePropertyPart(TableInterface $table): string
    {
        return sprintf('protected $table = \'%s\';', $table->getName());
    }

    /**
     * @param TableInterface $table
     *
     * @return string
     */
    private function generateNoTimestampsPropertyPart(TableInterface $table): string
    {
        $names = array_map(
            static function ($column) {
                return $column->getName();
            },
            $table->getColumns()
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
     * @param TableInterface $table
     *
     * @return string
     */
    private function generateSoftDeletesImportPart(TableInterface $table): string
    {
        return
            $this->isSoftDeletes($table->getColumns())
                ? 'use Illuminate\Database\Eloquent\SoftDeletes;' . PHP_EOL
                : '';
    }

    /**
     * @param TableInterface $table
     *
     * @return string
     */
    private function generateSoftDeletesTraitPart(TableInterface $table): string
    {
        return $this->isSoftDeletes($table->getColumns()) ? 'use SoftDeletes;' : '';
    }

    /**
     * @param TableInterface $table
     *
     * @return string
     */
    private function generateCastsPropertyPart(TableInterface $table): string
    {
        $toArrayCasts = array_filter(
            $table->getColumns(),
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
     * @param TableInterface $table
     *
     * @return string
     */
    private function generateNoIncrementingKeyPropertyPart(TableInterface $table): string
    {
        if (1 === count($table->getPrimary())) {
            $key = $table->getPrimary()[0];

            if (!$key->isIncremental()) {
                return 'public $incrementing = false;';
            }
        }

        return '';
    }

    /**
     * @param TableInterface $table
     *
     * @return string
     */
    private function generatePrimaryKeyTypeAttributePart(TableInterface $table): string
    {
        if (1 === count($table->getPrimary())) {
            $key = $table->getPrimary()[0];
            $type = $key->getPHPType();
            if (in_array($type, ['string'], true)) {
                return 'protected $keyType = \'' . $type . '\';';
            }
        }

        return '';
    }

    private function cleanEmptyLines(string $stub): string
    {
        return preg_replace(
            '#^{\n\n#m',
            "{\n",
            preg_replace(
                '#^\n\n#m',
                PHP_EOL,
                preg_replace('#^ {4}\n#m', '', $stub)
            )
        );
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
