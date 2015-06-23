<?php

// 버전 선언.

define('POSTCODIFY_VERSION', '2.5.1');
define('POSTCODIFY_LIB_DIR', dirname(__FILE__));

// 설정을 불러온다.

require POSTCODIFY_LIB_DIR . '/config.php';

// 오토로딩 함수를 등록한다.

function _postcodify_autoloader($class_name)
{
    if (preg_match('/^postcodify_([a-z0-9_]+)$/', strtolower($class_name), $matches) &&
        file_exists(POSTCODIFY_LIB_DIR . '/classes/' . $matches[1] . '.php'))
    {
        include POSTCODIFY_LIB_DIR . '/classes/' . $matches[1] . '.php';
    }
}

spl_autoload_register('_postcodify_autoloader');
