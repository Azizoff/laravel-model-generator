# About
Creates a new model class based on a database table  
!!! It works only with postgresql database.

# Installation
Insert Azizoff\ModelGenerator\ModelGeneratorProvider::class into "providers" section of /config/app.php

or paste into `AppServiceProvider::register()`
```php
if ($this->app->environment() === 'local'
        && class_exists(\Azizoff\ModelGenerator\ModelGeneratorProvider::class)
) {
        $this->app->register(\Azizoff\ModelGenerator\ModelGeneratorProvider::class);
}
```
