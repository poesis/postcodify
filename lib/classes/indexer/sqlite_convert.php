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

class Postcodify_Indexer_SQLite_Convert
{
    // 스키마 저장소.
    
    protected $_tables = array();
    protected $_columns = array();
    protected $_indexes = array();
    
    // 엔트리 포인트.
    
    public function start($args)
    {
        // SQLite 파일명을 구한다.
        
        if (count($args->args))
        {
            $filename = $args->args[0];
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
        
        Postcodify_Utility::print_message('Postcodify SQLite Converter ' . POSTCODIFY_VERSION);
        Postcodify_Utility::print_newline();
        
        Postcodify_Utility::print_message('SQLite DB를 초기화하는 중...');
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
        Postcodify_Utility::print_ok();
        
        // 데이터를 복사한다.
        
        $this->copy_data($mysql, $sqlite);
        
        // 인덱스를 생성한다.
        
        $this->create_indexes($sqlite);
        
        // 인덱스를 최적화한다.
        
        Postcodify_Utility::print_message('인덱스 최적화 중...');
        $this->wrap_up($sqlite);
        Postcodify_Utility::print_ok();
    }
    
    // SQLite DB를 초기화한다.
    
    public function initialize_db($filename)
    {
        $sqlite = new PDO('sqlite:' . $filename);
        $sqlite->exec('PRAGMA page_size = 4096');
        $sqlite->exec('PRAGMA synchronous = OFF');
        $sqlite->exec('PRAGMA journal_mode = OFF');
        $sqlite->exec('PRAGMA encoding = "UTF-8"');
        
        $schema = (include POSTCODIFY_LIB_DIR . '/resources/schema.php');
        
        foreach ($schema as $table_name => $table_definition)
        {
            $columns = array();
            foreach ($table_definition as $column_name => $column_definition)
            {
                switch ($column_name)
                {
                    case '_initial':
                    case '_interim':
                    case '_indexes':
                        foreach ($column_definition as $column)
                        {
                            $this->_indexes[$table_name][] = $column;
                        }
                        break;
                    default:
                        if ($column_name[0] !== '_')
                        {
                            $column_definition = preg_replace('/(SMALL|TINY)INT\b/', 'INT', $column_definition);
                            $column_definition = str_replace('INT PRIMARY KEY AUTO_INCREMENT', 'INTEGER PRIMARY KEY', $column_definition);
                            $column_definition = str_replace(' UNSIGNED', '', $column_definition);
                            $column_definition = str_replace('NUMERIC', 'CHAR', $column_definition);
                            $columns[] = $column_name . ' ' . $column_definition;
                        }
                }
            }
            
            $table_query = 'CREATE TABLE ' . $table_name . ' (' . implode(', ', $columns) . ')';
            $this->_tables[$table_name] = $table_query;
            
            reset($table_definition);
            $first_column = key($table_definition);
            $this->_columns[$table_name] = array($first_column, count($columns));
        }
        
        foreach ($this->_tables as $table_query)
        {
            $sqlite->exec($table_query);
        }
        
        return $sqlite;
    }
    
    // 데이터를 복사한다.
    
    public function copy_data($mysql, $sqlite)
    {
        foreach ($this->_columns as $table_name => $table_info)
        {
            Postcodify_Utility::print_message($table_name . ' 데이터 복사 중...');
            
            $row_count_query = $mysql->query('SELECT COUNT(*) FROM ' . $table_name);
            $row_count = intval($row_count_query->fetchColumn());
            
            $columns_placeholder = implode(', ', array_fill(0, $table_info[1], '?'));
            $primary_key = $table_info[0];
            $last_primary_key = 0;
            $increment = 2048;
            
            $ps = $sqlite->prepare('INSERT INTO ' . $table_name . ' VALUES (' . $columns_placeholder . ')');
            
            for ($i = 0; $i < $row_count; $i += $increment)
            {
                Postcodify_Utility::print_progress($i, $row_count);
                
                $sqlite->beginTransaction();
                
                if ($table_name === 'postcodify_settings')
                {
                    $cond = ' ORDER BY ' . $primary_key;
                }
                else
                {
                    $cond = ' WHERE ' . $primary_key . ' > ? ORDER BY ' . $primary_key . ' LIMIT ' . $increment;
                }
                
                $query = $mysql->prepare('SELECT * FROM ' . $table_name . $cond);
                $query->bindParam(1, $last_primary_key, PDO::PARAM_INT);
                $query->execute();
                
                while ($row = $query->fetch(PDO::FETCH_NUM))
                {
                    $last_primary_key = $row[0];
                    $ps->execute($row);
                }
                
                $sqlite->commit();
            }
            
            Postcodify_Utility::print_ok($row_count);
        }
    }
    
    // 인덱스를 생성한다.
    
    public function create_indexes($sqlite)
    {
        foreach ($this->_indexes as $table_name => $columns)
        {
            Postcodify_Utility::print_message($table_name . ' 인덱스 생성 중...');
            
            $count = 0;
            foreach ($columns as $column)
            {
                Postcodify_Utility::print_progress($count++, count($columns));
                $sqlite->exec('CREATE INDEX ' . $table_name . '_' . $column . ' ON ' . $table_name . ' (' . $column . ')');
            }
            
            Postcodify_Utility::print_ok(count($columns));
        }
    }
    
    // SQLite DB를 최적화한다.
    
    public function wrap_up($sqlite)
    {
        $sqlite->exec('ANALYZE');
    }
}
