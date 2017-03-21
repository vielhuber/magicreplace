# 🔍 magicreplace 🔍

magicreplace is a search/replace class with zero overhead.

## Intro

### There already exist cool tools like...

* [Velvet Blues Update URLs](https://wordpress.org/plugins/velvet-blues-update-urls/)
* [Better Search Replace](https://wordpress.org/plugins/better-search-replace/)
* [WP-CLI's search-replace](http://wp-cli.org/commands/search-replace/)
* [Search and Replace for WordPress Databases Script](https://interconnectit.com/products/search-and-replace-for-wordpress-databases/)

### How is magicreplace different from those tools?

* blazingly fast (~1sec runtime on 100mb database file with 300.000 lines)
* lightweight: only 2kb lines of code
* file based: does not need a database or a wp installation - works on plain (sql) files
* multi replace: does multiple replaces
* considers edge cases: objects inside serialized strings

## Usage as class

### Installation

```
composer require vielhuber/magicreplace
```
    
### Usage

```php
require __DIR__ . '/vendor/autoload.php';
use vielhuber\magicreplace\MagicReplace;
MagicReplace::run('input.sql','output.sql',['search-1'=>'replace-2','search-2'=>'replace-2']);
```

## Usage via command line

### Installation

```
wget https://raw.githubusercontent.com/vielhuber/magicreplace/master/magicreplace
```

### Usage

```
php magicreplace input.sql output.sql search-1 replace-1 search-2 replace-2
```
