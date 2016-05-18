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

class Postcodify_Indexer_VerifyDB
{
    // 확인할 데이터 정의.
    
    protected $_schema;
    protected $_add_old_postcodes = false;
    
    // 엔트리 포인트.
    
    public function start($args)
    {
        Postcodify_Utility::print_message('Postcodify Indexer ' . POSTCODIFY_VERSION);
        Postcodify_Utility::print_newline();
        
        if (in_array('--add-old-postcodes', $args->options))
        {
            $this->_add_old_postcodes = true;
        }
        
        if (!($db = Postcodify_Utility::get_db()))
        {
            echo '[ERROR] MySQL DB에 접속할 수 없습니다.' . PHP_EOL;
            exit(1);
        }
        
        $this->_schema = (include POSTCODIFY_LIB_DIR . '/resources/schema.php');
        $pass = true;
        
        echo '테이블 확인 중...' . PHP_EOL;
        $pass = $this->check_tables($db) && $pass;
        
        echo '인덱스 확인 중...' . PHP_EOL;
        $pass = $this->check_indexes($db) && $pass;
        
        if ($pass)
        {
            echo '데이터 확인 중...' . PHP_EOL;
            $pass = $pass && $this->check_data_count($db);
            $pass = $pass && $this->check_missing_postcodes($db);
        }
        else
        {
            echo 'DB 스키마에 문제가 있으므로 데이터 확인은 시도하지 않습니다.' . PHP_EOL;
        }
        
        if ($pass)
        {
            echo 'DB에 문제가 없습니다.' . PHP_EOL;
        }
        else
        {
            echo 'DB에 문제가 있습니다.' . PHP_EOL;
            exit(1);
        }
    }
    
    // 모든 테이블이 존재하는지 확인한다.
    
    public function check_tables($db)
    {
        $pass = true;
        $tables_query = $db->query("SHOW TABLES");
        $tables = $tables_query->fetchAll(PDO::FETCH_NUM);
        
        foreach ($this->_schema as $table_name => $columns)
        {
            $found = false;
            foreach ($tables as $table)
            {
                if ($table[0] === $table_name)
                {
                    $found = true;
                    break;
                }
            }
            if (!$found)
            {
                echo '[ERROR] ' . $table_name . ' 테이블이 없습니다.' . PHP_EOL;
                $pass = false;
            }
        }
        
        return $pass;
    }
    
    // 모든 인덱스가 존재하는지 확인한다.
    
    public function check_indexes($db)
    {
        $pass = true;
        
        foreach ($this->_schema as $table_name => $columns)
        {
            try
            {
                $table_indexes_query = $db->query("SHOW INDEX FROM $table_name");
                $table_indexes = $table_indexes_query->fetchAll(PDO::FETCH_NUM);
            }
            catch (PDOException $e)
            {
                echo '[ERROR] ' . $table_name . ' 테이블의 인덱스를 검사할 수 없습니다.' . PHP_EOL;
                $pass = false;
                continue;
            }
            
            $pk_found = false;
            foreach ($table_indexes as $table_index)
            {
                if ($table_index[2] === 'PRIMARY')
                {
                    $pk_found = true;
                }
            }
            if (!$pk_found)
            {
                echo '[ERROR] ' . $table_name . ' 테이블에 PRIMARY KEY가 없습니다.' . PHP_EOL;
                $pass = false;
            }
            
            $indexes = array();
            if (isset($columns['_initial'])) $indexes = array_merge($indexes, $columns['_initial']);
            if (isset($columns['_interim'])) $indexes = array_merge($indexes, $columns['_interim']);
            if (isset($columns['_indexes'])) $indexes = array_merge($indexes, $columns['_indexes']);
            
            foreach ($indexes as $index_name)
            {
                $found = false;
                foreach ($table_indexes as $table_index)
                {
                    if ($table_index[4] === $index_name)
                    {
                        $found = true;
                        break;
                    }
                }
                if (!$found)
                {
                    echo '[ERROR] ' . $table_name . ' 테이블에 ' . $index_name . ' 인덱스가 없습니다.' . PHP_EOL;
                    $pass = false;
                }
            }
        }
        
        return $pass;
    }
    
    // 데이터를 검사한다.
    
    public function check_data_count($db)
    {
        $pass = true;
        
        foreach ($this->_schema as $table_name => $columns)
        {
            if (!isset($columns['_count'])) continue;
            
            try
            {
                $count_query = $db->query("SELECT COUNT(*) FROM $table_name");
                $count = $count_query->fetchColumn();
                
                if ($count < $columns['_count'])
                {
                    echo '[ERROR] ' . $table_name . ' 테이블의 레코드 수가 부족합니다.' . PHP_EOL;
                    $pass = false;
                }
            }
            catch (PDOException $e)
            {
                echo '[ERROR] ' . $table_name . ' 테이블을 조회할 수 없습니다.' . PHP_EOL;
                $pass = false;
            }
        }
        
        return $pass;
    }
    
    // 우편번호 누락 레코드를 확인한다.
    
    public function check_missing_postcodes($db)
    {
        $pass = true;
        
        if ($this->_add_old_postcodes)
        {
            $pc6_query = $db->query("SELECT pa.*, pr.* FROM postcodify_addresses pa JOIN postcodify_roads pr ON pa.road_id = pr.road_id " .
                "WHERE (postcode6 IS NULL OR postcode6 = '000000') AND building_id IS NOT NULL ORDER BY pa.id LIMIT 100");
            if ($pc6_query->rowCount())
            {
                echo '[ERROR] 우편번호(기존번호)가 누락된 레코드가 있습니다.' . PHP_EOL;
                while ($entry = $pc6_query->fetch(PDO::FETCH_OBJ))
                {
                    echo '  #' . $entry->id . ' ' . $this->format_address($entry) . PHP_EOL;
                }
                $pass = false;
            }
        }
        
        $pc5_query = $db->query("SELECT pa.*, pr.* FROM postcodify_addresses pa JOIN postcodify_roads pr ON pa.road_id = pr.road_id " .
            "WHERE (postcode5 IS NULL OR postcode5 = '00000') AND building_id IS NOT NULL ORDER BY pa.id LIMIT 100");
        if ($pc5_query->rowCount())
        {
            echo '[ERROR] 우편번호(기초구역번호)가 누락된 레코드가 있습니다.' . PHP_EOL;
            while ($entry = $pc5_query->fetch(PDO::FETCH_OBJ))
            {
                echo '  #' . $entry->id . ' ' . $this->format_address($entry) . PHP_EOL;
            }
            $pass = false;
        }
        
        return $pass;
    }
    
    // 디버깅을 위해 주소를 포맷하는 메소드.
    
    public function format_address($entry)
    {
        if (preg_match('/사서함$/u', $entry->dongri_ko))
        {
            $result = $entry->sido_ko . ' ' . $entry->sigungu_ko . ' ' . $entry->ilbangu_ko . ' ' . $entry->eupmyeon_ko . ' ' .
                $entry->dongri_ko . ' ' . $entry->other_addresses;
        }
        else
        {
            $result = $entry->sido_ko . ' ' . $entry->sigungu_ko . ' ' . $entry->ilbangu_ko . ' ' . $entry->eupmyeon_ko . ' ' .
                $entry->road_name_ko . ' ' . $entry->num_major . ($entry->num_minor ? ('-' . $entry->num_minor) : '');
        }
        return preg_replace('/\s+/', ' ', $result);
    }
}
