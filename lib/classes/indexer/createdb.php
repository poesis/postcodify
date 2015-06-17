<?php

/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (인덱서)
 * 
 *  Copyright (c) 2014-2015, Poesis <root@poesis.kr>
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
    // 건물정보 파일 기준으로 각각 200MB 내외가 되도록 나누었다.
    
    protected $_thread_groups = array(
        '경기도',
        '경상북도',
        '경상남도',
        '전라남도|광주광역시',
        '전라북도|충청북도',
        '서울특별시|부산광역시|울산광역시',
        '충청남도|대전광역시|인천광역시',
        '강원도|대구광역시|세종특별자치시|제주특별자치도',
    );
    
    // 생성자.
    
    public function __construct()
    {
        $this->_data_dir = dirname(POSTCODIFY_LIB_DIR) . '/data';
        $this->_shmop_key = ftok(__FILE__, 't');
    }
    
    // 엔트리 포인트.
    
    public function start($args)
    {
        Postcodify_Utility::print_message('Postcodify Indexer ' . POSTCODIFY_VERSION);
        Postcodify_Utility::print_newline();
        
        $checkenv = new Postcodify_Indexer_CheckEnv;
        $checkenv->check();
        
        $this->create_tables();
        $this->load_basic_info();
        $this->load_road_list();
        $this->load_english_aliases();
        exit;
        
        $this->start_threaded_workers('load_juso', '주소 데이터를 로딩하는 중...');
        $this->start_threaded_workers('interim_indexes', '작업용 인덱스를 생성하는 중...');
        $this->start_threaded_workers('load_jibeon', '지번 데이터를 로딩하는 중...');
        $this->start_threaded_workers('load_extra_info', '부가정보 데이터를 로딩하는 중...');
        
        $this->load_pobox();
        
        /*
        $this->load_new_ranges();
        $this->load_old_ranges();
        */
        
        $this->save_english_keywords();
        $this->start_threaded_workers('final_indexes', '최종 인덱스를 생성하는 중...');
    }
    
    // 작업 쓰레드를 생성한다.
    
    public function start_threaded_workers($task_name, $display_title)
    {
        // 작업 이름을 화면에 표시한다.
        
        Postcodify_Utility::print_message($display_title);
        
        // 카운터로 사용하는 공유 메모리를 초기화한다.
        
        $shmop = shmop_open($this->_shmop_key, 'c', 0644, 4);
        shmop_write($shmop, pack('L', 0), 0);
        
        // 자식 프로세스 목록을 초기화한다.
        
        $children = array();
        
        // 수행할 작업을 판단한다.
        
        switch ($task_name)
        {
            case 'interim_indexes':
                $schema = $this->load_schema();
                $tasks = $schema->interim_indexes;
                $task_name = 'create_indexes';
                break;
            case 'final_indexes':
                $schema = $this->load_schema();
                $tasks = $schema->final_indexes;
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
                echo PHP_EOL . '[ERROR] 쓰레드를 생성할 수 없습니다.' . PHP_EOL;
                exit(3);
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
            Postcodify_Utility::print_progress($count);
            
            // 부모 프로세스는 쉰다.
            
            usleep(100000);
        }
        
        // 공유 메모리를 닫는다.
        
        $count = current(unpack('L', shmop_read($shmop, 0, 4)));
        shmop_close($shmop);
        
        // 작업 완료 메시지를 화면에 표시한다.
        
        Postcodify_Utility::print_ok($count);
    }
    
    // DB 스키마를 로딩한다.
    
    public function load_schema()
    {
        $create_tables = array();
        $interim_indexes = array();
        $final_indexes = array();
        
        $schema = (include POSTCODIFY_LIB_DIR . '/resources/schema.php');
        
        foreach ($schema as $table_name => $table_definition)
        {
            $table_query = 'CREATE TABLE ' . $table_name;
            $columns = array();
            foreach ($table_definition as $column_name => $column_definition)
            {
                switch ($column_name)
                {
                    case '_interim':
                        $interim_indexes[$table_name] = $column_definition;
                        break;
                    case '_indexes':
                        $final_indexes[$table_name] = $column_definition;
                        break;
                    default:
                        if (POSTCODIFY_DB_DRIVER !== 'mysql')
                        {
                            $column_definition = preg_replace('/(SMALL|TINY)INT\b/', 'INT', $column_definition);
                            $column_definition = str_replace('INT PRIMARY KEY AUTO_INCREMENT', 'INTEGER PRIMARY KEY', $column_definition);
                        }
                        if ($column_name[0] !== '_')
                        {
                            $columns[] = $column_name . ' ' . $column_definition;
                        }
                }
            }
            $table_query .= ' (' . implode(', ', $columns) . ')';
            if (POSTCODIFY_DB_DRIVER === 'mysql')
            {
                $table_query .= ' ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci';
            }
            $create_tables[] = $table_query;
        }
        
        return (object)array(
            'create_tables' => $create_tables,
            'interim_indexes' => $interim_indexes,
            'final_indexes' => $final_indexes,
        );
    }
    
    // 테이블을 생성한다.
    
    public function create_tables()
    {
        Postcodify_Utility::print_message('테이블을 생성하는 중...');
        $db = Postcodify_Utility::get_db();
        
        // 이미 있는 postcodify_* 테이블을 삭제한다.
        
        $existing_tables = array();
        if (POSTCODIFY_DB_DRIVER === 'mysql')
        {
            $existing_tables_query = $db->query("SHOW TABLES LIKE 'postcodify_%'");
        }
        else
        {
            $existing_tables_query = $db->query("SELECT name FROM sqlite_master WHERE type = 'table' and name LIKE 'postcodify_%'");
        }
        
        while ($table_name = $existing_tables_query->fetchColumn())
        {
            $existing_tables[] = $table_name;
        }
        
        foreach ($existing_tables as $table_name)
        {
            $db->exec("DROP TABLE $table_name");
        }
        
        // 새 테이블을 생성한다.
        
        $schema = $this->load_schema();
        foreach ($schema->create_tables as $table_query)
        {
            $db->exec($table_query);
        }
        
        unset($db);
        Postcodify_Utility::print_ok();
    }
    
    // 인덱스를 생성한다. (쓰레드 사용)
    
    public function create_indexes($columns, $table_name)
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
        
        $db->exec('ANALYZE TABLE ' . $table_name);
        unset($db);
    }
    
    // 기본 정보를 로딩한다.
    
    public function load_basic_info()
    {
        Postcodify_Utility::print_message('기본 정보를 로딩하는 중...');
        
        // 데이터 기준일을 파악한다.
        
        $year = $month = $day = null;
        
        $data_files = scandir(dirname(POSTCODIFY_LIB_DIR) . '/data');
        foreach ($data_files as $filename)
        {
            if (preg_match('/^(20[0-9]{2})([0-9]{2})RDNM(ADR|CODE)\.zip$/', $filename, $matches))
            {
                $year = intval($matches[1], 10);
                $month = intval($matches[2], 10);
                $day = intval(date('t', mktime(12, 0, 0, $month, 1, $year)));
            }
        }
        
        if (!$year || !$month || !$day)
        {
            echo '[ERROR] 데이터 기준일을 파악할 수 없습니다.' . PHP_EOL;
            exit(2);
        }
        else
        {
            $this->_data_date = sprintf('%04d%02d%02d', $year, $month, $day);
        }
        
        // DB에 저장한다.
        
        $db = Postcodify_Utility::get_db();
        $db->exec("INSERT INTO postcodify_settings (k, v) VALUES ('version', '" . POSTCODIFY_VERSION . "')");
        $db->exec("INSERT INTO postcodify_settings (k, v) VALUES ('updated', '" . $this->_data_date . "')");
        unset($db);
        Postcodify_Utility::print_ok();
    }
    
    // 도로명코드 목록을 로딩한다.
    
    public function load_road_list()
    {
        Postcodify_Utility::print_message('도로명코드 목록을 로딩하는 중...');
        
        // DB를 준비한다.
        
        $db = Postcodify_Utility::get_db();
        $db->beginTransaction();
        $ps = $db->prepare('INSERT INTO postcodify_roads (road_id, road_name_ko, road_name_en, ' .
            'sido_ko, sido_en, sigungu_ko, sigungu_en, ilbangu_ko, ilbangu_en, eupmyeon_ko, eupmyeon_en, updated) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        // Zip 파일을 연다.
        
        $zip = new Postcodify_Parser_Road_List;
        $zip->open_archive($this->_data_dir . '/' . substr($this->_data_date, 0, 6) . 'RDNMCODE.zip');
        $zip->open_named_file(iconv('UTF-8', 'CP949', '도로명코드_전체분'));
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 데이터를 한 줄씩 읽는다.
        
        while ($entry = $zip->read_line())
        {
            // 도로명을 캐시에 저장한다.
            
            Postcodify_Utility::$road_cache[$entry->road_id] = $entry->road_name_ko;
            
            // 영문 행정구역명을 캐시에 저장한다.
            
            Postcodify_Utility::$english_cache[$entry->road_name_ko] = $entry->road_name_en;
            Postcodify_Utility::$english_cache[$entry->sido_ko] = $entry->sido_en;
            if ($entry->sigungu_ko) Postcodify_Utility::$english_cache[$entry->sigungu_ko] = $entry->sigungu_en;
            if ($entry->ilbangu_ko) Postcodify_Utility::$english_cache[$entry->ilbangu_ko] = $entry->ilbangu_en;
            if ($entry->eupmyeon_ko) Postcodify_Utility::$english_cache[$entry->eupmyeon_ko] = $entry->eupmyeon_en;
            
            // 도로명 및 소속 행정구역 정보를 DB에 저장한다.
            
            $ps->execute(array(
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
            
            // 카운터를 표시한다.
            
            if (++$count % 512 === 0) Postcodify_Utility::print_progress($count);
            unset($entry);
        }
        
        // 뒷정리.
        
        $zip->close();
        unset($zip);
        
        $db->commit();
        unset($db);
        
        Postcodify_Utility::print_ok($count);
    }
    
    // 영문 행정구역명을 로딩한다.
    
    public function load_english_aliases()
    {
        Postcodify_Utility::print_message('영문 행정구역명을 로딩하는 중...');
        
        // Zip 파일을 연다.
        
        $zip = new Postcodify_Parser_English_Aliases;
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
            
            if (++$count % 512 === 0) Postcodify_Utility::print_progress($count);
            unset($entry);
        }
        
        // 뒷정리.
        
        $zip->close();
        unset($zip);
        
        Postcodify_Utility::print_ok($count);
    }
    
    // 주소 데이터를 로딩한다. (쓰레드 사용)
    
    public function load_juso($sido)
    {
        // DB를 준비한다.
        
        $db = Postcodify_Utility::get_db();
        $db->beginTransaction();
        $ps_addr_insert = $db->prepare('INSERT INTO postcodify_addresses (address_id, postcode5, ' .
            'road_id, num_major, num_minor, is_basement) VALUES (?, ?, ?, ?, ?, ?)');
        $ps_kwd_insert = $db->prepare('INSERT INTO postcodify_keywords (address_id, keyword_crc32) VALUES (?, ?)');
        $ps_num_insert = $db->prepare('INSERT INTO postcodify_numbers (address_id, num_major, num_minor) VALUES (?, ?, ?)');
        
        // 이 쓰레드에서 처리할 시·도 목록을 구한다.
        
        $sidos = explode('|', $sido);
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 시·도를 하나씩 처리한다.
        
        foreach ($sidos as $sido)
        {
            // Zip 파일을 연다.
            
            $zip = new Postcodify_Parser_Juso;
            $zip->open_archive($this->_data_dir . '/주소_' . $sido . '.zip');
            
            // Zip 파일에 포함된 텍스트 파일들을 하나씩 처리한다.
            
            while (($filename = $zip->open_next_file()) !== false)
            {
                // 데이터를 한 줄씩 읽는다.
                
                while ($entry = $zip->read_line())
                {
                    // 이 주소에 해당하는 도로명을 구한다.
                    
                    $road_name = Postcodify_Utility::$road_cache[$entry->road_id];
                    
                    // 키워드를 확장한다.
                    
                    $road_name_array = Postcodify_Utility::get_variations_of_road_name($road_name);
                    
                    // 주소를 저장한다.
                    
                    $ps_addr_insert->execute(array(
                        $entry->address_id,
                        $entry->postcode5,
                        $entry->road_id . $entry->road_section,
                        $entry->num_major,
                        $entry->num_minor,
                        $entry->is_basement,
                    ));
                    
                    $proxy_id = $db->lastInsertId();
                    
                    // 검색 키워드와 번호들을 저장한다.
                    
                    foreach ($road_name_array as $road_name)
                    {
                        $ps_kwd_insert->execute(array($proxy_id, Postcodify_Utility::crc32_x64($road_name)));
                    }
                    
                    $ps_num_insert->execute(array($proxy_id, $entry->num_major, $entry->num_minor));
                    
                    // 카운터를 표시한다.
                    
                    if (++$count % 512 === 0)
                    {
                        $shmop = shmop_open($this->_shmop_key, 'w', 0, 0);
                        $prev = current(unpack('L', shmop_read($shmop, 0, 4)));
                        shmop_write($shmop, pack('L', $prev + 512), 0);
                        shmop_close($shmop);
                    }
                    
                    // 메모리 누수를 방지하기 위해 모든 배열을 unset한다.
                    
                    unset($road_name_array);
                    unset($entry);
                }
            }
        }
        
        // 뒷정리.
        
        $zip->close();
        unset($zip);
        
        $db->commit();
        unset($db);
    }
    
    // 지번 데이터를 로딩한다. (쓰레드 사용)
    
    public function load_jibeon($sido)
    {
        // DB를 준비한다.
        
        $db = Postcodify_Utility::get_db();
        $db->beginTransaction();
        $ps_addr_select = $db->prepare('SELECT id, other_addresses FROM postcodify_addresses where address_id = ?');
        $ps_addr_update1 = $db->prepare('UPDATE postcodify_addresses SET dongri_ko = ?, dongri_en = ?, ' .
            'jibeon_major = ?, jibeon_minor = ?, is_mountain = ? WHERE id = ?');
        $ps_addr_update2 = $db->prepare('UPDATE postcodify_addresses SET other_addresses = ? WHERE id = ?');
        $ps_kwd_insert = $db->prepare('INSERT INTO postcodify_keywords (address_id, keyword_crc32) VALUES (?, ?)');
        $ps_num_insert = $db->prepare('INSERT INTO postcodify_numbers (address_id, num_major, num_minor) VALUES (?, ?, ?)');
        
        // 이 쓰레드에서 처리할 시·도 목록을 구한다.
        
        $sidos = explode('|', $sido);
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 시·도를 하나씩 처리한다.
        
        foreach ($sidos as $sido)
        {
            // Zip 파일을 연다.
            
            $zip = new Postcodify_Parser_Jibeon;
            $zip->open_archive($this->_data_dir . '/지번_' . $sido . '.zip');
            
            // Zip 파일에 포함된 텍스트 파일들을 하나씩 처리한다.
            
            while (($filename = $zip->open_next_file()) !== false)
            {
                // 동·리 키워드 중복 방지 캐시를 초기화한다.
                
                $kwcache = array();
                
                // 데이터를 한 줄씩 읽는다.
                
                while ($entry = $zip->read_line())
                {
                    // 키워드를 확장한다.
                    
                    $dongri_array = Postcodify_Utility::get_variations_of_dongri($entry->dongri);
                    
                    // 이 주소의 대체키 번호를 구한다.
                    
                    $ps_addr_select->execute(array($entry->address_id));
                    list($proxy_id, $other_addresses) = $ps_addr_select->fetch(PDO::FETCH_NUM);
                    $ps_addr_select->closeCursor();
                    $proxy_id = intval($proxy_id);
                    if (!$proxy_id) continue;
                    
                    // 대표지번인 경우 해당 레코드에 직접 저장한다.
                    
                    if ($entry->is_canonical)
                    {
                        $ps_addr_update1->execute(array(
                            $entry->dongri,
                            Postcodify_Utility::$english_cache[$entry->dongri],
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
                            $other_addresses . $entry->dongri . ' ' . $nums . "\n",
                            $proxy_id,
                        ));
                    }
                    
                    // 키워드와 번호들을 저장한다.
                    
                    foreach ($dongri_array as $dongri)
                    {
                        if (!isset($kwcache[$proxy_id][$dongri]))
                        {
                            $kwcache[$proxy_id][$dongri] = true;
                            $ps_kwd_insert->execute(array($proxy_id, Postcodify_Utility::crc32_x64($dongri)));
                        }
                    }
                    
                    if (count($kwcache) > 200)
                    {
                        $kwcache = array_slice($kwcache, 100);
                    }
                    
                    $ps_num_insert->execute(array($proxy_id, $entry->num_major, $entry->num_minor));
                    
                    // 카운터를 표시한다.
                    
                    if (++$count % 512 === 0)
                    {
                        $shmop = shmop_open($this->_shmop_key, 'w', 0, 0);
                        $prev = current(unpack('L', shmop_read($shmop, 0, 4)));
                        shmop_write($shmop, pack('L', $prev + 512), 0);
                        shmop_close($shmop);
                    }
                    
                    // 메모리 누수를 방지하기 위해 모든 배열을 unset한다.
                    
                    unset($dongri_array);
                    unset($entry);
                }
            }
        }
        
        // 뒷정리.
        
        $zip->close();
        unset($zip);
        
        $db->commit();
        unset($db);
    }
    
    // 부가정보 데이터를 로딩한다. (쓰레드 사용)
    
    public function load_extra_info($sido)
    {
        // DB를 준비한다.
        
        $db = Postcodify_Utility::get_db();
        $db->beginTransaction();
        $ps_addr_select = $db->prepare('SELECT id, dongri_ko, other_addresses FROM postcodify_addresses where address_id = ?');
        $ps_addr_update = $db->prepare('UPDATE postcodify_addresses SET postcode6 = ?, ' .
            'building_name = ?, building_num = ?, other_addresses = ? WHERE id = ?');
        $ps_kwd_select = $db->prepare('SELECT keyword_crc32 FROM postcodify_keywords WHERE address_id = ?');
        $ps_kwd_insert = $db->prepare('INSERT INTO postcodify_keywords (address_id, keyword_crc32) VALUES (?, ?)');
        $ps_building_insert = $db->prepare('INSERT INTO postcodify_buildings (address_id, keyword) VALUES (?, ?)');
        
        // 이 쓰레드에서 처리할 시·도 목록을 구한다.
        
        $sidos = explode('|', $sido);
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 시·도를 하나씩 처리한다.
        
        foreach ($sidos as $sido)
        {
            // Zip 파일을 연다.
            
            $zip = new Postcodify_Parser_Extra_Info;
            $zip->open_archive($this->_data_dir . '/부가정보_' . $sido . '.zip');
            
            // Zip 파일에 포함된 텍스트 파일들을 하나씩 처리한다.
            
            while (($filename = $zip->open_next_file()) !== false)
            {
                // 데이터를 한 줄씩 읽는다.
                
                while ($entry = $zip->read_line())
                {
                    // 이 주소의 대체키 번호를 구한다.
                    
                    $ps_addr_select->execute(array($entry->address_id));
                    list($proxy_id, $dongri_ko, $other_addresses) = $ps_addr_select->fetch(PDO::FETCH_NUM);
                    $ps_addr_select->closeCursor();
                    $proxy_id = intval($proxy_id);
                    if (!$proxy_id) continue;
                    
                    // 법정동명과 행정동명이 같은 경우 행정동명을 삭제한다.
                    
                    if ($dongri_ko === $entry->admin_dongri) $entry->admin_dongri = null;
                    
                    // 공동주택명을 구한다.
                    
                    $common_residence_name = strval($entry->common_residence_name);
                    if ($common_residence_name === '') $common_residence_name = null;
                    
                    // 아파트 동범위 정보를 구한다.
                    
                    if (isset(Postcodify_Utility::$building_number_cache[$entry->address_id]))
                    {
                        $building_num_split = explode('|', Postcodify_Utility::$building_number_cache[$entry->address_id]);
                        $entry->building_names[] = $common_residence_name = $building_num_split[0];
                        $building_num = $building_num_split[1];
                        unset($building_num_split);
                    }
                    else
                    {
                        $building_num = null;
                    }
                    
                    // 기타 주소를 정리한다.
                    
                    $building_names = $entry->building_names;
                    if (isset(Postcodify_Utility::$building_cache[$entry->address_id]))
                    {
                        $extra_building_names = explode('|', Postcodify_Utility::$building_cache[$entry->address_id]);
                        foreach ($extra_building_names as $extra_building_name)
                        {
                            $building_names[] = $extra_building_name;
                        }
                        $building_names = array_unique($building_names);
                    }
                    
                    if ($common_residence_name !== null)
                    {
                        $building_names = array_values($building_names);
                        $building_names_count = count($building_names);
                        for ($i = 0; $i < $building_names_count; $i++)
                        {
                            if ($building_names[$i] === '') continue;
                            if (strpos($common_residence_name, $building_names[$i]) !== false)
                            {
                                unset($building_names[$i]);
                            }
                        }
                    }
                    
                    if ($building_num !== null)
                    {
                        $building_names = array_values($building_names);
                        $building_names_count = count($building_names);
                        for ($i = 0; $i < $building_names_count; $i++)
                        {
                            if (preg_match('/^[0-9a-z]+동$/iu', $building_names[$i]))
                            {
                                unset($building_names[$i]);
                            }
                        }
                    }
                    elseif ($common_residence_name !== null)
                    {
                        $building_names = array_values($building_names);
                        $building_names_count = count($building_names);
                        for ($i = 0; $i < $building_names_count; $i++)
                        {
                            if (preg_match('/^([0-9]{1,2})0([123])동$/iu', $building_names[$i], $building_name_matches))
                            {
                                if ($building_name_matches[2] === '1')
                                {
                                    $building_num = $building_name_matches[1] . '01동~';
                                    unset($building_names[$i]);
                                }
                                elseif (preg_match('/' . $building_name_matches[1] . '(?:차|단지)/', $common_residence_name))
                                {
                                    $building_num = $building_name_matches[1] . '01동~';
                                    unset($building_names[$i]);
                                }
                            }
                        }
                    }
                    
                    $other_addresses = Postcodify_Utility::format_other_addresses($other_addresses, $building_names, $entry->admin_dongri);
                    
                    // 우편번호와 공동주택명 등의 정보를 저장한다.
                    
                    $ps_addr_update->execute(array(
                        $entry->postcode6,
                        $common_residence_name,
                        $building_num,
                        $other_addresses,
                        $proxy_id,
                    ));
                    
                    // 행정동·리 및 건물명 키워드를 저장한다.
                    
                    if ($entry->admin_dongri)
                    {
                        $ps_kwd_select->execute(array($proxy_id));
                        $existing_keywords = array();
                        $existing_keywords_raw = $ps_kwd_select->fetchAll(PDO::FETCH_NUM);
                        foreach ($existing_keywords_raw as $existing_keyword)
                        {
                            $existing_keywords[$existing_keyword[0]] = true;
                            unset($existing_keyword);
                        }
                        
                        $admin_dongri_variations = Postcodify_Utility::get_variations_of_dongri($entry->admin_dongri);
                        foreach ($admin_dongri_variations as $dongri)
                        {
                            $crc32 = Postcodify_Utility::crc32_x64($dongri);
                            if (!isset($existing_keywords[$crc32]))
                            {
                                $ps_kwd_insert->execute(array($proxy_id, $crc32));
                            }
                        }
                    }
                    
                    if ($common_residence_name !== null) $building_names[] = $common_residence_name;
                    $building_names_consolidated = Postcodify_Utility::consolidate_building_names($building_names);
                    if ($building_names_consolidated !== '')
                    {
                        $ps_building_insert->execute(array($proxy_id, $building_names_consolidated));
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
                    
                    unset($admin_dongri_variations);
                    unset($building_names);
                    unset($building_name_matches);
                    unset($extra_building_names);
                    unset($existing_keywords_raw);
                    unset($existing_keywords);
                    unset($entry);
                }
            }
        }
        
        // 뒷정리.
        
        $zip->close();
        unset($zip);
        
        $db->commit();
        unset($db);
    }
    
    // 사서함 데이터를 로딩한다.
    
    public function load_pobox()
    {
        Postcodify_Utility::print_message('사서함 데이터를 로딩하는 중...');
        
        // DB를 준비한다.
        
        $db = Postcodify_Utility::get_db();
        $db->beginTransaction();
        $ps_road_insert = $db->prepare('INSERT INTO postcodify_roads (road_id, ' .
            'sido_ko, sido_en, sigungu_ko, sigungu_en, ilbangu_ko, ilbangu_en, eupmyeon_ko, eupmyeon_en) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ps_addr_insert = $db->prepare('INSERT INTO postcodify_addresses (road_id, postcode6, postcode5, ' .
            'dongri_ko, dongri_en, jibeon_major, jibeon_minor, other_addresses) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $ps_pobox_insert = $db->prepare('INSERT INTO postcodify_pobox (address_id, keyword, ' .
            'range_start_major, range_start_minor, range_end_major, range_end_minor) ' .
            'VALUES (?, ?, ?, ?, ?, ?)');
        
        // Zip 파일을 연다.
        
        $zip = new Postcodify_Parser_NewPobox;
        $zip->open_archive($this->_data_dir . '/areacd_pobox_DB.zip');
        $openfile = $zip->open_named_file(iconv('UTF-8', 'CP949', '사서함'));
        if ($openfile === false)
        {
            $zip->open_named_file('.txt');
        }
        
        // 행정구역 캐시와 카운터를 초기화한다.
        
        $region_cache = array();
        $road_count = 0;
        $count = 0;
        
        // 데이터를 한 줄씩 읽는다.
        
        while ($entry = $zip->read_line())
        {
            // 불필요한 줄은 건너뛴다.
            
            if ($entry === true) continue;
            
            // 행정구역 정보를 생성한다.
            
            $road_id_hash = sha1($entry->sido . '|' . $entry->sigungu . '|' . $entry->ilbangu . '|' . $entry->eupmyeon);
            if (isset($region_cache[$road_id_hash]))
            {
                $road_id = $region_cache[$road_id_hash];
            }
            else
            {
                $road_id = sprintf('999999%06d00', ++$road_count);
                $region_cache[$road_id_hash] = $road_id;
                $ps_road_insert->execute(array(
                    $road_id,
                    $entry->sido,
                    $entry->sido ? Postcodify_Utility::$english_cache[$entry->sido] : null,
                    $entry->sigungu,
                    $entry->sigungu ? Postcodify_Utility::$english_cache[$entry->sigungu] : null,
                    $entry->ilbangu,
                    $entry->ilbangu ? Postcodify_Utility::$english_cache[$entry->ilbangu] : null,
                    $entry->eupmyeon,
                    $entry->eupmyeon ? Postcodify_Utility::$english_cache[$entry->eupmyeon] : null,
                ));
            }
            
            // 시작번호와 끝번호를 정리한다.
            
            $startnum = $entry->range_start_major . ($entry->range_start_minor ? ('-' . $entry->range_start_minor) : '');
            $endnum = $entry->range_end_major . ($entry->range_end_minor ? ('-' . $entry->range_end_minor) : '');
            if ($endnum === '' || $endnum === '-') $endnum = null;
            
            // 주소 레코드를 저장한다.
            
            $ps_addr_insert->execute(array(
                $road_id,
                $entry->postcode6,
                $entry->postcode5,
                $entry->pobox_name,
                'P.O.Box',
                $entry->range_start_major,
                $entry->range_start_minor,
                $startnum . ($endnum === null ? '' : (' ~ ' . $endnum)),
            ));
            
            $proxy_id = $db->lastInsertId();
            
            // 검색 키워드들을 정리하여 저장한다.
            
            $keywords = array(Postcodify_Utility::get_canonical($entry->pobox_name));
            
            if ($entry->pobox_name === '사서함')
            {
                if ($entry->sigungu !== '') $keywords[] = Postcodify_Utility::get_canonical($entry->sigungu . $entry->pobox_name);
                if ($entry->ilbangu !== '') $keywords[] = Postcodify_Utility::get_canonical($entry->ilbangu . $entry->pobox_name);
                if ($entry->eupmyeon !== '') $keywords[] = Postcodify_Utility::get_canonical($entry->eupmyeon . $entry->pobox_name);
            }
            
            if (!$entry->range_end_major) $entry->range_end_major = $entry->range_start_major;
            if (!$entry->range_end_minor) $entry->range_end_minor = $entry->range_start_minor;
            
            foreach ($keywords as $keyword)
            {
                $ps_pobox_insert->execute(array(
                    $proxy_id,
                    $keyword,
                    $entry->range_start_major,
                    $entry->range_start_minor,
                    $entry->range_end_major,
                    $entry->range_end_minor,
                ));
            }
            
            // 카운터를 표시한다.
            
            if (++$count % 512 === 0) Postcodify_Utility::print_progress($count);
            
            // 메모리 누수를 방지하기 위해 모든 배열을 unset한다.
            
            unset($keywords);
            unset($entry);
        }
        
        // 뒷정리.
        
        $zip->close();
        unset($zip);
        
        $db->commit();
        unset($db);
        
        Postcodify_Utility::print_ok();
    }
    
    // 새 우편번호 범위 DB를 로딩한다.
    
    public function load_new_ranges()
    {
        Postcodify_Utility::print_message('새 우편번호 범위 데이터를 로딩하는 중...');
        
        // DB를 준비한다.
        
        $db = Postcodify_Utility::get_db();
        $db->beginTransaction();
        $ps_insert_roads = $db->prepare('INSERT INTO postcodify_ranges_roads (sido_ko, sido_en, ' .
            'sigungu_ko, sigungu_en, ilbangu_ko, ilbangu_en, eupmyeon_ko, eupmyeon_en, ' .
            'road_name_ko, road_name_en, range_start_major, range_start_minor, range_end_major, range_end_minor, ' .
            'range_type, is_basement, postcode5) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ps_insert_jibeon = $db->prepare('INSERT INTO postcodify_ranges_jibeon (sido_ko, sido_en, ' .
            'sigungu_ko, sigungu_en, ilbangu_ko, ilbangu_en, eupmyeon_ko, eupmyeon_en, ' .
            'dongri_ko, dongri_en, range_start_major, range_start_minor, range_end_major, range_end_minor, ' .
            'is_mountain, admin_dongri, postcode5) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 도로명주소 범위 파일을 연다.
        
        $zip = new Postcodify_Parser_Ranges_Roads;
        $zip->open_archive($this->_data_dir . '/areacd_rangeaddr_DB.zip');
        $openfile = $zip->open_named_file(iconv('UTF-8', 'CP949', '도로명'));
        if ($openfile === false)
        {
            $zip->open_next_file();
        }
        
        // 데이터를 한 줄씩 읽는다.
        
        while ($entry = $zip->read_line())
        {
            // 불필요한 줄은 건너뛴다.
            
            if ($entry === true) continue;
            
            // 레코드를 저장한다.
            
            $ps_insert_roads->execute(array(
                $entry->sido_ko,
                $entry->sido_en,
                $entry->sigungu_ko,
                $entry->sigungu_en,
                $entry->ilbangu_ko,
                $entry->ilbangu_en,
                $entry->eupmyeon_ko,
                $entry->eupmyeon_en,
                $entry->road_name_ko,
                $entry->road_name_en,
                $entry->range_start_major,
                $entry->range_start_minor,
                $entry->range_end_major,
                $entry->range_end_minor,
                $entry->range_type,
                $entry->is_basement,
                $entry->postcode5,
            ));
            
            // 카운터를 표시한다.
            
            if (++$count % 512 === 0) Postcodify_Utility::print_progress($count);
            
            // 메모리 누수를 방지하기 위해 모든 배열을 unset한다.
            
            unset($entry);
        }
        
        // 도로명주소 범위 파일을 닫는다.
        
        $zip->close();
        unset($zip);
        
        // 지번주소 범위 파일을 연다.
        
        $zip = new Postcodify_Parser_Ranges_Jibeon;
        $zip->open_archive($this->_data_dir . '/areacd_rangeaddr_DB.zip');
        $openfile = $zip->open_named_file(iconv('UTF-8', 'CP949', '지번'));
        if ($openfile === false)
        {
            $zip->open_next_file();
            $zip->open_next_file();
        }
        
        // 데이터를 한 줄씩 읽는다.
        
        while ($entry = $zip->read_line())
        {
            // 불필요한 줄은 건너뛴다.
            
            if ($entry === true) continue;
            
            // 레코드를 저장한다.
            
            $ps_insert_jibeon->execute(array(
                $entry->sido_ko,
                $entry->sido_en,
                $entry->sigungu_ko,
                $entry->sigungu_en,
                $entry->ilbangu_ko,
                $entry->ilbangu_en,
                $entry->eupmyeon_ko,
                $entry->eupmyeon_en,
                $entry->dongri_ko,
                $entry->dongri_en,
                $entry->range_start_major,
                $entry->range_start_minor,
                $entry->range_end_major,
                $entry->range_end_minor,
                $entry->is_mountain,
                $entry->admin_dongri,
                $entry->postcode5,
            ));
            
            // 카운터를 표시한다.
            
            if (++$count % 512 === 0) Postcodify_Utility::print_progress($count);
            
            // 메모리 누수를 방지하기 위해 모든 배열을 unset한다.
            
            unset($entry);
        }
        
        // 지번주소 범위 파일을 닫는다.
        
        $zip->close();
        unset($zip);
        
        // 뒷정리.
        
        $db->commit();
        unset($db);
        
        Postcodify_Utility::print_ok($count);
    }
    
    // 구 우편번호 범위 DB를 로딩한다.
    
    public function load_old_ranges()
    {
        Postcodify_Utility::print_message('구 우편번호 범위 데이터를 로딩하는 중...');
        
        // DB를 준비한다.
        
        $db = Postcodify_Utility::get_db();
        $db->beginTransaction();
        $ps_insert = $db->prepare('INSERT INTO postcodify_ranges_oldcode (sido_ko, sido_en, sigungu_ko, sigungu_en, ' .
            'ilbangu_ko, ilbangu_en, eupmyeon_ko, eupmyeon_en, dongri_ko, dongri_en, ' .
            'range_start_major, range_start_minor, range_end_major, range_end_minor, is_mountain, ' .
            'island_name, building_name, building_num_start, building_num_end, postcode6) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        // Zip 파일을 연다.
        
        $zip = new Postcodify_Parser_Ranges_OldCode;
        $zip->open_archive($this->_data_dir . '/oldaddr_zipcode_DB.zip');
        $zip->open_next_file();
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 데이터를 한 줄씩 읽는다.
        
        while ($entry = $zip->read_line())
        {
            // 불필요한 줄은 건너뛴다.
            
            if ($entry === true) continue;
            
            // 레코드를 저장한다.
            
            $ps_insert->execute(array(
                $entry->sido,
                $entry->sido ? Postcodify_Utility::$english_cache[$entry->sido] : null,
                $entry->sigungu,
                $entry->sigungu ? Postcodify_Utility::$english_cache[$entry->sigungu] : null,
                $entry->ilbangu,
                $entry->ilbangu ? Postcodify_Utility::$english_cache[$entry->ilbangu] : null,
                $entry->eupmyeon,
                $entry->eupmyeon ? Postcodify_Utility::$english_cache[$entry->eupmyeon] : null,
                $entry->dongri,
                $entry->dongri ? Postcodify_Utility::$english_cache[Postcodify_Utility::get_canonical($entry->dongri)] : null,
                $entry->range_start_major,
                $entry->range_start_minor,
                $entry->range_end_major,
                $entry->range_end_minor,
                $entry->is_mountain,
                $entry->island_name,
                $entry->building_name,
                $entry->building_num_start,
                $entry->building_num_end,
                $entry->postcode6,
            ));
            
            // 카운터를 표시한다.
            
            if (++$count % 512 === 0) Postcodify_Utility::print_progress($count);
            
            // 메모리 누수를 방지하기 위해 모든 배열을 unset한다.
            
            unset($entry);
        }
        
        // 압축 파일을 닫는다.
        
        $zip->close();
        unset($zip);
        
        $db->commit();
        Postcodify_Utility::print_ok($count);
        
        // 기존에 입력된 데이터 중 우편번호가 누락된 것이 있는지 찾아서, 누락된 우편번호를 입력한다.
        
        Postcodify_Utility::print_message('구 우편번호가 누락된 데이터를 확인하는 중...');
        
        $db->beginTransaction();
        $count = 0;
        
        $last_id = 0;
        $update_class = new Postcodify_Indexer_Update;
        $ps_missing_postcode6 = $db->prepare('SELECT * FROM postcodify_addresses pa ' .
            'JOIN postcodify_roads pr ON pa.road_id = pr.road_id ' .
            'WHERE (pa.postcode6 IS NULL or pa.postcode6 = \'000000\') AND id > ? ' .
            'ORDER BY id LIMIT 20');
        $ps_addr_update = $db->prepare('UPDATE postcodify_addresses SET postcode6 = ? WHERE id = ?');
        
        while (true)
        {
            $ps_missing_postcode6->execute(array($last_id));
            $missing_entries = $ps_missing_postcode6->fetchAll(PDO::FETCH_OBJ);
            if (!count($missing_entries)) break;
            foreach ($missing_entries as $missing_entry)
            {
                $last_id = $missing_entry->id;
                $postcode6 = $update_class->find_postcode6($db, $missing_entry,
                    $missing_entry->dongri_ko, $missing_entry->dongri_ko,
                    $missing_entry->jibeon_major, $missing_entry->jibeon_minor);
                if ($postcode6 !== null)
                {
                    $ps_addr_update->execute(array($postcode6, $missing_entry->id));
                }
            }
        }
        
        // 뒷정리.
        
        $db->commit();
        unset($db);
        
        Postcodify_Utility::print_ok($count);
    }
    
    // 영문 검색 키워드를 저장한다.
    
    public function save_english_keywords()
    {
        Postcodify_Utility::print_message('영문 검색 키워드를 저장하는 중...');
        
        // DB를 준비한다.
        
        $db = Postcodify_Utility::get_db();
        $db->beginTransaction();
        $ps = $db->prepare('INSERT INTO postcodify_english (ko, ko_crc32, en, en_crc32) VALUES (?, ?, ?, ?)');
        
        // 카운터를 초기화한다.
        
        $count = 0;
        
        // 각 영문 키워드와 거기에 해당하는 한글 키워드를 DB에 입력한다.
        
        foreach (Postcodify_Utility::$english_cache as $ko => $en)
        {
            // 영문 키워드에서 불필요한 문자를 제거한다.
            
            $en_canonical = preg_replace('/[^a-z0-9]/', '', strtolower($en));
            
            // 양쪽 모두 CRC32 처리하여 저장한다.
            
            $ps->execute(array(
                $ko,
                Postcodify_Utility::crc32_x64($ko),
                $en,
                Postcodify_Utility::crc32_x64($en_canonical),
            ));
            
            // 카운터를 표시한다.
            
            if (++$count % 512 === 0) Postcodify_Utility::print_progress($count);
        }
        
        // 뒷정리.
        
        $db->commit();
        unset($db);
        
        Postcodify_Utility::print_ok($count);
    }
}
