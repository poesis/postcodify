<?php

/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (서버측 API)
 * 
 *  Copyright (c) 2014, Kijin Sung <root@poesis.kr>
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
require 'config.php';
require 'postcodify.class.php';

// 검색 키워드와 JSONP 콜백 함수명을 구한다.

$keywords = isset($_GET['q']) ? trim($_GET['q']) : (isset($argv[1]) ? trim($argv[1], ' "\'') : '');
$callback = isset($_GET['callback']) ? $_GET['callback'] : null;
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc()) $keywords = stripslashes($keywords);
if (preg_match('/[^a-zA-Z0-9_.]/', $callback)) $callback = null;

// 검색을 수행하고 결과를 전송한다.

$result = Postcodify::search($keywords);
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
echo ($callback ? ($callback . '(') : '') . json_encode($result) . ($callback ? ');' : '');
exit;
