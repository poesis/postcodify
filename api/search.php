<?php

// 주소 검색 API 스크립트.

define('VERSION', '1.1');
date_default_timezone_set('Asia/Seoul');
error_reporting(-1);
$start_time = microtime(true);

// 헤더를 전송한다.

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

// 결과를 전송하는 함수.

function send_response($json)
{
    $callback = (isset($_GET['callback']) && preg_match('/^[a-zA-Z0-9_.]+$/', $_GET['callback'])) ? $_GET['callback'] : null;
    echo ($callback ? ($callback . '(') : '') . $json . ($callback ? ');' : '');
    exit;
}

// 항상 64비트식으로 (음수 없이) CRC32를 계산하는 함수.

function crc32_x64($str)
{
    $crc32 = crc32($str);
    return ($crc32 >= 0) ? $crc32 : ($crc32 + 0x100000000);
}

// 설정을 불러온다.

require 'areas.php';
require 'config.php';

// 검색 키워드를 구한다.

if (isset($_GET['q']))
{
    $keywords = trim($_GET['q']);
}
elseif (isset($argv[1]))
{
    $keywords = trim($argv[1], ' "\'');
}
else
{
    send_response(json_encode(array('version' => VERSION, 'error' => 'Keyword Not Supplied', 'count' => 0, 'time' => 0, 'results' => array())));
}

// magic quotes를 사용하는 서버인 경우 불필요한 백슬래시를 제거한다.

if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc())
{
    $keywords = stripslashes($keywords);
}

// 검색 키워드의 길이와 인코딩을 확인한다.

if (!mb_check_encoding($keywords, 'UTF-8'))
{
    send_response(json_encode(array('version' => VERSION, 'error' => 'Keyword is Not Valid UTF-8', 'count' => 0, 'time' => 0, 'results' => array())));
}
$len = mb_strlen($keywords, 'UTF-8');
if ($len < 3 || $len > 90)
{
    send_response(json_encode(array('version' => VERSION, 'error' => 'Keyword Too Long or Too Short', 'count' => 0, 'time' => 0, 'results' => array())));
}

// 검색 키워드를 분해하여 각 구성요소를 정리한다.

$kw = array();
$keywords = preg_split('/\\s+/u', preg_replace('/[^\\sㄱ-ㅎ가-힣a-z0-9-]/u', '', strtolower($keywords)));

foreach ($keywords as $id => $keyword)
{
    // 키워드가 "산", "지하", 한글 1글자인 경우 건너뛴다.
    
    if (!ctype_alnum($keyword) && mb_strlen($keyword, 'UTF-8') < 2) continue;
    if ($keyword === '지하') continue;
    
    // 첫 번째 구성요소가 시도인지 확인한다.
    
    if ($id == 0 && count($keywords) > 1)
    {
        if (isset($areas_sido[$keyword]))
        {
            $kw['sido'] = $areas_sido[$keyword];
            continue;
        }
    }
    
    // 시군구읍면을 확인한다.
    
    if (preg_match('/.+([시군구읍면])$/u', $keyword, $matches))
    {
        if ($matches[1] === '읍' || $matches[1] === '면')
        {
            $kw['eupmyeon'] = $keyword;
            continue;
        }
        elseif (isset($kw['sigungu']) && in_array($keyword, $areas_ilbangu[$kw['sigungu']]))
        {
            $kw['ilbangu'] = $keyword;
            continue;
        }
        elseif (in_array($keyword, $areas_sigungu))
        {
            $kw['sigungu'] = $keyword;
            continue;
        }
        else
        {
            if (count($keywords) > $id + 1) continue;
        }
    }
    elseif (in_array($keyword . '시', $areas_sigungu))
    {
        $kw['sigungu'] = $keyword . '시';
        continue;
    }
    elseif (in_array($keyword . '군', $areas_sigungu))
    {
        $kw['sigungu'] = $keyword . '군';
        continue;
    }
    
    // 도로명+건물번호를 확인한다.
    
    if (preg_match('/^(.+[로길])((?:지하)?([0-9]+(?:-[0-9]+)?)(?:번지?)?)?$/u', $keyword, $matches))
    {
        $kw['road'] = $matches[1];
        if (isset($matches[3]) && $matches[3])
        {
            $kw['numbers'] = $matches[3];
            break;
        }
        continue;
    }
    
    // 동리+지번을 확인한다.
    
    if (preg_match('/^(.+(?:[0-9]가|[동리]))(산?([0-9]+(?:-[0-9]+)?)(?:번지?)?)?$/u', $keyword, $matches))
    {
        $kw['dongri'] = $matches[1];
        if (isset($matches[3]) && $matches[3])
        {
            $kw['numbers'] = $matches[3];
            break;
        }
        continue;
    }
    
    // 사서함을 확인한다.
    
    if (preg_match('/^(.*사서함)(([0-9]+(?:-[0-9]+)?)번?)?$/u', $keyword, $matches))
    {
        $kw['pobox'] = $matches[1];
        if (isset($matches[3]) && $matches[3])
        {
            $kw['numbers'] = $matches[3];
            break;
        }
        continue;
    }
    
    // 건물번호, 지번, 사서함 번호를 따로 적은 경우를 확인한다.
    
    if (preg_match('/^(?:산|지하)?([0-9]+(?:-[0-9]+)?)(?:번지?)?$/u', $keyword, $matches))
    {
        $kw['numbers'] = $matches[1];
        break;
    }
    
    // 그 밖의 키워드는 건물명으로 취급한다.
    
    $kw['building'] = $keyword;
    break;
}

// 건물번호 또는 지번을 주번과 부번으로 분리한다.

if (isset($kw['numbers']))
{
    $kw['numbers'] = explode('-', $kw['numbers']);
    if (!isset($kw['numbers'][1])) $kw['numbers'][1] = null;
}
else
{
    $kw['numbers'] = array(null, null);
}

// 검색한다.

try
{
    // DB에 연결한다.
    
    $dsn = DB_DRIVER . ':host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_DBNAME . ';charset=utf8';
    $db = new PDO($dsn, DB_USER, DB_PASS, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
    ));
    
    // 시도, 시군구, 일반구, 읍면 등으로 검색 결과를 제한하는 경우...
    
    if (isset($kw['sido']) || isset($kw['sigungu']) || isset($kw['ilbangu']) || isset($kw['eupmyeon']))
    {
        // 빈 칸은 null로 채운다.
        
        $kw['sido'] = isset($kw['sido']) ? $kw['sido'] : null;
        $kw['sigungu'] = isset($kw['sigungu']) ? $kw['sigungu'] : null;
        $kw['ilbangu'] = isset($kw['ilbangu']) ? $kw['ilbangu'] : null;
        $kw['eupmyeon'] = isset($kw['eupmyeon']) ? $kw['eupmyeon'] : null;
        
        // 도로명주소로 검색하는 경우...
        
        if (isset($kw['road']))
        {
            $ps = $db->prepare('CALL postcode_search_juso_in_area(?, ?, ?, ?, ?, ?, ?)');
            $ps->execute(array(crc32_x64($kw['road']), $kw['numbers'][0], $kw['numbers'][1], $kw['sido'], $kw['sigungu'], $kw['ilbangu'], $kw['eupmyeon']));
        }
        
        // 동리+지번으로 검색하는 경우...
        
        elseif (isset($kw['dongri']) && !isset($kw['building']))
        {
            $ps = $db->prepare('CALL postcode_search_jibeon_in_area(?, ?, ?, ?, ?, ?, ?)');
            $ps->execute(array(crc32_x64($kw['dongri']), $kw['numbers'][0], $kw['numbers'][1], $kw['sido'], $kw['sigungu'], $kw['ilbangu'], $kw['eupmyeon']));
        }
        
        // 건물명만으로 검색하는 경우...
        
        elseif (isset($kw['building']) && !isset($kw['dongri']))
        {
            $ps = $db->prepare('CALL postcode_search_building_in_area(?, ?, ?, ?, ?)');
            $ps->execute(array($kw['building'], $kw['sido'], $kw['sigungu'], $kw['ilbangu'], $kw['eupmyeon']));
        }
        
        // 동리 + 건물명으로 검색하는 경우...
        
        elseif (isset($kw['building']) && isset($kw['dongri']))
        {
            $ps = $db->prepare('CALL postcode_search_building_with_dongri_in_area(?, ?, ?, ?, ?, ?)');
            $ps->execute(array($kw['building'], crc32_x64($kw['dongri']), $kw['sido'], $kw['sigungu'], $kw['ilbangu'], $kw['eupmyeon']));
        }
        
        // 사서함으로 검색하는 경우...
        
        elseif (isset($kw['pobox']))
        {
            $ps = $db->prepare('CALL postcode_search_pobox_in_area(?, ?, ?, ?, ?, ?, ?)');
            $ps->execute(array($kw['pobox'], $kw['numbers'][0], $kw['numbers'][1], $kw['sido'], $kw['sigungu'], $kw['ilbangu'], $kw['eupmyeon']));
        }
        
        // 그 밖의 경우...
        
        else
        {
            send_response(json_encode(array('version' => VERSION, 'error' => '', 'count' => 0, 'time' => 0, 'results' => array())));
        }
    }
    
    // 지역 제한 없이 검색하는 경우...
    
    else
    {
        // 도로명주소로 검색하는 경우...
        
        if (isset($kw['road']))
        {
            $ps = $db->prepare('CALL postcode_search_juso(?, ?, ?)');
            $ps->execute(array(crc32_x64($kw['road']), $kw['numbers'][0], $kw['numbers'][1]));
        }
        
        // 동리+지번으로 검색하는 경우...
        
        elseif (isset($kw['dongri']) && !isset($kw['building']))
        {
            $ps = $db->prepare('CALL postcode_search_jibeon(?, ?, ?)');
            $ps->execute(array(crc32_x64($kw['dongri']), $kw['numbers'][0], $kw['numbers'][1]));
        }
        
        // 건물명만으로 검색하는 경우...
        
        elseif (isset($kw['building']) && !isset($kw['dongri']))
        {
            $ps = $db->prepare('CALL postcode_search_building(?)');
            $ps->execute(array($kw['building']));
        }
        
        // 동리 + 건물명으로 검색하는 경우...
        
        elseif (isset($kw['building']) && isset($kw['dongri']))
        {
            $ps = $db->prepare('CALL postcode_search_building_with_dongri(?, ?)');
            $ps->execute(array($kw['building'], crc32_x64($kw['dongri'])));
        }
        
        // 사서함으로 검색하는 경우...
        
        elseif (isset($kw['pobox']))
        {
            $ps = $db->prepare('CALL postcode_search_pobox(?, ?, ?)');
            $ps->execute(array($kw['pobox'], $kw['numbers'][0], $kw['numbers'][1]));
        }
        
        // 그 밖의 경우...
        
        else
        {
            send_response(json_encode(array('version' => VERSION, 'error' => '', 'count' => 0, 'time' => 0, 'results' => array())));
        }
    }
}
catch (Exception $e)
{
    error_log($e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    send_response(json_encode(array('version' => VERSION, 'error' => 'Internal Server Error', 'count' => 0, 'time' => 0, 'results' => array())));
}

// 검색 결과를 배열로 만든다.

$result = array(
    'version' => VERSION,
    'error' => '',
    'count' => 0,
    'time' => 0,
    'results' => array(),
);

while ($item = $ps->fetch(PDO::FETCH_OBJ))
{
    // 도로명주소를 생성한다.
    
    $address = trim($item->sido . ' ' . ($item->sigungu ? ($item->sigungu . ' ') : '') .
        ($item->ilbangu ? ($item->ilbangu . ' ') : '') .
        ($item->eupmyeon ? ($item->eupmyeon . ' ') : '') .
        $item->road_name . ' ' . ($item->is_basement ? '지하 ' : '') . ($item->num_major ?: '') . ($item->num_minor ? ('-' . $item->num_minor) : ''));
    
    // 추가정보를 정리한다.
    
    $address_extra_long = '';
    $address_extra_short = '';
    if (strval($item->dongri) !== '')
    {
        $address_extra_long = $item->dongri . ($item->jibeon ? (' ' . $item->jibeon) : '');
        $address_extra_short = $item->dongri;
    }
    if (strval($item->building_name) !== '')
    {
        $address_extra_long .= ', ' . $item->building_name;
        $address_extra_short .= ', ' . $item->building_name;
    }
    
    // 배열에 추가한다.
    
    $result['count']++;
    $result['results'][] = array(
        'dbid' => substr($item->id, 0, 10) === '9999999999' ? '' : $item->id,
        'code6' => substr($item->postcode6, 0, 3) . '-' . substr($item->postcode6, 3, 3),
        'code5' => strval($item->postcode5),
        'address' => strval($address),
        'canonical' => strval($item->dongri . ($item->jibeon ? (' ' . $item->jibeon) : '')),
        'extra_info_long' => strval($address_extra_long),
        'extra_info_short' => strval($address_extra_short),
        'other' => strval($item->other_addresses),
    );
}

// 소요 시간을 기록한다.

$result['time'] = number_format(microtime(true) - $start_time, 3);

// JSON으로 인코딩한다.

if (version_compare(PHP_VERSION, '5.4.0', '>='))
{
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
}
else
{
    $flags = null;
}

$result = json_encode($result, $flags);

// 결과를 출력한다.

send_response($result);
