#!/bin/sh

MYSQL_HOST="localhost"
MYSQL_USER=""
MYSQL_PASS=""
MYSQL_DBNAME=""

mysqldump --opt --order-by-primary \
    --single-transaction --no-autocommit --skip-tz-utc \
    -h$MYSQL_HOST -u$MYSQL_USER -p$MYSQL_PASS \
    $MYSQL_DBNAME | gzip > dump.sql.gz
