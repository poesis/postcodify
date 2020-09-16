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

class Postcodify_Utility
{
    // 인덱서가 지원하는 명령 목록.
    
    public static $indexer_commands = array(
        'download',
        'createdb',
        'verifydb',
        'sqlite-convert',
        'download-updates',
        'update',
        'set-postcode',
    );
    
    // 캐시를 위한 변수들.
    
    public static $road_cache = array();
    public static $building_cache = array();
    public static $building_number_cache = array();
    public static $english_cache = array();
    public static $oldcode_cache = array();
    
    // DB에 연결하는 함수.
    
    public static function get_db()
    {
        if (POSTCODIFY_DB_DRIVER === 'mysql')
        {
            $dsn = POSTCODIFY_DB_DRIVER . ':host=' . POSTCODIFY_DB_HOST . ';port=' . POSTCODIFY_DB_PORT . ';dbname=' . POSTCODIFY_DB_DBNAME;
            $dsn .= ';charset=utf8';
            
            $pdo_options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            );
        }
        else
        {
            $dsn = 'sqlite:' . POSTCODIFY_DB_DBNAME;
            $pdo_options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            );
        }
        
        $db = new PDO($dsn, POSTCODIFY_DB_USER, POSTCODIFY_DB_PASS, $pdo_options);
        return $db;
    }
    
    // 항상 64비트식으로 (음수 없이) CRC32를 계산하는 함수.
    
    public static function crc32_x64($str)
    {
        $crc32 = crc32($str);
        return ($crc32 >= 0) ? $crc32 : ($crc32 + 4294967296);
    }
    
    // 파일 다운로드 함수.
    
    public static function download($url, $target_filename = false, $progress_callback = false)
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
        
        if ($progress_callback && defined('CURLOPT_PROGRESSFUNCTION'))
        {
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, $progress_callback);
        }
        
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Compatible; Postcodify Downloader)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 3);
        
        $result = curl_exec($ch);
        curl_close($ch);
        if ($target_filename)
        {
            fclose($fp);
        }
        else
        {
            if (!preg_match('/charset="?utf-?8"?/i', $result))
            {
                $result = iconv('CP949', 'UTF-8', $result);
            }
        }
        return $result;
    }
    
    // 터미널에서 입력한 변수들을 확인하는 함수.
    
    public static function get_terminal_args()
    {
        $result = array(
            'command' => null,
            'args' => array(),
            'options' => array(),
        );
        
        foreach ($_SERVER['argv'] as $key => $arg)
        {
            if ($key === 0)
            {
                continue;
            }
            elseif (in_array($arg, self::$indexer_commands))
            {
                $result['command'] = $arg;
            }
            elseif (preg_match('/^--[a-z0-9-]+$/', $arg))
            {
                $result['options'][] = $arg;
            }
            else
            {
                $result['args'][] = $arg;
            }
        }
        
        return (object)$result;
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
    
    // 터미널에서 커서를 후퇴시키는 함수.
    
    public static function print_negative_spaces($count)
    {
        echo "\033[${count}D";
    }
    
    // 터미널에 메시지를 출력하고 커서를 오른쪽 끝으로 이동한다.
    
    public static function print_message($str)
    {
        $spaces = self::get_terminal_width() - mb_strwidth($str, 'UTF-8');
        echo $str . ($spaces > 0 ? str_repeat(' ', $spaces) : '');
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
    
    public static function print_ok($count = null)
    {
        self::print_negative_spaces(25);
        if ($count !== null)
        {
            echo str_pad(number_format($count), 17, ' ', STR_PAD_LEFT) . '  ' . '[ OK ]';
        }
        else
        {
            echo str_repeat(' ', 19) . '[ OK ]';
        }
        echo PHP_EOL;
    }
    
    // 터미널에 ERROR 메시지를 출력하고 커서를 다음 줄로 이동한다.
    
    public static function print_error()
    {
        self::print_negative_spaces(25);
        echo str_repeat(' ', 16) . '[ ERROR ]';
        echo PHP_EOL;
    }
    
    // 터미널의 커서를 다음 줄로 이동한다.
    
    public static function print_newline()
    {
        echo PHP_EOL;
    }
    
    // 터미널에 인덱서 사용 방법을 출력하고 종료한다.
    
    public static function print_usage_instructions()
    {
        $stderr = fopen('php://stderr', 'w');
        fwrite($stderr, 'Usage: php indexer.php <command> <options> [filename]' . PHP_EOL);
        fwrite($stderr, 'Valid commands:' . PHP_EOL);
        foreach (self::$indexer_commands as $command)
        {
            fwrite($stderr, '  ' . $command . PHP_EOL);
        }
        fwrite($stderr, 'Valid options:' . PHP_EOL);
        fwrite($stderr, '  filename (only with `sqlite-convert`)' . PHP_EOL);
        fwrite($stderr, '  --add-old-postcodes (since 3.3.0)' . PHP_EOL);
        fwrite($stderr, '  --no-old-postcodes (since 3.1.0, deprecated since 3.3.0)' . PHP_EOL);
        fwrite($stderr, 'Invalid options:' . PHP_EOL);
        fwrite($stderr, '  --dry-run (not supported since 3.0.0)' . PHP_EOL);
        fclose($stderr);
        exit(1);
    }
    
    // 검색 키워드에서 불필요한 문자와 띄어쓰기를 제거하는 함수.
    
    public static function get_canonical($str)
    {
        $str = str_replace(array('(주)', '(유)', '(사)', '(재)', '(아)', '㈜'), '', $str);
        return preg_replace('/[^ㄱ-ㅎ가-힣a-z0-9-]/uU', '', strtolower($str));
    }
    
    // 주소를 영문으로 변환하는 함수.
    
    public static function get_english($str)
    {
        if (isset(self::$english_cache[$str]))
        {
            return self::$english_cache[$str];
        }
        
        static $romaja_loaded = false;
        if (!$romaja_loaded)
        {
            include_once POSTCODIFY_LIB_DIR . '/resources/romaja.php';
            $romaja_loaded = true;
        }
        
        return self::$english_cache[$str] = Hangeul_Romaja::convert($str, Hangeul_Romaja::TYPE_ADDRESS);
    }
    
    // 도로명의 일반적인 변형들을 구하는 함수.
    
    public static function get_variations_of_road_name($str)
    {
        $str = preg_replace('/[^\\sㄱ-ㅎ가-힣a-z0-9]/u', '', $str);
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
        elseif (preg_match('/^(.+)([동서남북]?)([0-9-]+)번?([가나라다마바사아자차카타파하동서남북안밖좌우옆갓상하샛윗아래]?)길$/uU', $str, $matches))
        {
            if (preg_match('/[로길]$/uU', $matches[1]))
            {
                $keywords[] = $matches[1];
                $keywords[] = $matches[1] . $matches[3];
                $keywords[] = $matches[1] . $matches[3] . '길';
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
        elseif (preg_match('/^([가-힣]+)([동로])([0-9]+)가$/uU', $str, $matches))
        {
            $keywords[] = $str = $matches[1] . $matches[2];
            $keywords[] = $str = $matches[1] . $matches[2] . $matches[3] . '가';
            $keywords[] = $str = $matches[1] . $matches[3] . '가동';
            if ($matches[2] === '로')
            {
                $keywords[] = $str = $matches[1] . '로' . $matches[3] . '가동';
            }
        }
        
        if (strlen($str) > 9 && substr($str, strlen($str) - 6) === '본동')
        {
            $keywords[] = substr($str, 0, strlen($str) - 6) . '동';
        }
        
        rsort($keywords);
        return array_unique($keywords);
    }
    
    // 상세건물명 번호를 정리하여 문자열로 반환하는 메소드. 아파트 동 범위를 작성할 때 사용한다.
    
    public static function consolidate_building_nums($nums)
    {
        $intermediate = array('numeric' => array(), 'alphabet' => array(), 'other' => array());
        foreach ($nums as $num)
        {
            if (ctype_digit($num))
            {
                $intermediate['numeric'][] = intval($num);
            }
            elseif (ctype_alnum($num))
            {
                $intermediate['alphabet'][] = strtoupper($num);
            }
            else
            {
                switch ($num)
                {
                    case '에이': $intermediate['alphabet'][] = 'A'; break;
                    case '비': $intermediate['alphabet'][] = 'B'; break;
                    case '시': case '씨': $intermediate['alphabet'][] = 'C'; break;
                    case '디': $intermediate['alphabet'][] = 'D'; break;
                    default: $intermediate['other'][] = strtoupper($num);
                }
            }
        }
        
        sort($intermediate['numeric']);
        natsort($intermediate['alphabet']);
        natsort($intermediate['other']);
        
        $output = array();
        foreach ($intermediate as $key => $val)
        {
            if ($key === 'other')
            {
                foreach ($val as $vals)
                {
                    $output[] = $vals . '동';
                }
            }
            else
            {
                switch (count($val))
                {
                    case 0:
                        break;
                    case 1:
                        $output[] = reset($val) . '동';
                        break;
                    default:
                        $output[] = reset($val) . '~' . end($val) . '동';
                }
            }
        }
        
        return implode(', ', $output);
    }
    
    // 건물명 목록에서 불필요하거나 중복되는 것을 제거하여 반환하는 메소드.
    
    public static function consolidate_building_names($names, $skip_name = null)
    {
        // 불필요한 건물명을 제거한다.
        
        $input = array();
        foreach ($names as $val)
        {
            if (ctype_digit($val)) continue;
            if (self::is_ignorable_building_name($val)) continue;
            if ($skip_name !== null && strpos($skip_name, $val) !== false) continue;
            if (preg_match('/(?:(?:근린생활|동\.?식물관련|노유자|발전|창고)시설|(?:단독|다세대|다가구)주택)/u', $val)) continue;
            if (preg_match('/(?:주|(?:주|부속)건축물)제?(?:[0-9a-zA-Z-]+|에이|비|씨|디|[가나다라마바사아자차카타파하])(?:호|동|호동)/u', $val)) continue;
            if (preg_match('/[0-9a-zA-Z-]+(?:블럭|로트|롯트)/u', $val)) continue;
            if (preg_match('/^(?:[가-힣0-9]+[읍면]\s?)?[가-힣0-9]+[동리가]\s?[0-9]+(?:-[0-9]+)?(?:번지|\s)/u', $val)) continue;
            if (preg_match('/\((?:[가-힣0-9]{3,}\)?$|[가-힣0-9]{0,2})$/u', $val)) continue;
            $input[] = str_replace('㈜', '(주)', $val);
        }
        
        // 건물명 목록을 긴 것부터 짧은 순으로 정렬한다.
        
        usort($input, 'Postcodify_Utility::sort_building_names');
        
        // 짧은 건물명이 긴 건물명에 포함되어 있는 경우 제거한다.
        
        $output = array();
        foreach ($input as $val)
        {
            if ($val === '') continue;
            $exists = false;
            foreach ($output as $compare)
            {
                if (strpos($compare, $val) !== false)
                {
                    $exists = true;
                    break;
                }
            }
            if (!$exists)
            {
                $output[] = $val;
            }
        }
        
        return $output;
    }
    
    // 건물명 목록을 압축하여 문자열로 반환하는 메소드. 건물명 검색 테이블을 생성할 때 사용한다.
    
    public static function compress_building_names($names)
    {
        // 검색어에 포함될 수 없는 문자를 모두 제거한다.
        
        foreach ($names as $key => $val)
        {
            $val = self::get_canonical($val);
            if (trim($val) === '') unset($names[$key]);
        }
        
        // 하나로 합쳐서 반환한다.
        
        return implode(',', $names);
    }
    
    // 건물명의 길이를 비교하는 메소드. 중복 건물명 제거를 위한 콜백 메소드이다.
    
    protected static function sort_building_names($a, $b)
    {
        return strlen($b) - strlen($a);
    }
    
    // 무시할 건물명인지 확인하는 메소드.
    
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
                '동.식물관련시설', '노유자시설', '발전시설', '창고시설', '컨테이너',
            );
            foreach ($ignore_list_human_readable as $building_name)
            {
                $ignore_list[$building_name] = true;
            }
        }
        
        return isset($ignore_list[$str]);
    }
}
