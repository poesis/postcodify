<?php

// -------------------------------------------------------------------------------------------------
// 우편번호 DB 점검 프로그램.
// -------------------------------------------------------------------------------------------------

ini_set('display_errors', 'on');
ini_set('memory_limit', '1024M');
date_default_timezone_set('UTC');
error_reporting(-1);
gc_enable();

// 설정과 함수 파일을 인클루드한다.

require dirname(__FILE__) . '/config.php';
require dirname(__FILE__) . '/functions.php';

// 체크리스트를 정의한다.

$checklist_indexes = array(
    'postcodify_addresses' => array('postcode6', 'postcode5', 'road_id', 'road_section', 'updated'),
    'postcodify_keywords_juso' => array('address_id', 'keyword_crc32', 'num_major', 'num_minor'),
    'postcodify_keywords_jibeon' => array('address_id', 'keyword_crc32', 'num_major', 'num_minor'),
    'postcodify_keywords_building' => array('address_id'),
    'postcodify_keywords_pobox' => array('address_id', 'keyword', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor'),
    'postcodify_keywords_synonyms' => array('original_crc32'),
    'postcodify_metadata' => array(),
);

$checklist_procs = array(
    'postcodify_get_synonym',
    'postcodify_search_juso',
    'postcodify_search_jibeon',
    'postcodify_search_building',
    'postcodify_search_building_with_dongri',
    'postcodify_search_pobox',
);

$checklist_data = array(
    'postcodify_addresses' => array(5000000),
    'postcodify_keywords_juso' => array(10000000),
    'postcodify_keywords_jibeon' => array(10000000),
    'postcodify_keywords_building' => array(500000),
    'postcodify_keywords_pobox' => array(1000),
    'postcodify_keywords_synonyms' => array(200000),
    'postcodify_metadata' => array(2),
);

// DB에 연결한다.

$db = get_db();
if (!$db)
{
    echo "DB에 연결할 수 없습니다.\n";
    exit(1);
}

// 모든 테이블이 존재하는지 확인한다.

echo "테이블 검사 중...\n";
$all_tables_exist = true;
$tables_query = $db->query("SHOW TABLES");
$tables = $tables_query->fetchAll(PDO::FETCH_NUM);

foreach ($checklist_indexes as $table_name => $indexes)
{
    $found = false;
    foreach ($tables as $table)
    {
        if ($table[0] === $table_name)
        {
            $found = true;
            break;
        }
    }
    if (!$found)
    {
        echo "  - $table_name 테이블이 없습니다.\n";
        $all_tables_exist = false;
    }
}

// 모든 인덱스가 존재하는지 확인한다.

echo "인덱스 검사 중...\n";
$all_indexes_exist = true;

foreach ($checklist_indexes as $table_name => $indexes)
{
    try
    {
        $table_indexes_query = $db->query("SHOW INDEX FROM $table_name");
        $table_indexes = $indexes_query->fetchAll(PDO::FETCH_NUM);
    }
    catch (PDOException $e)
    {
        echo "  - $table_name 테이블의 인덱스를 검사할 수 없습니다.\n";
        $all_indexes_exist = false;
        continue;
    }
    
    $pk_found = false;
    foreach ($table_indexes as $table_index)
    {
        if ($index[2] === 'PRIMARY')
        {
            $pk_found = true;
        }
    }
    if (!$pk_found)
    {
        echo "  - $table_name 테이블에 PRIMARY KEY가 없습니다.\n";
        $all_indexes_exist = false;
    }
    
    foreach ($indexes as $index)
    {
        $found = false;
        foreach ($table_indexes as $table_index)
        {
            if ($index[4] === $index_name)
            {
                $found = true;
                break;
            }
        }
        if (!$found)
        {
            echo "  - $table_name 테이블에 $index_name 인덱스가 없습니다.\n";
            $all_indexes_exist = false;
        }
    }
}

// 모든 저장 프로시저가 존재하는지 확인한다.

echo "저장 프로시저 검사 중...\n";
$all_procs_exist = true;
$procs_query = $db->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE() AND Type = 'PROCEDURE'");
$procs = $procs_query->fetchAll(PDO::FETCH_NUM);

foreach ($checklist_procs as $proc_name)
{
    $found = false;
    foreach ($procs as $proc)
    {
        if ($proc[1] === $proc_name)
        {
            $found = true;
            break;
        }
    }
    if (!$found)
    {
        echo "  - $proc_name 저장 프로시저가 없습니다.\n";
        $all_procs_exist = false;
    }
}

// 데이터를 검사한다.

if ($all_tables_exist && $all_indexes_exist && $all_procs_exist)
{
    echo "데이터 검사 중, 다소 시간이 걸릴 수 있습니다...\n";
    $data_ok = true;
    
    foreach ($checklist_data as $table_name => $checklist)
    {
        try
        {
            $count_query = $db->query("SELECT COUNT(*) FROM $table_name");
            $count = $count_query->fetchColumn();
            
            if ($count < $checklist[0])
            {
                echo "  - $table_name 테이블의 레코드 수가 부족합니다.\n";
                $data_ok = false;
            }
        }
        catch (PDOException $e)
        {
            echo "  - $table_name 테이블을 쿼리할 수 없습니다.\n";
            $data_ok = false;
        }
    }
}
else
{
    echo "데이터 검사는 시도하지 않습니다.\n";
    $data_ok = false;
}

// 상태를 출력한다.

if ($all_tables_exist && $all_indexes_exist && $all_procs_exist && $data_ok)
{
    echo "DB에 문제가 없습니다.\n";
    exit(0);
}
else
{
    echo "DB에 문제가 있습니다.\n";
    exit(1);
}
