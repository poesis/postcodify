<?php

// 텍스트 파일들이 저장되어 있는 경로를 지정한다.

define('TXT_DIRECTORY', dirname(dirname(__FILE__)) . '/data');

// DB 접속 정보를 지정한다. 인덱서 사용시에는 MySQL밖에 지원하지 않는다.
// SQLite를 사용하려면 일단 MySQL DB를 생성한 후 sqlite-convert.php로 변환해야 한다.

define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', '');
define('DB_PASS', '');
define('DB_DBNAME', '');
