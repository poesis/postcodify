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

class Postcodify_Indexer_Download_Updates
{
    // 상수 선언 부분.
    
    const RELATIVE_DOMAIN = 'http://www.juso.go.kr';
    const DOWNLOAD_URL = '/dn.do?reqType=DC&stdde=%s&indutyCd=999&purpsCd=999&indutyRm=%%EC%%88%%98%%EC%%A7%%91%%EC%%A2%%85%%EB%%A3%%8C&purpsRm=%%EC%%88%%98%%EC%%A7%%91%%EC%%A2%%85%%EB%%A3%%8C';
    
    // 엔트리 포인트.
    
    public function start()
    {
        // 다운로드할 경로가 존재하는지 확인한다.
        
        Postcodify_Utility::print_message('Postcodify Indexer ' . POSTCODIFY_VERSION);
        Postcodify_Utility::print_newline();
        
        $download_path = dirname(POSTCODIFY_LIB_DIR) . '/data';
        
        if ((!file_exists($download_path) || !is_dir($download_path)) && !@mkdir($download_path, 0755, true))
        {
            echo '[ERROR] 다운로드 대상 경로(' . $download_path . ')가 존재하지 않습니다.' . PHP_EOL;
            exit(2);
        }
        
        // 어디까지 업데이트했는지 찾아본다.
        
        $db = Postcodify_Utility::get_db();
        $updated_query = $db->query('SELECT v FROM postcodify_settings WHERE k = \'updated\'');
        $updated = $updated_query->fetchColumn();
        $updated_query->closeCursor();
        if (!preg_match('/^20[0-9]{6}$/', $updated))
        {
            echo '[ERROR] 기존 DB의 데이터 기준일을 찾을 수 없습니다.' . PHP_EOL;
            exit(3);
        }
        
        $current_time = mktime(12, 0, 0, date('m'), date('d'), date('Y'));
        $updated_time = mktime(12, 0, 0, substr($updated, 4, 2), substr($updated, 6, 2), substr($updated, 0, 4));
        if ($updated_time < $current_time - (86400 * 365))
        {
            echo '[ERROR] 마지막 업데이트로부터 365일 이상이 경과하였습니다. DB를 새로 생성하시기 바랍니다.' . PHP_EOL;
            exit(3);
        }
        if ($updated_time >= $current_time)
        {
            echo '업데이트가 필요하지 않습니다.' . PHP_EOL;
            exit(0);
        }
        
        // 다운로드할 업데이트 목록을 생성한다.
        
        $updates = array();
        for ($time = $updated_time + 86400; $time < $current_time; $time += 86400)
        {
            $updates[] = date('Ymd', $time);
        }
        
        // 업데이트를 다운로드한다.
        
        foreach ($updates as $date)
        {
            $filepath = $download_path . '/' . $date . '_dailynoticedata.zip';
            if (file_exists($filepath))
            {
                Postcodify_Utility::print_message('파일이 이미 존재함: ' . $date . '_dailynoticedata.zip');
                continue;
            }
            
            Postcodify_Utility::print_message('다운로드: ' . $date . '_dailynoticedata.zip');
            
            $link = self::RELATIVE_DOMAIN . sprintf(self::DOWNLOAD_URL, $date);
            $result = Postcodify_Utility::download($link, $filepath, array(__CLASS__, 'progress'));
            if (!$result || !file_exists($filepath))
            {
                Postcodify_Utility::print_error();
                @unlink($filepath);
                continue;
            }
            
            if (filesize($filepath) < 512 && stripos(file_get_contents($filepath), 'not found') !== false)
            {
                Postcodify_Utility::print_error();
                @unlink($filepath);
                continue;
            }
            
            $zip = new ZipArchive;
            $result = $zip->open($filepath);
            if (!$result)
            {
                Postcodify_Utility::print_error();
                @unlink($filepath);
                continue;
            }
            
            Postcodify_Utility::print_ok(filesize($filepath));
        }
    }
    
    // 다운로드 진행 상황 표시 콜백 함수.
    
    public static function progress($ch, $fd, $size)
    {
        if ($size <= 0) return;
        Postcodify_Utility::print_progress($size);
    }
}
