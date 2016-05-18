<?php

/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (서버측 API)
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

class Postcodify_Server_Database
{
    // DB 핸들과 드라이버명.
    
    protected $_driver;
    protected $_dbh;
    
    // 생성자.
    
    public function __construct($driver, $host, $port, $user, $pass, $dbname)
    {
        // SQLite 사용시.
        
        if (strtolower($driver) === 'sqlite')
        {
            // PDO 모듈 사용 (기본값).
            
            if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers()))
            {
                $this->_driver = 'pdo_sqlite';
                $this->_dbh = new PDO('sqlite:' . $dbname);
                $this->_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->_dbh->exec('PRAGMA query_only = 1');
                $this->_dbh->exec('PRAGMA case_sensitive_like = 0');
            }
            
            // PDO 모듈을 사용할 수 없는 경우 예외를 던진다.
            
            else
            {
                throw new Exception('Database driver not supported: sqlite (No usable extension found)');
            }
        }
        
        // MySQL 사용시.
        
        else
        {
            // PDO 모듈 사용 (기본값).
            
            if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers()))
            {
                $this->_driver = 'pdo_mysql';
                $this->_dbh = new PDO('mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname . ';charset=utf8',
                    $user, $pass, array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                    )
                );
            }
            
            // MySQLi 모듈 사용 (차선책).
            
            elseif (class_exists('mysqli'))
            {
                $this->_driver = 'mysqli';
                $this->_dbh = @mysqli_connect($host, $user, $pass, $dbname, $port);
                if ($this->_dbh->connect_error) throw new Exception($this->_dbh->connect_error);
                $charset = @$this->_dbh->set_charset('utf8');
                if (!$charset) throw new Exception($this->_dbh->error);
                $driver = new MySQLi_Driver;
                $driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;
            }
            
            // MySQL 모듈 사용 (최후의 수단).
            
            elseif (function_exists('mysql_connect'))
            {
                $this->_driver = 'mysql';
                $this->_dbh = @mysql_connect($host . ':' . $port, $user, $pass);
                if (!$this->_dbh) throw new Exception(mysql_error($this->_dbh));
                $seldb = @mysql_select_db($dbname, $this->_dbh);
                if (!$seldb) throw new Exception(mysql_error($this->_dbh));
                $charset = function_exists('mysql_set_charset') ? @mysql_set_charset('utf8', $this->_dbh) : @mysql_query('SET NAMES utf8', $this->_dbh);
                if (!$charset) throw new Exception(mysql_error($this->_dbh));
            }
            
            // 아무 것도 사용할 수 없는 경우 예외를 던진다.
            
            else
            {
                throw new Exception('Database driver not supported: mysql (No usable extension found)');
            }
        }
    }
    
    // 쿼리 메소드.
    
    public function query($querystring, $joins, $conds, $args, $lang = 'KO', $sort = 'JUSO', $limit = 100, $offset = 0)
    {
        // 쿼리를 조합한다.
        
        $querystring = $querystring . ' ' . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $conds);
        
        switch ($lang . $sort)
        {
            case 'KOJUSO':
                $order_by = 'sido_ko, sigungu_ko, ilbangu_ko, eupmyeon_ko, road_name_ko, num_major, num_minor'; break;
            case 'KOJIBEON': case 'KOPOBOX':
                $order_by = 'sido_ko, sigungu_ko, ilbangu_ko, eupmyeon_ko, dongri_ko, jibeon_major, jibeon_minor'; break;
            case 'ENJUSO':
                $order_by = 'sido_en, sigungu_en, ilbangu_en, eupmyeon_en, road_name_en, num_major, num_minor'; break;
            case 'ENJIBEON': case 'ENPOBOX':
                $order_by = 'sido_en, sigungu_en, ilbangu_en, eupmyeon_en, dongri_en, jibeon_major, jibeon_minor'; break;
            default:
                $order_by = 'sido_ko, sigungu_ko, ilbangu_ko, eupmyeon_ko, road_name_ko, num_major, num_minor'; break;
        }
        
        $querystring .= ' ORDER BY ' . $order_by . ' LIMIT ' . intval($limit) . ' OFFSET ' . intval($offset);
        
        // 쿼리를 실행한다.
        
        switch ($this->_driver)
        {
            case 'pdo_sqlite':
                return $this->query_pdo_sqlite($querystring, $args);
            case 'pdo_mysql':
                return $this->query_pdo_mysql($querystring, $args);
            case 'mysqli':
                return $this->query_mysqli($querystring, $args);
            case 'mysql':
                return $this->query_mysql($querystring, $args);
            default:
                return array();
        }
    }
    
    // 쿼리 메소드 (PDO/SQLite).
    
    protected function query_pdo_sqlite($querystring, $args)
    {
        $ps = $this->_dbh->prepare($querystring);
        $ps->execute($args);
        return $ps->fetchAll(PDO::FETCH_OBJ);
    }
    
    // 쿼리 메소드 (PDO/MySQL).
    
    protected function query_pdo_mysql($querystring, $args)
    {
        $ps = $this->_dbh->prepare($querystring);
        $ps->execute($args);
        return $ps->fetchAll(PDO::FETCH_OBJ);
    }
    
    // 쿼리 메소드 (MySQLi).
    
    protected function query_mysqli($querystring, $args)
    {
        $querystring = explode('?', $querystring);
        foreach ($querystring as $key => $part)
        {
            if (isset($args[$key]))
            {
                if ($args[$key] === null)
                {
                    $querystring[$key] .= 'null';
                }
                elseif (is_numeric($args[$key]))
                {
                    $querystring[$key] .= $args[$key];
                }
                else
                {
                    $querystring[$key] .= "'" . $this->_dbh->real_escape_string($args[$key]) . "'";
                }
            }
        }
        $query = $this->_dbh->query(implode('', $querystring));
        $result = array();
        while ($row = $query->fetch_object())
        {
            $result[] = $row;
        }
        return $result;
    }
    
    // 쿼리 메소드 (MySQL).
    
    protected function query_mysql($querystring, $args)
    {
        $querystring = explode('?', $querystring);
        foreach ($querystring as $key => $part)
        {
            if (isset($args[$key]))
            {
                if ($args[$key] === null)
                {
                    $querystring[$key] .= 'null';
                }
                elseif (is_numeric($args[$key]))
                {
                    $querystring[$key] .= $args[$key];
                }
                else
                {
                    $querystring[$key] .= "'" . mysql_real_escape_string($args[$key], $this->_dbh) . "'";
                }
            }
        }
        $query = @mysql_query(implode('', $querystring), $this->_dbh);
        if (!$query) throw new Exception(mysql_error($this->_dbh));
        $result = array();
        while ($row = mysql_fetch_object($query))
        {
            $result[] = $row;
        }
        return $result;
    }
}
