<?php

/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (서버측 API)
 * 
 *  Copyright (c) 2014-2016, Poesis <root@poesis.kr>
 * 
 *  이 프로그램은 자유 소프트웨어입니다. 이 소프트웨어의 피양도자는 자유
 *  소프트웨어 재단이 공표한 GNU 약소 일반 공중 사용 허가서 (GNU LGPL) 제3판
 *  또는 그 이후의 판을 임의로 선택하여, 그 규정에 따라 이 프로그램을
 *  개작하거나 재배포할 수 있습니다.
 * 
 *  이 프로그램은 유용하게 사용될 수 있으리라는 희망에서 배포되고 있지만,
 *  특정한 목적에 맞는 적합성 여부나 판매용으로 사용할 수 있으리라는 묵시적인
 *  보증을 포함한 어떠한 형태의 보증도 제공하지 않습니다. 보다 자세한 사항에
 *  대해서는 GNU 약소 일반 공중 사용 허가서를 참고하시기 바랍니다.
 * 
 *  GNU 약소 일반 공중 사용 허가서는 이 프로그램과 함께 제공됩니다.
 *  만약 허가서가 누락되어 있다면 자유 소프트웨어 재단으로 문의하시기 바랍니다.
 */

date_default_timezone_set('Asia/Seoul');
error_reporting(-1);
require_once dirname(__FILE__) . '/../autoload.php';

// 광역시도 목록 검색인 경우 여기서 결과를 반환한다.

if (isset($_GET['request']) && $_GET['request'] === 'addr1')
{
    $sido = array_unique(Postcodify_Server_Areas::$sido);
    sort($sido);
    echo $_GET['callback'] . '(' . json_encode(array(
        'result' => true,
        'values' => $sido,
    )) . ');' . "\n";
    exit;
}

// 시군구 목록 검색인 경우 세종시처럼 빈 문자열을 반환하여 곧장 키워드 입력 단계로 넘어가도록 한다.

if (isset($_GET['request']) && $_GET['request'] === 'addr2')
{
    echo $_GET['callback'] . '(' . json_encode(array(
        'result' => true,
        'values' => array(''),
    )) . ');' . "\n";
    exit;
}

// GET 또는 터미널 파라미터로부터 검색 키워드를 조합한다.

if (isset($_GET['search_addr1']) && strlen($_GET['search_addr1']) && isset($_GET['search_word']) && strlen($_GET['search_word']))
{
    $keywords = $_GET['search_addr1'] . ' ' . $_GET['search_word'];
}
elseif (isset($_GET['search_word']) && strlen($_GET['search_word']))
{
    $keywords = $_GET['search_word'];
}
elseif (isset($argv[1]))
{
    $keywords = $argv[1];
}
else
{
    $keywords = '';   
}

// JSONP 콜백 함수명과 클라이언트 버전을 구한다.

$callback = isset($_GET['callback']) ? $_GET['callback'] : null;
$client_version = isset($_GET['v']) ? trim($_GET['v']) : POSTCODIFY_VERSION;
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc()) $keywords = stripslashes($keywords);
if (preg_match('/[^a-zA-Z0-9_.]/', $callback)) $callback = null;

// 검색을 수행한다.

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

if (!isset($result) || !is_object($result))
{
    $server = new Postcodify_Server;
    $server->db_driver = POSTCODIFY_DB_DRIVER;
    $server->db_dbname = POSTCODIFY_DB_DBNAME;
    $server->db_host = POSTCODIFY_DB_HOST;
    $server->db_port = POSTCODIFY_DB_PORT;
    $server->db_user = POSTCODIFY_DB_USER;
    $server->db_pass = POSTCODIFY_DB_PASS;
    $result = $server->search($keywords, 'UTF-8');
}

// 검색 결과를 XE KRZIP API와 같은 포맷으로 변환한다.

if ($result->error)
{
    $json = array('result' => false, 'msg' => "검색 서버와 통신 중 오류가 발생하였습니다.\n잠시 후 다시 시도해 주시기 바랍니다.\n\n" + $result->error);
}
elseif (!count($result->results))
{
    $json = array('result' => false, 'msg' => "검색 결과가 없습니다. 정확한 도로명주소 또는 지번주소로 검색해 주시기 바랍니다.\n\n" + $result->error);
}
else
{
    $json = array('result' => true, 'values' => array('address' => array(), 'next' => -1));
    foreach ($result->results as $entry)
    {
        $json['values']['address'][] = array(
            'seq' => $entry->building_id,
            'addr1' => $entry->ko_common,
            'addr2_new' => $entry->ko_doro,
            'addr2_old' => $entry->ko_jibeon,
            'bdname' => $entry->building_name,
            'zipcode' => $entry->postcode5,
        );
    }
}

$json_options = (PHP_SAPI === 'cli' && defined('JSON_PRETTY_PRINT')) ? 384 : 0;
echo ($callback ? ($callback . '(') : '') . json_encode($json, $json_options) . ($callback ? ');' : '') . "\n";
exit;
