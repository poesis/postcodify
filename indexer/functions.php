<?php

// DB에 연결하는 함수.

function get_db()
{
    $dsn = DB_DRIVER . ':host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_DBNAME;
    $dsn .= ';charset=utf8';
    
    $pdo_options = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
    );
    
    return new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
}

// 항상 64비트식으로 (음수 없이) CRC32를 계산하는 함수.

function crc32_x64($str)
{
    $crc32 = crc32($str);
    return ($crc32 >= 0) ? $crc32 : ($crc32 + 0x100000000);
}

// 검색 키워드에서 불필요한 문자와 띄어쓰기를 제거하는 함수.

function get_canonical($str)
{
    $str = str_replace(array('(주)', '(유)', '(사)', '(재)', '(아)'), '', $str);
    return preg_replace('/[^ㄱ-ㅎ가-힣a-z0-9-]/U', '', strtolower($str));
}

// 도로명의 일반적인 변형들을 구하는 함수.

function get_variations_of_road_name($str)
{
    $keywords = array($str);
    
    if (preg_match('/^(.+)([동서남북]?)([0-9-]+)번?로$/uU', $str, $matches))
    {
        $keywords[] = $matches[1] . '로';
        $keywords[] = $matches[1] . $matches[3] . '로';
        if ($matches[2])
        {
            $keywords[] = $matches[1] . $matches[2] . '로';
            $keywords[] = $matches[1] . $matches[2] . $matches[3] . '로';
        }
    }
    elseif (preg_match('/^(.+)([동서남북]?)([0-9-]+)번?([가나라다마바사아자차카타파하동서남북안]?)길$/uU', $str, $matches))
    {
        if (preg_match('/[로길]$/uU', $matches[1]))
        {
            $keywords[] = $matches[1];
        }
        else
        {
            $keywords[] = $matches[1] . '길';
            $keywords[] = $matches[1] . $matches[3] . '길';
            if ($matches[2])
            {
                $keywords[] = $matches[1] . $matches[2] . '길';
                $keywords[] = $matches[1] . $matches[2] . $matches[3] . '길';
            }
            if ($matches[4])
            {
                $keywords[] = $matches[1] . $matches[4] . '길';
                $keywords[] = $matches[1] . $matches[3] . $matches[4] . '길';
            }
        }
    }
    
    return array_unique($keywords);
}

// 동명 및 리명의 일반적인 변형들을 구하는 함수.

function get_variations_of_dongri($str, &$dongs)
{
    $keywords = array($str);
    
    if (preg_match('/^(.+)제?([0-9,]+)([동리])$/uU', $str, $matches))
    {
        $keywords[] = $str = $matches[1] . $matches[3];
        $matches[2] = preg_split('/[.,-]/', $matches[2]);
        foreach ($matches[2] as $match)
        {
            if (ctype_digit(trim($match))) $keywords[] = $matches[1] . $match . $matches[3];
        }
    }
    
    if (preg_match('/^(.+)([0-9]+)가동$/uU', $str, $matches))
    {
        $keywords[] = $str = $matches[1] . '동';
        $keywords[] = $str = $matches[1] . '동' . $matches[2] . '가';
    }
    elseif (preg_match('/^([가-힣]+)동?([0-9]+)가$/uU', $str, $matches))
    {
        $keywords[] = $str = $matches[1] . '동';
        $keywords[] = $str = $matches[1] . '동' . $matches[2] . '가';
        $keywords[] = $str = $matches[1] . $matches[2] . '가동';
    }
    
    if (substr($str, strlen($str) - 6) === '본동')
    {
        $dong_original_suspected = substr($str, 0, strlen($str) - 6) . '동';
        if (isset($dongs[$dong_original_suspected]))
        {
            $keywords[] = $dong_original_suspected;
        }
        else
        {
            $dongs[$str] = 1;
        }
    }
    else
    {
        $dongs[$str] = 1;
    }
    
    rsort($keywords);
    return array_unique($keywords);
}

// 건물명의 일반적인 변형들을 구하는 함수.

function get_variations_of_building_name($str)
{
    // 건물명 관련 설정.
    
    static $ignore_names = array(
        '주택', '단독주택', '창고', '축사', '화장실', '차고', '별관', '관사', '교회',
        '소매점', '일반음식점', '음식점', '우체국', '미술관', '주유소', '사무실', '관리실',
        '관리사무소', '노인정', '이발관', '상가', '폐상가', '공가', '폐가', '(가건물)'
    );
    
    // 불필요한 건물명은 제거한다.
    
    if (in_array($str, $ignore_names)) return array();
    
    // 반환할 배열을 초기화한다.
    
    $keywords = array($str);
    
    // 동수, 호수, 차수 등을 제거한다.
    
    if (preg_match('/^(.+)([0-9A-Za-z-]+(?:[동호차관]|단지)?)+$/uU', $str, $matches))
    {
        $keywords[] = $str = $matches[1];
    }
    elseif (preg_match('/^(.+)[0-9]+차(아파트|빌라|오피스텔)$/uU', $str, $matches))
    {
        $keywords[] = $str = $matches[1] . $matches[2];
    }
    elseif (preg_match('/^(.+)(?:[0-9]+|본)동(우체국|경찰서|주민센터)$/uU', $str, $matches))
    {
        $keywords[] = $str = $matches[1] . '동' . $matches[2];
        $keywords[] = $str = $matches[1] . $matches[2];
    }
    
    // 일반적인 단체, 학교, 관공서명 등에 붙는 잡다한 접두사를 제거한다.
    
    if (preg_match('/^(?:대한예수교장로회|기독교대한감리회|대한예수교연합침례회|사회복지법인|학교법인)(.+)$/uU', $str, $matches))
    {
        $keywords[] = $str = $matches[1];
    }
    
    if (preg_match('/^(?:서울|부산|대구|대전|광주|인천|울산|성남|수원)(.+)((?:초등|중|여자중|고등|여자고등)학교)$/uU', $str, $matches))
    {
        $keywords[] = $str = $matches[1] . $matches[2];
    }
    elseif (preg_match('/^(?:서울|부산|대구|대전|광주|인천|울산|성남|수원)(.+)(우체국|경찰서|주민센터)$/uU', $str, $matches))
    {
        $keywords[] = $str = $matches[1] . $matches[2];
    }
    
    // 복잡한 아파트 명칭의 단순한 형태를 추가한다.
    
    $keywords[] = str_replace('e-편한세상', 'e편한세상', $str);
    
    // 결과를 반환한다.
    
    return array_unique($keywords);
}
