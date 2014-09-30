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
    protected $_shmop_key;
    
    // 쓰레드별로 작업을 분할하는 데 사용하는 시도 목록.
    
    protected $_thread_groups = array(
        '경기도', '경상남북도', '전라남북도', '충청남북도',
        '서울특별시|부산광역시|세종특별자치시|제주특별자치도',
        '강원도|광주광역시|대구광역시|대전광역시|울산광역시|인천광역시',
    );
    
    // 작업용 인덱스 목록.
    
    protected $_interim_indexes = array(
        'postcodify_addresses' => array('address_id'),
        'postcodify_keywords_ko' => array('address_id'),
        'postcodify_keywords_en' => array('address_id'),
    );
    
    // 최종 인덱스 목록.
    
    protected $_final_indexes = array(
        'postcodify_roads' => array('sido_ko', 'sigungu_ko', 'ilbangu_ko', 'eupmyeon_ko'),
        'postcodify_addresses' => array('road_id', 'postcode5', 'postcode6', 'updated'),
        'postcodify_keywords_ko' => array('keyword_crc32'),
        'postcodify_keywords_en' => array('keyword_crc32'),
        'postcodify_numbers' => array('address_id', 'num_major', 'num_minor'),
        'postcodify_buildings' => array('address_id'),
        'postcodify_poboxes' => array('address_id', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor'),
    );
    
    // 생성자.
    
    public function __construct()
    {
        $this->_data_dir = dirname(POSTCODIFY_LIB_DIR) . '/data';
        $this->_shmop_key = ftok(__FILE__, 't');
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
        
        $this->print_message('주소 데이터를 로딩하는 중...');
        $this->start_threaded_workers('load_juso');
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('작업용 인덱스를 생성하는 중...');
        $this->start_threaded_workers('interim_indexes');
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('지번 데이터를 로딩하는 중...');
        $this->start_threaded_workers('load_jibeon');
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('부가정보 데이터를 로딩하는 중...');
        $this->start_threaded_workers('load_extra_info');
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('사서함 데이터를 로딩하는 중...');
        $this->load_pobox();
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('최종 인덱스를 생성하는 중...');
        $this->start_threaded_workers('final_indexes');
        $this->print_ok();
        $this->print_newline();
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
    
    // 작업 쓰레드를 생성한다.
    
    public function start_threaded_workers($task_name)
    {
        // 카운터로 사용하는 공유 메모리를 초기화한다.
        
        $shmop = shmop_open($this->_shmop_key, 'c', 0644, 4);
        shmop_write($shmop, pack('L', 0), 0);
        
        // 자식 프로세스 목록을 초기화한다.
        
        $children = array();
        
        // 수행할 작업을 판단한다.
        
        switch ($task_name)
        {
            case 'interim_indexes':
                $tasks = $this->_interim_indexes;
                $task_name = 'create_indexes';
                break;
            case 'final_indexes':
                $tasks = $this->_final_indexes;
                $task_name = 'create_indexes';
                break;
            default:
                $tasks = $this->_thread_groups;
        }
        
        // 자식 프로세스들을 생성한다.
        
        while (count($tasks))
        {
            reset($tasks);
            $task_key = key($tasks);
            $task = array_shift($tasks);
            $pid = pcntl_fork();
            
            if ($pid == -1)
            {
                echo PHP_EOL . '[ ERROR ] 쓰레드를 생성할 수 없습니다.' . PHP_EOL;
                exit(2);
            }
            elseif ($pid > 0)
            {
                $children[$pid] = $task_key;
            }
            else
            {
                $this->$task_name($task, $task_key);
                exit;
            }
        }
        
        // 자식 프로세스들이 작업을 마치기를 기다린다.
        
        while (count($children))
        {
            // 작업을 마친 자식 프로세스는 목록에서 삭제한다.
            
            $pid = pcntl_wait($status, WNOHANG | WUNTRACED);
            if ($pid) unset($children[$pid]);
            
            // 카운터를 확인한다.
            
            $count = current(unpack('L', shmop_read($shmop, 0, 4)));
            $this->print_progress($count);
            
            // 부모 프로세스는 쉰다.
            
            usleep(100000);
        }
        
        // 공유 메모리를 닫는다.
        
        shmop_close($shmop);
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
    
    // 인덱스를 생성한다. (쓰레드 사용)
    
    public function create_indexes($columns, $table_name)
    {
        if (!DRY_RUN)
        {
            $db = Postcodify_Utility::get_db();
            
            foreach ($columns as $column)
            {
                // 인덱스 생성 쿼리를 실행한다.
                
                try
                {
                    $db->exec('CREATE INDEX ' . $table_name . '_' . $column . ' ON ' . $table_name . ' (' . $column . ')');
                }
                catch (PDOException $e)
                {
                    if (strpos($e->getMessage(), 'STMT_CLOSE') === false)
                    {
                        throw $e;
                    }
                }
                
                // 카운터를 표시한다.
                
                $shmop = shmop_open($this->_shmop_key, 'w', 0, 0);
                $prev = current(unpack('L', shmop_read($shmop, 0, 4)));
                shmop_write($shmop, pack('L', $prev + 1), 0);
                shmop_close($shmop);
            }
            
            unset($db);
        }
    }
    
    // 데이터 기준일 정보를 로딩한다.
    
    public function load_data_date()
    {
        // 파일에서 데이터 기준일을 읽는다.
        
        $date = trim(file_get_contents($this->_data_dir . '/도로명코드_기준일.txt'));
        $this->_data_date = $date;
        
        // DB에 저장한다.
        
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
        // DB를 준비한다.
        
        if (!DRY_RUN)
        {
            $db = Postcodify_Utility::get_db();
            $db->beginTransaction();
            $ps = $db->prepare('INSERT INTO postcodify_roads (road_id, road_name_ko, road_name_en, ' .
                'sido_ko, sido_en, sigungu_ko, sigungu_en, ilbangu_ko, ilbangu_en, eupmyeon_ko, eupmyeon_en) ' .
                'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        }
        
        // Zip 파일을 연다.
        
        $zip = new Postcodify_Indexer_Parser_Road_List;
        $zip->open_archive($this->_data_dir . '/도로명코드_전체분.zip');
        $zip->open_next_file();
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 데이터를 한 줄씩 읽는다.
        
        while ($entry = $zip->read_line())
        {
            // 영문 행정구역명을 캐시에 저장한다.
            
            Postcodify_Utility::$english_cache['R_' . $entry->road_id] = $entry->road_name;
            Postcodify_Utility::$english_cache[$entry->road_name] = $entry->road_name_english;
            Postcodify_Utility::$english_cache[$entry->sido] = $entry->sido_english;
            if ($entry->sigungu) Postcodify_Utility::$english_cache[$entry->sigungu] = $entry->sigungu_english;
            if ($entry->ilbangu) Postcodify_Utility::$english_cache[$entry->ilbangu] = $entry->ilbangu_english;
            if ($entry->eupmyeon) Postcodify_Utility::$english_cache[$entry->eupmyeon] = $entry->eupmyeon_english;
            
            // 도로명 및 소속 행정구역 정보를 DB에 저장한다.
            
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
            
            // 카운터를 표시한다.
            
            if (++$count % 512 === 0) $this->print_progress($count);
            unset($entry);
        }
        
        // 뒷정리.
        
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
        // Zip 파일을 연다.
        
        $zip = new Postcodify_Indexer_Parser_Building_Info;
        $zip->open_archive($this->_data_dir . '/상세건물명.zip');
        $zip->open_next_file();
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 데이터를 한 줄씩 읽는다.
        
        while ($entry = $zip->read_line())
        {
            // 건물명을 캐시에 저장한다.
            
            if (count($entry->building_names))
            {
                Postcodify_Utility::$building_cache[$entry->address_id] = implode(',', $entry->building_names);
            }
            
            // 카운터를 표시한다.
            
            if (++$count % 512 === 0) $this->print_progress($count);
            unset($entry);
        }
        
        // 뒷정리.
        
        $zip->close();
        unset($zip);
    }
    
    // 영문 행정구역명을 로딩한다.
    
    public function load_english_aliases()
    {
        // Zip 파일을 연다.
        
        $zip = new Postcodify_Indexer_Parser_English_Aliases;
        $zip->open_archive($this->_data_dir . '/english_aliases_DB.zip');
        $zip->open_next_file();
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 데이터를 한 줄씩 읽는다.
        
        while ($entry = $zip->read_line())
        {
            // 영문 행정구역명을 캐시에 저장한다.
            
            Postcodify_Utility::$english_cache[$entry->ko] = $entry->en;
            
            // 카운터를 표시한다.
            
            if (++$count % 512 === 0) $this->print_progress($count);
            unset($entry);
        }
        
        // 뒷정리.
        
        $zip->close();
        unset($zip);
    }
    
    // 주소 데이터를 로딩한다. (쓰레드 사용)
    
    public function load_juso($sido)
    {
        // DB를 준비한다.
        
        if (!DRY_RUN)
        {
            $db = Postcodify_Utility::get_db();
            $db->beginTransaction();
            $ps_addr_insert = $db->prepare('INSERT INTO postcodify_addresses (address_id, postcode5, ' .
                'road_id, num_major, num_minor, is_basement) VALUES (?, ?, ?, ?, ?, ?)');
            $ps_kwko_insert = $db->prepare('INSERT INTO postcodify_keywords_ko (address_id, keyword_crc32) VALUES (?, ?)');
            $ps_kwen_insert = $db->prepare('INSERT INTO postcodify_keywords_en (address_id, keyword_crc32) VALUES (?, ?)');
            $ps_numb_insert = $db->prepare('INSERT INTO postcodify_numbers (address_id, num_major, num_minor) VALUES (?, ?, ?)');
        }
        
        // 이 쓰레드에서 처리할 시·도 목록을 구한다.
        
        $sidos = explode('|', $sido);
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 시·도를 하나씩 처리한다.
        
        foreach ($sidos as $sido)
        {
            // Zip 파일을 연다.
            
            $zip = new Postcodify_Indexer_Parser_Juso;
            $zip->open_archive($this->_data_dir . '/주소_' . $sido . '.zip');
            
            // Zip 파일에 포함된 텍스트 파일들을 하나씩 처리한다.
            
            while (($filename = $zip->open_next_file()) !== false)
            {
                // 데이터를 한 줄씩 읽는다.
                
                while ($entry = $zip->read_line())
                {
                    // 이 주소에 해당하는 도로명을 구한다.
                    
                    $road_name_ko = Postcodify_Utility::$english_cache['R_' . $entry->road_id];
                    $road_name_en = Postcodify_Utility::$english_cache[$road_name_ko];
                    $proxy_id = null;
                    
                    // 키워드를 확장한다.
                    
                    $road_name_ko_array = Postcodify_Utility::get_variations_of_road_name($road_name_ko);
                    $road_name_en_array = array(preg_replace('/[^a-z0-9]/', '', strtolower($road_name_en)));
                    
                    // 주소를 저장한다.
                    
                    if (!DRY_RUN)
                    {
                        $ps_addr_insert->execute(array(
                            $entry->address_id,
                            $entry->postcode5,
                            $entry->road_id . $entry->road_section,
                            $entry->num_major,
                            $entry->num_minor,
                            $entry->is_basement,
                        ));
                        $proxy_id = $db->lastInsertId();
                    }
                    
                    // 키워드와 번호들을 저장한다.
                    
                    if (!DRY_RUN)
                    {
                        foreach ($road_name_ko_array as $road_name_ko)
                        {
                            $ps_kwko_insert->execute(array($proxy_id, Postcodify_Utility::crc32_x64($road_name_ko)));
                        }
                        
                        foreach ($road_name_en_array as $road_name_en)
                        {
                            $ps_kwen_insert->execute(array($proxy_id, Postcodify_Utility::crc32_x64($road_name_en)));
                        }
                        
                        $ps_numb_insert->execute(array($proxy_id, $entry->num_major, $entry->num_minor));
                    }
                    
                    // 카운터를 표시한다.
                    
                    if (++$count % 512 === 0)
                    {
                        $shmop = shmop_open($this->_shmop_key, 'w', 0, 0);
                        $prev = current(unpack('L', shmop_read($shmop, 0, 4)));
                        shmop_write($shmop, pack('L', $prev + 512), 0);
                        shmop_close($shmop);
                    }
                    
                    // 메모리 누수를 방지하기 위해 모든 배열을 unset한다.
                    
                    unset($road_name_ko_array);
                    unset($road_name_en_array);
                    unset($entry);
                }
            }
        }
        
        // 뒷정리.
        
        $zip->close();
        unset($zip);
        
        if (!DRY_RUN)
        {
            $db->commit();
            unset($db);
        }
    }
    
    // 지번 데이터를 로딩한다. (쓰레드 사용)
    
    public function load_jibeon($sido)
    {
        // DB를 준비한다.
        
        if (!DRY_RUN)
        {
            $db = Postcodify_Utility::get_db();
            $db->beginTransaction();
            $ps_addr_select = $db->prepare('SELECT id FROM postcodify_addresses where address_id = ?');
            $ps_addr_update1 = $db->prepare('UPDATE postcodify_addresses SET dongri_ko = ?, dongri_en = ?, ' .
                'jibeon_major = ?, jibeon_minor = ?, is_mountain = ? WHERE id = ?');
            $ps_addr_update2 = $db->prepare('UPDATE postcodify_addresses SET other_addresses = ' .
                'CONCAT_WS(\'\\n\', other_addresses, ?) WHERE id = ?');
            $ps_kwko_insert = $db->prepare('INSERT INTO postcodify_keywords_ko (address_id, keyword_crc32) VALUES (?, ?)');
            $ps_kwen_insert = $db->prepare('INSERT INTO postcodify_keywords_en (address_id, keyword_crc32) VALUES (?, ?)');
            $ps_numb_insert = $db->prepare('INSERT INTO postcodify_numbers (address_id, num_major, num_minor) VALUES (?, ?, ?)');
        }
        
        // 이 쓰레드에서 처리할 시·도 목록을 구한다.
        
        $sidos = explode('|', $sido);
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 시·도를 하나씩 처리한다.
        
        foreach ($sidos as $sido)
        {
            // Zip 파일을 연다.
            
            $zip = new Postcodify_Indexer_Parser_Jibeon;
            $zip->open_archive($this->_data_dir . '/지번_' . $sido . '.zip');
            
            // Zip 파일에 포함된 텍스트 파일들을 하나씩 처리한다.
            
            while (($filename = $zip->open_next_file()) !== false)
            {
                // 동·리 키워드 중복 방지 캐시를 초기화한다.
                
                $kwcache = array();
                
                // 데이터를 한 줄씩 읽는다.
                
                while ($entry = $zip->read_line())
                {
                    // 영문 동·리명을 구한다.
                    
                    $dongri_en = Postcodify_Utility::$english_cache[$entry->dongri];
                    
                    // 키워드를 확장한다.
                    
                    $dongri_ko_array = Postcodify_Utility::get_variations_of_dongri($entry->dongri);
                    $dongri_en_array = array(preg_replace('/[^a-z0-9]/', '', strtolower($dongri_en)));
                    
                    // 이 주소의 대체키 번호를 구한다.
                    
                    $ps_addr_select->execute(array($entry->address_id));
                    $proxy_id = intval($ps_addr_select->fetchColumn());
                    $ps_addr_select->closeCursor();
                    
                    // 동·리 키워드 중복 방지 캐시에 현재 항목을 추가한다.
                    
                    $kwcache[$proxy_id] = implode('|', $dongri_ko_array + $dongri_en_array);
                    if (count($kwcache) > 2100)
                    {
                        $kwcache = array_slice($kwcache, 100);
                    }
                    
                    // 지번 정보를 저장한다.
                    
                    if (!DRY_RUN)
                    {
                        // 대표지번인 경우 해당 레코드에 직접 저장한다.
                        
                        if ($entry->is_canonical)
                        {
                            $ps_addr_update1->execute(array(
                                $entry->dongri,
                                $dongri_en,
                                $entry->num_major,
                                $entry->num_minor,
                                $entry->is_mountain,
                                $proxy_id,
                            ));
                        }
                        
                        // 대표지번이 아닌 경우 기타 주소 목록에 추가하기만 한다.
                        
                        else
                        {
                            $nums = ($entry->is_mountain ? '산' : '') . $entry->num_major .
                                ($entry->num_minor ? ('-' . $entry->num_minor) : '');
                            $ps_addr_update2->execute(array(
                                $entry->dongri . ' ' . $nums,
                                $proxy_id,
                            ));
                        }
                    }
                    
                    // 키워드와 번호들을 저장한다.
                    
                    if (!DRY_RUN)
                    {
                        foreach ($dongri_ko_array as $dongri_ko)
                        {
                            if (!isset($kwcache[$proxy_id]) || strpos($kwcache[$proxy_id], $dongri_ko) === false)
                            {
                                $kwcache[$proxy_id] = isset($kwcache[$proxy_id]) ? ($kwcache[$proxy_id] . '|' . $dongri_ko) : $dongri_ko;
                                $ps_kwko_insert->execute(array($proxy_id, Postcodify_Utility::crc32_x64($dongri_ko)));
                            }
                        }
                        
                        foreach ($dongri_en_array as $dongri_en)
                        {
                            if (!isset($kwcache[$proxy_id]) || strpos($kwcache[$proxy_id], $dongri_en) === false)
                            {
                                $kwcache[$proxy_id] = isset($kwcache[$proxy_id]) ? ($kwcache[$proxy_id] . '|' . $dongri_en) : $dongri_en;
                                $ps_kwen_insert->execute(array($proxy_id, Postcodify_Utility::crc32_x64($dongri_en)));
                            }
                        }
                        
                        $ps_numb_insert->execute(array($proxy_id, $entry->num_major, $entry->num_minor));
                    }
                    
                    // 카운터를 표시한다.
                    
                    if (++$count % 512 === 0)
                    {
                        $shmop = shmop_open($this->_shmop_key, 'w', 0, 0);
                        $prev = current(unpack('L', shmop_read($shmop, 0, 4)));
                        shmop_write($shmop, pack('L', $prev + 512), 0);
                        shmop_close($shmop);
                    }
                    
                    // 메모리 누수를 방지하기 위해 모든 배열을 unset한다.
                    
                    unset($dongri_ko_array);
                    unset($dongri_en_array);
                    unset($existing_ko);
                    unset($existing_en);
                    unset($entry);
                }
            }
        }
        
        // 뒷정리.
        
        $zip->close();
        unset($zip);
        
        if (!DRY_RUN)
        {
            $db->commit();
            unset($db);
        }
    }
    
    // 부가정보 데이터를 로딩한다. (쓰레드 사용)
    
    public function load_extra_info($sido)
    {
        
    }
    
    // 사서함 데이터를 로딩한다.
    
    public function load_pobox()
    {
        
    }
    
    // 지번 우편번호 데이터를 로딩한다.
    
    public function load_jibeon_postcode()
    {
        
    }
}
