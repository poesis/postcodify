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

class Postcodify_Indexer_CreateDB
{
    // 설정 저장.
    
    protected $_data_dir;
    protected $_data_date;
    
    protected $_thread_groups = array(
        '경기도', '경상남북도', '전라남북도', '충청남북도',
        '서울특별시|부산광역시|세종특별자치시|제주특별자치도',
        '강원도|광주광역시|대구광역시|대전광역시|울산광역시|인천광역시',
    );
    
    // 생성자.
    
    public function __construct()
    {
        $this->_data_dir = dirname(POSTCODIFY_LIB_DIR) . '/data';
    }
    
    // 엔트리 포인트.
    
    public function start()
    {
        $this->print_message('Postcodify Indexer ' . POSTCODIFY_VERSION . (DRY_RUN ? ' (시험구동)' : ''));
        $this->print_newline();
        
        $this->print_message('테이블을 생성하는 중...');
        $this->create_tables();
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('데이터 기준일 정보를 저장하는 중...');
        $this->load_data_date();
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('도로명코드 목록을 로딩하는 중...');
        $this->load_road_info();
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('상세건물명을 로딩하는 중...');
        $this->load_building_info();
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('영문 행정구역명을 로딩하는 중...');
        $this->load_english_aliases();
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('주소 파일을 로딩하는 중...');
        $this->print_newline();
        $this->load_juso();
    }
    
    // 터미널에 메시지를 출력하고 커서를 오른쪽 끝으로 이동한다.
    
    public function print_message($str)
    {
        echo $str . str_repeat(' ', max(12, TERMINAL_WIDTH - Postcodify_Utility::get_terminal_width($str)));
    }
    
    // 터미널에 진행 상황을 출력한다.
    
    public function print_progress($num)
    {
        Postcodify_Utility::print_negative_spaces(12);
        echo str_pad(number_format($num), 10, ' ', STR_PAD_LEFT) . '  ';
    }
    
    // 터미널에 OK 메시지를 출력한다.
    
    public function print_ok()
    {
        Postcodify_Utility::print_negative_spaces(12);
        echo str_repeat(' ', 6) . '[ OK ]';
    }
    
    // 터미널의 커서를 다음 줄로 이동한다.
    
    public function print_newline()
    {
        echo PHP_EOL;
    }
    
    // 테이블을 생성한다.
    
    public function create_tables()
    {
        if (!DRY_RUN)
        {
            $db = Postcodify_Utility::get_db();
            $db->exec(file_get_contents(POSTCODIFY_LIB_DIR . '/resources/schema-mysql.sql'));
            unset($db);
        }
    }
    
    // 인덱스를 생성한다.
    
    public function create_indexes()
    {
        if (!DRY_RUN)
        {
            $db = Postcodify_Utility::get_db();
            unset($db);
        }
    }
    
    // 데이터 기준일 정보를 로딩한다.
    
    public function load_data_date()
    {
        $date = trim(file_get_contents($this->_data_dir . '/도로명코드_기준일.txt'));
        $this->_data_date = $date;
        
        if (!DRY_RUN)
        {
            $db = Postcodify_Utility::get_db();
            $db->exec("INSERT INTO postcodify_settings (k, v) VALUES ('version', '" . POSTCODIFY_VERSION . "')");
            $db->exec("INSERT INTO postcodify_settings (k, v) VALUES ('updated', '" . $this->_data_date . "')");
            unset($db);
        }
    }
    
    // 도로명코드 목록을 로딩한다.
    
    public function load_road_info()
    {
        if (!DRY_RUN)
        {
            $db = Postcodify_Utility::get_db();
            $db->beginTransaction();
            $ps = $db->prepare('INSERT INTO postcodify_roads (road_id, road_name_ko, road_name_en, ' .
                'sido_ko, sido_en, sigungu_ko, sigungu_en, ilbangu_ko, ilbangu_en, eupmyeon_ko, eupmyeon_en) ' .
                'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        }
        
        $zip = new Postcodify_Indexer_Parser_Road_List;
        $zip->open_archive($this->_data_dir . '/도로명코드_전체분.zip');
        $zip->open_next_file();
        
        $count = 0;
        
        while ($entry = $zip->read_line())
        {
            Postcodify_Utility::$english_cache[$entry->road_name] = $entry->road_name_english;
            Postcodify_Utility::$english_cache[$entry->sido] = $entry->sido_english;
            if ($entry->sigungu) Postcodify_Utility::$english_cache[$entry->sigungu] = $entry->sigungu_english;
            if ($entry->ilbangu) Postcodify_Utility::$english_cache[$entry->ilbangu] = $entry->ilbangu_english;
            if ($entry->eupmyeon) Postcodify_Utility::$english_cache[$entry->eupmyeon] = $entry->eupmyeon_english;
            
            if (!DRY_RUN)
            {
                $ps->execute(array(
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
            
            if (++$count % 512 === 0) $this->print_progress($count);
            unset($entry);
        }
        
        $zip->close();
        unset($zip);
        
        if (!DRY_RUN)
        {
            $db->commit();
            unset($db);
        }
    }
    
    // 상세건물명을 로딩한다.
    
    public function load_building_info()
    {
        $zip = new Postcodify_Indexer_Parser_Building_Info;
        $zip->open_archive($this->_data_dir . '/상세건물명.zip');
        $zip->open_next_file();
        
        $count = 0;
        
        while ($entry = $zip->read_line())
        {
            if (count($entry->building_names))
            {
                Postcodify_Utility::$building_cache[$entry->address_id] = implode(',', $entry->building_names);
            }
            
            if (++$count % 512 === 0) $this->print_progress($count);
            unset($entry);
        }
    }
    
    // 영문 행정구역명을 로딩한다.
    
    public function load_english_aliases()
    {
        $zip = new Postcodify_Indexer_Parser_English_Aliases;
        $zip->open_archive($this->_data_dir . '/english_aliases_DB.zip');
        $zip->open_next_file();
        
        $count = 0;
        
        while ($entry = $zip->read_line())
        {
            Postcodify_Utility::$english_cache[$entry->ko] = $entry->en;
            if (++$count % 512 === 0) $this->print_progress($count);
            unset($entry);
        }
    }
    
    // 주소 파일을 로딩한다.
    
    public function load_juso()
    {
        
    }
    
    // 지번 파일을 로딩한다.
    
    public function load_jibeon()
    {
        
    }
    
    // 부가정보 파일을 로딩한다.
    
    public function load_extra_info()
    {
        
    }
    
    // 사서함 파일을 로딩한다.
    
    public function load_pobox()
    {
        
    }
    
    // 지번 우편번호 파일을 로딩한다.
    
    public function load_jibeon_postcode()
    {
        
    }
}
