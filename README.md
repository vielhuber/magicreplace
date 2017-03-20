# magicreplace
A search/replace class with zero overhead.

There exist cool tools like...

* Velvet Blues Update URLs (https://wordpress.org/plugins/velvet-blues-update-urls/)
* Better Search Replace (https://wordpress.org/plugins/better-search-replace/)
* WP-CLI's search-replace (http://wp-cli.org/commands/search-replace/)
* Search and Replace for WordPress Databases Script (https://interconnectit.com/products/search-and-replace-for-wordpress-databases/)

How is magicreplace different from those tools?

* blazingly fast (~1sec runtime on 100mb database file with 300.000 lines)
* file based: does not need a database - works on plain (sql) files
* multi replace: does multiple replaces
* considers edge cases: objects inside serialized strings
