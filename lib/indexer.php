<?php

/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (인덱서)
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

ini_set('default_socket_timeout', -1);
ini_set('display_errors', 'on');
ini_set('memory_limit', '1024M');
date_default_timezone_set('Asia/Seoul');
error_reporting(-1);
if (function_exists('gc_enable')) gc_enable();

require dirname(__FILE__) . '/autoload.php';

// 명령줄에 주어진 옵션을 파악한다.

if (PHP_SAPI !== 'cli')
{
    Postcodify_Utility::print_usage_instructions();
}

$args = Postcodify_Utility::get_terminal_args();

if ($args->command === null)
{
    Postcodify_Utility::print_usage_instructions();
}
if (in_array('--dry-run', $args->options))
{
    Postcodify_Utility::print_usage_instructions();
}

// 필요한 클래스를 호출한다.

$start_time = time();
$class_name = 'Postcodify_Indexer_' . ucfirst(str_replace('-', '_', $argv[1]));
$obj = new $class_name();
$obj->start($args);

// 소요된 시간을 출력한다.

echo str_repeat('-', Postcodify_Utility::get_terminal_width()) . PHP_EOL;

$elapsed = time() - $start_time;
$elapsed_hours = floor($elapsed / 3600);
$elapsed = $elapsed - ($elapsed_hours * 3600);
$elapsed_minutes = floor($elapsed / 60);
$elapsed_seconds = $elapsed % 60;

echo '작업을 모두 마쳤습니다. 경과 시간 : ';
if ($elapsed_hours) echo $elapsed_hours . '시간 ';
if ($elapsed_hours || $elapsed_minutes) echo $elapsed_minutes . '분 ';
echo $elapsed_seconds . '초';
echo PHP_EOL;
exit(0);
