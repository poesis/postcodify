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

class Postcodify_Indexer_Update
{
    // 변경사유 관련 상수들.
    
    const ADDR_NEW = 31;
    const ADDR_MODIFIED = 34;
    const ADDR_DELETED = 63;
    
    // 설정 저장.
    
    protected $_data_dir;
    protected $_ranges_available = true;
    protected $_add_old_postcodes = false;
    
    // 생성자.
    
    public function __construct()
    {
        $this->_data_dir = dirname(POSTCODIFY_LIB_DIR) . '/data';
    }
    
    // 엔트리 포인트.
    
    public function start($args)
    {
        Postcodify_Utility::print_message('Postcodify Indexer ' . POSTCODIFY_VERSION);
        Postcodify_Utility::print_newline();
        
        if (in_array('--add-old-postcodes', $args->options))
        {
            $this->_add_old_postcodes = true;
        }
        
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
        
        // 구 우편번호를 사용하도록 설정되어 있는지 확인한다.
        $oldpostcodes_query = $db->query('SELECT v FROM postcodify_settings WHERE k = \'oldpostcodes\'');
        $oldpostcodes = $oldpostcodes_query->fetchColumn();
        $oldpostcodes_query->closeCursor();
        unset($oldpostcodes_query);
        
        if ($oldpostcodes == 1)
        {
            $this->_add_old_postcodes = true;
        }
        
        // 범위 데이터를 사용할 수 있는지 확인한다.
        
        $tables_query = $db->query("SHOW TABLES LIKE 'postcodify_ranges_roads'");
        if ($tables_query->fetchColumn() === false)
        {
            $this->_ranges_available = false;
        }
        unset($tables_query);
        unset($db);
        
        // 업데이트를 적용한다.
        
        $updated_new = $this->load_updates($updated);
        
        // 데이터 기준일 정보를 업데이트한다.
        
        $updated_new = strval(max(intval($updated), intval($updated_new)));
        
        if ($updated_new === strval($updated))
        {
            echo '업데이트할 것이 없습니다.' . PHP_EOL;
            exit;
        }
        else
        {
            $db = Postcodify_Utility::get_db();
            $updated_query = $db->prepare('UPDATE postcodify_settings SET v = ? WHERE k = \'updated\'');
            $updated_query->execute(array($updated_new));
            unset($updated_query);
            unset($db);
        }
    }
    
    // 업데이트를 로딩한다.
    
    public function load_updates($after_date)
    {
        // DB를 준비한다.
        
        $db = Postcodify_Utility::get_db();
        $db->beginTransaction();
        
        // 도로명코드 테이블 관련 쿼리들.
        
        $ps_road_select = $db->prepare('SELECT * FROM postcodify_roads WHERE road_id = ?');
        $ps_road_insert = $db->prepare('INSERT INTO postcodify_roads (road_id, road_name_ko, road_name_en, ' .
            'sido_ko, sido_en, sigungu_ko, sigungu_en, ilbangu_ko, ilbangu_en, eupmyeon_ko, eupmyeon_en, updated) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ps_road_update = $db->prepare('UPDATE postcodify_roads SET road_name_ko = ?, road_name_en = ?, ' .
            'sido_ko = ?, sido_en = ?, sigungu_ko = ?, sigungu_en = ?, ilbangu_ko = ?, ilbangu_en = ?, ' .
            'eupmyeon_ko = ?, eupmyeon_en = ?, updated = ? WHERE road_id = ?');
        
        // 주소 테이블 관련 쿼리들.
        
        $ps_addr_select = $db->prepare('SELECT * FROM postcodify_addresses WHERE road_id >= ? AND road_id <= ? AND ' .
            'num_major = ? AND (num_minor = ? OR (? IS NULL AND num_minor IS NULL))' .
            'AND is_basement = ? ORDER BY id LIMIT 1');
        $ps_addr_insert = $db->prepare('INSERT INTO postcodify_addresses (postcode5, postcode6, ' .
            'road_id, num_major, num_minor, is_basement, dongri_id, dongri_ko, dongri_en, jibeon_major, jibeon_minor, is_mountain, ' .
            'building_id, building_name, building_nums, other_addresses, updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ps_addr_update = $db->prepare('UPDATE postcodify_addresses SET postcode5 = ?, postcode6 = ?, ' .
            'road_id = ?, num_major = ?, num_minor = ?, is_basement = ?, dongri_id = ?, dongri_ko = ?, dongri_en = ?, ' .
            'jibeon_major = ?, jibeon_minor = ?, is_mountain = ?, building_id = ?, building_name = ?, building_nums = ?, ' .
            'other_addresses = ?, updated = ?, deleted = NULL WHERE id = ?');
        $ps_addr_update_other = $db->prepare('UPDATE postcodify_addresses SET other_addresses = ? WHERE id = ?');
        $ps_addr_delete = $db->prepare('UPDATE postcodify_addresses SET deleted = ? WHERE id = ?');
        
        // 도로명 및 동·리 키워드 관련 쿼리들.
        
        $ps_kwd_select = $db->prepare('SELECT keyword_crc32 FROM postcodify_keywords WHERE address_id = ?');
        $ps_kwd_insert = $db->prepare('INSERT INTO postcodify_keywords (address_id, keyword_crc32) VALUES (?, ?)');
        
        // 건물번호 및 지번 키워드 관련 쿼리들.
        
        $ps_num_select = $db->prepare('SELECT num_major, num_minor FROM postcodify_numbers WHERE address_id = ?');
        $ps_num_insert = $db->prepare('INSERT INTO postcodify_numbers (address_id, num_major, num_minor) VALUES (?, ?, ?)');
        
        // 건물명 키워드 관련 쿼리들.
        
        $ps_building_select = $db->prepare('SELECT keyword FROM postcodify_buildings WHERE address_id = ?');
        $ps_building_insert = $db->prepare('INSERT INTO postcodify_buildings (address_id, keyword) VALUES (?, ?)');
        $ps_building_update = $db->prepare('UPDATE postcodify_buildings SET keyword = ? WHERE address_id = ?');
        $ps_building_delete = $db->prepare('DELETE FROM postcodify_buildings WHERE address_id = ?');
        
        // 데이터 파일 목록을 구한다.
        
        $files = glob($this->_data_dir . '/????????_dailynoticedata.zip');
        $last_date = $after_date;
        
        // 각 파일을 순서대로 파싱한다.
        
        foreach ($files as $filename)
        {
            // 데이터 기준일 이전의 파일은 무시한다.
            
            $file_date = substr(basename($filename), 0, 8);
            if (strcmp($file_date, $after_date) <= 0) continue;
            if (strcmp($file_date, $last_date) > 0) $last_date = $file_date;
            
            Postcodify_Utility::print_message('업데이트 파일: ' . $file_date . '_dailynoticedata.zip');
            
            // 도로정보 파일을 연다.
            
            Postcodify_Utility::print_message('  - 도로명코드');
            
            $zip = new Postcodify_Parser_Road_List;
            $zip->open_archive($filename);
            $open_status = $zip->open_named_file('TI_SPRD_STRET');
            if (!$open_status) continue;
            
            // 카운터를 초기화한다.
            
            $count = 0;
            
            // 데이터를 한 줄씩 읽는다.
            
            while ($entry = $zip->read_line())
            {
                // 카운터를 표시한다.
                
                if (++$count % 32 === 0) Postcodify_Utility::print_progress($count);
                
                // 폐지된 도로는 무시한다.
                
                if ($entry->change_reason === 1)
                {
                    unset($entry);
                    continue;
                }
                
                // 이미 존재하는 도로인지 확인한다.
                
                $ps_road_select->execute(array($entry->road_id . $entry->road_section));
                $road_exists = $ps_road_select->fetchColumn();
                $ps_road_select->closeCursor();
                
                // 신규 또는 변경된 도로 정보를 DB에 저장한다.
                
                if (!$road_exists)
                {
                    $ps_road_insert->execute(array(
                        $entry->road_id . $entry->road_section,
                        $entry->road_name_ko,
                        $entry->road_name_en,
                        $entry->sido_ko,
                        $entry->sido_en,
                        $entry->sigungu_ko,
                        $entry->sigungu_en,
                        $entry->ilbangu_ko,
                        $entry->ilbangu_en,
                        $entry->eupmyeon_ko,
                        $entry->eupmyeon_en,
                        $entry->updated,
                    ));
                }
                else
                {
                    $ps_road_update->execute(array(
                        $entry->road_name_ko,
                        $entry->road_name_en,
                        $entry->sido_ko,
                        $entry->sido_en,
                        $entry->sigungu_ko,
                        $entry->sigungu_en,
                        $entry->ilbangu_ko,
                        $entry->ilbangu_en,
                        $entry->eupmyeon_ko,
                        $entry->eupmyeon_en,
                        $entry->updated,
                        $entry->road_id . $entry->road_section,
                    ));
                }
                
                // 뒷정리.
                
                unset($entry);
            }
            
            // 파일을 닫는다.
            
            $zip->close();
            unset($zip);
            
            Postcodify_Utility::print_ok($count);
            
            // 건물정보 파일을 연다.
            
            Postcodify_Utility::print_message('  - 건물정보');
            
            $zip = new Postcodify_Parser_NewAddress;
            $zip->open_archive($filename);
            $open_status = $zip->open_named_file('TI_SPBD_BULD');
            if (!$open_status) continue;
            
            // 카운터를 초기화한다.
            
            $count = 0;
            
            // 이전 주소를 초기화한다.
            
            $is_first_entry = true;
            $last_entry = null;
            $last_nums = array();
            
            // 데이터를 한 줄씩 읽어 처리한다.
            
            while (true)
            {
                // 읽어온 줄을 분석한다.
                
                $entry = $zip->read_line();
                if ($is_first_entry && $entry === false) break;
                $is_first_entry = false;
                
                // 이전 주소가 없다면 방금 읽어온 줄을 이전 주소로 설정한다.
                
                if ($last_entry === null)
                {
                    $last_entry = $entry;
                    if (($entry->has_detail || $entry->is_common_residence) && preg_match('/.+동$/u', $entry->building_detail))
                    {
                        $last_nums = array(preg_replace('/동$/u', '', $entry->building_detail));
                    }
                    elseif ($entry->building_detail)
                    {
                        $last_entry->building_names[] = $last_entry->building_detail;
                        $last_nums = array();
                    }
                    else
                    {
                        $last_nums = array();
                    }
                }
                
                // 방금 읽어온 줄이 이전 주소와 다른 경우, 이전 주소 정리가 끝난 것이므로 이전 주소를 저장해야 한다.
                
                elseif ($entry === false ||
                    $last_entry->road_id !== $entry->road_id ||
                    $last_entry->road_section !== $entry->road_section ||
                    $last_entry->num_major !== $entry->num_major ||
                    $last_entry->num_minor !== $entry->num_minor ||
                    $last_entry->is_basement !== $entry->is_basement)
                {
                    // 디버깅을 위한 변수들.
                    
                    $update_type = false;
                    $postcode6_is_guess = false;
                    $postcode5_is_guess = false;
                    
                    // 이미 존재하는 주소인지 확인한다.
                    
                    $ps_addr_select->execute(array(
                        $last_entry->road_id . '00', $last_entry->road_id . '99',
                        $last_entry->num_major, $last_entry->num_minor, $last_entry->num_minor, $last_entry->is_basement));
                    $address_info = $ps_addr_select->fetchObject();
                    $ps_addr_select->closeCursor();
                    
                    // 도로 정보를 파악한다.
                    
                    $ps_road_select->execute(array($last_entry->road_id . $last_entry->road_section));
                    $road_info = $ps_road_select->fetchObject();
                    $ps_road_select->closeCursor();
                    
                    // 도로 정보가 없는 경우, 더미 레코드를 생성한다.
                    
                    if (!$road_info)
                    {
                        // 더미 레코드를 DB에 입력한다.
                        
                        $ps_road_insert->execute(array(
                            $last_entry->road_id . $last_entry->road_section,
                            $last_entry->road_name,
                            $this->find_english_name($db, $last_entry->road_name),
                            $last_entry->sido,
                            $this->find_english_name($db, $last_entry->sido),
                            $last_entry->sigungu,
                            $this->find_english_name($db, $last_entry->sigungu),
                            $last_entry->ilbangu,
                            $this->find_english_name($db, $last_entry->ilbangu),
                            $last_entry->eupmyeon,
                            $this->find_english_name($db, $last_entry->eupmyeon),
                            '99999999',
                        ));
                        
                        // 입력한 더미 레코드를 DB에서 다시 불러온다.
                        
                        $ps_road_select->execute(array($last_entry->road_id . $last_entry->road_section));
                        $road_info = $ps_road_select->fetchObject();
                        $ps_road_select->closeCursor();
                    }
                    
                    // 이미 존재하는 주소인 경우, 기존의 검색 키워드와 번호들을 가져온다.
                    
                    $existing_keywords = $existing_numbers = $existing_buildings = array();
                    
                    if ($address_info)
                    {
                        $ps_kwd_select->execute(array($address_info->id));
                        while ($row = $ps_kwd_select->fetchColumn())
                        {
                            $existing_keywords[$row] = true;
                        }
                        $ps_kwd_select->closeCursor();
                        
                        $ps_num_select->execute(array($address_info->id));
                        while ($row = $ps_num_select->fetch(PDO::FETCH_NUM))
                        {
                            $existing_numbers[implode('-', $row)] = true;
                        }
                        $ps_num_select->closeCursor();
                        
                        $ps_building_select->execute(array($address_info->id));
                        while ($row = $ps_building_select->fetchColumn())
                        {
                            $existing_buildings[] = $row;
                        }
                        $ps_building_select->closeCursor();
                    }
                    
                    // 신설과 변경 주소 구분은 변경코드에 의존하지 않고,
                    // 로컬 DB에 해당 주소가 이미 존재하는지에 따라 판단한다.
                    
                    if ($last_entry->change_reason !== self::ADDR_DELETED)
                    {
                        // 우편번호가 누락된 경우, 범위 데이터를 사용하여 찾거나 기존 주소의 우편번호를 그대로 사용한다.
                        
                        if ($last_entry->postcode6 === null || $last_entry->postcode6 === '000000')
                        {
                            if ($address_info && $address_info->postcode6 !== null)
                            {
                                $last_entry->postcode6 = $address_info->postcode6;
                            }
                            elseif ($this->_add_old_postcodes)
                            {
                                $last_entry->postcode6 = $this->find_postcode6($db, $road_info, $last_entry->dongri, $last_entry->admin_dongri, $last_entry->jibeon_major, $last_entry->jibeon_minor);
                                $postcode6_is_guess = true;
                            }
                        }
                        if ($last_entry->postcode5 === null || $last_entry->postcode5 === '00000')
                        {
                            if ($address_info && $address_info->postcode5 !== null)
                            {
                                $last_entry->postcode5 = $address_info->postcode5;
                            }
                            else
                            {
                                $last_entry->postcode5 = $this->find_postcode5($db, $road_info, $last_entry->num_major, $last_entry->num_minor, $last_entry->dongri, $last_entry->admin_dongri, $last_entry->jibeon_major, $last_entry->jibeon_minor, $last_entry->postcode6);
                                $postcode5_is_guess = true;
                            }
                        }
                        
                        // 영문 동·리를 구한다.
                        
                        if ($address_info && $last_entry->dongri === $address_info->dongri_ko)
                        {
                            $dongri_en = $address_info->dongri_en;
                        }
                        else
                        {
                            $dongri_en = $this->find_english_name($db, $last_entry->dongri);
                        }
                        
                        // 상세건물명과 기타 주소를 정리한다.
                        
                        $building_nums = Postcodify_Utility::consolidate_building_nums($last_nums);
                        if ($building_nums === '') $building_nums = null;
                        
                        $other_addresses = array();
                        if ($last_entry->admin_dongri && $last_entry->admin_dongri !== $last_entry->dongri)
                        {
                            $other_addresses[] = $last_entry->admin_dongri;
                        }
                        $last_entry->building_names = array_unique($last_entry->building_names);
                        $last_entry->building_names = Postcodify_Utility::consolidate_building_names($last_entry->building_names, $last_entry->common_residence_name);
                        natcasesort($last_entry->building_names);
                        foreach ($last_entry->building_names as $building_name)
                        {
                            $other_addresses[] = str_replace(';', ':', $building_name);
                        }
                        
                        // 더미 레코드에 관련지번이 먼저 입력되어 있는 경우, 다시 추가한다.
                        
                        if ($address_info && strval($address_info->updated) === '99999999' && strval($address_info->other_addresses) !== '')
                        {
                            $other_addresses[] = $address_info->other_addresses;
                        }
                        
                        $other_addresses = implode('; ', $other_addresses);
                        if ($other_addresses === '') $other_addresses = null;
                        
                        // 공동주택인데 공동주택명이 없는 경우 다른 건물명을 이용한다.
                        
                        if ($last_entry->is_common_residence && $last_entry->common_residence_name === null)
                        {
                            if (strpos($other_addresses, '; ') === false)
                            {
                                $last_entry->common_residence_name = $other_addresses;
                                $other_addresses = null;
                            }
                        }
                        
                        // 신설 주소인 경우...
                        
                        if (!$address_info)
                        {
                            $ps_addr_insert->execute(array(
                                $last_entry->postcode5,
                                $last_entry->postcode6,
                                $last_entry->road_id . $last_entry->road_section,
                                $last_entry->num_major,
                                $last_entry->num_minor,
                                $last_entry->is_basement,
                                $last_entry->dongri_id,
                                $last_entry->dongri,
                                $dongri_en,
                                $last_entry->jibeon_major,
                                $last_entry->jibeon_minor,
                                $last_entry->is_mountain,
                                $last_entry->building_id,
                                $last_entry->common_residence_name,
                                $building_nums,
                                $other_addresses,
                                $last_entry->updated,
                            ));
                            
                            $proxy_id = $db->lastInsertId();
                            $update_type = 'C';
                        }
                        
                        // 변경 주소인 경우...
                        
                        else
                        {
                            $ps_addr_update->execute(array(
                                $last_entry->postcode5,
                                $last_entry->postcode6,
                                $last_entry->road_id . $last_entry->road_section,
                                $last_entry->num_major,
                                $last_entry->num_minor,
                                $last_entry->is_basement,
                                $last_entry->dongri_id,
                                $last_entry->dongri,
                                $dongri_en,
                                $last_entry->jibeon_major,
                                $last_entry->jibeon_minor,
                                $last_entry->is_mountain,
                                $last_entry->building_id,
                                $last_entry->common_residence_name,
                                $building_nums,
                                $other_addresses,
                                $last_entry->updated,
                                $address_info->id,
                            ));
                            
                            $proxy_id = $address_info->id;
                            $update_type = 'M';
                        }
                        
                        // 검색 키워드를 정리하여 저장한다.
                        
                        $keywords = array();
                        $keywords = array_merge($keywords, Postcodify_Utility::get_variations_of_road_name($road_info->road_name_ko));
                        $keywords = array_merge($keywords, Postcodify_Utility::get_variations_of_dongri($last_entry->dongri));
                        $keywords = array_merge($keywords, Postcodify_Utility::get_variations_of_dongri($last_entry->admin_dongri));
                        $keywords = array_unique($keywords);
                        foreach ($keywords as $keyword)
                        {
                            $keyword_crc32 = Postcodify_Utility::crc32_x64($keyword);
                            if (isset($existing_keywords[$keyword_crc32])) continue;
                            $ps_kwd_insert->execute(array($proxy_id, $keyword_crc32));
                        }
                        
                        // 번호들을 정리하여 저장한다.
                        
                        $numbers = array(
                            array($last_entry->num_major, $last_entry->num_minor),
                            array($last_entry->jibeon_major, $last_entry->jibeon_minor),
                        );
                        /*
                        if (preg_match('/([0-9]+)번?길$/u', $road_info->road_name_ko, $road_name_matches))
                        {
                            $numbers[] = array(intval($road_name_matches[1]), null);
                        }
                        */
                        foreach ($numbers as $number)
                        {
                            $number_key = implode('-', $number);
                            if (isset($existing_numbers[$number_key])) continue;
                            $ps_num_insert->execute(array($proxy_id, $number[0], $number[1]));
                        }
                        
                        // 건물명을 정리하여 저장한다.
                        
                        $building_names = array_merge($existing_buildings, $last_entry->building_names);
                        $building_names_str = Postcodify_Utility::compress_building_names($building_names);
                        if ($building_names_str !== '' && !in_array($building_names_str, $existing_buildings))
                        {
                            if (count($existing_buildings))
                            {
                                $ps_building_update->execute(array($building_names_str, $proxy_id));
                            }
                            else
                            {
                                $ps_building_insert->execute(array($proxy_id, $building_names_str));
                            }
                        }
                    }
                    
                    // 폐지된 주소인 경우...
                    
                    if ($last_entry->change_reason === self::ADDR_DELETED)
                    {
                        // 행자부에서 멀쩡한 주소를 삭제했다가 며칠 후 다시 추가하는 경우가 종종 있다.
                        // 이걸 너무 열심히 따라하면 애꿎은 사용자들이 불편을 겪게 되므로
                        // 주소가 폐지된 것으로 나오더라도 DB에는 그대로 두는 것이 좋다.
                        // 나중에 다시 추가될 경우 위의 코드에 따라 업데이트로 처리하면 그만이다.
                        
                        if ($address_info)
                        {
                            $ps_addr_delete->execute(array(
                                $last_entry->updated,
                                $address_info->id,
                            ));
                        }
                        $update_type = 'D';
                    }
                    
                    // 카운터를 표시한다.
                    
                    if (++$count % 32 === 0) Postcodify_Utility::print_progress($count);
                    
                    // 불필요한 변수들을 unset한다.
                    
                    unset($address_info, $road_info, $road_name_matches, $existing_keywords, $existing_numbers, $existing_buildings);
                    unset($keywords, $numbers, $building_names, $building_names_str);
                    unset($last_entry, $last_nums);
                    
                    // 방금 읽어온 줄을 새로운 이전 주소로 설정한다.
                    
                    if ($entry !== false)
                    {
                        $last_entry = $entry;
                        if (($entry->has_detail || $entry->is_common_residence) && preg_match('/.+동$/u', $entry->building_detail))
                        {
                            $last_nums = array(preg_replace('/동$/u', '', $entry->building_detail));
                        }
                        elseif ($entry->building_detail)
                        {
                            $last_entry->building_names[] = $last_entry->building_detail;
                            $last_nums = array();
                        }
                        else
                        {
                            $last_nums = array();
                        }
                    }
                }
                
                // 그 밖의 경우, 이전 주소에 상세주소를 추가한다.
                
                else
                {
                    if (count($entry->building_names))
                    {
                        $last_entry->building_names = array_merge($last_entry->building_names, $entry->building_names);
                    }
                    
                    if (($entry->has_detail || $entry->is_common_residence) && preg_match('/.+동$/u', $entry->building_detail))
                    {
                        $last_nums[] = preg_replace('/동$/u', '', $entry->building_detail);
                    }
                    elseif ($entry->building_detail)
                    {
                        $last_entry->building_names[] = $entry->building_detail;
                    }
                }
                
                // 더이상 데이터가 없는 경우 루프를 탈출한다.
                
                if ($entry === false) break;
                
                // 메모리 누수를 방지하기 위해 모든 배열을 unset한다.
                
                unset($entry);
            }
            
            // 건물정보 파일을 닫는다.
            
            $zip->close();
            unset($zip);
            
            Postcodify_utility::print_ok($count);
            
            // 관련지번 파일을 연다.
            
            Postcodify_Utility::print_message('  - 관련지번');
            
            $zip = new Postcodify_Parser_NewJibeon;
            $zip->open_archive($filename);
            $open_status = $zip->open_named_file('TI_SCCO_MVMN');
            if (!$open_status) continue;
            
            // 카운터를 초기화한다.
            
            $count = 0;
            
            // 관련지번 업데이트는 건물정보 파일처럼 도로명주소 단위로 연속되어 나온다는 보장이 없다.
            // 따라서 $last_entry를 사용하는 방식은 적절하지 않고, 1천 건 단위로 분류하여 사용한다.
            // 1천 건의 경계선에 걸리는 주소는 두 번에 걸쳐 업데이트되는 비효율성이 있으나,
            // 현실적으로 하루에 1천 건 이상 업데이트되는 경우는 드물다.
            
            while (true)
            {
                // 1천 건 단위로 읽어와서 도로명주소 단위로 분류한다.
                
                $entries = array();
                for ($i = 0; $i < 1000; $i++)
                {
                    $entry = $zip->read_line();
                    if ($entry === false) break;
                    $key = $entry->road_id . '|' . $entry->num_major . '|' . $entry->num_minor . '|' . $entry->is_basement;
                    $entries[$key][] = array($entry->dongri, $entry->jibeon_major, $entry->jibeon_minor, $entry->is_mountain);
                    unset($entry);
                }
                
                // 더이상 데이터가 없는 경우 루프를 탈출한다.
                
                if (!count($entries)) break;
                
                // 분류한 데이터를 처리한다.
                
                foreach ($entries as $key => $jibeons)
                {
                    // 분류에 사용했던 키를 분해하여 원래의 도로명주소를 구한다.
                    
                    list($road_id, $num_major, $num_minor, $is_basement) = explode('|', $key);
                    $num_major = intval($num_major);
                    $num_minor = intval($num_minor); if (!$num_minor) $num_minor = null;
                    $is_basement = intval($is_basement);
                    
                    // 이 주소에 해당하는 도로명주소 레코드를 가져온다.
                    
                    $ps_addr_select->execute(array($road_id . '00', $road_id . '99', $num_major, $num_minor, $num_minor, $is_basement));
                    $address_info = $ps_addr_select->fetchObject();
                    $ps_addr_select->closeCursor();
                    
                    // 레코드를 찾은 경우, 기존 정보를 가져온다.
                    
                    if ($address_info)
                    {
                        // 기존의 건물명 및 지번 목록을 파싱한다.
                        
                        $other_addresses = array('b' => array(), 'j' => array());
                        $other_addresses_raw = explode('; ', $address_info->other_addresses);
                        foreach ($other_addresses_raw as $i => $other_address)
                        {
                            if (preg_match('/^(.+[동리로가])\s(산?[0-9-]+(?:,\s산?[0-9-]+)*)$/u', $other_address, $matches))
                            {
                                $dongri = $matches[1];
                                $nums = explode(', ', $matches[2]);
                                $other_addresses[$dongri] = $nums;
                            }
                            elseif (strlen($other_address))
                            {
                                $other_addresses['b'][] = $other_address;
                            }
                        }
                        
                        // 기존의 검색 키워드와 번호들을 가져온다.
                        
                        $existing_keywords = array();
                        $ps_kwd_select->execute(array($address_info->id));
                        while ($row = $ps_kwd_select->fetchColumn())
                        {
                            $existing_keywords[$row] = true;
                        }
                        $ps_kwd_select->closeCursor();
                        
                        $existing_numbers = array();
                        $ps_num_select->execute(array($address_info->id));
                        while ($row = $ps_num_select->fetch(PDO::FETCH_NUM))
                        {
                            $existing_numbers[implode('-', $row)] = true;
                        }
                        $ps_num_select->closeCursor();
                    }
                    
                    // 도로명주소 레코드가 없는 경우, 더미 레코드를 생성한다.
                    
                    else
                    {
                        // 더미 레코드를 DB에 입력한다.
                        
                        $ps_addr_insert->execute(array(
                            null,
                            null,
                            $road_id . '00',
                            $num_major,
                            $num_minor,
                            $is_basement,
                            null,
                            $jibeons[0][0],
                            $this->find_english_name($db, $jibeons[0][0]),
                            $jibeons[0][1],
                            $jibeons[0][2],
                            $jibeons[0][3],
                            null,
                            null,
                            null,
                            null,
                            '99999999',
                        ));
                        
                        // 입력한 더미 레코드를 DB에서 다시 불러온다.
                        
                        $ps_addr_select->execute(array($road_id . '00', $road_id . '99', $num_major, $num_minor, $num_minor, $is_basement));
                        $address_info = $ps_addr_select->fetchObject();
                        $ps_addr_select->closeCursor();
                        
                        // 기존의 건물명, 지번 목록, 검색 키워드와 번호들은 빈 배열로 초기화한다.
                        
                        $other_addresses = array('b' => array(), 'j' => array());
                        $existing_keywords = array();
                        $existing_numbers = array();
                    }
                
                    // 업데이트된 지번 목록을 추가하고, 중복을 제거한다.
                    
                    foreach ($jibeons as $last_num)
                    {
                        $numtext = ($last_num[3] ? '산' : '') . $last_num[1] . ($last_num[2] ? ('-' . $last_num[2]) : '');
                        $other_addresses['j'][$last_num[0]][] = $numtext;
                    }
                    foreach ($other_addresses['j'] as $dongri => $nums)
                    {
                        $other_addresses['j'][$dongri] = array_unique($other_addresses['j'][$dongri]);
                    }
                    
                    // 기타 주소 목록을 정리하여 업데이트한다.
                    
                    $other_addresses_temp = array();
                    foreach ($other_addresses['b'] as $building_name)
                    {
                        $other_addresses_temp[] = $building_name;
                    }
                    foreach ($other_addresses['j'] as $dongri => $nums)
                    {
                        natsort($nums);
                        $other_addresses_temp[] = $dongri . ' ' . implode(', ', $nums);
                    }
                    $ps_addr_update_other->execute(array(implode('; ', $other_addresses_temp), $address_info->id));
                    
                    // 업데이트된 검색 키워드와 번호들을 추가한다.
                    
                    $keywords = array();
                    foreach ($jibeons as $last_num)
                    {
                        $keywords = array_merge($keywords, Postcodify_Utility::get_variations_of_dongri($last_num[0]));
                    }
                    $keywords = array_unique($keywords);
                    foreach ($keywords as $keyword)
                    {
                        $keyword_crc32 = Postcodify_Utility::crc32_x64($keyword);
                        if (isset($existing_keywords[$keyword_crc32])) continue;
                        $ps_kwd_insert->execute(array($address_info->id, $keyword_crc32));
                    }
                    
                    // 번호들을 정리하여 저장한다.
                    
                    foreach ($jibeons as $last_num)
                    {
                        $number_key = $last_num[1] . '-' . $last_num[2];
                        if (isset($existing_numbers[$number_key])) continue;
                        $ps_num_insert->execute(array($address_info->id, $last_num[1], $last_num[2]));
                    }
                    
                    // 카운터를 표시한다.
                    
                    if (++$count % 32 === 0) Postcodify_Utility::print_progress($count);
                    
                    // 불필요한 변수들을 unset한다.
                    
                    unset($key, $jibeons, $road_id, $num_major, $num_minor, $is_basement);
                    unset($address_info, $other_addresses, $other_addresses_raw, $other_addresses_temp, $nums, $last_num);
                    unset($existing_keywords, $existing_numbers);
                    unset($keywords, $numbers);
                }
                
                // 불필요한 변수들을 unset한다.
                
                unset($entries);
            }
            
            // 관련지번 파일을 닫는다.
            
            $zip->close();
            unset($zip);
            
            Postcodify_utility::print_ok($count);
        }
        
        // 뒷정리.
        
        $db->commit();
        unset($db);
        
        // 마지막으로 처리한 파일의 기준일을 반환한다.
        
        return $last_date;
    }
    
    // 주어진 주소와 가장 근접한 기존 우편번호를 찾는 함수.
    
    public function find_postcode6($db, $road_info, $dongri, $admin_dongri, $jibeon_major = null, $jibeon_minor = null)
    {
        // 범위 데이터를 사용할 수 없는 경우 null을 반환한다.
        
        if (!$this->_ranges_available) return null;
        
        // Prepared Statement를 생성한다.
        
        static $ps1 = null;
        static $ps2 = null;
        
        if ($ps1 === null)
        {
            $ps1 = $db->prepare('SELECT postcode6 FROM postcodify_ranges_oldcode WHERE ' .
                '(sido_ko = ? OR ? IS NULL) AND ' .
                '(sigungu_ko IS NULL OR sigungu_ko = ? OR ? IS NULL) AND ' .
                '(ilbangu_ko IS NULL OR ilbangu_ko = ? OR ? IS NULL) AND ' .
                '(eupmyeon_ko IS NULL OR eupmyeon_ko = ? OR ? IS NULL) AND ' .
                '(dongri_ko = ? OR dongri_ko = ? OR dongri_ko LIKE ? OR dongri_ko LIKE ? OR dongri_ko LIKE ? OR dongri_ko LIKE ? OR dongri_ko IS NULL) AND ' .
                'range_start_major <= ? AND (range_end_major IS NULL OR range_end_major >= ?) AND ' .
                '(range_start_minor IS NULL OR (range_start_minor <= ? AND (range_end_minor IS NULL OR range_end_minor >= ?))) ' .
                'ORDER BY dongri_ko DESC, range_start_major DESC, range_start_minor DESC LIMIT 1');
                
            $ps2 = $db->prepare('SELECT postcode6 FROM postcodify_ranges_oldcode WHERE ' .
                '(sido_ko = ? OR ? IS NULL) AND ' .
                '(sigungu_ko IS NULL OR sigungu_ko = ? OR ? IS NULL) AND ' .
                '(ilbangu_ko IS NULL OR ilbangu_ko = ? OR ? IS NULL) AND ' .
                '(eupmyeon_ko IS NULL OR eupmyeon_ko = ? OR ? IS NULL) AND ' .
                '(dongri_ko = ? OR dongri_ko = ? OR dongri_ko IS NULL) AND ' .
                '(range_start_major IS NULL OR (range_start_major <= ? AND (range_end_major IS NULL OR range_end_major >= ?) AND ' .
                '(range_start_minor IS NULL OR (range_start_minor <= ? AND (range_end_minor IS NULL OR range_end_minor >= ?))))) ' .
                'ORDER BY dongri_ko DESC, range_start_major ASC, range_start_minor ASC LIMIT 1');
        }
        
        // 지번이 주어진 경우 지번 범위를 기준으로 먼저 찾는다.
        
        if ($jibeon_major)
        {
            $ps1->execute(array(
                $road_info->sido_ko ? $road_info->sido_ko : null,
                $road_info->sido_ko ? $road_info->sido_ko : null,
                $road_info->sigungu_ko ? $road_info->sigungu_ko : null,
                $road_info->sigungu_ko ? $road_info->sigungu_ko : null,
                $road_info->ilbangu_ko ? $road_info->ilbangu_ko : null,
                $road_info->ilbangu_ko ? $road_info->ilbangu_ko : null,
                $road_info->eupmyeon_ko ? $road_info->eupmyeon_ko : null,
                $road_info->eupmyeon_ko ? $road_info->eupmyeon_ko : null,
                $dongri ? $dongri : null,
                $admin_dongri ? $admin_dongri : null,
                preg_match('/^(.+?)[0-9](동|리)$/u', $dongri, $matches) ? ($matches[1] . '_' . $matches[2]) : ($dongri ? $dongri : ''),
                preg_match('/^(.+?)[0-9](동|리)$/u', $admin_dongri, $matches) ? ($matches[1] . '_' . $matches[2]) : ($admin_dongri ? $admin_dongri : ''),
                preg_match('/^(.+?)제[0-9](동|리)$/u', $dongri, $matches) ? ($matches[1] . '_' . $matches[2]) : ($dongri ? $dongri : ''),
                preg_match('/^(.+?)제[0-9](동|리)$/u', $admin_dongri, $matches) ? ($matches[1] . '_' . $matches[2]) : ($admin_dongri ? $admin_dongri : ''),
                $jibeon_major, $jibeon_major,
                $jibeon_minor, $jibeon_minor,
            ));
            
            $postcode6 = $ps1->fetchColumn();
            $ps1->closeCursor();
            if ($postcode6)
            {
                return $postcode6;
            }
        }
        
        // 지번이 주어지지 않았거나, 지번 기준 검색결과가 없는 경우 읍면동리를 기준으로 찾아본다.
        
        $ps2->execute(array(
            $road_info->sido_ko ? $road_info->sido_ko : null,
            $road_info->sido_ko ? $road_info->sido_ko : null,
            $road_info->sigungu_ko ? $road_info->sigungu_ko : null,
            $road_info->sigungu_ko ? $road_info->sigungu_ko : null,
            $road_info->ilbangu_ko ? $road_info->ilbangu_ko : null,
            $road_info->ilbangu_ko ? $road_info->ilbangu_ko : null,
            $road_info->eupmyeon_ko ? $road_info->eupmyeon_ko : null,
            $road_info->eupmyeon_ko ? $road_info->eupmyeon_ko : null,
            $dongri ? $dongri : null,
            $admin_dongri ? $admin_dongri : null,
            $jibeon_major, $jibeon_major,
            $jibeon_minor, $jibeon_minor,
        ));
        
        // 검색 결과가 있을 경우 우편번호를 반환하고, 그렇지 않으면 null을 반환한다.
        
        $postcode6 = $ps2->fetchColumn();
        $ps2->closeCursor();
        return $postcode6 ? $postcode6 : null;
    }
    
    // 주어진 주소와 가장 근접한 기초구역번호(새우편번호)를 찾는 함수.
    
    public function find_postcode5($db, $road_info, $num_major, $num_minor, $dongri, $admin_dongri, $jibeon_major, $jibeon_minor, $postcode6)
    {
        // 범위 데이터를 사용할 수 없는 경우 null을 반환한다.
        
        if (!$this->_ranges_available) return null;
        
        // Prepared Statement를 생성한다.
        
        static $ps1 = null;
        static $ps2 = null;
        /*
        static $ps3 = null;
        static $ps4 = null;
        static $ps5 = null;
        static $ps6 = null;
        static $ps7 = null;
        */
        
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
            /*
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
            */
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
        else
        {
            $ps1->closeCursor();
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
        else
        {
            $ps2->closeCursor();
        }
        
        // 같은 지번에 이미 부여된 기초구역번호가 있는지 찾아본다.
        /*
        $ps3->execute(array(Postcodify_Utility::crc32_x64($dongri), $jibeon_major, $jibeon_minor));
        if ($postcode5 = $ps3->fetchColumn())
        {
            $ps3->closeCursor();
            return $postcode5;
        }
        else
        {
            $ps3->closeCursor();
        }
        
        $ps4->execute(array(Postcodify_Utility::crc32_x64($dongri), $jibeon_major));
        if ($postcode5 = $ps4->fetchColumn())
        {
            $ps4->closeCursor();
            return $postcode5;
        }
        else
        {
            $ps4->closeCursor();
        }
        
        // 같은 도로, 같은 구간, 같은 방향에서 가장 가까운 기초구역번호를 찾아본다.
        
        $ps5->execute(array($road_info->road_id, $num_major % 2, $num_major));
        if ($postcode5 = $ps5->fetchColumn())
        {
            $ps5->closeCursor();
            return $postcode5;
        }
        else
        {
            $ps5->closeCursor();
        }
        
        // 같은 도로, 구간과 관계없이 같은 방향에서 가장 가까운 기초구역번호를 찾아본다.
        
        $road_id_short = substr($road_info->road_id, 0, 12);
        $ps6->execute(array($road_id_short . '00', $road_id_short . '99', $num_major % 2, $num_major));
        if ($postcode5 = $ps6->fetchColumn())
        {
            $ps6->closeCursor();
            return $postcode5;
        }
        else
        {
            $ps6->closeCursor();
        }
        
        // 같은 기존 우편번호가 부여된 주소들 중 가장 가까운 기초구역번호를 찾아본다.
        
        $ps7->execute(array($postcode6, $num_major));
        if ($postcode5 = $ps7->fetchColumn())
        {
            $ps7->closeCursor();
            return $postcode5;
        }
        else
        {
            $ps7->closeCursor();
        }
        */
        
        // 아직도 못 찾았으면 null을 반환한다.
        
        return null;
    }
    
    // 주어진 이름의 영문 명칭을 찾는 메소드.
    
    public function find_english_name($db, $korean_name)
    {
        return Postcodify_Utility::get_english($korean_name);
        
        // Prepared Statement를 생성한다.
        /*
        static $ps = null;
        if ($ps === null)
        {
            $ps = $db->prepare('SELECT en FROM postcodify_english WHERE ko = ?');
        }
        
        // 쿼리를 실행한다.
        
        $ps->execute(array($korean_name));
        if ($english_name = $ps->fetchColumn())
        {
            $ps->closeCursor();
            return $english_name;
        }
        else
        {
            $ps->closeCursor();
            return null;
        }
        */
    }
    
    // 디버깅을 위해 주소를 포맷하는 메소드.
    
    public function format_address($road_info, $entry)
    {
        $result = $road_info->sido_ko . ' ' . $road_info->sigungu_ko . ' ' . $road_info->ilbangu_ko . ' ' . $road_info->eupmyeon_ko . ' ' .
            $road_info->road_name_ko . ' ' . $entry->num_major . ($entry->num_minor ? ('-' . $entry->num_minor) : '');
        return preg_replace('/\s+/', ' ', $result);
    }
}
