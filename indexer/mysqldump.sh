#!/bin/sh

MYSQL_HOST="localhost"
MYSQL_USER="me"
MYSQL_PASS="me"
MYSQL_DBNAME="me_postcode"

mysqldump --opt --routines --single_transaction \
    -h$MYSQL_HOST -u$MYSQL_USER -p$MYSQL_PASS \
    $MYSQL_DBNAME | gzip > dump.sql.gz
