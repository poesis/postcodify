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

class Postcodify_SQLite
{
    // SQLite 커넥션을 저장하는 변수.
    
    protected static $dbh = array();
    
    // 쿼리를 실행하고 결과를 반환하는 메소드.
    
    public static function query($db_name, $proc_name, array $params)
    {
        // 최초 호출시 DB에 연결한다.
        
        if (!isset(self::$dbh[$db_name]))
        {
            self::$dbh[$db_name] = new PDO('sqlite:' . $db_name);
            self::$dbh[$db_name]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$dbh[$db_name]->exec('PRAGMA query_only = 1');
            self::$dbh[$db_name]->exec('PRAGMA case_sensitive_like = 0');
        }
        
        // 쿼리문을 분석하여 파라미터를 삽입한다.
        // 반복되는 파라미터는 2번씩 삽입해야 하므로 주의한다.
        
        if (!isset(self::$procs[$proc_name])) return array();
        
        $querystring = trim(self::$procs[$proc_name]);
        $statement = self::$dbh[$db_name]->prepare($querystring);
        $named_params = array();
        $params[] = null; reset($params);
        
        if (preg_match_all('/:[a-z0-9_]+/', $querystring, $matches))
        {
            foreach ($matches[0] as $param_name)
            {
                if (!strncmp($param_name, ':repeat_', 8))
                {
                    $named_params[$param_name] = prev($params);
                    next($params);
                }
                else
                {
                    $named_params[$param_name] = current($params);
                    next($params);
                }
            }
        }
        
        // 쿼리문을 실행하고 결과를 반환한다.
        
        $statement->execute($named_params);
        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    // SQLite는 저장 프로시저를 지원하지 않으므로 직접 쿼리를 작성해야 한다.
    // 이런 포맷을 사용하면 위에서 별도로 분석을 거쳐 파라미터를 반복해야 한다는 단점이 있으나,
    // MySQL에서 정의한 저장 프로시저 구조와 기존 Postcodify 클래스와의 호환성 유지를 위해
    // 일단은 이렇게 해두고 사용하려고 한다. 실제 성능에 미치는 영향은 거의 없다.
    
    protected static $procs = array(
    
        // 도로명주소 검색 (단순) 프로시저.
        
        "postcode_search_juso" => "
            SELECT DISTINCT pa.* FROM postcode_addresses AS pa
            INNER JOIN postcode_keywords_juso AS pk ON pa.id = pk.address_id
            WHERE pk.keyword_crc32 = :keyword_crc32
                AND (:num1 IS NULL OR pk.num_major = :repeat_num1)
                AND (:num2 IS NULL OR pk.num_minor = :repeat_num2)
            ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
            LIMIT 100;
        ",
        
        // 도로명주소 검색 (지역 제한) 프로시저.
        
        "postcode_search_juso_in_area" => "
            SELECT DISTINCT pa.* FROM postcode_addresses AS pa
            INNER JOIN postcode_keywords_juso AS pk ON pa.id = pk.address_id
            WHERE pk.keyword_crc32 = :keyword_crc32
                AND (:num1 IS NULL OR pk.num_major = :repeat_num1)
                AND (:num2 IS NULL OR pk.num_minor = :repeat_num2)
                AND (:area1 IS NULL OR pa.sido = :repeat_area1)
                AND (:area2 IS NULL OR pa.sigungu = :repeat_area2)
                AND (:area3 IS NULL OR pa.ilbangu = :repeat_area3)
                AND (:area4 IS NULL OR pa.eupmyeon = :repeat_area4)
            ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
            LIMIT 100;
        ",
        
        // 지번 검색 (단순) 프로시저.
        
        "postcode_search_jibeon" => "
            SELECT DISTINCT pa.* FROM postcode_addresses AS pa
            INNER JOIN postcode_keywords_jibeon AS pk ON pa.id = pk.address_id
            WHERE pk.keyword_crc32 = :keyword_crc32
                AND (:num1 IS NULL OR pk.num_major = :repeat_num1)
                AND (:num2 IS NULL OR pk.num_minor = :repeat_num2)
            ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
            LIMIT 100;
        ",
        
        // 지번 검색 (지역 제한) 프로시저.
        
        "postcode_search_jibeon_in_area" => "
            SELECT DISTINCT pa.* FROM postcode_addresses AS pa
            INNER JOIN postcode_keywords_jibeon AS pk ON pa.id = pk.address_id
            WHERE pk.keyword_crc32 = :keyword_crc32
                AND (:num1 IS NULL OR pk.num_major = :repeat_num1)
                AND (:num2 IS NULL OR pk.num_minor = :repeat_num2)
                AND (:area1 IS NULL OR pa.sido = :repeat_area1)
                AND (:area2 IS NULL OR pa.sigungu = :repeat_area2)
                AND (:area3 IS NULL OR pa.ilbangu = :repeat_area3)
                AND (:area4 IS NULL OR pa.eupmyeon = :repeat_area4)
            ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
            LIMIT 100;
        ",
        
        // 건물명 검색 (단순) 프로시저.
        
        "postcode_search_building" => "
            SELECT DISTINCT pa.* FROM postcode_addresses AS pa
            INNER JOIN postcode_keywords_building AS pk ON pa.id = pk.address_id
            WHERE pk.keyword LIKE ('%' || :keyword || '%')
            ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
            LIMIT 100;
        ",

        // 건물명 검색 (지역 제한) 프로시저.

        "postcode_search_building_in_area" => "
            SELECT DISTINCT pa.* FROM postcode_addresses AS pa
            INNER JOIN postcode_keywords_building AS pk ON pa.id = pk.address_id
            WHERE pk.keyword LIKE ('%' || :keyword || '%')
                AND (:area1 IS NULL OR pa.sido = :repeat_area1)
                AND (:area2 IS NULL OR pa.sigungu = :repeat_area2)
                AND (:area3 IS NULL OR pa.ilbangu = :repeat_area3)
                AND (:area4 IS NULL OR pa.eupmyeon = :repeat_area4)
            ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
            LIMIT 100;
        ",
        
        // 건물명 + 동/리 검색 (단순) 프로시저.
        
        "postcode_search_building_with_dongri" => "
            SELECT DISTINCT pa.* FROM postcode_addresses AS pa
            INNER JOIN postcode_keywords_building AS pkb ON pa.id = pkb.address_id
            INNER JOIN postcode_keywords_jibeon AS pkj ON pa.id = pkj.address_id
            WHERE pkb.keyword LIKE ('%' || :keyword || '%')
                AND pkj.keyword_crc32 = :dongri_crc32
            ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
            LIMIT 100;
        ",
        
        // 건물명 + 동/리 검색 (지역 제한) 프로시저.
        
        "postcode_search_building_with_dongri_in_area" => "
            SELECT DISTINCT pa.* FROM postcode_addresses AS pa
            INNER JOIN postcode_keywords_building AS pkb ON pa.id = pkb.address_id
            INNER JOIN postcode_keywords_jibeon AS pkj ON pa.id = pkj.address_id
            WHERE pkb.keyword LIKE ('%' || :keyword || '%')
                AND pkj.keyword_crc32 = :dongri_crc32
                AND (:area1 IS NULL OR pa.sido = :repeat_area1)
                AND (:area2 IS NULL OR pa.sigungu = :repeat_area2)
                AND (:area3 IS NULL OR pa.ilbangu = :repeat_area3)
                AND (:area4 IS NULL OR pa.eupmyeon = :repeat_area4)
            ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
            LIMIT 100;
        ",
        
        // 사서함 검색 (단순) 프로시저.
        
        "postcode_search_pobox" => "
            SELECT DISTINCT pa.* FROM postcode_addresses AS pa
            INNER JOIN postcode_keywords_pobox AS pk ON pa.id = pk.address_id
            WHERE pk.keyword LIKE ('%' || :keyword || '%')
                AND (:num1 IS NULL OR :repeat_num1 BETWEEN pk.range_start_major AND pk.range_end_major)
                AND (:num2 IS NULL OR :repeat_num2 BETWEEN pk.range_start_minor AND pk.range_end_minor)
            ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
            LIMIT 100;
        ",
        
        // 사서함 검색 (지역 제한) 프로시저.
        
        "postcode_search_pobox_in_area" => "
            SELECT DISTINCT pa.* FROM postcode_addresses AS pa
            INNER JOIN postcode_keywords_pobox AS pk ON pa.id = pk.address_id
            WHERE pk.keyword LIKE ('%' || :keyword || '%')
                AND (:num1 IS NULL OR :repeat_num1 BETWEEN pk.range_start_major AND pk.range_end_major)
                AND (:num2 IS NULL OR :repeat_num2 BETWEEN pk.range_start_minor AND pk.range_end_minor)
                AND (:area1 IS NULL OR pa.sido = :repeat_area1)
                AND (:area2 IS NULL OR pa.sigungu = :repeat_area2)
                AND (:area3 IS NULL OR pa.ilbangu = :repeat_area3)
                AND (:area4 IS NULL OR pa.eupmyeon = :repeat_area4)
            ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
            LIMIT 100;
        ",
    );
}
