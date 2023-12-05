# Laravel Model Documenter

## Running the command

`php artisan model:document` will run the model-documenter on all models in `app_path('Models')` and subfolders.

If you want to run the command only on specific models, you can give it the filename without extension as an argument:
`php artisan model:document ForumThread` will make it run only on `ForumThread.php` in the models folder. The argument
also takes multiple files, separated by comma:
`php artisan model:document ForumThread,ForumPost`

## Docs

If you want to use this outside of the command or write your own command, you can use the ModelData directly:
```php
<?php
    $modelData = new ModelData('/path/to/file.php');
```

The ModelData has several public properties that get populated by the constructor:

```php
<?php
    string $modelData->name; // The models class name
    array $modelData->fileContents; // Contains all lines in the .php file
    string $modelData->classDocBlock; // The old classlevel docblock, if one exists
    array $modelData->properties; // An array of properties where the key is the property name and the value is its php type
    array $modelData->relations; // An array of relations where the key is the relation name and the value is its php type
    array $modelData->requiredImports; // An array of imports required to be in the file, i.e. Carbon or Collection
```
