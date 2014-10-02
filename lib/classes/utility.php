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

class Postcodify_Utility
{
    // 도로명 캐시.
    
    public static $road_cache = array();
    
    // 영문 번역 캐시.
    
    public static $english_cache = array();
    
    // 상세건물명 캐시.
    
    public static $building_cache = array();
    
    // DB에 연결하는 함수.
    
    public static function get_db()
    {
        $dsn = POSTCODIFY_DB_DRIVER . ':host=' . POSTCODIFY_DB_HOST . ';port=' . POSTCODIFY_DB_PORT . ';dbname=' . POSTCODIFY_DB_DBNAME;
        $dsn .= ';charset=utf8';
        
        $pdo_options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );
        
        $db = new PDO($dsn, POSTCODIFY_DB_USER, POSTCODIFY_DB_PASS, $pdo_options);
        return $db;
    }
    
    // 항상 64비트식으로 (음수 없이) CRC32를 계산하는 함수.
    
    public static function crc32_x64($str)
    {
        $crc32 = crc32($str);
        return ($crc32 >= 0) ? $crc32 : ($crc32 + 0x100000000);
    }
    
    // 파일 다운로드 함수.
    
    public static function download($url, $target_filename = false)
    {
        $ch = curl_init($url);
        if ($target_filename)
        {
            $fp = fopen($target_filename, 'w');
            curl_setopt($ch, CURLOPT_FILE, $fp);
        }
        else
        {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        }
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Compatible; Postcodify Downloader)');
        $result = curl_exec($ch);
        curl_close($ch);
        if ($target_filename)
        {
            fclose($fp);
        }
        else
        {
            if (!preg_match('/charset=utf-?8/i', $result))
            {
                $result = iconv('CP949', 'UTF-8', $result);
            }
        }
        return $result;
    }
    
    // 터미널의 가로 폭을 측정하는 함수.
    
    public static function get_terminal_width()
    {
        static $width = null;
        if ($width !== null) return $width;
        
        $width = intval(trim(exec('tput cols')));
        if (!$width) $width = 80;
        return $width;
    }
    
    // 터미널에 표시할 문자열의 가로 폭을 계산하는 함수.
    
    public static function get_printed_width($str)
    {
        return strlen($str) - ((strlen($str) - mb_strlen($str, 'UTF-8')) / 2);
    }
    
    // 터미널에서 커서를 후퇴시키는 함수.
    
    public static function print_negative_spaces($count)
    {
        echo "\033[${count}D";
    }
    
    // 터미널에 메시지를 출력하고 커서를 오른쪽 끝으로 이동한다.
    
    public static function print_message($str)
    {
        echo $str . str_repeat(' ', self::get_terminal_width() - self::get_printed_width($str));
    }
    
    // 터미널에 진행 상황을 출력한다.
    
    public static function print_progress($num, $max = null)
    {
        if ($max === null)
        {
            self::print_negative_spaces(25);
            echo str_pad(number_format($num), 23, ' ', STR_PAD_LEFT) . '  ';
        }
        else
        {
            self::print_negative_spaces(25);
            echo str_pad(number_format($num) . ' / ' . number_format($max), 23, ' ', STR_PAD_LEFT) . '  ';
        }
    }
    
    // 터미널에 OK 메시지를 출력하고 커서를 다음 줄로 이동한다.
    
    public static function print_ok()
    {
        self::print_negative_spaces(25);
        echo str_repeat(' ', 19) . '[ OK ]';
        echo PHP_EOL;
    }
    
    // 터미널의 커서를 다음 줄로 이동한다.
    
    public static function print_newline()
    {
        echo PHP_EOL;
    }
    
    // 검색 키워드에서 불필요한 문자와 띄어쓰기를 제거하는 함수.
    
    public static function get_canonical($str)
    {
        $str = str_replace(array('(주)', '(유)', '(사)', '(재)', '(아)'), '', $str);
        return preg_replace('/[^ㄱ-ㅎ가-힣a-z0-9-]/uU', '', strtolower($str));
    }
    
    // 기타 주소를 정리하는 함수.
    
    public static function organize_other_addresses($other_addresses, $building_names, $admin_dongri)
    {
        // 지번주소 목록을 분리한다.
        
        if (!is_array($other_addresses))
        {
            $other_addresses = explode("\n", $other_addresses);
        }
        
        // 동별로 묶어서 재구성한다.
        
        $numeric_addresses = array();
        foreach ($other_addresses as $address)
        {
            $address = explode(' ', $address);
            if (count($address) < 2) continue;
            $numeric_addresses[$address[0]][] = $address[1];
        }
        
        $other_addresses = array();
        foreach ($numeric_addresses as $dongri => $numbers)
        {
            natsort($numbers);
            $other_addresses[] = $dongri . ' ' . implode(', ', $numbers);
        }
        
        // 행정동명을 추가한다.
        
        $admin_dongri = strval($admin_dongri);
        if ($admin_dongri !== '' && !isset($numeric_addresses[$admin_dongri]))
        {
            $other_addresses[] = $admin_dongri;
        }
        
        // 건물 이름들을 추가한다.
        
        natsort($building_names);
        foreach ($building_names as $building_name)
        {
            $other_addresses[] = str_replace(';', ':', $building_name);
        }
        
        // 정리하여 반환한다.
        
        return implode('; ', $other_addresses);
    }
    
    // 도로명의 일반적인 변형들을 구하는 함수.
    
    public static function get_variations_of_road_name($str)
    {
        $keywords = array($str);
        
        if (preg_match('/^(.+)([동서남북]?)([0-9-]+)번?로$/uU', $str, $matches))
        {
            $keywords[] = $matches[1] . '로';
            $keywords[] = $matches[1] . $matches[3] . '로';
            $keywords[] = $matches[1] . $matches[3];
            if ($matches[2])
            {
                $keywords[] = $matches[1] . $matches[2] . '로';
                $keywords[] = $matches[1] . $matches[2] . $matches[3] . '로';
                $keywords[] = $matches[1] . $matches[2] . $matches[3];
            }
        }
        elseif (preg_match('/^(.+)([동서남북]?)([0-9-]+)번?([가나라다마바사아자차카타파하동서남북안]?)길$/uU', $str, $matches))
        {
            if (preg_match('/[로길]$/uU', $matches[1]))
            {
                $keywords[] = $matches[1];
                $keywords[] = $matches[1] . $matches[3];
            }
            else
            {
                $keywords[] = $matches[1] . '길';
                $keywords[] = $matches[1] . $matches[3] . '길';
                $keywords[] = $matches[1] . $matches[3];
                if ($matches[2])
                {
                    $keywords[] = $matches[1] . $matches[2] . '길';
                    $keywords[] = $matches[1] . $matches[2] . $matches[3] . '길';
                    $keywords[] = $matches[1] . $matches[2] . $matches[3];
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
    
    public static function get_variations_of_dongri($str)
    {
        $keywords = preg_match('/[.,-]/', $str) ? array() : array($str);
        
        if (preg_match('/^(.+)제?([0-9.,-]+)([동리])$/uU', $str, $matches))
        {
            $keywords[] = $str = $matches[1] . $matches[3];
            $matches[2] = preg_split('/[.,-]/', $matches[2]);
            foreach ($matches[2] as $match)
            {
                if (ctype_digit(trim($match)))
                {
                    $keywords[] = $matches[1] . $match . $matches[3];
                    $keywords[] = $matches[1] . '제' . $match . $matches[3];
                }
            }
            $keywords[] = $matches[1] . implode('', $matches[2]) . $matches[3];
            $keywords[] = $matches[1] . '제' . implode('', $matches[2]) . $matches[3];
        }
        elseif (!count($keywords))
        {
            $split_keywords = preg_split('/[.,-]/', $str);
            $split_keywords_count = count($split_keywords);
            foreach ($split_keywords as $key => $value)
            {
                if ($key < $split_keywords_count - 1) $value .= substr($str, strlen($str) - 3);
                $keywords[] = $value;
                $keywords[] = preg_replace('/[0-9.]+/', '', $value);
            }
            return array_unique($keywords);
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
        
        if (strlen($str) > 9 && substr($str, strlen($str) - 6) === '본동')
        {
            $keywords[] = substr($str, 0, strlen($str) - 6) . '동';
        }
        
        rsort($keywords);
        return array_unique($keywords);
    }
    
    // 건물명의 일반적인 변형들을 구하는 함수.
    
    public static function get_variations_of_building_name($str)
    {
        // 무의미한 건물명은 무시한다.
        
        if (self::is_ignorable_building_name($str)) return array();
        
        // 그 밖에 불필요한 건물명을 제거한다.
        
        if (ctype_digit($str)) return array();
        if (preg_match('/^(주|주건축물제|제)([0-9a-zA-Z]+|에이|비|씨|디|[가나다라마바사아자차카타파하])(호|동|호동)$/u', $str)) return array();
        
        // 반환할 배열을 초기화한다.
        
        $keywords = array($str);
        
        // 건물명 중간에 붙어 검색을 방해하는 동수, 호수, 차수 등을 제거하여 ○○3차아파트를 ○○아파트로도 검색할 수 있도록 한다.
        
        if (preg_match('/^(.+)[0-9]+차(아파트|빌라|오피스텔)$/uU', $str, $matches))
        {
            $keywords[] = $str = $matches[1] . $matches[2];
        }
        elseif (preg_match('/^(.+)(?:[0-9]+|본)동(우체국|경찰서|주민센터)$/uU', $str, $matches))
        {
            $keywords[] = $str = $matches[1] . '동' . $matches[2];
            $keywords[] = $str = $matches[1] . $matches[2];
        }
        
        // 일관성 없는 아파트 명칭의 단순한 형태를 추가한다.
        
        $keywords[] = str_replace('e-편한세상', 'e편한세상', $str);
        
        // 결과를 반환한다.
        
        return array_unique($keywords);
    }
    
    // 무시할 건물명인지 확인하는 함수.
    
    protected static function is_ignorable_building_name($str)
    {
        static $ignore_list = array();
        if (!count($ignore_list))
        {
            $ignore_list_human_readable = array(
                '주택', '단독주택', '창고', '화장실', '차고', '본관', '본관동', '별관', '별관동', '증축', '관사', '교회',
                '소매점', '일반음식점', '음식점', '우체국', '미술관', '주유소', '사무실', '관리실', '대웅전',
                '관리사무소', '노인정', '이발관', '상가', '폐상가', '공가', '폐가', '축사', '폐축사', '가건물',
                '다세대주택', '공장', '정비공장', '제실', '컨테이너', '사무소', '무벽건물', '재실', '철거', '제각', '퇴비사',
                '슈퍼', '민박', '경로당', '정자', '비닐하우스', '하우스', '우사', '돈사', '견사', '양계장', '건물',
                '빈집', '다가구주택', '상점', '고물상', '원룸', '폐창고', '농막', '사찰', '회관', '관리사', '폐공장',
                '식당', '주차장', '사당', '온실', '빌라', '일반공장', '공중화장실', '마을공동시설', '방앗간',
                '여관', '학원', '수리점', '약국', '다세대', '다가구', '미용실', '고시원', '세탁소', '공사중',
            );
            foreach ($ignore_list_human_readable as $building_name)
            {
                $ignore_list[$building_name] = true;
            }
        }
        
        return isset($ignore_list[$str]);
    }
}
