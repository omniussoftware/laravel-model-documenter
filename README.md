# Laravel Model Documenter

## Getting started

`php artisan vendor:publish --provider="Enz0project\ModelDocumenter\ModelDocumenterProvider" --tag="config"`

## Running the command

`php artisan enz0project:model-documenter` will run the model-documenter on all models specified in the folder in `./config/modeldocumenter.php`. 

If you want to run the the model-documenter only on specific models, you can give it the filename without extension as an argument:
`php artisan enz0project:model-documenter ForumThread` will make it run only on `ForumThread.php` in the models folder.
If you want to run it on multiple, specific models, you can separate them by comma:
`php artisan enz0project:model-documenter User,ForumThread,ForumPost,ForumComment`

## Docs

If you want to use this outside of the command or write your own command, you can use the ModelAnalyzer:
```php
<?php
    $modelAnalyzer = new ModelAnalyzer();
    $modelData = $modelAnalyzer->analyze('/path/to/file.php');
```

The `analyze` method gives back a ModelData object that can be used like this:

```php
<?php
    string $name = $modelData->getName(); // The models class name
    int $type = $modelData->getType(); // Is one of the constants in ModelAnalyzer, i.e. TYPE_CLASS or TYPE_INTERFACE
    array $fileContents = $modelData->getFileContents(); // Contains all lines in the .php file
    string $classDocBlock = $modelData->getClassDocBlock(); // The old classlevel docblock, if one exists
    array $properties = $modelData->getProperties(); // An array of properties where the key is the property name and the value is its php type
    array|null $relations = $modelData->getRelations(); // An array of relations where the key is the relation name and the value is its php type
    array $requiredImports = $modelData->getRequiredImports(); // An array of imports required to be in the file, i.e. Carbon or Collection
```
