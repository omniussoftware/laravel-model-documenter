# Laravel Model Documenter

## Getting started:

`php artisan vendor:publish --provider="enz0project\ModelDocumenter\ModelDocumenterProvider"`

## Docs

If you want to use this outside of the command or write your own command, you can use the ModelAnalyzer:
```php
<?php
    $modelAnalyzer = new ModelAnalyzer();
    $modelData = $modelAnalyzer->analyze('/path/to/file.php');
```

The `analyze` method gives back an object that looks like this:

```php
<?php
$modelData = (object) [
    'name', // The models class name
    'type', // Is one of the constants in ModelAnalyzer, i.e. TYPE_CLASS or TYPE_INTERFACE
    'fileContents', // Contains all lines in the .php file
    'classDocBlock', // The old classlevel docblock, if one exists
    'properties', // An array of properties where the key is the property name and the value is its php type
    'relations', // An array of relations where the key is the relation name and the value is its php type
    'requiredImports', // An array of imports required to be in the file, i.e. Carbon or Collection
];
```