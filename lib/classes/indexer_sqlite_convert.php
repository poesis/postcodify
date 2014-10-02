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

class Postcodify_Indexer_SQLite_Convert
{
    // 생성할 테이블 정보.
    
    protected $_tables = array(
        'postcodify_roads' => array(11, 'road_id'),
        'postcodify_addresses' => array(16, 'id'),
        'postcodify_keywords' => array(3, 'seq'),
        'postcodify_english' => array(3, 'seq'),
        'postcodify_numbers' => array(4, 'seq'),
        'postcodify_buildings' => array(3, 'seq'),
        'postcodify_pobox' => array(7, 'seq'),
        'postcodify_settings' => array(2, 'k'),
    );
    
    // 생성할 인덱스 목록.
    
    protected $_indexes = array(
        'postcodify_roads' => array('sido_ko', 'sigungu_ko', 'ilbangu_ko', 'eupmyeon_ko'),
        'postcodify_addresses' => array('address_id', 'road_id'),
        'postcodify_keywords' => array('address_id', 'keyword_crc32'),
        'postcodify_english' => array('en_crc32', 'ko_crc32'),
        'postcodify_numbers' => array('address_id', 'num_major', 'num_minor'),
        'postcodify_buildings' => array('address_id'),
        'postcodify_pobox' => array('address_id', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor'),
    );
    
    // 엔트리 포인트.
    
    public function start()
    {
        // SQLite 파일명을 구한다.
        
        if (isset($GLOBALS['argv'][2]))
        {
            $filename = $GLOBALS['argv'][2];
            if (@file_put_contents($filename, '') === false)
            {
                echo $filename . ' 파일을 생성할 수 없습니다. 경로와 퍼미션을 확인해 주십시오.' . PHP_EOL;
                exit(1);
            }
        }
        else
        {
            echo 'SQLite 파일명을 지정해 주십시오.' . PHP_EOL;
            exit(1);
        }
        
        // MySQL DB에 연결한다.
        
        if (!($mysql = Postcodify_Utility::get_db()))
        {
            echo '[ERROR] MySQL DB에 접속할 수 없습니다.' . PHP_EOL;
            exit(1);
        }
        
        // SQLite DB를 초기화한다.
        
        echo 'SQLite DB를 초기화하는 중...' . PHP_EOL;
        try
        {
            $sqlite = $this->initialize_db($filename);
        }
        catch (PDOException $e)
        {
            echo '[ERROR] SQLite DB 초기화에 실패했습니다.' . PHP_EOL;
            echo $e->getMessage() . PHP_EOL;
            exit(1);
        }
        
        // 데이터를 복사한다.
        
        $this->copy_data($mysql, $sqlite);
        
        // 인덱스를 생성한다.
        
        $this->create_indexes($sqlite);
        
        // 인덱스를 최적화한다.
        
        $this->wrap_up($sqlite);
    }
    
    // SQLite DB를 초기화한다.
    
    public function initialize_db($filename)
    {
        $sqlite = new PDO('sqlite:' . $filename);
        $sqlite->exec('PRAGMA page_size = 4096');
        $sqlite->exec('PRAGMA synchronous = OFF');
        $sqlite->exec('PRAGMA journal_mode = OFF');
        $sqlite->exec('PRAGMA encoding = "UTF-8"');
        $sqlite->exec(file_get_contents(POSTCODIFY_LIB_DIR . '/resources/schema-sqlite.sql'));
        return $sqlite;
    }
    
    // 데이터를 복사한다.
    
    public function copy_data($mysql, $sqlite)
    {
        foreach ($this->_tables as $table_name => $table_info)
        {
            echo $table_name . ' 데이터 복사 중...' . PHP_EOL;
            
            $row_count_query = $mysql->query('SELECT COUNT(*) FROM ' . $table_name);
            $row_count = intval($row_count_query->fetchColumn());
            
            $columns_placeholder = implode(', ', array_fill(0, $table_info[0], '?'));
            $primary_key = $table_info[1];
            $last_primary_key = 0;
            $increment = 2048;
            
            $ps = $sqlite->prepare('INSERT INTO ' . $table_name . ' VALUES (' . $columns_placeholder . ')');
            
            for ($i = 0; $i < $row_count; $i += $increment)
            {
                $sqlite->beginTransaction();
                
                $query = $mysql->prepare('SELECT * FROM ' . $table_name . ' WHERE ' . $primary_key . ' > ? ORDER BY ' . $primary_key . ' LIMIT ' . $increment);
                $query->bindParam(1, $last_primary_key, PDO::PARAM_INT);
                $query->execute();
                
                while ($row = $query->fetch(PDO::FETCH_NUM))
                {
                    $last_primary_key = $row[0];
                    $ps->execute($row);
                }
                
                $sqlite->commit();
            }
        }
    }
    
    // 인덱스를 생성한다.
    
    public function create_indexes($sqlite)
    {
        foreach ($this->_indexes as $table_name => $columns)
        {
            echo $table_name . ' 인덱스 생성 중...' . PHP_EOL;
            
            foreach ($columns as $column)
            {
                $sqlite->exec('CREATE INDEX ' . $table_name . '_' . $column . ' ON ' . $table_name . ' (' . $column . ')');
            }
        }
    }
    
    // SQLite DB를 최적화한다.
    
    public function wrap_up($sqlite)
    {
        echo '최적화 중...';
        $sqlite->exec('ANALYZE');
    }
}
