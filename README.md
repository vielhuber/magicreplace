# ✨ magicreplace ✨

magicreplace is a search/replace class with zero overhead.

## Intro

### There already exist cool tools like...

* [Velvet Blues Update URLs](https://wordpress.org/plugins/velvet-blues-update-urls/)
* [Better Search Replace](https://wordpress.org/plugins/better-search-replace/)
* [WP-CLI's search-replace](http://wp-cli.org/commands/search-replace/)
* [Search and Replace for WordPress Databases Script](https://interconnectit.com/products/search-and-replace-for-wordpress-databases/)

### How is magicreplace different from those tools?

* Blazingly fast (~1sec runtime on 100mb database file with 300.000 rows)
* Lightweight: only 7kb in size
* File based: does not need a database or a wp installation - works on plain (sql) files
* Multi replace: does multiple replaces
* Considers edge cases: objects/classes inside serialized strings
* Can be used either with the command line or as a class

## Command line

### Installation

```
wget https://raw.githubusercontent.com/vielhuber/magicreplace/master/src/magicreplace.php
```

### Usage

```
php magicreplace.php input.sql output.sql search-1 replace-1 search-2 replace-2
```


## Class

### Installation

```
composer require vielhuber/magicreplace
```
    
### Usage

```php
<?php
require __DIR__ . '/vendor/autoload.php';
use vielhuber\magicreplace\magicreplace;
magicreplace::run('input.sql','output.sql',['search-1'=>'replace-2','search-2'=>'replace-2']);
```

