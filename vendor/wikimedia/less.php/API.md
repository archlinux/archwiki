Less.php API
========

## Basic use

#### Parse strings

```php
$parser = new Less_Parser();
$parser->parse( '@color: #36c; .link { color: @color; } a { color: @color; }' );
$css = $parser->getCss();
```

#### Parse files

The `parseFile()` function takes two parameters:

* The absolute path to a `.less` file.
* The base URL for any relative image or CSS references in the `.less` file,
  typically the same directory that contains the `.less` file or a public equivalent.

```php
$parser = new Less_Parser();
$parser->parseFile( '/var/www/mysite/bootstrap.less', 'https://example.org/mysite/' );
$css = $parser->getCss();
```

#### Handle invalid syntax

An exception will be thrown if the compiler encounters invalid LESS.

```php
try{
  $parser = new Less_Parser();
  $parser->parseFile( '/var/www/mysite/bootstrap.less', 'https://example.org/mysite/' );
  $css = $parser->getCss();
} catch (Exception $e) {
  echo $e->getMessage();
}
```

#### Parse multiple inputs

Less.php can parse multiple input sources (e.g. files and/or strings) and generate a single CSS output.

```php
$parser = new Less_Parser();
$parser->parseFile( '/var/www/mysite/bootstrap.less', '/mysite/' );
$parser->parse( '@color: #36c; .link { color: @color; } a { color: @color; }' );
$css = $parser->getCss();
```

#### Metadata

Less.php keeps track of which `.less` files have been parsed, i.e. the input
file(s) and any direct and indirect imports.

```php
$parser = new Less_Parser();
$parser->parseFile( '/var/www/mysite/bootstrap.less', '/mysite/' );
$css = $parser->getCss();
$files = $parser->getParsedFiles();
```

#### Compress output

You can tell Less.php to remove comments and whitespace to generate minified CSS.

```php
$options = [ 'compress' => true ];
$parser = new Less_Parser( $options );
$parser->parseFile( '/var/www/mysite/bootstrap.less', '/mysite/' );
$css = $parser->getCss();
```

#### Get variables

You can use the `getVariables()` method to get an all variables defined and
their value in an associative array. Note that the input must be compiled first
by calling `getCss()`.

```php
$parser = new Less_Parser;
$parser->parseFile( '/var/www/mysite/bootstrap.less');
$css = $parser->getCss();
$variables = $parser->getVariables();

```

#### Set variables

Use the `ModifyVars()` method to inject additional variables, i.e. custom values
computed or accessed from your PHP code.

```php
$parser = new Less_Parser();
$parser->parseFile( '/var/www/mysite/bootstrap.less', '/mysite/' );
$parser->ModifyVars( [ 'font-size-base' => '16px' ] );
$css = $parser->getCss();
```

#### Import directories

By default, Less.php will look for imported files in the directory of the file passed to `parseFile()`.

If you use `parse()`, or if need to enable additional import directories, you can specify these by
calling `SetImportDirs()`.

```php
$directories = [ '/var/www/mysite/bootstrap/' => '/mysite/bootstrap/' ];
$parser = new Less_Parser();
$parser->SetImportDirs( $directories );
$parser->parseFile( '/var/www/mysite/theme.less', '/mysite/' );
$css = $parser->getCss();
```

## Caching

Compiling LESS code into CSS can be a time-consuming process. It is recommended to cache your results.

#### Basic cache

Use the `Less_Cache` class to save and reuse the results of compiling LESS files.
This class will check the modified time and size of each LESS file (including imported files) and
either re-use or re-generate the CSS output accordingly.

The cache files are determinstically named, based on the full list of referenced LESS files and the metadata (file path, file mtime, file size) of each file. This means that each time a change is made, a different cache filename is used.

```php
$lessFiles = [ '/var/www/mysite/bootstrap.less' => '/mysite/' ];
$options = [ 'cache_dir' => '/var/www/writable_folder' ];
$cssOutputFile = Less_Cache::Get( $lessFiles, $options );
$css = file_get_contents( '/var/www/writable_folder/' . $cssOutputFile );
```

#### Caching with variables

Passing custom variables to `Less_Cache::Get()`:

```php
$lessFiles = [ '/var/www/mysite/bootstrap.less' => '/mysite/' ];
$options = [ 'cache_dir' => '/var/www/writable_folder' ];
$variables = [ 'width' => '100px' ];
$cssOutputFile = Less_Cache::Get( $lessFiles, $options, $variables );
$css = file_get_contents( '/var/www/writable_folder/' . $cssOutputFile );
```

#### Incremental caching

**Warning:** The incremental cache in Less_Parser is significantly slower and more memory-intense than `Less_Cache`! If you call Less.php during web requests, or otherwise call it repeatedly with files that might be unchanged, use `Less_Cache` instead.

You can turn off the incremental cache to save memory and disk space ([ref](https://github.com/wikimedia/less.php/issues/104)). Without incremental cache, cache misses may be slower.

```php
// Disable incremental cache
$lessFiles = [ __DIR__ . '/assets/bootstrap.less' => '/mysite/assets/' ];
$options = [
  'cache_dir' => '/var/www/writable_folder',
  'cache_incremental' => false,
];
$css = Less_Cache::Get( $lessFiles, $options );
```

When you instantiate Less_Parser, and call `parseFile()` or `getCss()`, it is assumed that at least one input file has changed (i.e. the whole-output cache from Less_Cache was a cache miss). The incremental cache exists to speed up cache misses on very large code bases, and is enabled by default when you call Less_Cache.

It is inherent to the Less language that later imports may change variables or extend mixins used in earlier files, and that imports may reference variables defined by earlier imports. Thus one can't actually cache the CSS output of an import. All inputs needs to be re-compiled together to produce the correct CSS output. The incremental cache merely allows the `parseFile()` method to skip parsing for unchanged files (i.e. interpreting Less syntax into an object structure). The `getCss()` method will still traverse and compile the representation of all input files.

## Source maps

Less.php supports v3 sourcemaps.

#### Inline

The sourcemap will be appended to the generated CSS file.

```php
$options = [ 'sourceMap' => true ];
$parser = new Less_Parser($options);
$parser->parseFile( '/var/www/mysite/bootstrap.less', '/mysite/' );
$css = $parser->getCss();
```

#### Saving to map file

```php
$options = [
   'sourceMap' => true,
   'sourceMapWriteTo' => '/var/www/mysite/writable_folder/filename.map',
   'sourceMapURL' => '/mysite/writable_folder/filename.map',
];
$parser = new Less_Parser($options);
$parser->parseFile( '/var/www/mysite/bootstrap.less', '/mysite/' );
$css = $parser->getCss();
```

## Command line

An additional script has been included to use the Less.php compiler from the command line.
In its simplest invocation, you specify an input file and the compiled CSS is written to standard out:

```
$ lessc input.less > output.css
```

By using the `-w` flag you can watch a specified input file and have it compile as needed to the output file:

```
$ lessc -w input.less output.css
```

Errors from watch mode are written to standard out.

For more information, run `lessc --help`
