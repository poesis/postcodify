<?php

// 버전 선언.

define('POSTCODIFY_VERSION', '3.5.0');
define('POSTCODIFY_LIB_DIR', dirname(__FILE__));

// 설정을 불러온다.

require POSTCODIFY_LIB_DIR . '/config.php';

// 오토로딩 함수를 등록한다.

function _postcodify_autoloader($class_name)
{
    if (preg_match('/^postcodify_([a-z0-9_]+)$/', strtolower($class_name), $matches))
    {
        $class_location = preg_replace('/^(indexer|parser|server)_/', '$1/', $matches[1]);
        if (file_exists(POSTCODIFY_LIB_DIR . '/classes/' . $class_location . '.php'))
        {
            include POSTCODIFY_LIB_DIR . '/classes/' . $class_location . '.php';
        }
    }
}

spl_autoload_register('_postcodify_autoloader');
