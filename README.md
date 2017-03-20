# magicreplace
A search/replace class with zero overhead.

What does magicreplace different from tools like...
Velvet Blues Update URLs (https://wordpress.org/plugins/velvet-blues-update-urls/)
Better Search Replace (https://wordpress.org/plugins/better-search-replace/)
WP-CLI's search-replace (http://wp-cli.org/commands/search-replace/)
Search and Replace for WordPress Databases Script (https://interconnectit.com/products/search-and-replace-for-wordpress-databases/)

- blazingly fast (~1sec runtime on 100mb database file)
- file based: works only on sql files (spitted out with mysqldump)
- multi replace: does multiple replaces in one call
- considers edge cases: objects inside serialized strings
