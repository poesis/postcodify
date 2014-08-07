<?php

/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (서버측 API)
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

class Postcodify_MySQL
{
    // MySQL 커넥션을 저장하는 변수.
    
    protected static $dbh = null;
    protected static $dbh_extension;
    
    // 쿼리를 실행하고 결과를 반환하는 메소드.
    
    public static function query($db_config, $proc_name, array $params)
    {
        // 최초 호출시 DB에 연결한다.
        
        if (self::$dbh === null || self::$dbh_extension === null)
        {
            // PDO 모듈 사용 (기본값, 권장).
            
            if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers()))
            {
                self::$dbh_extension = 'pdo';
                self::$dbh = new PDO('mysql:host=' . $db_config['host'] . ';port=' . $db_config['port'] . ';dbname=' . $db_config['dbname'] . ';charset=utf8',
                    $db_config['user'], $db_config['pass'], array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                    )
                );
            }
            
            // MySQLi 모듈 사용 (차선책).
            
            elseif (class_exists('mysqli'))
            {
                self::$dbh_extension = 'mysqli';
                self::$dbh = @mysqli_connect($db_config['host'], $db_config['user'], $db_config['pass'], $db_config['dbname'], $db_config['port']);
                if (self::$dbh->connect_error) throw new Exception(self::$dbh->connect_error);
                $charset = @self::$dbh->set_charset('utf8');
                if (!$charset) throw new Exception(self::$dbh->error);
                $driver = new MySQLi_Driver;
                $driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;
            }
            
            // MySQL 모듈 사용 (최후의 수단).
            
            elseif (function_exists('mysql_connect'))
            {
                self::$dbh_extension = 'mysql';
                self::$dbh = @mysql_connect($db_config['host'] . ':' . $db_config['port'], $db_config['user'], $db_config['pass']);
                if (!self::$dbh) throw new Exception(mysql_error(self::$dbh));
                $seldb = @mysql_select_db($db_config['dbname'], self::$dbh);
                if (!$seldb) throw new Exception(mysql_error(self::$dbh));
                $charset = function_exists('mysql_set_charset') ? @mysql_set_charset('utf8', self::$dbh) : @mysql_query('SET NAMES utf8', self::$dbh);
                if (!$charset) throw new Exception(mysql_error(self::$dbh));
            }
            
            // 아무 것도 사용할 수 없는 경우 예외를 던진다.
            
            else
            {
                throw new Exception('Database driver not supported: mysql (No usable extension found)');
            }
        }
        
        // 프로시저를 실행한다.
        // 동리 + 지번 검색처럼 곧이어 다른 검색을 할 경우에 대비하여 쿼리 결과를 깨끗하게 닫아 주어야 한다.
        
        switch (self::$dbh_extension)
        {
            // PDO 모듈 사용 (기본값, 권장).
            
            case 'pdo':
                $placeholders = implode(', ', array_fill(0, count($params), '?'));
                $ps = self::$dbh->prepare('CALL ' . $proc_name . '(' . $placeholders . ')');
                $ps->execute($params);
                $result = $ps->fetchAll(PDO::FETCH_OBJ);
                $ps->nextRowset();
                return $result;
                
            // MySQLi 모듈 사용 (차선책).
            
            case 'mysqli':
                $escaped_params = array();
                foreach ($params as $param)
                {
                   $escaped_params[] = $param === null ? 'null' : ("'" . self::$dbh->real_escape_string($param) . "'");
                }
                $escaped_params = implode(', ', $escaped_params);
                $query = self::$dbh->query('CALL ' . $proc_name . '(' . $escaped_params . ')');
                $result = array();
                while ($row = $query->fetch_object())
                {
                    $result[] = $row;
                }
                self::$dbh->next_result();
                return $result;
                
            // MySQL 모듈 사용 (최후의 수단).
            
            case 'mysql':
                $escaped_params = array();
                foreach ($params as $param)
                {
                   $escaped_params[] = $param === null ? 'null' : ("'" . mysql_real_escape_string($param, self::$dbh) . "'");
                }
                $escaped_params = implode(', ', $escaped_params);
                $query = @mysql_query('CALL ' . $proc_name . '(' . $escaped_params . ')', self::$dbh);
                if (!$query) throw new Exception(mysql_error(self::$dbh));
                $result = array();
                while ($row = mysql_fetch_object($query))
                {
                    $result[] = $row;
                }
                if ($proc_name === 'postcodify_search_jibeon' && !count($result))
                {
                    mysql_close(self::$dbh); self::$dbh = null;
                }
                return $result;
                
            // 아무 것도 사용할 수 없는 경우 빈 결과를 반환한다.
            
            default:
                return array();
        }
    }
}
