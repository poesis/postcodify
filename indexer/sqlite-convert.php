<?php

// -------------------------------------------------------------------------------------------------
// MySQL로 생성한 우편번호 DB를 SQLite로 변환하는 프로그램.
// -------------------------------------------------------------------------------------------------

ini_set('display_errors', 'on');
ini_set('memory_limit', '1024M');
date_default_timezone_set('UTC');
error_reporting(-1);
gc_enable();

// 시작한 시각을 기억한다.

$start_time = time();

// 설정과 함수 파일을 인클루드한다.

require dirname(__FILE__) . '/config.php';
require dirname(__FILE__) . '/functions.php';

// MySQL DB에 접속한다.

$mysql = get_db();
if (!$mysql)
{
    echo "MySQL DB에 접속할 수 없습니다.\n";
    exit(1);
}

// SQLite 파일명을 구한다.

if (isset($argv[1]))
{
    $filename = $argv[1];
    file_put_contents($filename, '');
}
else
{
    echo "파일명을 지정해 주십시오.\n";
    exit(1);
}

// SQLite DB를 초기화한다.

echo "SQLite DB를 초기화하는 중...\n";
$sqlite = new PDO('sqlite:' . $filename);
$sqlite->exec('PRAGMA page_size = 4096');
$sqlite->exec('PRAGMA synchronous = OFF');
$sqlite->exec('PRAGMA journal_mode = OFF');
$sqlite->exec('PRAGMA encoding = "UTF-8"');
$sqlite->exec(file_get_contents(__DIR__ . '/resources/schema-sqlite.sql'));

// 테이블을 복사한다.

$sqlite_tables = array(
    'postcodify_addresses' => array(21, 'id'),
    'postcodify_keywords_juso' => array(5, 'seq'),
    'postcodify_keywords_jibeon' => array(5, 'seq'),
    'postcodify_keywords_building' => array(3, 'seq'),
    'postcodify_keywords_pobox' => array(7, 'seq'),
    'postcodify_keywords_synonyms' => array(3, 'seq'),
    'postcodify_metadata' => array(2, 'k'),
);

foreach ($sqlite_tables as $table_name => $table_info)
{
    echo "$table_name 데이터 복사 중...   ";
    $row_count_query = $mysql->query('SELECT COUNT(*) FROM ' . $table_name);
    $row_count = intval($row_count_query->fetchColumn());
    echo str_pad(number_format(0), 10, ' ', STR_PAD_LEFT) . ' / ' . str_pad(number_format($row_count), 10, ' ', STR_PAD_LEFT);
    
    $columns_placeholder = implode(', ', array_fill(0, $table_info[0], '?'));
    $primary_key = $table_info[1];
    $last_primary_key = 0;
    $increment = 2048;
    
    $ps = $sqlite->prepare('INSERT INTO ' . $table_name . ' VALUES (' . $columns_placeholder . ')');
    
    for ($i = 0; $i < $row_count; $i += $increment)
    {
        $sqlite->beginTransaction();
        $query = $mysql->prepare('SELECT * FROM ' . $table_name . ' WHERE ' . $primary_key . ' > ? ORDER BY ' . $primary_key . ' LIMIT ' . $increment);
        $query->execute(array($last_primary_key));
        while ($row = $query->fetch(PDO::FETCH_NUM))
        {
            $last_primary_key = $row[0];
            $ps->execute($row);
        }
        $sqlite->commit();
        echo "\033[23D" . str_pad(number_format($i), 10, ' ', STR_PAD_LEFT) . ' / ' . str_pad(number_format($row_count), 10, ' ', STR_PAD_LEFT);
    }
    
    echo "\033[23D" . str_pad(number_format($row_count), 10, ' ', STR_PAD_LEFT) . ' / ' . str_pad(number_format($row_count), 10, ' ', STR_PAD_LEFT);
    echo "\n";
}

$elapsed = time() - $start_time;
$elapsed_hours = floor($elapsed / 3600);
$elapsed = $elapsed - ($elapsed_hours * 3600);
$elapsed_minutes = floor($elapsed / 60);
$elapsed_seconds = $elapsed - ($elapsed_minutes * 60);

echo '데이터 복사를 마쳤습니다. 경과 시간 : ';
if ($elapsed_hours) echo $elapsed_hours . '시간 ';
if ($elapsed_hours || $elapsed_minutes) echo $elapsed_minutes . '분 ';
echo $elapsed_seconds . '초' . "\n";

// 인덱스를 생성한다.

$sqlite_indexes = array(
    'postcodify_addresses' => array('postcode6', 'postcode5'),
    'postcodify_keywords_juso' => array('address_id', 'keyword_crc32', 'num_major', 'num_minor'),
    'postcodify_keywords_jibeon' => array('address_id', 'keyword_crc32', 'num_major', 'num_minor'),
    'postcodify_keywords_building' => array('address_id'),
    'postcodify_keywords_pobox' => array('address_id', 'keyword', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor'),
    'postcodify_keywords_synonyms' => array('original_crc32'),
);

foreach ($sqlite_indexes as $table_name => $columns)
{
    foreach ($columns as $column)
    {
        echo $table_name . '_' . $column . " 인덱스 생성 중...\n";
        $sqlite->exec('CREATE INDEX ' . $table_name . '_' . $column . ' ON ' . $table_name . ' (' . $column . ')');
    }
}

$sqlite->exec('ANALYZE');

$elapsed = time() - $start_time;
$elapsed_hours = floor($elapsed / 3600);
$elapsed = $elapsed - ($elapsed_hours * 3600);
$elapsed_minutes = floor($elapsed / 60);
$elapsed_seconds = $elapsed - ($elapsed_minutes * 60);

echo '인덱스 생성을 마쳤습니다. 경과 시간 : ';
if ($elapsed_hours) echo $elapsed_hours . '시간 ';
if ($elapsed_hours || $elapsed_minutes) echo $elapsed_minutes . '분 ';
echo $elapsed_seconds . '초' . "\n";
