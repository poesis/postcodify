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

class Postcodify_Indexer_Download
{
    // 상수 선언 부분.
    
    const RELATIVE_DOMAIN = 'http://www.juso.go.kr';
    const DOWNLOAD_URL = '/dn.do?reqType=ALLRDNM&regYmd=%1$04d&ctprvnCd=00&gubun=RDNM&stdde=%1$04d%2$02d&fileName=%1$04d%2$02d_%%EA%%B1%%B4%%EB%%AC%%BCDB_%%EC%%A0%%84%%EC%%B2%%B4%%EB%%B6%%84.zip&realFileName=%1$04d%2$02dALLRDNM00.zip&indutyCd=999&purpsCd=999&indutyRm=%%EC%%88%%98%%EC%%A7%%91%%EC%%A2%%85%%EB%%A3%%8C&purpsRm=%%EC%%88%%98%%EC%%A7%%91%%EC%%A2%%85%%EB%%A3%%8C';
    const POBOX_URL = 'http://www.epost.go.kr/search/areacd/areacd_pobox_DB.zip';
    const RANGES_URL = 'http://www.epost.go.kr/search/areacd/areacd_rangeaddr_DB.zip';
    const OLDADDR_ZIPCODE_URL = 'http://cdn.poesis.kr/archives/oldaddr_zipcode_DB.zip';
    const OLDADDR_SPECIAL_URL = 'http://cdn.poesis.kr/archives/oldaddr_special_DB.zip';
    
    // 엔트리 포인트.
    
    public function start()
    {
        // 다운로드할 경로가 존재하는지 확인한다.
        
        Postcodify_Utility::print_message('Postcodify Indexer ' . POSTCODIFY_VERSION);
        Postcodify_Utility::print_newline();
        
        $download_path = dirname(POSTCODIFY_LIB_DIR) . '/data';
        $downloaded_files = 0;
        
        if ((!file_exists($download_path) || !is_dir($download_path)) && !@mkdir($download_path, 0755))
        {
            echo '[ERROR] 다운로드 대상 경로(' . $download_path . ')가 존재하지 않습니다.' . PHP_EOL;
            exit(2);
        }
        
        // 데이터가 존재하는 가장 최근 년월을 찾는다.
        
        $current_day = intval(date('d'));
        $data_year = intval(date('Y', time() - (86400 * ($current_day > 15 ? 35 : 50))));
        $data_month = intval(date('m', time() - (86400 * ($current_day > 15 ? 35 : 50))));
        $data_day = intval(date('t', mktime(12, 0, 0, $data_month, 1, $data_year)));
        
        Postcodify_Utility::print_message('데이터 기준일은 ' . $data_year . '년 ' . $data_month . '월 ' . $data_day . '일입니다.');
        
        // 주소 데이터를 다운로드한다.
        
        $download_url = self::RELATIVE_DOMAIN . sprintf(self::DOWNLOAD_URL, $data_year, $data_month);
        $filename = sprintf('%04d%02d%s.zip', $data_year, $data_month, 'ALLRDNM');
        $filepath = $download_path . '/' . $filename;
        
        Postcodify_Utility::print_message('다운로드: ' . $filename);
        $result = Postcodify_Utility::download($download_url, $filepath, array(__CLASS__, 'progress'));
        if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
        {
            Postcodify_Utility::print_error();
            exit(2);
        }
        else
        {
            Postcodify_Utility::print_ok(filesize($filepath));
            $downloaded_files++;
        }
        
        // 우체국 사서함 파일을 다운로드한다.
        
        Postcodify_Utility::print_message('다운로드: ' . basename(self::POBOX_URL));
        $filepath = $download_path . '/' . basename(self::POBOX_URL);
        $result = Postcodify_Utility::download(self::POBOX_URL, $filepath, array(__CLASS__, 'progress'));
        if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
        {
            Postcodify_Utility::print_error();
            exit(2);
        }
        else
        {
            Postcodify_Utility::print_ok(filesize($filepath));
            $downloaded_files++;
        }
        
        // 우편번호 범위 데이터를 다운로드한다.
        
        Postcodify_Utility::print_message('다운로드: ' . basename(self::RANGES_URL));
        $filepath = $download_path . '/' . basename(self::RANGES_URL);
        $result = Postcodify_Utility::download(self::RANGES_URL, $filepath, array(__CLASS__, 'progress'));
        if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
        {
            Postcodify_Utility::print_error();
            exit(2);
        }
        else
        {
            Postcodify_Utility::print_ok(filesize($filepath));
            $downloaded_files++;
        }
        
        // 구 우편번호 범위 데이터를 다운로드한다.
        
        Postcodify_Utility::print_message('다운로드: ' . basename(self::OLDADDR_ZIPCODE_URL));
        $filepath = $download_path . '/' . basename(self::OLDADDR_ZIPCODE_URL);
        $result = Postcodify_Utility::download(self::OLDADDR_ZIPCODE_URL, $filepath, array(__CLASS__, 'progress'));
        if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
        {
            Postcodify_Utility::print_error();
            exit(2);
        }
        else
        {
            Postcodify_Utility::print_ok(filesize($filepath));
            $downloaded_files++;
        }
        
        Postcodify_Utility::print_message('다운로드: ' . basename(self::OLDADDR_SPECIAL_URL));
        $filepath = $download_path . '/' . basename(self::OLDADDR_SPECIAL_URL);
        $result = Postcodify_Utility::download(self::OLDADDR_SPECIAL_URL, $filepath, array(__CLASS__, 'progress'));
        if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
        {
            Postcodify_Utility::print_error();
            exit(2);
        }
        else
        {
            Postcodify_Utility::print_ok(filesize($filepath));
            $downloaded_files++;
        }
        
        // 파일 수가 맞는지 확인한다.
        
        if ($downloaded_files < 5)
        {
            echo '[ERROR] 다운로드한 파일 수가 일치하지 않습니다.' . PHP_EOL;
            exit(2);
        }
    }
    
    // 다운로드 진행 상황 표시 콜백 함수.
    
    public static function progress($ch, $fd = null, $size = null)
    {
        if ($size <= 0) return;
        Postcodify_Utility::print_progress($size);
    }
}
