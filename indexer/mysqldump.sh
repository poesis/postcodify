#!/bin/sh

MYSQL_HOST="localhost"
MYSQL_USER=""
MYSQL_PASS=""
MYSQL_DBNAME=""

mysqldump --opt --order-by-primary --routines \
    --single_transaction --no-autocommit --skip-tz-utc \
    -h$MYSQL_HOST -u$MYSQL_USER -p$MYSQL_PASS \
    $MYSQL_DBNAME | sed 's/DEFINER=`.*`@`.*` //' \
    | gzip > dump.sql.gz

