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
    
    // 생성자.
    
    public function __construct()
    {
        $this->_data_dir = dirname(POSTCODIFY_LIB_DIR) . '/data';
    }
    
    // 엔트리 포인트.
    
    public function start()
    {
        $this->create_tables();
        $this->load_data_date();
        $this->load_road_info();
    }
    
    // 테이블을 생성한다.
    
    public function create_tables()
    {
        $db = Postcodify_Utility::get_db();
        $db->exec(file_get_contents(POSTCODIFY_LIB_DIR . '/resources/schema-mysql.sql'));
        unset($db);
    }
    
    // 데이터 기준일 정보를 로딩한다.
    
    public function load_data_date()
    {
        $date = trim(file_get_contents($this->_data_dir . '/도로명코드_기준일.txt'));
        $this->_data_date = $date;
        
        $db = Postcodify_Utility::get_db();
        $db->exec("INSERT INTO postcodify_metadata (k, v) VALUES ('version', '" . POSTCODIFY_VERSION . "')");
        $db->exec("INSERT INTO postcodify_metadata (k, v) VALUES ('updated', '" . $this->_data_date . "')");
        unset($db);
    }
    
    // 도로명코드 목록을 로딩한다.
    
    public function load_road_info()
    {
        $db = Postcodify_Utility::get_db();
        $db->beginTransaction();
        $ps = $db->prepare('INSERT INTO postcodify_roads (road_id, road_name_ko, road_name_en, ' .
            'sido_ko, sido_en, sigungu_ko, sigungu_en, ilbangu_ko, ilbangu_en, eupmyeon_ko, eupmyeon_en) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        $zip = new Postcodify_Indexer_Parser_Road_List;
        $zip->open_archive($this->_data_dir . '/도로명코드_전체분.zip');
        $zip->open_first_file();
        
        while ($entry = $zip->read_line())
        {
            $ps->execute(array(
                $entry['road_id'] . $entry['road_section'],
                $entry['road_name'],
                $entry['road_name_english'],
                $entry['sido'],
                $entry['sido_english'],
                $entry['sigungu'],
                $entry['sigungu_english'],
                $entry['ilbangu'],
                $entry['ilbangu_english'],
                $entry['eupmyeon'],
                $entry['eupmyeon_english'],
            ));
        }
        
        $zip->close();
        $db->commit();
        unset($zip);
        unset($db);
    }
}
