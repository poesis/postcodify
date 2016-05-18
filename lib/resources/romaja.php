<?php

/*
* ------------------------------------------------------------------------------
*                               한글 로마자 변환기
* ------------------------------------------------------------------------------
*
* Copyright (c) 2015, Kijin Sung <kijin@kijinsung.com>
*
* All rights reserved.
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to
* deal in the Software without restriction, including without limitation
* the right to use, copy, modify, merge, publish, distribute, sublicense,
* and/or sell copies of the Software, and to permit persons to whom the
* Software is furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included
* in all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*/

class Hangeul_Romaja
{
    // 설정을 위한 상수들.
    
    const TYPE_DEFAULT = 0;
    const TYPE_NAME = 1;
    const TYPE_ADDRESS = 2;
    const CAPITALIZE_NONE = 4;
    const CAPITALIZE_FIRST = 8;
    const CAPITALIZE_WORDS = 16;
    const PRONOUNCE_NUMBERS = 32;
    
    // 주어진 한글 단어 또는 문장을 로마자로 변환한다.
    
    public static function convert($str, $type = 0, $options = 0)
    {
        // 빈 문자열은 처리하지 않는다.
        
        if ($str === '') return '';
        
        // 이름인 경우 별도 처리.
        
        if ($type === self::TYPE_NAME)
        {
            if (mb_strlen($str, 'UTF-8') > 2)
            {
                $possible_surname = mb_substr($str, 0, 2, 'UTF-8');
                if (in_array($possible_surname, self::$long_surnames))
                {
                    $surname = $possible_surname;
                    $firstname = mb_substr($str, 2, null, 'UTF-8');
                }
                else
                {
                    $surname = mb_substr($str, 0, 1, 'UTF-8');
                    $firstname = mb_substr($str, 1, null, 'UTF-8');
                }
            }
            else
            {
                $surname = mb_substr($str, 0, 1, 'UTF-8');
                $firstname = mb_substr($str, 1, null, 'UTF-8');
            }
            $str = $surname . ' ' . $firstname;
            $options |= self::CAPITALIZE_WORDS;
        }
        
        // 주소인 경우 별도 처리.
        
        if ($type === self::TYPE_ADDRESS)
        {
            $str = implode(', ', array_reverse(preg_split('/\s+/', $str)));
            $str = preg_replace('/([동리])([0-9]+)가/u', '$1 $2가', $str);
            $str = preg_replace('/([0-9]+)가([0-9]+)동/u', ' $1가 $2동', $str);
            $str = preg_replace('/\b(.+[로길])(.+길)\b/u', '$1 $2', $str);
            $str = preg_replace_callback('/([0-9]+)?([시도군구읍면동리로길가])(?=$|,|\s)/u', array(__CLASS__, 'conv_address'), $str);
            $str = preg_replace('/([문산])로 ([0-9]+)-ga/u', '$1노 $2-ga', $str);
            $str = trim($str);
            $options |= self::CAPITALIZE_WORDS;
        }
        
        // 문자열을 한 글자씩 자른다.
        
        $chars = preg_split('//u', $str);
        
        // 각 글자를 초성, 중성, 종성으로 분리한다.
        
        $parts = array();
        foreach ($chars as $char)
        {
            if ($char === '')
            {
                continue;
            }
            elseif (preg_match('/[가-힣]/u', $char))
            {
                $char = hexdec(substr(json_encode($char), 3, 4)) - 44032;
                $part3 = intval($char % 28);
                $part2 = intval($char / 28) % 21;
                $part1 = intval($char / 28 / 21);
                $parts[] = array(1, $part1);
                $parts[] = array(2, $part2);
                $parts[] = array(3, $part3);
            }
            else
            {
                $parts[] = array(0, $char);
            }
        }
        
        // 각 문자를 처리한다.
        
        $parts_count = count($parts);
        $result = array();
        for ($i = 0; $i < $parts_count; $i++)
        {
            $parttype = $parts[$i][0];
            $part = $parts[$i][1];
            
            switch ($parttype)
            {
                case 1:
                    $result[] = self::$charmap1[$part];
                    break;
                    
                case 2:
                    $result[] = self::$charmap2[$part];
                    break;
                    
                case 3:
                    if ($i < $parts_count - 1 && $part > 0)
                    {
                        $nextpart = $parts[$i + 1];
                        if ($nextpart[0] === 1)
                        {
                            $newparts = self::transform($part, $nextpart[1], $parttype);
                            $part = $newparts[0];
                            $parts[$i + 1][1] = $newparts[1];
                        }
                    }
                    $result[] = self::$charmap3[$part];
                    break;
                    
                default:
                    $result[] = $part;
            }
        }
        
        // 불필요한 공백이나 반복되는 글자를 제거한다.
        
        $result = implode('', $result);
        $result = str_replace(array('kkk', 'ttt', 'ppp'), array('kk', 'tt', 'pp'), $result);
        $result = preg_replace('/\s+/', ' ', $result);
        
        // 숫자 발음표현 처리를 거친다.
        
        if ($options & self::PRONOUNCE_NUMBERS)
        {
            if ($type === self::TYPE_ADDRESS)
            {
                $result = explode(', ', $result);
                foreach ($result as $i => $word)
                {
                    if (preg_match('/[a-z]/i', $word))
                    {
                        $result[$i] = preg_replace_callback('/[0-9]+/', array(__CLASS__, 'conv_number'), $word);
                    }
                }
                $result = implode(', ', $result);
            }
            else
            {
                $result = preg_replace_callback('/[0-9]+/', array(__CLASS__, 'conv_number'), $result);
            }
        }
        
        // 대문자 처리를 거친다.
        
        if ($options & self::CAPITALIZE_WORDS)
        {
            $result = implode(' ', array_map('ucfirst', explode(' ', $result)));
        }
        elseif ($options & self::CAPITALIZE_FIRST)
        {
            $result = ucfirst($result);
        }
        
        // 결과를 반환한다.
        
        return $result;
    }
    
    // 주소를 처리한다.
    
    protected static function conv_address($matches)
    {
        if ($matches[1])
        {
            return ' ' . $matches[1] . '-' . self::convert($matches[2]);
        }
        else
        {
            return '-' . self::convert($matches[2]);
        }
    }
    
    // 숫자 발음표현을 처리한다.
    
    protected static function conv_number($matches)
    {
        $number = strval(intval($matches[0]));
        $pronounced = '';
        $largest_place = strlen($number) - 1;
        for ($i = 0; $i <= $largest_place; $i++)
        {
            $digit = self::$numbers_pronunciation['digits'][intval($number[$i])];
            $place = self::$numbers_pronunciation['places'][$largest_place - $i];
            if ($digit === '일' && $place !== '') $digit = '';
            if ($digit !== '영') $pronounced .= ($digit . $place);
        }
        $pronounced = self::convert($pronounced);
        return "$number($pronounced)";
    }
    
    // 자음 동화를 처리한다.
    
    protected static function transform($part, $nextpart, $type)
    {
        $key = self::$ordmap3[$part] . self::$ordmap1[$nextpart];
        if (isset(self::$transforms_always[$key]))
        {
            $resultkey = str_replace('  ', '   ', self::$transforms_always[$key]);
            $result = array(
                intval(array_search(substr($resultkey, 0, 3), self::$ordmap3)),
                intval(array_search(substr($resultkey, 3, 3), self::$ordmap1)),
            );
        }
        elseif ($type !== self::TYPE_ADDRESS && isset(self::$transforms_non_address[$key]))
        {
            $resultkey = str_replace('  ', '   ', self::$transforms_non_address[$key]);
            $result = array(
                intval(array_search(substr($resultkey, 0, 3), self::$ordmap3)),
                intval(array_search(substr($resultkey, 3, 3), self::$ordmap1)),
            );
        }
        else
        {
            $result = array($part, $nextpart);
        }
        
        if ($result[0] == 8 && $result[1] == 5)
        {
            $result[1] = 19;
        }
        return $result;
    }
    
    // 숫자 발음표현 목록.
    
    protected static $numbers_pronunciation = array(
        'digits' => array('영', '일', '이', '삼', '사', '오', '육', '칠', '팔', '구'),
        'places' => array('', '십', '백', '천', '만'),
    );
        
    // 자음 동화 목록 (항상 적용).
    
    protected static $transforms_always = array(
        'ㄱㄴ' => 'ㅇㄴ',
        'ㄱㄹ' => 'ㅇㄴ',
        'ㄱㅁ' => 'ㅇㅁ',
        'ㄱㅇ' => '  ㄱ',
        'ㄲㄴ' => 'ㅇㄴ',
        'ㄲㄹ' => 'ㅇㄴ',
        'ㄲㅁ' => 'ㅇㅁ',
        'ㄲㅇ' => '  ㄲ',
        'ㄳㅇ' => 'ㄱㅅ',
        'ㄴㄹ' => 'ㄹㄹ',
        'ㄴㅋ' => 'ㅇㅋ',
        'ㄵㄱ' => 'ㄴㄲ',
        'ㄵㄷ' => 'ㄴㄸ',
        'ㄵㄹ' => 'ㄹㄹ',
        'ㄵㅂ' => 'ㄴㅃ',
        'ㄵㅅ' => 'ㄴㅆ',
        'ㄵㅇ' => 'ㄴㅈ',
        'ㄵㅈ' => 'ㄴㅉ',
        'ㄵㅋ' => 'ㅇㅋ',
        'ㄵㅎ' => 'ㄴㅊ',
        'ㄶㄱ' => 'ㄴㅋ',
        'ㄶㄷ' => 'ㄴㅌ',
        'ㄶㄹ' => 'ㄹㄹ',
        'ㄶㅂ' => 'ㄴㅍ',
        'ㄶㅈ' => 'ㄴㅊ',
        'ㄷㄴ' => 'ㄴㄴ',
        'ㄷㄹ' => 'ㄴㄴ',
        'ㄷㅁ' => 'ㅁㅁ',
        'ㄷㅂ' => 'ㅂㅂ',
        'ㄷㅇ' => '  ㄷ',
        'ㄹㄴ' => 'ㄹㄹ',
        'ㄹㅇ' => '  ㄹ',
        'ㄺㄴ' => 'ㄹㄹ',
        'ㄺㅇ' => 'ㄹㄱ',
        'ㄻㄴ' => 'ㅁㄴ',
        'ㄻㅇ' => 'ㄹㅁ',
        'ㄼㄴ' => 'ㅁㄴ',
        'ㄼㅇ' => 'ㄹㅂ',
        'ㄽㄴ' => 'ㄴㄴ',
        'ㄽㅇ' => 'ㄹㅅ',
        'ㄾㄴ' => 'ㄷㄴ',
        'ㄾㅇ' => 'ㄹㅌ',
        'ㄿㄴ' => 'ㅁㄴ',
        'ㄿㅇ' => 'ㄹㅍ',
        'ㅀㄴ' => 'ㄴㄴ',
        'ㅀㅇ' => '  ㄹ',
        'ㅁㄹ' => 'ㅁㄴ',
        'ㅂㄴ' => 'ㅁㄴ',
        'ㅂㄹ' => 'ㅁㄴ',
        'ㅂㅁ' => 'ㅁㅁ',
        'ㅂㅇ' => '  ㅂ',
        'ㅄㄴ' => 'ㅁㄴ',
        'ㅄㄹ' => 'ㅁㄴ',
        'ㅄㄹ' => 'ㅁㄴ',
        'ㅄㅁ' => 'ㅁㅁ',
        'ㅄㅇ' => 'ㅂㅅ',
        'ㅅㄴ' => 'ㄴㄴ',
        'ㅅㄹ' => 'ㄴㄴ',
        'ㅅㅁ' => 'ㅁㅁ',
        'ㅅㅂ' => 'ㅂㅂ',
        'ㅅㅇ' => '  ㅅ',
        'ㅆㄴ' => 'ㄴㄴ',
        'ㅆㄹ' => 'ㄴㄴ',
        'ㅆㅁ' => 'ㅁㅁ',
        'ㅆㅂ' => 'ㅂㅂ',
        'ㅆㅇ' => '  ㅆ',
        'ㅇㄹ' => 'ㅇㄴ',
        'ㅈㅇ' => '  ㅈ',
        'ㅊㅇ' => '  ㅊ',
        'ㅋㅇ' => '  ㅋ',
        'ㅌㅇ' => '  ㅌ',
        'ㅍㅇ' => '  ㅍ',
    );
    
    // 자음 동화 목록 (주소에서는 적용하지 않음).
    
    protected static $transforms_non_address = array(
        'ㄴㄱ' => 'ㅇㄱ',
        'ㄴㅁ' => 'ㅁㅁ',
        'ㄴㅂ' => 'ㅁㅂ',
        'ㄴㅍ' => 'ㅁㅍ',
    );
    
    // 두 글자짜리 성씨 목록.
    
    protected static $long_surnames = array(
        '남궁',
        '독고',
        '동방',
        '사공',
        '서문',
        '선우',
        '소봉',
        '제갈',
        '황보',
    );
    
    // 초성 목록 (번역표).
    
    protected static $charmap1 = array(
        'g',    // ㄱ
        'kk',   // ㄲ
        'n',    // ㄴ
        'd',    // ㄷ
        'tt',   // ㄸ
        'r',    // ㄹ
        'm',    // ㅁ
        'b',    // ㅂ
        'pp',   // ㅃ
        's',    // ㅅ
        'ss',   // ㅆ
        '',     // ㅇ
        'j',    // ㅈ
        'jj',   // ㅉ
        'ch',   // ㅊ
        'k',    // ㅋ
        't',    // ㅌ
        'p',    // ㅍ
        'h',    // ㅎ
        'l',    // ㄹㄹ
    );
    
    // 중성 목록 (번역표).
    
    protected static $charmap2 = array(
        'a',    // ㅏ
        'ae',   // ㅐ
        'ya',   // ㅑ
        'yae',  // ㅒ
        'eo',   // ㅓ
        'e',    // ㅔ
        'yeo',  // ㅕ
        'ye',   // ㅖ
        'o',    // ㅗ
        'wa',   // ㅘ
        'wae',  // ㅙ
        'oe',   // ㅚ
        'yo',   // ㅛ
        'u',    // ㅜ
        'wo',   // ㅝ
        'we',   // ㅞ
        'wi',   // ㅟ
        'yu',   // ㅠ
        'eu',   // ㅡ
        'ui',   // ㅢ
        'i',    // ㅣ
    );
    
    // 종성 목록 (번역표).
    
    protected static $charmap3 = array(
        '',     // 받침이 없는 경우
        'k',    // ㄱ
        'k',    // ㄲ
        'k',    // ㄳ
        'n',    // ㄴ
        'n',    // ㄵ
        'n',    // ㄶ
        't',    // ㄷ
        'l',    // ㄹ
        'k',    // ㄺ
        'm',    // ㄻ
        'p',    // ㄼ
        't',    // ㄽ
        't',    // ㄾ
        'p',    // ㄿ
        'l',    // ㅀ
        'm',    // ㅁ
        'p',    // ㅂ
        'p',    // ㅄ
        't',    // ㅅ
        't',    // ㅆ
        'ng',   // ㅇ
        't',    // ㅈ
        't',    // ㅊ
        'k',    // ㅋ
        't',    // ㅌ
        'p',    // ㅍ
        '',     // ㅎ
    );
    
    // 초성 목록 (순서표).
    
    protected static $ordmap1 = array(
        'ㄱ',
        'ㄲ',
        'ㄴ',
        'ㄷ',
        'ㄸ',
        'ㄹ',
        'ㅁ',
        'ㅂ',
        'ㅃ',
        'ㅅ',
        'ㅆ',
        'ㅇ',
        'ㅈ',
        'ㅉ',
        'ㅊ',
        'ㅋ',
        'ㅌ',
        'ㅍ',
        'ㅎ',
    );
    
    // 중성 목록 (순서표).
    
    protected static $ordmap2 = array(
        'ㅏ',
        'ㅐ',
        'ㅑ',
        'ㅒ',
        'ㅓ',
        'ㅔ',
        'ㅕ',
        'ㅖ',
        'ㅗ',
        'ㅘ',
        'ㅙ',
        'ㅚ',
        'ㅛ',
        'ㅜ',
        'ㅝ',
        'ㅞ',
        'ㅟ',
        'ㅠ',
        'ㅡ',
        'ㅢ',
        'ㅣ',
    );
    
    // 종성 목록 (순서표).
    
    protected static $ordmap3 = array(
        '   ',  // 받침이 없는 경우
        'ㄱ',
        'ㄲ',
        'ㄳ',
        'ㄴ',
        'ㄵ',
        'ㄶ',
        'ㄷ',
        'ㄹ',
        'ㄺ',
        'ㄻ',
        'ㄼ',
        'ㄽ',
        'ㄾ',
        'ㄿ',
        'ㅀ',
        'ㅁ',
        'ㅂ',
        'ㅄ',
        'ㅅ',
        'ㅆ',
        'ㅇ',
        'ㅈ',
        'ㅊ',
        'ㅋ',
        'ㅌ',
        'ㅍ',
        'ㅎ',
    );
}
