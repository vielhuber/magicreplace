# ✨ magicreplace ✨

magicreplace is a search/replace class with zero overhead.

## Intro

### The problem

When moving databases, usually the url environment also changes.
If the URL is hardcoded in the database (like WordPress [does](https://make.wordpress.org/core/handbook/contribute/design-decisions/#absolute-versus-relative-urls)), those URLs have to be changed.
If you now do a search and replace on your entire database to change the URLs,
you will corrupt data that has been serialized. Just try out

```php
unserialize(str_replace('www.foo.tld', 'www.barrr.tld', serialize('url=www.foo.tld')));
```

and you will get an ugly error.

### There already exist cool tools that solve this issue, for example...

-   [Velvet Blues Update URLs](https://wordpress.org/plugins/velvet-blues-update-urls/)
-   [Better Search Replace](https://wordpress.org/plugins/better-search-replace/)
-   [Suchen & Ersetzen](https://de.wordpress.org/plugins/search-and-replace/)
-   [WP Migrate DB](https://de.wordpress.org/plugins/wp-migrate-db/)
-   [WP-CLI's search-replace](http://wp-cli.org/commands/search-replace/)
-   [Search-Replace-DB](https://github.com/interconnectit/Search-Replace-DB)
-   [SerPlace](http://pixelentity.com/wordpress-search-replace-domain/)

### How is magicreplace different from those tools?

-   Fast (~1sec runtime on 100mb database file with 300.000 rows)
-   Lightweight: only 7kb in size
-   Works also on big files with small memory limit settings
-   File based: does not need a database or a wp installation - works on plain (sql) files
-   Local usage: does not need a remote server or a webservice
-   Multi replace: does multiple replaces
-   Considers edge cases: Can handle objects and even references
-   Ignores classes that are not available at runtime
-   Can be used either with the command line or as a class
-   Acts carefully: If serialization fails, nothing is changed
-   Never changes data (out of bound ints are preserved, auto generated dates are not updated)
-   Does its work in junks to overcome php limits

### Disclaimer

This does not release you from taking backups. Use this script at your own risk!

## Command line

### Requirements

##### Mac

```
brew install coreutils
```

##### Windows

Runs out of the box with [WSL/WSL2](https://docs.microsoft.com/en-us/windows/wsl/about)/[Cygwin](https://cygwin.com/install.html).

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
magicreplace::run('input.sql', 'output.sql', ['search-1' => 'replace-2', 'search-2' => 'replace-2']);
```

## Recommended replace strategy

If you want for example to replace http://www.foo.tld with https://www.bar.tld, the safest method to do so is with the following replacements (in the given order):

-   http://www.foo.tld https://www.bar.tld
-   https://www.foo.tld https://www.bar.tld
-   http://foo.tld https://www.bar.tld
-   https://foo.tld https://www.bar.tld
-   www.foo.tld www.bar.tld
-   foo.tld bar.tld

## Testing

Just place these 3 files in a (optionally nested) subfolder of `tests/data`:

-   `input.sql`: The desired input file
-   `output.sql`: The desired output file
-   `settings.sql`: Define your replacements

Example `settings.sql` file:

```
{
    "replace": {
        "http://www.foo.tld": "https://www.bar.tld",
        "https://www.foo.tld": "https://www.bar.tld"
    }
}
```

If a test fails, the expected output is stored in `expected.sql`.

You can even auto generate test cases (that compares magicreplace to [Search-Replace-DB](https://github.com/interconnectit/Search-Replace-DB) and only gives you the diff) if you omit `input.sql` and `output.sql` and define a mysql database to dump from locally. Example `settings.sql` file:

```
{
    "source": {
        "host": "localhost",
        "port": "3306",
        "database": "xxx",
        "username": "xxx",
        "password": "xxx",
    },
    "replace": {
        "http://www.foo.tld": "https://www.bar.tld",
        "https://www.foo.tld": "https://www.bar.tld"
    }
}
```

`input.sql` and `output.sql` then gets generated automatically. You also can provide a `whitelist.sql` file that includes all lines from `input.sql` that should be ignored (e.g. where magicreplace acts differently from Search-Replace-DB).
