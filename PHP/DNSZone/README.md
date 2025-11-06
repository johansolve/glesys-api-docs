## Synopsis

This project contains CLI tools for importing and exporting DNS zone files to/from your GleSYS account.

## Import - php_glesys_zone_import.php

Import a BIND-format DNS zone file to GleSYS DNS.

### Usage

php php_glesys_zone_import.php <DNS zone file> <api user> <api key> <optional: domain name>

### Author

The script was created by Tobias Rahm 2015-11-28

## Export - php_glesys_zone_export.php

Export a DNS zone from GleSYS to BIND-format zone file.

### Usage

php php_glesys_zone_export.php <domain> <api user> <api key> [output file]

If output file is not specified, the zone will be written to stdout.

### Examples

Export to file:
php php_glesys_zone_export.php example.com CL12345 your-api-key zone.txt

Export to stdout:
php php_glesys_zone_export.php example.com CL12345 your-api-key
