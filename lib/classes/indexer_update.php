<?php

/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (인덱서)
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

class Postcodify_Indexer_Update
{
    // 설정 저장.
    
    protected $_data_dir;
    protected $_dry_run = false;
    
    // 생성자.
    
    public function __construct()
    {
        $this->_data_dir = dirname(POSTCODIFY_LIB_DIR) . '/data/updates';
    }
    
    // 엔트리 포인트.
    
    public function start($args)
    {
        if (in_array('--dry-run', $args->options))
        {
            $this->_dry_run = true;
        }
        
        Postcodify_Utility::print_message('Postcodify Indexer ' . POSTCODIFY_VERSION . ($this->_dry_run ? ' (시험구동)' : ''));
        Postcodify_Utility::print_newline();
        
        // 어디까지 업데이트했는지 찾아본다.
        
        $db = Postcodify_Utility::get_db();
        $updated_query = $db->query('SELECT v FROM postcodify_settings WHERE k = \'updated\'');
        $updated = $updated_query->fetchColumn();
        $updated_query->closeCursor();
        unset($updated_query);
        unset($db);
        
        if (!preg_match('/^20[0-9]{6}$/', $updated))
        {
            echo '[ERROR] 기존 DB의 데이터 기준일을 찾을 수 없습니다.' . PHP_EOL;
            exit(3);
        }
        
        // 신설·변경·폐지된 도로 정보를 로딩한다.
        
        echo '업데이트된 도로 정보를 로딩하는 중...' . PHP_EOL;
        $this->load_updated_road_list();
        
        // 신설·변경·폐지된 주소 정보를 로딩한다.
        
        echo '업데이트된 주소 정보를 로딩하는 중...' . PHP_EOL;
        $this->load_updated_addresses();
        
        // 데이터 기준일 정보를 업데이트한다.
        
        $db = Postcodify_Utility::get_db();
        $updated_query = $db->prepare('UPDATE postcodify_settings SET v = ? WHERE k = \'updated\'');
        $updated_query->execute(array($updated));
        unset($updated_query);
        unset($db);
    }
    
    // 업데이트된 도로 정보를 로딩한다.
    
    public function load_updated_road_list()
    {
        // DB를 준비한다.
        
        if (!$this->_dry_run)
        {
            $db = Postcodify_Utility::get_db();
            $db->beginTransaction();
            $ps_exists = $db->prepare('SELECT 1 FROM postcodify_roads WHERE road_id = ?');
            $ps_insert = $db->prepare('INSERT INTO postcodify_roads (road_id, road_name_ko, road_name_en, ' .
                'sido_ko, sido_en, sigungu_ko, sigungu_en, ilbangu_ko, ilbangu_en, eupmyeon_ko, eupmyeon_en) ' .
                'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ps_update = $db->prepare('UPDATE postcodify_roads SET road_name_ko = ?, road_name_en = ?, ' .
                'sido_ko = ?, sido_en = ?, sigungu_ko = ?, sigungu_en = ?, ilbangu_ko = ?, ilbangu_en = ?, ' .
                'eupmyeon_ko = ?, eupmyeon_en = ? WHERE road_id = ?');
        }
        
        // 데이터 파일 목록을 구한다.
        
        $files = glob($this->_data_dir . '/AlterD.JUSUZC.*.TXT');
        
        // 각 파일을 순서대로 파싱한다.
        
        foreach ($files as $filename)
        {
            // 파일을 연다.
            
            Postcodify_Utility::print_message('  - ' . substr(basename($filename), 14));
            $file = new Postcodify_Parser_Updated_Road_List;
            $file->open($filename);
            
            // 카운터를 초기화한다.
            
            $count = 0;
            
            // 데이터를 한 줄씩 읽는다.
            
            while ($entry = $file->read_line())
            {
                // 이미 존재하는 도로인지 확인한다.
                
                if (!$this->_dry_run)
                {
                    $ps_exists->execute(array($entry->road_id . $entry->road_section));
                    $road_exists = $ps_exists->fetchColumn();
                    $ps_exists->closeCursor();
                }
                else
                {
                    $road_exists = 0;
                }
                
                // 도로 정보를 DB에 저장한다.
                
                if (!$this->_dry_run)
                {
                    if (!$road_exists)
                    {
                        $ps_insert->execute(array(
                            $entry->road_id . $entry->road_section,
                            $entry->road_name,
                            $entry->road_name_english,
                            $entry->sido,
                            $entry->sido_english,
                            $entry->sigungu,
                            $entry->sigungu_english,
                            $entry->ilbangu,
                            $entry->ilbangu_english,
                            $entry->eupmyeon,
                            $entry->eupmyeon_english,
                        ));
                    }
                    else
                    {
                        $ps_update->execute(array(
                            $entry->road_name,
                            $entry->road_name_english,
                            $entry->sido,
                            $entry->sido_english,
                            $entry->sigungu,
                            $entry->sigungu_english,
                            $entry->ilbangu,
                            $entry->ilbangu_english,
                            $entry->eupmyeon,
                            $entry->eupmyeon_english,
                            $entry->road_id . $entry->road_section,
                        ));
                    }
                }
                
                // 카운터를 표시한다.
                
                if (++$count % 64 === 0) Postcodify_Utility::print_progress($count);
                unset($entry);
            }
            
            // 파일을 닫는다.
            
            $file->close();
            unset($file);
            
            Postcodify_Utility::print_ok();
        }
        
        // 뒷정리.
        
        if (!$this->_dry_run)
        {
            $db->commit();
            unset($db);
        }
    }
    
    // 업데이트된 주소 정보를 로딩한다.
    
    public function load_updated_addresses()
    {
        // DB를 준비한다.
        
        if (!$this->_dry_run)
        {
            $db = Postcodify_Utility::get_db();
            $db->beginTransaction();
            $ps_exists = $db->prepare('SELECT 1 FROM postcodify_addresses WHERE address_id = ?');
        }
        
        // 데이터 파일 목록을 구한다.
        
        $files = glob($this->_data_dir . '/AlterD.JUSUBH.*.TXT');
        
        // 각 파일을 순서대로 파싱한다.
        
        foreach ($files as $filename)
        {
            // 파일을 연다.
            
            Postcodify_Utility::print_message('  - ' . substr(basename($filename), 14));
            $file = new Postcodify_Parser_Updated_Address;
            $file->open($filename);
            
            // 카운터를 초기화한다.
            
            $count = 0;
            
            // 데이터를 한 줄씩 읽는다.
            
            while ($entry = $file->read_line())
            {
                // 이미 존재하는 주소인지 확인한다.
                
                if (!$this->_dry_run)
                {
                    $ps_exists->execute(array($entry->address_id));
                    $address_exists = $ps_exists->fetchColumn();
                    $ps_exists->closeCursor();
                }
                else
                {
                    $address_exists = 0;
                }
                
                // 카운터를 표시한다.
                
                if (++$count % 64 === 0) Postcodify_Utility::print_progress($count);
                unset($entry);
            }
            
            // 파일을 닫는다.
            
            $file->close();
            unset($file);
            
            Postcodify_Utility::print_ok();
        }
        
        // 뒷정리.
        
        if (!$this->_dry_run)
        {
            $db->commit();
            unset($db);
        }
    }
}
