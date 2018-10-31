# ✨ magicreplace ✨

magicreplace is a search/replace class with zero overhead.

## Intro

### The problem

When moving databases, usually the url environment also changes.
If the URL is hardcoded in the database (like WordPress [does](https://make.wordpress.org/core/handbook/contribute/design-decisions/#absolute-versus-relative-urls)), those URLs have to be changed.
If you now do a search and replace on your entire database to change the URLs,
you will corrupt data that has been serialized. Just try out
```php
unserialize(str_replace('www.foo.tld','www.barrr.tld',serialize('url=www.foo.tld')));
```
and you will get an ugly error.

### There already exist cool tools that solve this issue, for example...

* [Velvet Blues Update URLs](https://wordpress.org/plugins/velvet-blues-update-urls/)
* [Better Search Replace](https://wordpress.org/plugins/better-search-replace/)
* [Suchen & Ersetzen](https://de.wordpress.org/plugins/search-and-replace/)
* [WP-CLI's search-replace](http://wp-cli.org/commands/search-replace/)
* [Search and Replace for WordPress Databases Script](https://interconnectit.com/products/search-and-replace-for-wordpress-databases/)
* [SerPlace](http://pixelentity.com/wordpress-search-replace-domain/)

### How is magicreplace different from those tools?

* Fast (~1sec runtime on 100mb database file with 300.000 rows)
* Lightweight: only 7kb in size
* Works also on big files with small memory limit settings
* File based: does not need a database or a wp installation - works on plain (sql) files
* Local usage: does not need a remote server or a webservice
* Multi replace: does multiple replaces
* Considers edge cases: Can handle objects and even references
* Ignores classes that are not available at runtime
* Can be used either with the command line or as a class
* Acts carefully: If serialization fails, nothing is changed
* Does its work in junks to overcome php limits

### Disclaimer

This does not release you from taking backups. Use this script at your own risk.

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

