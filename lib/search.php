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
require_once dirname(__FILE__) . '/autoload.php';

// 검색 키워드, JSONP 콜백 함수명, 클라이언트 버전을 구한다.

$keywords = isset($_GET['q']) ? trim($_GET['q']) : (isset($argv[1]) ? trim($argv[1], ' "\'') : '');
$callback = isset($_GET['callback']) ? $_GET['callback'] : null;
$client_version = isset($_GET['v']) ? trim($_GET['v']) : POSTCODIFY_VERSION;
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc()) $keywords = stripslashes($keywords);
if (preg_match('/[^a-zA-Z0-9_.]/', $callback)) $callback = null;

// 검색 서버를 설정한다.

$server = new Postcodify_Server;
$server->db_driver = POSTCODIFY_DB_DRIVER;
$server->db_dbname = POSTCODIFY_DB_DBNAME;
$server->db_host = POSTCODIFY_DB_HOST;
$server->db_port = POSTCODIFY_DB_PORT;
$server->db_user = POSTCODIFY_DB_USER;
$server->db_pass = POSTCODIFY_DB_PASS;
$server->cache_driver = defined('POSTCODIFY_CACHE_DRIVER') ? POSTCODIFY_CACHE_DRIVER : '';
$server->cache_host = defined('POSTCODIFY_CACHE_HOST') ? POSTCODIFY_CACHE_HOST : 'localhost';
$server->cache_port = defined('POSTCODIFY_CACHE_PORT') ? POSTCODIFY_CACHE_PORT : 11211;
$server->cache_ttl = defined('POSTCODIFY_CACHE_TTL') ? POSTCODIFY_CACHE_TTL : 86400;

// 검색을 수행하고 결과를 전송한다.

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

$result = $server->search($keywords, 'UTF-8', $client_version);
$json_options = (PHP_SAPI === 'cli' && defined('JSON_PRETTY_PRINT')) ? 384 : 0;
echo ($callback ? ($callback . '(') : '') . json_encode($result, $json_options) . ($callback ? ');' : '') . "\n";
exit;
