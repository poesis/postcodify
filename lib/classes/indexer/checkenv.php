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

class Postcodify_Indexer_CheckEnv
{
    // 구동 환경을 점검한다.
    
    public function check($add_old_postcodes)
    {
        // 기본적인 환경을 확인한다.
        
        if (version_compare(PHP_VERSION, '5.2', '<'))
        {
            echo '[ERROR] PHP 버전은 5.2 이상이어야 합니다.' . PHP_EOL;
            exit(2);
        }
        
        if (strtolower(PHP_SAPI) !== 'cli')
        {
            echo '[ERROR] 이 프로그램은 명령줄(CLI)에서 실행되어야 합니다.' . PHP_EOL;
            exit(2);
        }
        
        if (strtolower(substr(PHP_OS, 0, 3)) === 'win')
        {
            echo '[ERROR] 윈도우 환경은 지원하지 않습니다.' . PHP_EOL;
            exit(2);
        }
        
        // 필요한 모듈과 함수들을 확인한다.
        
        if (!class_exists('PDO') || !in_array('mysql', PDO::getAvailableDrivers()))
        {
            echo '[ERROR] PDO 모듈이 설치되지 않았거나 MySQL 드라이버를 사용할 수 없습니다.' . PHP_EOL;
            exit(2);
        }
        
        if (!class_exists('ZipArchive'))
        {
            echo '[ERROR] Zip 모듈이 설치되어 있지 않습니다.' . PHP_EOL;
            exit(2);
        }
        
        if (!function_exists('mb_check_encoding'))
        {
            echo '[ERROR] mbstring 모듈이 설치되어 있지 않습니다.' . PHP_EOL;
            exit(2);
        }
        
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_wait'))
        {
            echo '[ERROR] pcntl_* 함수가 없거나 php.ini에서 막아 놓았습니다.' . PHP_EOL;
            exit(2);
        }
        
        if (!function_exists('shmop_open'))
        {
            echo '[ERROR] shmop_* 함수가 없거나 php.ini에서 막아 놓았습니다.' . PHP_EOL;
            exit(2);
        }
        
        // 필요한 데이터 파일이 모두 있는지 확인한다.
        
        $data_address_file = null;
        $data_files = scandir(dirname(POSTCODIFY_LIB_DIR) . '/data');
        foreach ($data_files as $filename)
        {
            if (preg_match('/^20[0-9]{4}ALLRDNM\.zip$/', $filename, $matches))
            {
                $data_address_file = $filename;
            }
        }
        
        if (!$data_address_file)
        {
            echo '[ERROR] ******ALLRDNM.zip 파일을 찾을 수 없습니다.' . PHP_EOL;
            exit(2);
        }
        
        if (!file_exists(dirname(POSTCODIFY_LIB_DIR) . '/data/areacd_pobox_DB.zip'))
        {
            echo '[ERROR] 우체국 사서함 (areacd_pobox_DB.zip) 파일을 찾을 수 없습니다.' . PHP_EOL;
            exit(2);
        }
        
        if (!file_exists(dirname(POSTCODIFY_LIB_DIR) . '/data/areacd_rangeaddr_DB.zip'))
        {
            echo '[ERROR] 우편번호 범위 (areacd_rangeaddr_DB.zip) 파일을 찾을 수 없습니다.' . PHP_EOL;
            exit(2);
        }
        
        if ($add_old_postcodes)
        {
            if (!file_exists(dirname(POSTCODIFY_LIB_DIR) . '/data/oldaddr_zipcode_DB.zip'))
            {
                echo '[ERROR] 구 우편번호 범위 (oldaddr_zipcode_DB.zip) 파일을 찾을 수 없습니다.' . PHP_EOL;
                exit(2);
            }
            
            if (!file_exists(dirname(POSTCODIFY_LIB_DIR) . '/data/oldaddr_special_DB.zip'))
            {
                echo '[ERROR] 구 우편번호 범위 (oldaddr_special_DB.zip) 파일을 찾을 수 없습니다.' . PHP_EOL;
                exit(2);
            }
        }
        
        // DB의 사양을 점검한다.
        
        if (!($db = Postcodify_Utility::get_db()))
        {
            echo '[ERROR] MySQL DB에 접속할 수 없습니다.' . PHP_EOL;
            exit(2);
        }
        
        $version_query = $db->query('SELECT VERSION()');
        $version = $version_query->fetchColumn();
        
        if (!version_compare($version, '5.0', '>='))
        {
            echo '[ERROR] MySQL DB의 버전은 5.0 이상이어야 합니다. 현재 사용중인 DB의 버전은 ' . $version . '입니다.' . PHP_EOL;
            exit(2);
        }
        
        $innodb_found = false;
        $innodb_query = $db->query('SHOW ENGINES');
        while ($row = $innodb_query->fetch(PDO::FETCH_NUM))
        {
            if (strtolower($row[0]) === 'innodb')
            {
                $innodb_found = true;
                break;
            }
            unset($row);
        }
        
        if (!$innodb_found)
        {
            echo '[ERROR] MySQL DB가 InnoDB 테이블 저장 엔진을 지원하지 않습니다.' . PHP_EOL;
            exit(2);
        }
        
        $buffersize_query = $db->query('SHOW VARIABLES LIKE \'innodb_buffer_pool_size\'');
        $buffersize = $buffersize_query->fetchColumn(1);
        
        if ($buffersize < 128 * 1024 * 1024)
        {
            $buffersize = round($buffersize / 1024 / 1024) . 'M';
            echo '[ERROR] MySQL DB의 InnoDB 버퍼 크기를 128M 이상으로 설정해 주십시오. 현재 설정은 ' . $buffersize . '입니다.' . PHP_EOL;
            exit(2);
        }
    }
}
