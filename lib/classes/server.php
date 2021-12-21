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

class Postcodify_Server
{
    // DB 설정.
    
    public $db_driver = 'mysql';
    public $db_host = 'localhost';
    public $db_port = 3306;
    public $db_user = '';
    public $db_pass = '';
    public $db_dbname = '';
    protected $_dbh;
    
    // Memcached 설정.
    
    public $cache_driver = '';
    public $cache_host = 'localhost';
    public $cache_port = 11211;
    public $cache_ttl = 86400;
    protected $_ch;
    
    // 검색을 수행하는 메소드. Postcodify_Server_Result 객체를 반환한다.
    // 인코딩의 경우 EUC-KR을 사용하려면 CP949라고 입력해 주어야 한다.
    // 새주소 중 EUC-KR에서 지원되지 않는 문자가 포함된 것도 있기 때문이다.
    
    public function search($keywords, $encoding = 'UTF-8', $version = null)
    {
        // 버전을 확인한다.
        
        if ($version === null) $version = POSTCODIFY_VERSION;
        
        // 검색 시작 시각을 기록한다.
        
        $start_time = microtime(true);
        
        // 검색 키워드의 유효성을 확인한다.
        
        if (($keywords = trim($keywords)) === '')
        {
            return new Postcodify_Server_Result('Keyword Not Supplied');
        }
        if (!mb_check_encoding($keywords, $encoding))
        {
            return new Postcodify_Server_Result('Keyword is Not Valid ' . $encoding);
        }
        if ($encoding !== 'UTF-8')
        {
            $keywords = mb_convert_encoding($keywords, 'UTF-8', $encoding);
        }
        if (($len = mb_strlen($keywords, 'UTF-8')) < 3 || $len > 80)
        {
            return new Postcodify_Server_Result('Keyword is Too Long or Too Short');
        }
        
        // 검색 키워드를 분석하여 쿼리 객체를 생성한다.
        
        $q = Postcodify_Server_Query::parse_keywords($keywords);
        
        // 캐시에 데이터가 있는지 확인한다.
        
        if ($this->cache_driver && !$q->numbers[0])
        {
            if ($this->_ch === null)
            {
                $this->_ch = new Postcodify_Server_Cache($this->cache_driver, $this->cache_host, $this->cache_port, $this->cache_ttl);
            }
            $cache_key = sha1(strval($q));
            $addresses = null;
        }
        else
        {
            $cache_key = null;
            $addresses = null;
        }
        
        // 캐시에 데이터가 있는지 확인한다.
        
        if ($cache_key !== null)
        {
            $data_source = 'cache';
            list($addresses, $search_type, $search_error) = $this->_ch->get($cache_key);
        }
        
        // 캐시에서 찾지 못한 경우 DB에서 검색 쿼리를 실행한다.
        
        if ($addresses === null)
        {
            $data_source = 'db';
            list($addresses, $search_type, $search_error) = $this->get_addresses($q);
        }
        
        // 오류가 발생한 경우 처리를 중단한다.
        
        if ($search_error !== null)
        {
            return new Postcodify_Server_Result('Database Error');
        }
        
        // 검색 결과를 캐시에 저장한다.
        
        if ($cache_key !== null && $data_source === 'db')
        {
            $this->_ch->set($cache_key, $addresses, $search_type);
        }
        
        // 검색 결과 오브젝트를 생성한다.
        
        $result = new Postcodify_Server_Result;
        
        // 검색 언어, 정렬 방식 등을 기록한다.
        
        $result->lang = $q->lang;
        $result->sort = $q->sort;
        $result->nums = $q->numbers[0] . ($q->numbers[1] ? ('-' . $q->numbers[1]) : '');
        $result->type = $search_type;
        $result->cache = $data_source === 'cache' ? 'HIT' : 'MISS';
        
        // 각 레코드를 추가한다.
        
        foreach ($addresses as $row)
        {
            // 한글 도로명 및 지번주소를 정리한다.
            
            $ko_common = trim($row->sido_ko . ' ' . ($row->sigungu_ko ? ($row->sigungu_ko . ' ') : '') .
                ($row->ilbangu_ko ? ($row->ilbangu_ko . ' ') : '') . ($row->eupmyeon_ko ? ($row->eupmyeon_ko . ' ') : ''));
            $ko_doro = trim($row->road_name_ko . ' ' . ($row->is_basement ? '지하 ' : '') .
                ($row->num_major ? $row->num_major : '') . ($row->num_minor ? ('-' . $row->num_minor) : ''));
            $ko_jibeon = trim($row->dongri_ko . ' ' . ($row->is_mountain ? '산' : '') .
                ($row->jibeon_major ? $row->jibeon_major : '') . ($row->jibeon_minor ? ('-' . $row->jibeon_minor) : ''));
            
            // 영문 도로명 및 지번주소를 정리한다.
            
            $en_common = trim(($row->eupmyeon_en ? ($row->eupmyeon_en . ', ') : '') .
                ($row->ilbangu_en ? ($row->ilbangu_en . ', ') : '') . ($row->sigungu_en ? ($row->sigungu_en . ', ') : '') .
                $row->sido_en);
            $en_doro = trim(($row->is_basement ? 'Jiha ' : '') .
                ($row->num_major ? $row->num_major : '') . ($row->num_minor ? ('-' . $row->num_minor) : '') .
                ', ' . $row->road_name_en);
            $en_jibeon = trim(($row->is_mountain ? 'San ' : '') .
                ($row->jibeon_major ? $row->jibeon_major : '') . ($row->jibeon_minor ? ('-' . $row->jibeon_minor) : '') .
                ', ' . $row->dongri_en);
            
            // 추가정보를 정리한다.
            
            if ($result->sort === 'POBOX')
            {
                $ko_doro = $ko_jibeon = $row->dongri_ko . ' ' . $row->other_addresses;
                $en_doro = $en_jibeon = $row->dongri_en . ' ' . $row->other_addresses;
                $extra_long = $extra_short = $other_addresses = '';
            }
            else
            {
                $extra_long = trim($ko_jibeon . (strval($row->building_name) !== '' ? (', ' . $row->building_name) : ''), ', ');
                $extra_short = trim($row->dongri_ko . (strval($row->building_name) !== '' ? (', ' . $row->building_name) : ''), ', ');
                $other_addresses = strval($row->other_addresses);
            }
            
            // 요청받은 버전에 따라 다른 형태로 작성한다.
            
            if (version_compare($version, '3', '>='))
            {
                $record = new Postcodify_Server_Record_v3;
                $record->postcode5 = strval($row->postcode5);
                $record->postcode6 = strval($row->postcode6);
                $record->ko_common = $ko_common;
                $record->ko_doro = $ko_doro;
                $record->ko_jibeon = $ko_jibeon;
                $record->en_common = $en_common;
                $record->en_doro = $en_doro;
                $record->en_jibeon = $en_jibeon;
                $record->building_id = strval($row->building_id);
                $record->building_name = strval($row->building_name);
                $record->building_nums = isset($row->building_nums) ? strval($row->building_nums) : '';
                $record->other_addresses = $other_addresses;
                $record->road_id = ($result->sort === 'POBOX') ? '' : substr($row->road_id, 0, 12);
                $record->internal_id = strval($row->id);
                $record->address_id = substr($row->dongri_id ?: $row->building_id, 0, 10) . ($row->is_mountain ? '2' : '1') .
                    str_pad($row->jibeon_major, 4, '0', STR_PAD_LEFT) . str_pad($row->jibeon_minor, 4, '0', STR_PAD_LEFT);
            }
            elseif (version_compare($version, '1.8', '>='))
            {
                $record = new Postcodify_Server_Record_v18;
                $record->dbid = strval($row->building_id);
                $record->code6 = substr($row->postcode6, 0, 3) . '-' . substr($row->postcode6, 3, 3);
                $record->code5 = strval($row->postcode5);
                $record->address = array('base' => $ko_common, 'new' => $ko_doro, 'old' => $ko_jibeon, 'building' => strval($row->building_name));
                $record->english = array('base' => $en_common, 'new' => $en_doro, 'old' => $en_jibeon, 'building' => '');
                $record->other = array(
                    'long' => strval($extra_long),
                    'short' => strval($extra_short),
                    'others' => strval($other_addresses),
                    'addrid' => strval($row->id),
                    'roadid' => ($result->sort === 'POBOX') ? '' : $row->road_id,
                    'bldnum' => isset($row->building_nums) ? strval($row->building_nums) : '',
                );
            }
            else
            {
                $record = new Postcodify_Server_Record_v17;
                $record->dbid = strval($row->building_id);
                $record->code6 = substr($row->postcode6, 0, 3) . '-' . substr($row->postcode6, 3, 3);
                $record->code5 = strval($row->postcode5);
                $record->address = trim($ko_common . ' ' . $ko_doro);
                $record->canonical = strval($ko_jibeon);
                $record->extra_info_long = strval($extra_long);
                $record->extra_info_short = strval($extra_short);
                $record->english_address = trim($en_doro . ', ' . $en_common);
                $record->jibeon_address = trim($ko_common . ' ' . $ko_jibeon);
                $record->other = strval($other_addresses);
            }
            
            // 반환할 인코딩이 UTF-8이 아닌 경우 여기서 변환한다.
            
            if ($encoding !== 'UTF-8')
            {
                $properties = get_object_vars($record);
                foreach ($properties as $key => $value)
                {
                    $record->$key = mb_convert_encoding($value, $encoding, 'UTF-8');
                }
            }
            
            // 레코드를 추가하고 레코드 카운터를 조정한다.
            
            $result->results[] = $record;
            $result->count++;
        }
        
        // 정확한 주소만 반환하는 옵션을 처리한다.
        
        if (isset($_GET['exact']) && $_GET['exact'] === 'Y' && in_array($result->type, array('JUSO+NUMS', 'JIBEON+NUMS')))
        {
            $matches = array();
            foreach ($result->results as $record)
            {
                if ($record instanceof Postcodify_Server_Record_v3 && (isset($q->road) || isset($q->dongri)) &&
                    (($result->type === 'JUSO+NUMS' && preg_match('/' . preg_quote($q->road . ' ' . $result->nums, '/') . '$/', $record->ko_doro)) ||
                    ($result->type === 'JIBEON+NUMS' && preg_match('/' . preg_quote($q->dongri . ' ' . $result->nums, '/') . '$/', $record->ko_jibeon))))
                {
                    $matches[] = $record;
                }
            }
            if (count($matches) == 1)
            {
                $result->results = $matches;
                $result->count = 1;
            }
        }
        
        // 검색 소요 시간을 기록한다.
        
        $result->time = number_format(microtime(true) - $start_time, 3);
        
        // 결과를 반환한다.
        
        return $result;
    }
    
    // 주어진 쿼리를 DB에서 실행하는 메소드.
    
    protected function get_addresses($q)
    {
        // 반환할 변수들을 초기화한다.
        
        $addresses = array();
        $search_type = 'NONE';
        $search_error = null;
        
        try
        {
            // DB에 연결한다.
            
            if ($this->_dbh === null)
            {
                $this->_dbh = new Postcodify_Server_Database($this->db_driver, $this->db_host, $this->db_port,
                    $this->db_user, $this->db_pass, $this->db_dbname);
            }
            
            // 쿼리 작성을 준비한다.
            
            $query = 'SELECT DISTINCT pa.*, pr.* FROM postcodify_addresses pa';
            $joins = array('JOIN postcodify_roads pr ON pa.road_id = pr.road_id');
            $conds = array('pa.building_id IS NOT NULL');
            $args = array();
            
            // 특정 지역으로 검색을 제한하는 경우를 처리한다.
            
            if ($q->use_area)
            {
                if ($q->sido)
                {
                    $conds[] = 'pr.sido_ko = ?';
                    $args[] = $q->sido;
                }
                if ($q->sigungu)
                {
                    $conds[] = 'pr.sigungu_ko = ?';
                    $args[] = $q->sigungu;
                }
                if ($q->ilbangu)
                {
                    $conds[] = 'pr.ilbangu_ko = ?';
                    $args[] = $q->ilbangu;
                }
                if ($q->eupmyeon)
                {
                    $conds[] = 'pr.eupmyeon_ko = ?';
                    $args[] = $q->eupmyeon;
                }
            }
            
            // 도로명주소로 검색하는 경우...
            
            if ($q->road !== null && !count($q->buildings))
            {
                // 도로명 쿼리를 작성한다.
                
                $search_type = 'JUSO';
                $joins[] = 'JOIN postcodify_keywords pk ON pa.id = pk.address_id';
                if ($q->lang === 'KO')
                {
                    $conds[] = 'pk.keyword_crc32 = ?';
                    $args[] = self::crc32_x64($q->road);
                }
                else
                {
                    $joins[] = 'JOIN postcodify_english pe ON pe.ko_crc32 = pk.keyword_crc32';
                    $conds[] = 'pe.en_crc32 = ?';
                    $args[] = self::crc32_x64($q->road);
                }
                
                // 건물번호 쿼리를 작성한다.
                
                if ($q->numbers[0])
                {
                    $search_type .= '+NUMS';
                    $joins[] = 'JOIN postcodify_numbers pn ON pa.id = pn.address_id';
                    $conds[] = 'pn.num_major = ?';
                    $args[] = $q->numbers[0];
                    if ($q->numbers[1])
                    {
                        $conds[] = 'pn.num_minor = ?';
                        $args[] = $q->numbers[1];
                    }
                }
                
                // 검색을 수행한다.
                
                $addresses = $this->_dbh->query($query, $joins, $conds, $args, $q->lang, $q->sort);
            }
            
            // 지번주소로 검색하는 경우...
            
            elseif ($q->dongri !== null && !count($q->buildings))
            {
                // 동·리 쿼리를 작성한다.
                
                $search_type = 'JIBEON';
                $joins[] = 'JOIN postcodify_keywords pk ON pa.id = pk.address_id';
                if ($q->lang === 'KO')
                {
                    $conds[] = 'pk.keyword_crc32 = ?';
                    $args[] = self::crc32_x64($q->dongri);
                }
                else
                {
                    $joins[] = 'JOIN postcodify_english pe ON pe.ko_crc32 = pk.keyword_crc32';
                    $conds[] = 'pe.en_crc32 = ?';
                    $args[] = self::crc32_x64($q->dongri);
                }
                
                // 번지수 쿼리를 작성한다.
                
                if ($q->numbers[0])
                {
                    $search_type .= '+NUMS';
                    $joins[] = 'JOIN postcodify_numbers pn ON pa.id = pn.address_id';
                    $conds[] = 'pn.num_major = ?';
                    $args[] = $q->numbers[0];
                    if ($q->numbers[1])
                    {
                        $conds[] = 'pn.num_minor = ?';
                        $args[] = $q->numbers[1];
                    }
                }
                
                // 일단 검색해 본다.
                
                $addresses = $this->_dbh->query($query, $joins, $conds, $args, $q->lang, $q->sort);
                
                // 검색 결과가 없다면 건물명을 동리로 잘못 해석했을 수도 있으므로 건물명 검색을 다시 시도해 본다.
                
                if ($q->numbers[0] === null && $q->numbers[1] === null && !count($addresses) && $q->lang === 'KO')
                {
                    array_pop($joins);
                    array_pop($conds);
                    array_pop($args);
                    
                    $joins[] = 'JOIN postcodify_buildings pb ON pa.id = pb.address_id';
                    $conds[] = 'pb.keyword LIKE ?';
                    $args[] = '%' . $q->dongri . '%';
                    
                    $addresses = $this->_dbh->query($query, $joins, $conds, $args, $q->lang, $q->sort);
                    if (count($addresses))
                    {
                        $search_type = 'BUILDING';
                        $q->sort = 'JUSO';
                    }
                }
            }
            
            // 건물명만으로 검색하는 경우...
            
            elseif (count($q->buildings) && $q->road === null && $q->dongri === null)
            {
                $search_type = 'BUILDING';
                $joins[] = 'JOIN postcodify_buildings pb ON pa.id = pb.address_id';
                foreach ($q->buildings as $building_name)
                {
                    $conds[] = 'pb.keyword LIKE ?';
                    $args[] = '%' . $building_name . '%';
                }
                
                $addresses = $this->_dbh->query($query, $joins, $conds, $args, $q->lang, $q->sort);
            }
            
            // 도로명 + 건물명으로 검색하는 경우...
            
            elseif (count($q->buildings) && $q->road !== null)
            {
                $search_type = 'BUILDING+JUSO';
                $joins[] = 'JOIN postcodify_keywords pk ON pa.id = pk.address_id';
                $conds[] = 'pk.keyword_crc32 = ?';
                $args[] = self::crc32_x64($q->road);
                
                $joins[] = 'JOIN postcodify_buildings pb ON pa.id = pb.address_id';
                foreach ($q->buildings as $building_name)
                {
                    $conds[] = 'pb.keyword LIKE ?';
                    $args[] = '%' . $building_name . '%';
                }
                
                $addresses = $this->_dbh->query($query, $joins, $conds, $args, $q->lang, $q->sort);
            }
            
            // 동리 + 건물명으로 검색하는 경우...
            
            elseif (count($q->buildings) && $q->dongri !== null)
            {
                $search_type = 'BUILDING+DONG';
                $joins[] = 'JOIN postcodify_keywords pk ON pa.id = pk.address_id';
                $conds[] = 'pk.keyword_crc32 = ?';
                $args[] = self::crc32_x64($q->dongri);
                
                $joins[] = 'JOIN postcodify_buildings pb ON pa.id = pb.address_id';
                foreach ($q->buildings as $building_name)
                {
                    $conds[] = 'pb.keyword LIKE ?';
                    $args[] = '%' . $building_name . '%';
                }
                
                $addresses = $this->_dbh->query($query, $joins, $conds, $args, $q->lang, $q->sort);
            }
            
            // 사서함으로 검색하는 경우...
            
            elseif ($q->pobox !== null)
            {
                $search_type = 'POBOX';
                $joins[] = 'JOIN postcodify_pobox pp ON pa.id = pp.address_id';
                $conds[] = 'pp.keyword LIKE ?';
                $args[] = '%' . $q->pobox . '%';
                
                if ($q->numbers[0])
                {
                    $conds[] = 'pp.range_start_major <= ? AND pp.range_end_major >= ?';
                    $args[] = $q->numbers[0];
                    $args[] = $q->numbers[0];
                    if ($q->numbers[1])
                    {
                        $conds[] = '(pp.range_start_minor IS NULL OR (pp.range_start_minor <= ? AND pp.range_end_minor >= ?))';
                        $args[] = $q->numbers[1];
                        $args[] = $q->numbers[1];
                    }
                }
                
                $addresses = $this->_dbh->query($query, $joins, $conds, $args, $q->lang, $q->sort);
            }
            
            // 읍면으로 검색하는 경우...
            
            elseif ($q->use_area && $q->eupmyeon)
            {
                $search_type = 'EUPMYEON';
                $conds[] = 'pa.postcode5 IS NOT NULL';
                $addresses = $this->_dbh->query($query, $joins, $conds, $args, $q->lang, $q->sort);
                
                // 검색 결과가 없다면 건물명을 읍면으로 잘못 해석했을 수도 있으므로 건물명 검색을 다시 시도해 본다.
                
                if (!count($addresses) && $q->lang === 'KO')
                {
                    array_pop($conds);
                    array_pop($conds);
                    array_pop($args);
                    
                    $joins[] = 'JOIN postcodify_buildings pb ON pa.id = pb.address_id';
                    $conds[] = 'pb.keyword LIKE ?';
                    $args[] = '%' . $q->eupmyeon . '%';
                    
                    $addresses = $this->_dbh->query($query, $joins, $conds, $args, $q->lang, $q->sort);
                    if (count($addresses))
                    {
                        $search_type = 'BUILDING';
                        $q->sort = 'JUSO';
                    }
                }
            }
            
            // 그 밖의 경우 검색 결과가 없는 것으로 한다.
            
            else
            {
                $addresses = array();
            }
        }
        catch (Exception $e)
        {
            error_log('Postcodify ("' . $q . '"): ' . $e->getMessage());
            $search_type = 'ERROR';
            $search_error = $e->getMessage();
            $addresses = array();
        }
        
        return array($addresses, $search_type, $search_error);
    }
    
    // 항상 64비트식으로 (음수가 나오지 않도록) CRC32를 계산하는 메소드.
    
    public static function crc32_x64($str)
    {
        $crc32 = crc32($str);
        return ($crc32 >= 0) ? $crc32 : ($crc32 + 4294967296);
    }
}
