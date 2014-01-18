<?php

// -------------------------------------------------------------------------------------------------
// 우편번호 DB 생성 프로그램.
// -------------------------------------------------------------------------------------------------

ini_set('display_errors', 'on');
ini_set('memory_limit', '1024M');
date_default_timezone_set('UTC');
error_reporting(-1);
gc_enable();

// 시작한 시각을 기억한다.

$start_time = time();

// 설정과 함수 파일을 인클루드한다.

define('CONVERTING', 'YES');
require dirname(__FILE__) . '/config.php';
require dirname(__FILE__) . '/functions.php';
require dirname(__FILE__) . '/update.php';
echo "\n";

// 필요한 기능이 모두 있는지 확인한다.

if (version_compare(PHP_VERSION, '5.3.7', '<'))
{
    echo '[ERROR] PHP 버전은 5.3.7 이상이어야 합니다.' . "\n\n";
    exit(1);
}

if (strtolower(PHP_SAPI) !== 'cli')
{
    echo '[ERROR] 이 프로그램은 명령줄(CLI)에서 실행되어야 합니다.' . "\n\n";
    exit(1);
}

if (!class_exists('PDO') || !in_array('mysql', PDO::getAvailableDrivers()))
{
    echo '[ERROR] PDO 모듈이 설치되지 않았거나 MySQL 드라이버를 사용할 수 없습니다.' . "\n\n";
    exit(1);
}

if (!class_exists('ZipArchive'))
{
    echo '[ERROR] Zip 모듈이 설치되어 있지 않습니다.' . "\n\n";
    exit(1);
}

if (!function_exists('iconv') || !function_exists('mb_check_encoding'))
{
    echo '[ERROR] iconv 및 mbstring 모듈이 설치되어 있지 않습니다.' . "\n\n";
    exit(1);
}

if (!function_exists('pcntl_fork') || !function_exists('pcntl_wait'))
{
    echo '[ERROR] pcntl_* 함수가 없거나 php.ini에서 막아 놓았습니다.' . "\n\n";
    exit(1);
}

// 필요한 파일이 모두 있는지 확인한다.

if (!file_exists(TXT_DIRECTORY . '/도로명코드_전체분.zip'))
{
    echo '[ERROR] 도로명코드_전체분.zip 파일을 찾을 수 없습니다.' . "\n\n";
    exit(1);
}

if (count(glob(TXT_DIRECTORY . '/주소_*.zip')) < 1)
{
    echo '[ERROR] 주소_*.zip 파일을 찾을 수 없거나 일부 누락되었습니다.' . "\n\n";
    exit(1);
}

if (count(glob(TXT_DIRECTORY . '/지번_*.zip')) < 1)
{
    echo '[ERROR] 지번_*.zip 파일을 찾을 수 없거나 일부 누락되었습니다.' . "\n\n";
    exit(1);
}

if (count(glob(TXT_DIRECTORY . '/부가정보_*.zip')) < 1)
{
    echo '[ERROR] 부가정보_*.zip 파일을 찾을 수 없거나 일부 누락되었습니다.' . "\n\n";
    exit(1);
}

if (!file_exists(TXT_DIRECTORY . '/newaddr_pobox_DB.zip'))
{
    echo '[ERROR] 사서함 (newaddr_pobox_DB.zip) 파일을 찾을 수 없습니다.' . "\n\n";
    exit(1);
}

// -------------------------------------------------------------------------------------------------
// DB에 연결하고 테이블 및 검색 프로시저를 생성한다.
// -------------------------------------------------------------------------------------------------

echo '[Step 1/8] 테이블과 프로시저를 생성하는 중 ... ' . "\n\n";

get_db()->exec(file_get_contents(__DIR__ . '/schema.sql'));

// -------------------------------------------------------------------------------------------------
// 전체 도로명 코드 및 소속 행정구역 데이터를 구하여 입력한다.
// -------------------------------------------------------------------------------------------------

echo '[Step 2/8] 도로 목록을 메모리에 읽어들이는 중 ... ' . "\n\n";

$roads = array();
$roads_count = 0;

// 파일을 연다.

$filename = TXT_DIRECTORY . '/도로명코드_전체분.zip';
echo '  -->  ' . basename($filename) . ' ... ' . str_repeat(' ', 10);

$zip = new ZipArchive;
$zip->open($filename);
$fp = $zip->getStream($zip->getNameIndex(0));

while ($line = trim(fgets($fp)))
{
    // 한 줄을 읽어 UTF-8로 변환하고, | 문자를 기준으로 데이터를 쪼갠다.
    
    $line = explode('|', iconv('EUC-KR', 'UTF-8', $line));
    if (count($line) < 17 || !ctype_digit($line[0])) continue;
    
    // 도로ID, 도로명, 통과하는 읍면동별 구간번호, 소속 행정구역을 읽어들인다.
    
    $road_id = trim($line[0]);
    $road_name = trim($line[1]);
    $road_section = str_pad(trim($line[3]), 2, '0', STR_PAD_LEFT);
    $sido = trim($line[4]);
    $sigungu = trim($line[6]);
    $eupmyeon = trim($line[8]);
    
    // 동 정보는 여기서 기억할 필요가 없다.
    
    $eupmyeon_suffix = strlen($eupmyeon) > 3 ? substr($eupmyeon, strlen($eupmyeon) - 3) : null;
    if ($eupmyeon_suffix !== '읍' && $eupmyeon_suffix !== '면')
    {
        $eupmyeon = '';
    }
    
    // 특별시/광역시 아래의 자치구와 행정시 아래의 일반구를 구분한다.
    
    if (($pos = strpos($sigungu, ' ')) !== false)
    {
        $ilbangu = substr($sigungu, $pos + 1);
        $sigungu = substr($sigungu, 0, $pos);
    }
    else
    {
        $ilbangu = '';
    }
    
    // 도로 정보를 메모리에 저장한다.
    
    $road_info = $road_name . '|' . $sido . '|' . $sigungu . '|' . $ilbangu . '|' . $eupmyeon;
    $roads[$road_id][$road_section] = $road_info;
    
    // 상태를 표시한다.
    
    if ($roads_count % 1024 == 0)
    {
        echo "\033[10D" . str_pad(number_format($roads_count, 0), 10, ' ', STR_PAD_LEFT);
    }
    
    $roads_count++;
    
    // 가비지 컬렉션.
    
    unset($road_info);
    unset($line);
}

// 마무리...

echo "\033[10D" . str_pad(number_format($roads_count, 0), 10, ' ', STR_PAD_LEFT) . "\n\n";
$zip->close();
unset($zip);

// -------------------------------------------------------------------------------------------------
// 시도별 주소 파일에서 도로명주소 데이터를 구하여 입력한다.
// -------------------------------------------------------------------------------------------------

echo '[Step 3/8] 쓰레드를 사용하여 "주소" 파일을 로딩하는 중 ... ' . "\n\n";

$files = glob(TXT_DIRECTORY . '/주소_*.zip');
$children = array();

// 파일마다 별도의 쓰레드를 시작한다.

while (count($files))
{
    $filename = array_shift($files);
    $pid = pcntl_fork();
    
    if ($pid == -1)
    {
        echo '[ERROR] 쓰레드를 생성할 수 없습니다.' . "\n\n";
        exit(1);
    }
    elseif ($pid > 0)
    {
        echo '  -->  ' . basename($filename) . ' 쓰레드 시작 ... ' . "\n";
        $children[$pid] = $filename;
    }
    else
    {
        // 쓰레드를 초기화한다.
        
        $db = get_db();
        $ps_address_insert = $db->prepare('INSERT INTO postcode_addresses ' .
            '(id, postcode5, postcode6, road_id, road_section, road_name, ' .
            'num_major, num_minor, is_basement, sido, sigungu, ilbangu, eupmyeon) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ps_keyword_insert = $db->prepare('INSERT INTO postcode_keywords_juso ' .
            '(address_id, keyword_crc32, num_major, num_minor) ' .
            'VALUES (?, ?, ?, ?)');
        
        // 트랜잭션을 시작한다.
        
        $db->beginTransaction();
        
        // 파일을 연다.
        
        $zip = new ZipArchive;
        $zip->open($filename);
        for ($fi = 0; $fi < $zip->numFiles; $fi++)
        {
            $fp = $zip->getStream($zip->getNameIndex($fi));
            while ($line = trim(fgets($fp)))
            {
                // 한 줄을 읽어 UTF-8로 변환하고, | 문자를 기준으로 데이터를 쪼갠다.
                
                $line = explode('|', iconv('EUC-KR', 'UTF-8', $line));
                if (count($line) < 11 || !ctype_digit($line[0])) continue;
                
                // 상세 데이터를 읽어들인다.
                
                $address_id = trim($line[0]);
                $postcode5 = trim($line[6]);
                $road_id = trim($line[1]);
                $road_section = str_pad(trim($line[2]), 2, '0', STR_PAD_LEFT);
                $num_major = (int)trim($line[4]); if (!$num_major) $num_major = null;
                $num_minor = (int)trim($line[5]); if (!$num_minor) $num_minor = null;
                $is_basement = (int)trim($line[3]);
                
                // 아까 읽어들인 도로명코드에 따라 시도, 시군구 등의 정보를 입력한다.
                
                if (isset($roads[$road_id][$road_section]))
                {
                    $road_info = $roads[$road_id][$road_section];
                    if (!$road_info) continue;
                }
                else
                {
                    continue;
                }
                
                $road_info = explode('|', $road_info);
                $road_name = $road_info[0];
                $sido = $road_info[1];
                $sigungu = $road_info[2]; if ($sigungu === '') $sigungu = null;
                $ilbangu = $road_info[3]; if ($ilbangu === '') $ilbangu = null;
                $eupmyeon = $road_info[4]; if ($eupmyeon === '') $eupmyeon = null;
                
                // postcode_addresses 테이블에 삽입한다.
                
                $ps_address_insert->execute(array($address_id, $postcode5, null, $road_id, $road_section, $road_name,
                    $num_major, $num_minor, $is_basement, $sido, $sigungu, $ilbangu, $eupmyeon));
                
                // 검색 키워드들을 정리하여 postcode_keywords_road 테이블에 삽입한다.
                
                $keywords = get_variations_of_road_name(get_canonical($road_name));
                foreach ($keywords as $keyword)
                {
                    $ps_keyword_insert->execute(array($address_id, crc32_x64($keyword), $num_major, $num_minor));
                }
                
                // 가비지 컬렉션.
                
                unset($road_info);
                unset($keywords);
                unset($line);
            }
        }
        
        // 트랜잭션을 마친다.
        
        $zip->close();
        $db->commit();
        exit;
    }
}

// 쓰레드들이 작업을 마치기를 기다린다.

echo "\n";

while (count($children))
{
    $pid = pcntl_wait($status, WNOHANG | WUNTRACED);
    if ($pid)
    {
        echo '  <--  ' . basename($children[$pid]) . ' 쓰레드 종료. ';
        echo (count($children) - 1) . ' 쓰레드 남음.' . "\n";
        unset($children[$pid]);
    }
    sleep(1);
}

echo "\n";

// 도로 목록은 더이상 필요하지 않으므로 메모리에서 삭제한다.

unset($roads);

// -------------------------------------------------------------------------------------------------
// 시도별 지번 파일에서 지번주소와 도로명주소간의 맵핑 데이터를 구하여 입력한다.
// -------------------------------------------------------------------------------------------------

echo '[Step 4/8] 쓰레드를 사용하여 "지번" 파일을 로딩하는 중 ... ' . "\n\n";

$files = glob(TXT_DIRECTORY . '/지번_*.zip');
$children = array();

// 파일마다 별도의 쓰레드를 시작한다.

while (count($files))
{
    $filename = array_shift($files);
    $pid = pcntl_fork();
    
    if ($pid == -1)
    {
        echo '[ERROR] 쓰레드를 생성할 수 없습니다.' . "\n\n";
        exit(1);
    }
    elseif ($pid > 0)
    {
        echo '  -->  ' . basename($filename) . ' 쓰레드 시작 ... ' . "\n";
        $children[$pid] = $filename;
    }
    else
    {
        // 쓰레드를 초기화한다.
        
        $db = get_db();
        $ps_address_update1 = $db->prepare('UPDATE postcode_addresses ' .
            'SET dongri = ?, jibeon = ? ' .
            'WHERE id = ?');
        $ps_address_update2 = $db->prepare('UPDATE postcode_addresses ' .
            'SET other_addresses = CONCAT_WS(\'\\n\', other_addresses, ?) ' .
            'WHERE id = ?');
        $ps_keyword_insert = $db->prepare('INSERT INTO postcode_keywords_jibeon ' .
            '(address_id, keyword_crc32, num_major, num_minor) ' .
            'VALUES (?, ?, ?, ?)');
        
        $dongs[$filename] = array();
        
        // 트랜잭션을 시작한다.
        
        $db->beginTransaction();
        
        // 파일을 연다.
        
        $zip = new ZipArchive;
        $zip->open($filename);
        for ($fi = 0; $fi < $zip->numFiles; $fi++)
        {
            $fp = $zip->getStream($zip->getNameIndex($fi));
            while ($line = trim(fgets($fp)))
            {
                // 한 줄을 읽어 UTF-8로 변환하고, | 문자를 기준으로 데이터를 쪼갠다.
                
                $line = explode('|', iconv('EUC-KR', 'UTF-8', $line));
                if (count($line) < 11 || !ctype_digit($line[0])) continue;
                
                // 상세 데이터를 읽어들인다.
                
                $address_id = trim($line[0]);
                $dongri = trim($line[6]);
                if ($dongri === '') $dongri = trim($line[5]);
                
                $num_major = (int)trim($line[8]); if (!$num_major) $num_major = null;
                $num_minor = (int)trim($line[9]); if (!$num_minor) $num_minor = null;
                $is_mountain = (int)trim($line[7]);
                $is_canonical = (int)trim($line[10]);
                
                // postcode_addresses 테이블의 해당 레코드에 법정동 및 지번 정보를 추가한다.
                
                $insert_jibeon = (($is_mountain ? '산' : '') . $num_major . ($num_minor ? ('-' . $num_minor) : ''));
                
                if ($is_canonical)
                {
                    $ps_address_update1->execute(array($dongri, $insert_jibeon, $address_id));
                }
                else
                {
                    $ps_address_update2->execute(array($dongri . ' ' . $insert_jibeon, $address_id));
                }
                
                // 검색 키워드들을 정리하여 postcode_keywords_jibeon 테이블에 삽입한다.
                
                $keywords = get_variations_of_dongri($dongri, $dongs[$filename]);
                foreach ($keywords as $keyword)
                {
                    $ps_keyword_insert->execute(array($address_id, crc32_x64($keyword), $num_major, $num_minor));
                }
                
                // 가비지 컬렉션.
                
                unset($keywords);
                unset($line);
            }
        }
        
        // 트랜잭션을 마친다.
        
        unset($dongs[$filename]);
        $zip->close();
        $db->commit();
        exit;
    }
}

// 쓰레드들이 작업을 마치기를 기다린다.

echo "\n";

while (count($children))
{
    $pid = pcntl_wait($status, WNOHANG | WUNTRACED);
    if ($pid)
    {
        echo '  <--  ' . basename($children[$pid]) . ' 쓰레드 종료. ';
        echo (count($children) - 1) . ' 쓰레드 남음.' . "\n";
        unset($children[$pid]);
    }
    sleep(1);
}

echo "\n";

// -------------------------------------------------------------------------------------------------
// 시도별 부가정보 파일에서 행정동명, 건물명, 6자리 우편번호를 구하여 입력한다.
// -------------------------------------------------------------------------------------------------

echo '[Step 5/8] 쓰레드를 사용하여 "부가정보" 파일을 로딩하는 중 ... ' . "\n\n";

$files = glob(TXT_DIRECTORY . '/부가정보_*.zip');
$children = array();

// 파일마다 별도의 쓰레드를 시작한다.

while (count($files))
{
    $filename = array_shift($files);
    $pid = pcntl_fork();
    
    if ($pid == -1)
    {
        echo '[ERROR] 쓰레드를 생성할 수 없습니다.' . "\n\n";
        exit(1);
    }
    elseif ($pid > 0)
    {
        echo '  -->  ' . basename($filename) . ' 쓰레드 시작 ... ' . "\n";
        $children[$pid] = $filename;
    }
    else
    {
        // 쓰레드를 초기화한다.
        
        $db = get_db();
        $ps_address_select = $db->prepare('SELECT dongri, jibeon, other_addresses ' .
            'FROM postcode_addresses ' .
            'WHERE id = ?');
        $ps_address_update = $db->prepare('UPDATE postcode_addresses ' .
            'SET postcode6 = ?, building_name = ?, other_addresses = ? ' .
            'WHERE id = ?');
        $ps_keyword_jibeon_insert = $db->prepare('INSERT INTO postcode_keywords_jibeon ' .
            '(address_id, keyword_crc32, num_major, num_minor) ' .
            'VALUES (?, ?, ?, ?)');
        $ps_keyword_building_insert = $db->prepare('INSERT INTO postcode_keywords_building ' .
            '(address_id, keyword, dongri_crc32_1, dongri_crc32_2, dongri_crc32_3, dongri_crc32_4) ' .
            'VALUES (?, ?, ?, ?, ?, ?)');
        
        $dongs[$filename] = array();
        
        // 트랜잭션을 시작한다.
        
        $db->beginTransaction();
        
        // 파일을 연다.
        
        $zip = new ZipArchive;
        $zip->open($filename);
        for ($fi = 0; $fi < $zip->numFiles; $fi++)
        {
            $fp = $zip->getStream($zip->getNameIndex($fi));
            while ($line = trim(fgets($fp)))
            {
                // 한 줄을 읽어 UTF-8로 변환하고, | 문자를 기준으로 데이터를 쪼갠다.
                
                $line = explode('|', iconv('EUC-KR', 'UTF-8', $line));
                if (count($line) < 9 || !ctype_digit($line[0])) continue;
                
                // 상세 데이터를 읽어들인다.
                
                $address_id = trim($line[0]);
                $postcode6 = trim($line[3]);
                $admin_dong = trim($line[2]);
                $building1 = trim($line[5]);
                $building2 = trim($line[6]);
                $building3 = trim($line[7]);
                $is_common_residence = (int)trim($line[8]);
                
                // 공동주택명을 구한다.
                
                $building_name = ($is_common_residence && $building2 !== '') ? $building2 : null;
                if ($building_name !== null && preg_match('/^(.+)[0-9a-zA-Z-]+동$/uU', $building_name, $matches))
                {
                    $building_name = trim($matches[1]);
                }
                
                // 지번 및 기타주소 정리 : 해당 주소와 연관된 모든 지번을 구한다.
                
                $ps_address_select->execute(array($address_id));
                list($legal_dong, $legal_jibeon, $other_addresses) = $ps_address_select->fetch(PDO::FETCH_NUM);
                $ps_address_select->closeCursor();
                $other_addresses = strlen($other_addresses) ? explode("\n", $other_addresses) : array();
                
                // 지번 및 기타주소 정리 : 지번 주소를 동별로 묶어서 재구성한다.
                
                $addresses_numeric = array();
                $addresses_building = array();
                $keywords_nums = array($legal_jibeon);
                
                foreach ($other_addresses as $other)
                {
                    $other = explode(' ', $other);
                    if (count($other) < 2) continue;
                    $addresses_numeric[$other[0]][] = $other[1];
                    $keywords_nums[] = str_replace('산', '', $other[1]);
                }
                
                // 지번 및 기타주소 정리 : 행정동명을 추가한다.
                
                if ($admin_dong !== '' && $legal_dong !== '' && $admin_dong !== $legal_dong)
                {
                    if (!preg_match('/[시군구읍면리]$/u', $admin_dong)) $addresses_building[] = $admin_dong;
                }
                
                // 지번 및 기타주소 정리 : 건물 이름들을 추가한다.
                
                if ($building1 !== '') $addresses_building[] = str_replace(';', ':', $building1);
                if ($building2 !== '') $addresses_building[] = str_replace(';', ':', $building2);
                if ($building3 !== '') $addresses_building[] = str_replace(';', ':', $building3);
                
                $addresses_building = array_unique($addresses_building);
                
                // 지번 및 기타주소 정리 : 지번 주소를 동별로 묶고 지번순으로 정렬하여 재입력한다.
                
                $other_addresses = array();
                foreach ($addresses_numeric as $dongname => $numbers)
                {
                    natsort($numbers);
                    $other_addresses[] = $dongname . ' ' . implode(', ', $numbers);
                }
                
                // 지번 및 기타주소 정리 : 건물명 중 중복되는 것은 빼고 재입력한다.
                
                natsort($addresses_building);
                foreach ($addresses_building as $key => $address)
                {
                    foreach ($addresses_building as $key2 => $address2)
                    {
                        if ($key !== $key2 && strpos($address2, $address) !== false)
                        {
                            unset($addresses_building[$key]);
                        }
                        elseif ($address === $building_name)
                        {
                            unset($addresses_building[$key]);
                        }
                    }
                    
                    if (isset($addresses_building[$key])) $other_addresses[] = $address;
                }
                
                // 정리된 데이터를 DB에 다시 저장한다.
                
                $ps_address_update->execute(array(
                    $postcode6,
                    $building_name,
                    implode('; ', $other_addresses),
                    $address_id,
                ));
                
                // 행정동이 있는 경우 검색 키워드 목록에 추가한다.
                
                if ($admin_dong !== '')
                {
                    // 행정동명에 다양한 변형을 가해 키워드 목록을 구한다.
                    
                    $keywords_dongs = get_variations_of_dongri($admin_dong, $dongs[$filename]);
                    $keywords_dongs = array_combine($keywords_dongs, $keywords_dongs);
                    
                    // 이미 키워드로 등록된 법정동명은 제외한다.
                    
                    if ($legal_dong !== '')
                    {
                        $legal_dong_variations = get_variations_of_dongri($legal_dong, $dongs[$filename]);
                        foreach ($legal_dong_variations as $legal_dong_variation)
                        {
                            if (isset($keywords_dongs[$legal_dong_variation]))
                            {
                                unset($keywords_dongs[$legal_dong_variation]);
                            }
                        }
                    }
                }
                else
                {
                    $keywords_dongs = array();
                }
                
                // 건물명이 있는 경우 검색 키워드 목록에 추가한다.
                
                $keywords = array();
                if ($building1 !== '')
                {
                    $keywords = $keywords + get_variations_of_building_name(get_canonical($building1));
                }
                if ($building2 !== '')
                {
                    $keywords = $keywords + get_variations_of_building_name(get_canonical($building2));
                }
                if ($building3 !== '')
                {
                    $keywords = $keywords + get_variations_of_building_name(get_canonical($building3));
                }
                
                $keywords = array_unique($keywords);
                $keywords_nums = array_unique($keywords_nums);
                
                // 지번 검색 키워드들을 postcode_keywords_jibeon 테이블에 삽입한다.
                
                foreach ($keywords_dongs as $keyword)
                {
                    foreach ($keywords_nums as $num)
                    {
                        $num = explode('-', $num); if (!isset($num[1])) $num[1] = null;
                        $ps_keyword_jibeon_insert->execute(array($address_id, crc32_x64($keyword), $num[0], $num[1]));
                    }
                }
                
                // 건물명 검색 키워드들을 postcode_keywords_building 테이블에 삽입한다.
                
                $dongris = array();
                if ($legal_dong) $dongris[] = crc32_x64($legal_dong);
                if ($admin_dong)
                {
                    $dongris2 = get_variations_of_dongri($admin_dong, $dongs[$filename]);
                    foreach ($dongris2 as $dongri2) $dongris[] = crc32_x64($dongri2);
                    $dongris = array_values(array_unique($dongris));
                }
                $dongris[] = null; $dongris[] = null; $dongris[] = null; $dongris[] = null;
                
                foreach ($keywords as $keyword)
                {
                    if (isset($keywords_dongs[$keyword])) continue;
                    $ps_keyword_building_insert->execute(array($address_id, $keyword, $dongris[0], $dongris[1], $dongris[2], $dongris[3]));
                }
                
                // 가비지 컬렉션.
                
                if (isset($num)) unset($num);
                if (isset($legal_dong)) unset($legal_dong);
                if (isset($legal_dong_variations)) unset($legal_dong_variations);
                if (isset($legal_dong_variation)) unset($legal_dong_variation);
                if (isset($legal_jibeon)) unset($legal_jibeon);
                if (isset($addresses_numeric)) unset($addresses_numeric);
                if (isset($addresses_building)) unset($addresses_building);
                if (isset($other_addresses)) unset($other_addresses);
                if (isset($other_address)) unset($other_address);
                if (isset($matches)) unset($matches);
                if (isset($numbers)) unset($numbers);
                unset($keywords_nums);
                unset($keywords_dongs);
                unset($keywords);
                unset($dongris);
                unset($dongris2);
                unset($line);
            }
        }
        
        // 트랜잭션을 마친다.
        
        unset($dongs[$filename]);
        $zip->close();
        $db->commit();
        exit;
    }
}

echo "\n";

// 쓰레드들이 작업을 마치기를 기다린다.

while (count($children))
{
    $pid = pcntl_wait($status, WNOHANG | WUNTRACED);
    if ($pid)
    {
        echo '  <--  ' . basename($children[$pid]) . ' 쓰레드 종료. ';
        echo (count($children) - 1) . ' 쓰레드 남음.' . "\n";
        unset($children[$pid]);
    }
    sleep(1);
}

echo "\n";

// -------------------------------------------------------------------------------------------------
// 사서함 파일을 입력한다.
// -------------------------------------------------------------------------------------------------

echo '[Step 6/8] 사서함 데이터를 로딩하는 중 ... ' . "\n\n";

// 준비.

$db = get_db();
$ps_address_insert = $db->prepare('INSERT INTO postcode_addresses ' .
    '(id, postcode5, postcode6, road_id, road_section, road_name, ' .
    'num_major, num_minor, is_basement, sido, sigungu, ilbangu, eupmyeon) ' .
    'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$ps_keyword_insert = $db->prepare('INSERT INTO postcode_keywords_pobox ' .
    '(address_id, keyword, range_start_major, range_start_minor, range_end_major, range_end_minor) ' .
    'VALUES (?, ?, ?, ?, ?, ?)');
$poboxes_count = 0;
$poboxes_is_utf8 = null;

// 트랜잭션을 시작한다.

$db->beginTransaction();
        
// 파일을 연다.

$filename = TXT_DIRECTORY . '/newaddr_pobox_DB.zip';
echo '  -->  ' . basename($filename) . ' ... ' . "\n\n";

$zip = new ZipArchive;
$zip->open($filename);
for ($fi = 0; $fi < $zip->numFiles; $fi++)
{
    $fp = $zip->getStream($zip->getNameIndex($fi));
    while ($line = trim(fgets($fp)))
    {
        // 파일이 UTF-8인지 EUC-KR인지 판단한다.
        
        if ($poboxes_is_utf8 === null)
        {
            if (strlen($line) > 3 && substr($line, 0, 3) === pack('H*','EFBBBF')) $line = substr($line, 3);
            $poboxes_is_utf8 = (bool)mb_check_encoding($line, 'UTF-8');
        }
        if ($poboxes_is_utf8 === false)
        {
            $line = mb_convert_encoding($line, 'UTF-8', 'EUC-KR');
        }
        
        // 한 줄을 읽어 | 문자를 기준으로 데이터를 쪼갠다.
        
        $line = explode('|', $line);
        if (count($line) < 10 || !ctype_digit($line[0])) continue;
        
        // 상세 데이터를 읽어들인다.
        
        $postcode6 = trim($line[0]);
        $sido = trim($line[2]);
        $sigungu = trim($line[3]);
        $eupmyeon = trim($line[4]);
        $pobox_name = trim($line[5]);
        
        $range_start_major = trim($line[6]); if (!$range_start_major) $range_start_major = null;
        $range_start_minor = trim($line[7]); if (!$range_start_minor) $range_start_minor = null;
        $range_end_major = trim($line[8]); if (!$range_end_major) $range_end_major = null;
        $range_end_minor = trim($line[9]); if (!$range_end_minor) $range_end_minor = null;
        
        // 특별시/광역시 아래의 자치구와 행정시 아래의 일반구를 구분한다.
        
        if (($pos = strpos($sigungu, ' ')) !== false)
        {
            $ilbangu = substr($sigungu, $pos + 1);
            $sigungu = substr($sigungu, 0, $pos);
        }
        else
        {
            $ilbangu = null;
        }
        
        // 관리번호와 도로번호를 생성한다.
        
        $address_id = '9999999999999999999' . str_pad($poboxes_count + 1, 6, '0', STR_PAD_LEFT);
        $road_id = '999999999999';
        
        // postcode_addresses 테이블에 삽입한다.
        
        $startnum = $range_start_major . ($range_start_minor ? ('-' . $range_start_minor) : '');
        $endnum = $range_end_major . ($range_end_minor ? ('-' . $range_end_minor) : '');
        if ($endnum === '' || $endnum === '-') $endnum = null;
        $insert_name = $pobox_name . ' ' . $startnum . ($endnum === null ? '' : (' ~ ' . $endnum));
        
        $ps_address_insert->execute(array($address_id, null, $postcode6, $road_id, '00', trim($insert_name),
            null, null, 0, $sido, $sigungu, $ilbangu, $eupmyeon));
        
        // 검색 키워드들을 정리하여 postcode_keywords_pobox 테이블에 삽입한다.
        
        $keywords = array(get_canonical($pobox_name));
        
        if ($pobox_name === '사서함')
        {
            if ($sigungu !== '') $keywords[] = get_canonical($sigungu . $pobox_name);
            if ($ilbangu !== '') $keywords[] = get_canonical($ilbangu . $pobox_name);
            if ($eupmyeon !== '') $keywords[] = get_canonical($eupmyeon . $pobox_name);
        }
        
        if (!$range_end_major) $range_end_major = $range_start_major;
        if (!$range_end_minor) $range_end_minor = $range_start_minor;
        
        foreach ($keywords as $keyword)
        {
            $ps_keyword_insert->execute(array($address_id, $keyword, $range_start_major, $range_start_minor, $range_end_major, $range_end_minor));
        }
        
        // 통계에 반영한다.
        
        $poboxes_count++;
        
        // 가비지 컬렉션.
        
        if (isset($num_array)) unset($num_array);
        unset($line);
    }
}

// 트랜잭션을 마친다.

$db->commit();
$zip->close();
unset($zip);

// 경과시간을 측정한다.

$elapsed = time() - $start_time;
$elapsed_hours = floor($elapsed / 3600);
$elapsed = $elapsed - ($elapsed_hours * 3600);
$elapsed_minutes = floor($elapsed / 60);
$elapsed_seconds = $elapsed - ($elapsed_minutes * 60);

echo '  <--  경과 시간 : ';
if ($elapsed_hours) echo $elapsed_hours . '시간 ';
if ($elapsed_hours || $elapsed_minutes) echo $elapsed_minutes . '분 ';
echo $elapsed_seconds . '초' . "\n\n";

// -------------------------------------------------------------------------------------------------
// 인덱스를 생성한다.
// -------------------------------------------------------------------------------------------------

echo '[Step 7/8] 인덱스를 생성하는 중. 아주 긴 시간이 걸릴 수 있습니다 ... ' . "\n\n";

$indexes = array(
    'postcode_addresses' => array('postcode6', 'postcode5', 'road_id', 'road_section', 'sido', 'sigungu', 'ilbangu', 'eupmyeon'),
    'postcode_keywords_juso' => array('address_id', 'keyword_crc32', 'num_major', 'num_minor'),
    'postcode_keywords_jibeon' => array('address_id', 'keyword_crc32', 'num_major', 'num_minor'),
    'postcode_keywords_building' => array('address_id', 'keyword', 'dongri_crc32_1', 'dongri_crc32_2', 'dongri_crc32_3', 'dongri_crc32_4'),
    'postcode_keywords_pobox' => array('address_id', 'keyword', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor'),
);

while (count($indexes))
{
    $table_name = key($indexes);
    $columns = array_shift($indexes);
    $pid = pcntl_fork();
    
    if ($pid == -1)
    {
        echo '[ERROR] 쓰레드를 생성할 수 없습니다.' . "\n\n";
        exit(1);
    }
    elseif ($pid > 0)
    {
        echo '  -->  ' . $table_name . ' 쓰레드 시작 ... ' . "\n";
        $children[$pid] = $table_name;
    }
    else
    {
        $columns_query = array();
        foreach ($columns as $column)
        {
            $columns_query[] = 'ADD INDEX (' . $column . ')';
        }
        
        $db = get_db();
        $db->query('ALTER TABLE ' . $table_name . ' ' . implode(', ', $columns_query));
        exit;
    }
}

echo "\n";

// 쓰레드들이 작업을 마치기를 기다린다.

while (count($children))
{
    $pid = pcntl_wait($status, WNOHANG | WUNTRACED);
    if ($pid)
    {
        echo '  <--  ' . $children[$pid] . ' 쓰레드 종료. ';
        echo (count($children) - 1) . ' 쓰레드 남음.' . "\n";
        unset($children[$pid]);
    }
    sleep(1);
}

echo "\n";

// 경과시간을 측정한다.

$elapsed = time() - $start_time;
$elapsed_hours = floor($elapsed / 3600);
$elapsed = $elapsed - ($elapsed_hours * 3600);
$elapsed_minutes = floor($elapsed / 60);
$elapsed_seconds = $elapsed - ($elapsed_minutes * 60);

echo '  <--  경과 시간 : ';
if ($elapsed_hours) echo $elapsed_hours . '시간 ';
if ($elapsed_hours || $elapsed_minutes) echo $elapsed_minutes . '분 ';
echo $elapsed_seconds . '초' . "\n\n";

// -------------------------------------------------------------------------------------------------
// 업데이트를 적용한다.
// -------------------------------------------------------------------------------------------------

echo '[Step 8/8] 업데이트를 적용하는 중 ... ' . "\n\n";

// update.php를 호출한다.

do_updates();

// 끝!

$elapsed = time() - $start_time;
$elapsed_hours = floor($elapsed / 3600);
$elapsed = $elapsed - ($elapsed_hours * 3600);
$elapsed_minutes = floor($elapsed / 60);
$elapsed_seconds = $elapsed - ($elapsed_minutes * 60);

echo '작업을 모두 마쳤습니다. 경과 시간 : ';
if ($elapsed_hours) echo $elapsed_hours . '시간 ';
if ($elapsed_hours || $elapsed_minutes) echo $elapsed_minutes . '분 ';
echo $elapsed_seconds . '초' . "\n\n";
exit;
