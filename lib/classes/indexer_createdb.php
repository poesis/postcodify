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
        $this->start_threaded_workers('juso');
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('작업용 인덱스를 생성하는 중...');
        $this->create_interim_indexes();
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('지번 데이터를 로딩하는 중...');
        $this->start_threaded_workers('jibeon');
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('부가정보 데이터를 로딩하는 중...');
        $this->start_threaded_workers('extra_info');
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('사서함 데이터를 로딩하는 중...');
        $this->load_pobox();
        $this->print_ok();
        $this->print_newline();
        
        $this->print_message('최종 인덱스를 생성하는 중...');
        $this->create_final_indexes();
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
    
    public function start_threaded_workers($task)
    {
        // 카운터로 사용하는 공유 메모리를 초기화한다.
        
        $shmop = shmop_open($this->_shmop_key, 'c', 0644, 4);
        shmop_write($shmop, pack('L', 0), 0);
        
        // 자식 프로세스 목록을 초기화한다.
        
        $children = array();
        $sidos = $this->_thread_groups;
        
        // 자식 프로세스들을 생성한다.
        
        while (count($sidos))
        {
            $sido = array_shift($sidos);
            $pid = pcntl_fork();
            
            if ($pid == -1)
            {
                echo PHP_EOL . '[ ERROR ] 쓰레드를 생성할 수 없습니다.' . PHP_EOL;
                exit(2);
            }
            elseif ($pid > 0)
            {
                $children[$pid] = $sido;
            }
            else
            {
                $method_name = 'load_' . $task;
                $this->$method_name($sido);
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
    
    // 작업용 인덱스를 생성한다.
    
    public function create_interim_indexes()
    {
        if (!DRY_RUN)
        {
            $db = Postcodify_Utility::get_db();
            $db->exec('CREATE INDEX postcodify_addresses_address_id ON postcodify_addresses (address_id)');
            unset($db);
        }
    }
    
    // 최종 인덱스를 생성한다.
    
    public function create_final_indexes()
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
            $ps = $db->prepare('INSERT INTO postcodify_addresses');
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
                    // 카운터를 표시한다.
                    
                    if (++$count % 512 === 0)
                    {
                        $shmop = shmop_open($this->_shmop_key, 'w', 0, 0);
                        $prev = current(unpack('L', shmop_read($shmop, 0, 4)));
                        shmop_write($shmop, pack('L', $prev + 512), 0);
                        shmop_close($shmop);
                    }
                    
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
