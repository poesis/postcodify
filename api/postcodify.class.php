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

class Postcodify
{
    // 버전 상수.
    
    const VERSION = '1.7.1';
    
    // DB 커넥션 핸들을 저장하는 변수들.
    
    protected static $dbh;
    protected static $dbh_extension;
    
    // DB 설정을 저장하는 변수들.
    
    protected static $db_host;
    protected static $db_port;
    protected static $db_user;
    protected static $db_pass;
    protected static $db_dbname;
    protected static $db_driver;
    
    // DB 설정을 전달하는 메소드. search() 메소드 호출 전에 반드시 먼저 호출해야 한다.
    // SQLite 사용시에는 $dbname에 파일명을 입력해 주도록 한다.
    
    public static function dbconfig($host, $port, $user, $pass, $dbname, $driver = 'mysql')
    {
        self::$db_host = $host;
        self::$db_port = $port;
        self::$db_user = $user;
        self::$db_pass = $pass;
        self::$db_dbname = $dbname;
        self::$db_driver = $driver;
    }
    
    // 실제 검색을 수행하는 메소드. Postcodify_Result 객체를 반환한다.
    // 인코딩의 경우 EUC-KR을 사용하려면 CP949라고 입력해 주어야 한다.
    // 새주소 중 EUC-KR에서 지원되지 않는 문자가 포함된 것도 있기 때문이다.
    
    public static function search($kw, $encoding = 'UTF-8')
    {
        // 검색 시작 시각을 기록한다.
        
        $start_time = microtime(true);
        
        // 검색 키워드의 유효성을 확인한다.
        
        if ($kw === '')
        {
            return new Postcodify_Result('Keyword Not Supplied');
        }
        if (!mb_check_encoding($kw, $encoding))
        {
            return new Postcodify_Result('Keyword is Not Valid ' . $encoding);
        }
        if ($encoding !== 'UTF-8')
        {
            $kw = mb_convert_encoding($kw, 'UTF-8', $encoding);
        }
        if (($len = mb_strlen($kw, 'UTF-8')) < 3 || $len > 80)
        {
            return new Postcodify_Result('Keyword is Too Long or Too Short');
        }
        
        $kw = self::parse_keywords($kw);
        
        // DB에 연결하여 검색 쿼리를 실행한다.
        
        try
        {
            // 시도, 시군구, 일반구, 읍면 등으로 검색 결과를 제한하는 경우 추가 파라미터 목록을 작성한다.
            
            $extra_params = $kw->use_area ? array($kw->sido, $kw->sigungu, $kw->ilbangu, $kw->eupmyeon) : array();
            
            // 도로명주소로 검색하는 경우...
            
            if ($kw->road !== null)
            {
                $rows = self::call_db_procedure('postcode_search_juso',
                    array(self::crc32_x64($kw->road), $kw->numbers[0], $kw->numbers[1]), $extra_params);
                
                // 검색 결과가 없다면 도로번호를 건물번호로 잘못 해석했을 수도 있으므로 조합을 바꾸어 다시 시도해 본다.
                
                if (!count($rows) && $kw->numbers[1] === null)
                {
                    $possible_road_name = self::crc32_x64($kw->road . $kw->numbers[0] . '번길');
                    $rows = self::call_db_procedure('postcode_search_juso',
                        array($possible_road_name, $kw->extra_numbers[0], $kw->extra_numbers[1]), $extra_params);
                }
            }
            
            // 동리 + 지번으로 검색하는 경우...
            
            elseif ($kw->dongri !== null && $kw->building === null)
            {
                $rows = self::call_db_procedure('postcode_search_jibeon',
                    array(self::crc32_x64($kw->dongri), $kw->numbers[0], $kw->numbers[1]), $extra_params);
                
                // 검색 결과가 없다면 건물명을 동리로 잘못 해석했을 수도 있으므로 건물명 검색을 다시 시도해 본다.
                
                if ($kw->numbers[0] === null && $kw->numbers[1] === null && !count($rows))
                {
                    $rows = self::call_db_procedure('postcode_search_building', array($kw->dongri), $extra_params);
                }
            }
            
            // 건물명만으로 검색하는 경우...
            
            elseif ($kw->building !== null && $kw->dongri === null)
            {
                $rows = self::call_db_procedure('postcode_search_building',
                    array($kw->building), $extra_params);
            }
            
            // 동리 + 건물명으로 검색하는 경우...
            
            elseif ($kw->building !== null && $kw->dongri !== null)
            {
                $rows = self::call_db_procedure('postcode_search_building_with_dongri',
                    array($kw->building, self::crc32_x64($kw->dongri)), $extra_params);
            }
            
            // 사서함으로 검색하는 경우...
            
            elseif ($kw->pobox !== null)
            {
                $rows = self::call_db_procedure('postcode_search_pobox',
                    array($kw->pobox, $kw->numbers[0], $kw->numbers[1]), $extra_params);
            }
            
            // 그 밖의 경우 검색 결과가 없는 것으로 한다.
            
            else
            {
                return new Postcodify_Result('');
            }
        }
        catch (Exception $e)
        {
            error_log('Postcodify ("' . $kw . '"): ' . $e->getMessage());
            return new Postcodify_Result('Database Error');
        }
        
        // 검색 결과 오브젝트를 생성한다.
        
        $result = new Postcodify_Result;
        
        foreach ($rows as $row)
        {
            // 도로명 및 지번주소를 정리한다.
            
            $address_base = trim($row->sido . ' ' . ($row->sigungu ? ($row->sigungu . ' ') : '') .
                ($row->ilbangu ? ($row->ilbangu . ' ') : '') . ($row->eupmyeon ? ($row->eupmyeon . ' ') : ''));
                
            $address_road = $address_base . ' ' . $row->road_name . ' ' . ($row->is_basement ? '지하 ' : '') . ($row->num_major ? $row->num_major : '') . ($row->num_minor ? ('-' . $row->num_minor) : '');
            $address_old = $address_base . ' ' . $row->dongri . ($row->jibeon ? (' ' . $row->jibeon) : '');
            
            // 추가정보를 정리한다.
            
            $address_extra_long = '';
            $address_extra_short = '';
            if (strval($row->dongri) !== '')
            {
                $address_extra_long = $row->dongri . ($row->jibeon ? (' ' . $row->jibeon) : '');
                $address_extra_short = $row->dongri;
            }
            if (strval($row->building_name) !== '')
            {
                $address_extra_long .= ', ' . $row->building_name;
                $address_extra_short .= ', ' . $row->building_name;
            }
            
            // 결과 오브젝트에 추가한다.
            
            $record = new Postcodify_Result_Record;
            $record->dbid = substr($row->id, 0, 10) === '9999999999' ? '' : $row->id;
            $record->code6 = substr($row->postcode6, 0, 3) . '-' . substr($row->postcode6, 3, 3);
            $record->code5 = strval($row->postcode5);
            $record->address = strval($address_road);
            $record->canonical = strval($row->dongri . ($row->jibeon ? (' ' . $row->jibeon) : ''));
            $record->extra_info_long = strval($address_extra_long);
            $record->extra_info_short = strval($address_extra_short);
            $record->english_address = isset($row->english_address) ? strval($row->english_address) : '';
            $record->jibeon_address = strval($address_old);
            $record->other = strval($row->other_addresses);
            if ($encoding !== 'UTF-8')
            {
                $properties = get_object_vars($record);
                foreach ($properties as $key => $value)
                {
                    $record->$key = mb_convert_encoding($value, $encoding, 'UTF-8');
                }
            }
            $result->results[] = $record;
            $result->count++;
        }
        
        // 검색 소요 시간을 기록한다.
        
        $result->time = number_format(microtime(true) - $start_time, 3);
        
        // 결과를 반환한다.
        
        return $result;
    }
    
    // DB에 저장된 프로시저를 실행하고 결과를 반환하는 메소드.
    
    protected static function call_db_procedure($proc_name, array $params, array $extra_params)
    {
        // 파라미터 목록을 정리한다.
        
        if (count($extra_params))
        {
            $proc_name .= '_in_area';
            foreach ($extra_params as $param)
            {
                $params[] = $param;
            }
        }
        
        // SQLite인 경우 별도의 클래스로 쿼리를 전달한다.
        
        if (self::$db_driver === 'sqlite')
        {
            require_once dirname(__FILE__) . '/postcodify.sqlite.php';
            return Postcodify_SQLite::query(self::$db_dbname, $proc_name, $params);
        }
        
        // MySQL인 경우 최초 호출시 DB에 연결한다.
        
        if (self::$dbh === null || self::$dbh_extension === null)
        {
            // PDO 모듈 사용 (기본값, 권장).
            
            if (class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers()))
            {
                self::$dbh_extension = 'pdo';
                self::$dbh = new PDO('mysql:host=' . self::$db_host . ';port=' . self::$db_port . ';dbname=' . self::$db_dbname . ';charset=utf8',
                    self::$db_user, self::$db_pass, array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                    )
                );
            }
            
            // MySQLi 모듈 사용 (차선책).
            
            elseif (class_exists('mysqli'))
            {
                self::$dbh_extension = 'mysqli';
                self::$dbh = @mysqli_connect(self::$db_host, self::$db_user, self::$db_pass, self::$db_dbname, self::$db_port);
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
                self::$dbh = @mysql_connect(self::$db_host . ':' . self::$db_port, self::$db_user, self::$db_pass);
                if (!self::$dbh) throw new Exception(mysql_error(self::$dbh));
                $seldb = @mysql_select_db(self::$db_dbname, self::$dbh);
                if (!$seldb) throw new Exception(mysql_error(self::$dbh));
                $charset = function_exists('mysql_set_charset') ? @mysql_set_charset('utf8', self::$dbh) : @mysql_query('SET NAMES utf8', self::$dbh);
                if (!$charset) throw new Exception(mysql_error(self::$dbh));
            }
            
            // 아무 것도 사용할 수 없는 경우 빈 결과를 반환한다.
            
            else
            {
                return array();
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
                if ($proc_name === 'postcode_search_jibeon' && !count($result))
                {
                    mysql_close(self::$dbh); self::$dbh = null;
                }
                return $result;
                
            // 아무 것도 사용할 수 없는 경우 빈 결과를 반환한다.
            
            default: return array();
        }
    }
    
    // 검색어를 분해, 분석하여 키워드 목록을 생성하는 메소드.
    
    protected static function parse_keywords($str)
    {
        // 지번을 00번지 0호로 쓴 경우 검색 가능한 형태로 변환한다.
        
        $str = preg_replace('/([0-9]+)번지\\s?([0-9]+)호(?:\\s|$)/u', '$1-$2', $str);
        
        // 행정동, 도로명 등의 숫자 앞에 공백에 있는 경우 붙여쓴다.
        
        $str = preg_replace('/\s+([동서남북]?[0-9]+번?[가나다라마바사아자차카타파하동서남북안]?[로길동리가])(?=\s|\d|$)/u', '$1', $str);
        
        // 키워드 목록 객체를 초기화한다.
        
        $kw = new Postcodify_Keywords;
        
        // 검색어를 단어별로 분리한다.
        
        $str = str_replace(array(',', '(', ')'), ' ', strtolower($str));
        $str = preg_split('/\\s+/u', preg_replace('/[^\\sㄱ-ㅎ가-힣a-z0-9-]/u', '', $str));
        
        // 대한민국 행정구역 목록 파일을 로딩한다.
        
        require_once dirname(__FILE__) . '/postcodify.areas.php';
        
        // 각 단어의 의미를 파악한다.
        
        foreach ($str as $id => $keyword)
        {
            // 키워드가 "산", "지하", 한글 1글자인 경우 건너뛴다.
            
            if (!ctype_alnum($keyword) && mb_strlen($keyword, 'UTF-8') < 2) continue;
            if ($keyword === '지하') continue;
            
            // 첫 번째 구성요소가 시도인지 확인한다.
            
            if ($id == 0 && count($str) > 1)
            {
                if (isset(Postcodify_Areas::$sido[$keyword]))
                {
                    $kw->sido = Postcodify_Areas::$sido[$keyword];
                    $kw->use_area = true;
                    continue;
                }
            }
            
            // 시군구읍면을 확인한다.
            
            if (preg_match('/.*([시군구읍면])$/u', $keyword, $matches))
            {
                if ($matches[1] === '읍' || $matches[1] === '면')
                {
                    if (!$kw->sigungu && preg_match('/^(.+)군([읍면])$/u', $keyword, $gun) && in_array($gun[1] . '군', Postcodify_Areas::$sigungu))
                    {
                        $kw->sigungu = $gun[1] . '군';
                        $kw->eupmyeon = $gun[1] . $gun[2];
                    }
                    elseif ($kw->sigungu && ($keyword === '읍' || $keyword === '면'))
                    {
                        $kw->eupmyeon = preg_replace('/군$/u', $keyword, $kw->sigungu);
                    }
                    else
                    {
                        $kw->eupmyeon = $keyword;
                    }
                    $kw->use_area = true;
                    continue;
                }
                elseif (isset($kw->sigungu) && in_array($keyword, Postcodify_Areas::$ilbangu[$kw->sigungu]))
                {
                    $kw->ilbangu = $keyword;
                    $kw->use_area = true;
                    continue;
                }
                elseif (in_array($keyword, Postcodify_Areas::$sigungu))
                {
                    $kw->sigungu = $keyword;
                    $kw->use_area = true;
                    continue;
                }
                else
                {
                    if (count($str) > $id + 1) continue;
                }
            }
            elseif (in_array($keyword . '시', Postcodify_Areas::$sigungu))
            {
                $kw->sigungu = $keyword . '시';
                $kw->use_area = true;
                continue;
            }
            elseif (in_array($keyword . '군', Postcodify_Areas::$sigungu))
            {
                $kw->sigungu = $keyword . '군';
                $kw->use_area = true;
                continue;
            }
            
            // 도로명+건물번호를 확인한다.
            
            if (preg_match('/^(.+[로길])((?:지하)?([0-9]+(?:-[0-9]+)?)(?:번지?)?)?$/u', $keyword, $matches))
            {
                $kw->road = $matches[1];
                if (isset($matches[3]) && $matches[3])
                {
                    $kw->numbers = $matches[3];
                    $kw->extra_numbers = true;
                    break;
                }
                continue;
            }
            
            // 동리+지번을 확인한다.
            
            if (preg_match('/^(.{1,5}(?:[0-9]가|[동리]))(산?([0-9]+(?:-[0-9]+)?)(?:번지?)?)?$/u', $keyword, $matches))
            {
                $kw->dongri = $matches[1];
                if (isset($matches[3]) && $matches[3])
                {
                    $kw->numbers = $matches[3];
                    break;
                }
                continue;
            }
            
            // 사서함을 확인한다.
            
            if (preg_match('/^(.*사서함)(([0-9]+(?:-[0-9]+)?)번?)?$/u', $keyword, $matches))
            {
                $kw->pobox = $matches[1];
                if (isset($matches[3]) && $matches[3])
                {
                    $kw->numbers = $matches[3];
                    break;
                }
                continue;
            }
            
            // 건물번호, 지번, 사서함 번호를 따로 적은 경우를 확인한다.
            
            if (preg_match('/^(?:산|지하)?([0-9]+(?:-[0-9]+)?)(?:번지?)?$/u', $keyword, $matches))
            {
                $kw->numbers = $matches[1];
                break;
            }
            
            // 그 밖의 키워드는 건물명으로 취급한다.
            
            $kw->building = $keyword;
            break;
        }
        
        // 건물번호 또는 지번을 주번과 부번으로 분리한다.
        
        if (isset($kw->numbers))
        {
            $kw->numbers = explode('-', $kw->numbers);
            if (!isset($kw->numbers[1])) $kw->numbers[1] = null;
        }
        else
        {
            $kw->numbers = array(null, null);
        }
        
        // 혹시 도로명+건물번호 외에 번호가 또 있는지 확인한다. (도로명에 숫자가 포함되어 있어 혼동의 우려가 있는 경우)
        
        if ($kw->extra_numbers & $kw->numbers[0] !== null && $kw->numbers[1] === null && isset($str[$id + 1]))
        {
            if (preg_match('/^(?:산|지하)?([0-9]+(?:-[0-9]+)?)(?:번지?)?$/u', $str[$id + 1], $matches))
            {
                $kw->extra_numbers = explode('-', $matches[1]);
                if (!isset($kw->extra_numbers[1])) $kw->extra_numbers[1] = null;
            }
            else
            {
                $kw->extra_numbers = array(null, null);
            }
        }
        else
        {
            $kw->extra_numbers = array(null, null);
        }
        
        // 분해 결과를 반환한다.
        
        return $kw;
    }
    
    // 항상 64비트식으로 (음수가 나오지 않도록) CRC32를 계산하는 메소드.
    
    public static function crc32_x64($str)
    {
        $crc32 = crc32($str);
        return ($crc32 >= 0) ? $crc32 : ($crc32 + 0x100000000);
    }
}

// Postcodify 검색 키워드 클래스.

class Postcodify_Keywords
{
    public function __toString()
    {
        $result = array();
        if ($this->sido !== null) $result[] = $this->sido;
        if ($this->sigungu !== null) $result[] = $this->sigungu;
        if ($this->ilbangu !== null) $result[] = $this->ilbangu;
        if ($this->eupmyeon !== null) $result[] = $this->eupmyeon;
        if ($this->dongri !== null) $result[] = $this->dongri;
        if ($this->road !== null) $result[] = $this->road;
        if ($this->building !== null) $result[] = $this->building;
        if ($this->pobox !== null) $result[] = $this->pobox;
        if (isset($this->numbers[0])) $result[] = $this->numbers[0] .
            (isset($this->numbers[1]) ? ('-' . $this->numbers[1]) : '');
        return implode(' ', $result);
    }
    
    public $sido;
    public $sigungu;
    public $ilbangu;
    public $eupmyeon;
    public $dongri;
    public $road;
    public $building;
    public $pobox;
    public $numbers;
    public $extra_numbers;
    public $extra_keywords;
    public $use_area = false;
}

// Postcodify 검색 결과 (전체) 클래스.

class Postcodify_Result
{
    public function __construct($error = '')
    {
        $this->version = Postcodify::VERSION;
        $this->error = $error;
    }
    
    public $version = '';
    public $error = '';
    public $count = 0;
    public $time = 0;
    public $results = array();
}

// Postcodify 검색 결과 (각 레코드) 클래스.

class Postcodify_Result_Record
{
    public $dbid;
    public $code6;
    public $code5;
    public $address;
    public $canonical;
    public $extra_info_long;
    public $extra_info_short;
    public $english_address;
    public $jibeon_address;
    public $other;
}
