# Laravel model generator

## About
Creates a new model class based on a database table  
!!! It works only with postgresql database.

## Installation
Insert Azizoff\ModelGenerator\ModelGeneratorProvider::class into "providers" section of /config/app.php

or paste into `AppServiceProvider::register()`
```php
if ($this->app->environment() === 'local'
        && class_exists(\Azizoff\ModelGenerator\ModelGeneratorProvider::class)
) {
        $this->app->register(\Azizoff\ModelGenerator\ModelGeneratorProvider::class);
}
```

## Config
You can also publish the config file
```bash
php artisan vendor:publish --provider="Azizoff\\ModelGenerator\\ModelGeneratorProvider" --tag=config
```

## Command
```bash
php artisan model:generate <tablename>
```

```bash
php artisan model:generate <tablename> --model=\\Http\\Models\\CustomModelName
```

