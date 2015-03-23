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
    protected $_ranges_available = true;
    
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
        
        if (!preg_match('/^20[0-9]{6}$/', $updated))
        {
            echo '[ERROR] 기존 DB의 데이터 기준일을 찾을 수 없습니다.' . PHP_EOL;
            exit(3);
        }
        
        // 범위 데이터를 사용할 수 있는지 확인한다.
        
        $tables_query = $db->query("SHOW TABLES LIKE 'postcodify_ranges_roads'");
        if ($tables_query->fetchColumn() === false)
        {
            $this->_ranges_available = false;
        }
        unset($tables_query);
        unset($db);
        
        // 신설·변경·폐지된 도로 정보를 로딩한다.
        
        echo '업데이트된 도로 정보를 로딩하는 중...' . PHP_EOL;
        $updated1 = $this->load_updated_road_list($updated);
        
        // 신설·변경·폐지된 주소 정보를 로딩한다.
        
        echo '업데이트된 주소 정보를 로딩하는 중...' . PHP_EOL;
        $updated2 = $this->load_updated_addresses($updated);
        
        // 데이터 기준일 정보를 업데이트한다.
        
        $updated = strval(max(intval($updated), intval($updated1), intval($updated2)));
        
        $db = Postcodify_Utility::get_db();
        $updated_query = $db->prepare('UPDATE postcodify_settings SET v = ? WHERE k = \'updated\'');
        $updated_query->execute(array($updated));
        unset($updated_query);
        unset($db);
    }
    
    // 업데이트된 도로 정보를 로딩한다.
    
    public function load_updated_road_list($after_date)
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
        $last_date = $after_date;
        
        // 각 파일을 순서대로 파싱한다.
        
        foreach ($files as $filename)
        {
            // 데이터 기준일 이전의 파일은 무시한다.
            
            $file_date = substr(basename($filename), 14, 8);
            if (strcmp($file_date, $after_date) <= 0) continue;
            if (strcmp($file_date, $last_date) > 0) $last_date = $file_date;
            
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
                    $road_exists = false;
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
                
                if (++$count % 16 === 0) Postcodify_Utility::print_progress($count);
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
        
        // 마지막으로 처리한 파일의 기준일을 반환한다.
        
        return $last_date;
    }
    
    // 업데이트된 주소 정보를 로딩한다.
    
    public function load_updated_addresses($after_date)
    {
        // DB를 준비한다.
        
        if (!$this->_dry_run)
        {
            $db = Postcodify_Utility::get_db();
            $db->beginTransaction();
            $ps_exists = $db->prepare('SELECT * FROM postcodify_addresses WHERE address_id = ?');
            $ps_road_info = $db->prepare('SELECT * FROM postcodify_roads WHERE road_id = ?');
            $ps_addr_insert = $db->prepare('INSERT INTO postcodify_addresses (address_id, postcode5, postcode6, ' .
                'road_id, num_major, num_minor, is_basement, dongri_ko, dongri_en, jibeon_major, jibeon_minor, is_mountain, ' .
                'building_name, other_addresses, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ps_addr_update = $db->prepare('UPDATE postcodify_addresses SET address_id = ?, postcode5 = ?, postcode6 = ?, ' .
                'road_id = ?, num_major = ?, num_minor = ?, is_basement = ?, dongri_ko = ?, dongri_en = ?, ' .
                'jibeon_major = ?, jibeon_minor = ?, is_mountain = ?, building_name = ?, other_addresses = ?, updated = ? ' .
                'WHERE id = ?');
            $ps_select_keywords = $db->prepare('SELECT keyword_crc32 FROM postcodify_keywords WHERE address_id = ?');
            $ps_select_numbers = $db->prepare('SELECT num_major, num_minor FROM postcodify_numbers WHERE address_id = ?');
            $ps_select_buildings = $db->prepare('SELECT keyword FROM postcodify_buildings WHERE address_id = ?');
            $ps_insert_keywords = $db->prepare('INSERT INTO postcodify_keywords (address_id, keyword_crc32) VALUES (?, ?)');
            $ps_insert_numbers = $db->prepare('INSERT INTO postcodify_numbers (address_id, num_major, num_minor) VALUES (?, ?, ?)');
            $ps_insert_buildings = $db->prepare('INSERT INTO postcodify_buildings (address_id, keyword) VALUES (?, ?)');
            $ps_delete_buildings = $db->prepare('DELETE FROM postcodify_buildings WHERE address_id = ?');
        }
        
        // 데이터 파일 목록을 구한다.
        
        $files = glob($this->_data_dir . '/AlterD.JUSUBH.*.TXT');
        $last_date = $after_date;
        
        // 각 파일을 순서대로 파싱한다.
        
        foreach ($files as $filename)
        {
            // 데이터 기준일 이전의 파일은 무시한다.
            
            $file_date = substr(basename($filename), 14, 8);
            if (strcmp($file_date, $after_date) <= 0) continue;
            if (strcmp($file_date, $last_date) > 0) $last_date = $file_date;
            
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
                    $address_info = $ps_exists->fetchObject();
                    $ps_exists->closeCursor();
                }
                else
                {
                    $address_info = false;
                }
                
                // 도로 정보를 파악한다.
                
                if (!$this->_dry_run)
                {
                    $ps_road_info->execute(array($entry->road_id . $entry->road_section));
                    $road_info = $ps_road_info->fetchObject();
                    $ps_road_info->closeCursor();
                }
                else
                {
                    $road_info = false;
                }
                
                // 도로 정보가 없는 레코드는 무시한다.
                
                if (!$road_info) continue;
                
                // 기존의 검색 키워드와 번호들을 가져온다.
                
                $existing_keywords = $existing_numbers = $existing_buildings = array();
                
                if (!$this->_dry_run && $address_info)
                {
                    $ps_select_keywords->execute(array($address_info->id));
                    while ($row = $ps_select_keywords->fetchColumn())
                    {
                        $existing_keywords[$row] = true;
                    }
                    $ps_select_keywords->closeCursor();
                    
                    $ps_select_numbers->execute(array($address_info->id));
                    while ($row = $ps_select_numbers->fetch(PDO::FETCH_NUM))
                    {
                        $existing_numbers[implode('-', $row)] = true;
                    }
                    $ps_select_numbers->closeCursor();
                    
                    $ps_select_buildings->execute(array($address_info->id));
                    while ($row = $ps_select_buildings->fetchColumn())
                    {
                        $existing_buildings[] = $row;
                    }
                    $ps_select_buildings->closeCursor();
                }
                
                // 신설 또는 변경된 주소인 경우...
                
                if (!$this->_dry_run && $entry->change_code !== Postcodify_Parser_Updated_Address::CODE_DELETED)
                {
                    // 이미 존재하는 주소가 아닌 경우... (신설)
                    
                    if (!$address_info)
                    {
                        // 우편번호가 누락된 경우 구 주소 우편번호 데이터를 사용하여 찾는다.
                        
                        if (trim($entry->postcode6) === '' || $entry->postcode6 === '000000')
                        {
                            $entry->postcode6 = $this->find_postcode6($db, $road_info, $entry->dongri, $entry->admin_dongri, $entry->jibeon_major, $entry->jibeon_major, $entry->jibeon_minor);
                        }
                        
                        // 기초구역번호를 구한다.
                        
                        $postcode5 = $this->find_postcode5($db, $road_info, $entry->num_major, $entry->num_minor, $entry->dongri, $entry->admin_dongri, $entry->jibeon_major, $entry->jibeon_minor, $entry->postcode6);
                        
                        // 영문 동·리를 구한다.
                        
                        $dongri_en = $this->find_dongri_english($entry->dongri);
                        
                        // 기타 주소 목록을 생성한다.
                        
                        $other_addresses = $entry->admin_dongri ? array($entry->admin_dongri) : array();
                        $other_addresses = array_merge($other_addresses, $entry->building_names);
                        $other_addresses = implode('; ', $other_addresses);
                        
                        // DB에 저장한다.
                        
                        $ps_addr_insert->execute(array(
                            $entry->address_id,
                            $postcode5,
                            $entry->postcode6,
                            $entry->road_id . $entry->road_section,
                            $entry->num_major,
                            $entry->num_minor,
                            $entry->is_basement,
                            $entry->dongri,
                            $dongri_en,
                            $entry->jibeon_major,
                            $entry->jibeon_minor,
                            $entry->is_mountain,
                            $entry->common_residence_name,
                            $other_addresses,
                            $entry->change_date,
                        ));
                        $proxy_id = $db->lastInsertId();
                    }
                    
                    // 이미 존재하는 주소인 경우... (변경)
                    
                    else
                    {
                        // 우편번호가 누락된 경우 기존의 우편번호를 사용한다.
                        
                        if (trim($entry->postcode6) === '' || $entry->postcode6 === '000000')
                        {
                            $entry->postcode6 = $address_info->postcode6;
                        }
                        
                        // 영문 동·리를 구한다.
                        
                        if ($entry->dongri === $address_info->dongri_ko)
                        {
                            $dongri_en = $address_info->dongri_en;
                        }
                        else
                        {
                            $dongri_en = $this->find_dongri_english($entry->dongri);
                        }
                        
                        // 기타 주소 목록을 생성한다.
                        
                        $other_addresses = explode('; ', $address_info->other_addresses);
                        foreach ($entry->building_names as $building_name)
                        {
                            if (!in_array($building_name, $other_addresses))
                            {
                                $other_addresses[] = $building_name;
                            }
                        }
                        $other_addresses = implode('; ', $other_addresses);
                        
                        // DB에 저장한다.
                        
                        $ps_addr_update->execute(array(
                            $entry->address_id,
                            $address_info->postcode5,
                            $entry->postcode6,
                            $entry->road_id . $entry->road_section,
                            $entry->num_major,
                            $entry->num_minor,
                            $entry->is_basement,
                            $entry->dongri,
                            $dongri_en,
                            $entry->jibeon_major,
                            $entry->jibeon_minor,
                            $entry->is_mountain,
                            $entry->common_residence_name,
                            $other_addresses,
                            $entry->change_date,
                            $address_info->id,
                        ));
                        $proxy_id = $address_info->id;
                    }
                    
                    // 검색 키워드를 정리하여 저장한다.
                    
                    $keywords = array();
                    $keywords = array_merge($keywords, Postcodify_Utility::get_variations_of_road_name($road_info->road_name_ko));
                    $keywords = array_merge($keywords, Postcodify_Utility::get_variations_of_dongri($entry->dongri));
                    $keywords = array_merge($keywords, Postcodify_Utility::get_variations_of_dongri($entry->admin_dongri));
                    $keywords = array_unique($keywords);
                    foreach ($keywords as $keyword)
                    {
                        $keyword_crc32 = Postcodify_Utility::crc32_x64($keyword);
                        if (isset($existing_keywords[$keyword_crc32])) continue;
                        $ps_insert_keywords->execute(array($proxy_id, $keyword_crc32));
                    }
                    
                    // 번호들을 정리하여 저장한다.
                    
                    $numbers = array(
                        array($entry->num_major, $entry->num_minor),
                        array($entry->jibeon_major, $entry->jibeon_minor),
                    );
                    foreach ($numbers as $number)
                    {
                        $number_key = implode('-', $number);
                        if (isset($existing_numbers[$number_key])) continue;
                        $ps_insert_numbers->execute(array($proxy_id, $number[0], $number[1]));
                    }
                    
                    // 건물명을 정리하여 저장한다.
                    
                    $building_names = array_merge($existing_buildings, $entry->building_names);
                    $building_names_consolidated = Postcodify_Utility::consolidate_building_names($building_names);
                    if ($building_names_consolidated !== '')
                    {
                        if (count($existing_buildings)) $ps_delete_buildings->execute(array($proxy_id));
                        $ps_insert_buildings->execute(array($proxy_id, $building_names_consolidated));
                    }
                }
                
                // 폐지된 주소인 경우...
                
                if (!$this->_dry_run && $entry->change_code === Postcodify_Parser_Updated_Address::CODE_DELETED)
                {
                    // 안행부에서 멀쩡한 주소를 삭제했다가 며칠 후 다시 추가하는 경우가 종종 있다.
                    // 이걸 너무 열심히 따라하면 애꿎은 사용자들이 불편을 겪게 되므로
                    // 주소가 폐지된 것으로 나오더라도 DB에는 그대로 두는 것이 좋다.
                }
                
                // 카운터를 표시한다.
                
                if (++$count % 16 === 0) Postcodify_Utility::print_progress($count);
                unset($address_info);
                unset($road_info);
                unset($existing_keywords);
                unset($existing_numbers);
                unset($existing_buildings);
                unset($keywords);
                unset($numbers);
                unset($building_names);
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
        
        // 마지막으로 처리한 파일의 기준일을 반환한다.
        
        return $last_date;
    }
    
    // 주어진 주소와 가장 근접한 기존 우편번호를 찾는 함수.
    
    public function find_postcode6($db, $road_info, $dongri, $admin_dongri, $jibeon_major, $jibeon_minor)
    {
        // 시험구동인 경우 null을 반환한다.
        
        if ($this->_dry_run) return null;
        
        // 범위 데이터를 사용할 수 없는 경우 null을 반환한다.
        
        if (!$this->_ranges_available) return null;
        
        // Prepared Statement를 생성한다.
        
        static $ps = null;
        if ($ps === null)
        {
            $ps = $db->prepare('SELECT postcode6 FROM postcodify_ranges_oldcode WHERE sido_ko = ? AND ' .
                '(sigungu_ko IS NULL OR sigungu_ko = ?) AND (ilbangu_ko IS NULL OR ilbangu_ko = ?) AND ' .
                '(eupmyeon_ko IS NULL OR eupmyeon_ko = ?) AND (dongri_ko = ? OR dongri_ko = ?) AND ' .
                '(range_start_major IS NULL OR (range_start_major <= ? AND range_end_major >= ? AND ' .
                '(range_start_minor IS NULL OR (range_start_minor <= ? AND range_end_minor >= ?)))) ORDER BY seq LIMIT 1');
        }
        
        // 우편번호를 찾는다.
        
        $ps->execute(array(
            $road_info->sido_ko,
            $road_info->sigungu_ko,
            $road_info->ilbangu_ko,
            $road_info->eupmyeon_ko,
            $dongri,
            $admin_dongri,
            $jibeon_major,
            $jibeon_major,
            $jibeon_minor,
            $jibeon_minor,
        ));
        
        // 검색 결과가 있을 경우 우편번호를 반환하고, 그렇지 않으면 null을 반환한다.
        
        $postcode6 = $ps->fetchColumn();
        $ps->closeCursor();
        
        if ($postcode6)
        {
            return $postcode6;
        }
        else
        {
            return null;
        }
    }
    
    // 주어진 주소와 가장 근접한 기초구역번호(새우편번호)를 찾는 함수.
    
    public function find_postcode5($db, $road_info, $num_major, $num_minor, $dongri, $admin_dongri, $jibeon_major, $jibeon_minor, $postcode6)
    {
        // 시험구동인 경우 null을 반환한다.
        
        if ($this->_dry_run) return null;
        
        // 범위 데이터를 사용할 수 없는 경우 null을 반환한다.
        
        if (!$this->_ranges_available) return null;
        
        // Prepared Statement를 생성한다.
        
        static $ps1 = null;
        static $ps2 = null;
        static $ps3 = null;
        static $ps4 = null;
        static $ps5 = null;
        static $ps6 = null;
        static $ps7 = null;
        
        if ($ps1 === null)
        {
            $ps1 = $db->prepare('SELECT postcode5 FROM postcodify_ranges_roads WHERE sido_ko = ? AND ' .
                '(sigungu_ko IS NULL OR sigungu_ko = ?) AND (ilbangu_ko IS NULL OR ilbangu_ko = ?) AND ' .
                '(eupmyeon_ko IS NULL OR eupmyeon_ko = ?) AND road_name_ko = ? AND ' .
                'range_start_major <= ? AND range_end_major >= ? AND ' .
                '(range_start_minor IS NULL OR (range_start_minor <= ? AND range_end_minor >= ?)) AND ' .
                '(range_type = 0 OR range_type = 3 OR range_type = ?) ORDER BY seq LIMIT 1');
            $ps2 = $db->prepare('SELECT postcode5 FROM postcodify_ranges_jibeon WHERE sido_ko = ? AND ' .
                '(sigungu_ko IS NULL OR sigungu_ko = ?) AND (ilbangu_ko IS NULL OR ilbangu_ko = ?) AND ' .
                '(eupmyeon_ko IS NULL OR eupmyeon_ko = ?) AND (dongri_ko = ? OR admin_dongri = ?) AND ' .
                'range_start_major <= ? AND range_end_major >= ? AND ' .
                '(range_start_minor IS NULL OR (range_start_minor <= ? AND range_end_minor >= ?)) ORDER BY seq LIMIT 1');
            $ps3 = $db->prepare('SELECT postcode5 FROM postcodify_addresses pa ' .
                'JOIN postcodify_keywords pk ON pa.id = pk.address_id ' .
                'JOIN postcodify_numbers pn ON pa.id = pn.address_id ' .
                'WHERE pk.keyword_crc32 = ? AND pn.num_major = ? AND pn.num_minor = ? LIMIT 1');
            $ps4 = $db->prepare('SELECT postcode5 FROM postcodify_addresses pa ' .
                'JOIN postcodify_keywords pk ON pa.id = pk.address_id ' .
                'JOIN postcodify_numbers pn ON pa.id = pn.address_id ' .
                'WHERE pk.keyword_crc32 = ? AND pn.num_major = ? LIMIT 1');
            $ps5 = $db->prepare('SELECT postcode5 FROM postcodify_addresses WHERE road_id = ? ' .
                'AND num_major % 2 = ? ORDER BY ABS(? - num_major) DESC LIMIT 1');
            $ps6 = $db->prepare('SELECT postcode5 FROM postcodify_addresses WHERE road_id >= ? AND road_id <= ? ' . 
                'AND num_major % 2 = ? ORDER BY ABS(? - num_major) DESC LIMIT 1');
            $ps7 = $db->prepare('SELECT postcode5 FROM postcodify_addresses WHERE postcode6 = ? ' . 
                'ORDER BY ABS(? - num_major) DESC LIMIT 1');
        }
        
        // 도로명주소 범위 데이터를 사용하여 기초구역번호를 찾아본다.
        
        $ps1->execute(array(
            $road_info->sido_ko,
            $road_info->sigungu_ko,
            $road_info->ilbangu_ko,
            $road_info->eupmyeon_ko,
            $road_info->road_name_ko,
            $num_major,
            $num_major,
            $num_minor,
            $num_minor,
            $num_major % 2 ? 2 : 1,
        ));
        if ($postcode5 = $ps1->fetchColumn())
        {
            $ps1->closeCursor();
            return $postcode5;
        }
        
        // 지번주소 범위 데이터를 사용하여 기초구역번호를 찾아본다.
        
        $ps2->execute(array(
            $road_info->sido_ko,
            $road_info->sigungu_ko,
            $road_info->ilbangu_ko,
            $road_info->eupmyeon_ko,
            $dongri,
            $admin_dongri,
            $jibeon_major,
            $jibeon_major,
            $jibeon_minor,
            $jibeon_minor,
        ));
        if ($postcode5 = $ps2->fetchColumn())
        {
            $ps2->closeCursor();
            return $postcode5;
        }
        
        // 같은 지번에 이미 부여된 기초구역번호가 있는지 찾아본다.
        
        $ps3->execute(array(Postcodify_Utility::crc32_x64($dongri), $jibeon_major, $jibeon_minor));
        if ($postcode5 = $ps3->fetchColumn())
        {
            $ps3->closeCursor();
            return $postcode5;
        }
        $ps4->execute(array(Postcodify_Utility::crc32_x64($dongri), $jibeon_major));
        if ($postcode5 = $ps4->fetchColumn())
        {
            $ps4->closeCursor();
            return $postcode5;
        }
        
        // 같은 도로, 같은 구간, 같은 방향에서 가장 가까운 기초구역번호를 찾아본다.
        
        $ps5->execute(array($road_info->road_id, $num_major % 2, $num_major));
        if ($postcode5 = $ps5->fetchColumn())
        {
            $ps5->closeCursor();
            return $postcode5;
        }
        
        // 같은 도로, 구간과 관계없이 같은 방향에서 가장 가까운 기초구역번호를 찾아본다.
        
        $road_id_short = substr($road_info->road_id, 0, 12);
        $ps6->execute(array($road_id_short . '00', $road_id_short . '99', $num_major % 2, $num_major));
        if ($postcode5 = $ps6->fetchColumn())
        {
            $ps6->closeCursor();
            return $postcode5;
        }
        
        // 같은 기존 우편번호가 부여된 주소들 중 가장 가까운 기초구역번호를 찾아본다.
        
        $ps7->execute(array($postcode6, $num_major));
        if ($postcode5 = $ps7->fetchColumn())
        {
            $ps7->closeCursor();
            return $postcode5;
        }
        
        // 아직도 못 찾았으면 null을 반환한다.
        
        return null;
    }
    
    // 주어진 동·리의 영문 명칭을 찾는 함수.
    
    public function find_dongri_english($dongri)
    {
        // 시험구동인 경우 null을 반환한다.
        
        if ($this->_dry_run) return null;
        
        // DB 관련 객체들을 캐싱해 두는 변수들.
        
        static $db = null;
        static $ps = null;
        
        // DB에 연결한다.
        
        if ($db === null)
        {
            $db = Postcodify_Utility::get_db();
            $ps = $db->prepare('SELECT en FROM postcodify_english WHERE ko = ?');
        }
        
        // 쿼리를 실행한다.
        
        $ps->execute(array($dongri));
        if ($dongri_en = $ps->fetchColumn())
        {
            $ps->closeCursor();
            return $dongri_en;
        }
        else
        {
            return null;
        }
    }
}
