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

class Postcodify_Server_Query
{
    // 기본 속성들.
    
    public $sido;
    public $sigungu;
    public $ilbangu;
    public $eupmyeon;
    public $dongri;
    public $road;
    public $pobox;
    public $numbers;
    public $buildings = array();
    public $use_area = false;
    public $lang = 'KO';
    public $sort = 'JUSO';
    
    // 검색어를 분석하여 쿼리 객체를 반환하는 메소드.
    
    public static function parse_keywords($keywords)
    {
        // 지번을 00번지 0호로 쓴 경우 검색 가능한 형태로 변환한다.
        
        $keywords = preg_replace('/([0-9]+)번지\\s?([0-9]+)호(?:\\s|$)/u', '$1-$2', $keywords);
        
        // 행정동, 도로명 등의 숫자 앞에 공백에 있는 경우 붙여쓴다.
        
        $keywords = preg_replace('/(^|\s)(?:' .
            '([가-힣]{1,3})\s+([0-9]{1,2}[동리가])|' .
            '([가-힣]+|[가-힣0-9.]+로)\s+([동서남북]?[0-9]+번?[가나다라마바사아자차카타파하동서남북안밖좌우옆갓상하샛윗아래]?[로길]))' .
            '(?=\s|\d|$)/u', '$1$2$3$4$5', $keywords, 1);
        
        // 검색어에서 불필요한 문자를 제거한다.
        
        $keywords = str_replace(array(',', '(', '|', ')'), ' ', $keywords);
        $keywords = preg_replace('/[^\\sㄱ-ㅎ가-힣a-z0-9@-]/u', '', strtolower($keywords));
        
        // 쿼리 객체를 초기화한다.
        
        $q = new Postcodify_Server_Query;
        
        // 영문 도로명주소 또는 지번주소인지 확인한다.
        
        if (preg_match('/^(?:b|san|jiha)?(?:\\s*|-)([0-9]+)?(?:-([0-9]+))?\\s*([a-z0-9-\x20]+(ro|gil|dong|ri))(?:\\s|$)/i', $keywords, $matches))
        {
            $addr_english = preg_replace('/[^a-z0-9]/', '', strtolower($matches[3]));
            $addr_type = strtolower($matches[4]);
            if ($addr_type === 'ro' || $addr_type === 'gil')
            {
                $q->road = $addr_english;
            }
            else
            {
                $q->dongri = $addr_english;
                $q->sort = 'JIBEON';
            }
            $q->numbers = array($matches[1] ? $matches[1] : null, $matches[2] ? $matches[2] : null);
            $q->lang = 'EN';
            return $q;
        }
        
        // 영문 사서함 주소인지 확인한다.
        
        if (preg_match('/p\\s*o\\s*box\\s*#?\\s*([0-9]+)(?:-([0-9]+))?/', $keywords, $matches))
        {
            $q->pobox = '사서함';
            $q->numbers = array($matches[1] ? $matches[1] : null, isset($matches[2]) ? $matches[2] : null);
            $q->lang = 'EN';
            $q->sort = 'POBOX';
            return $q;
        }
        
        // 검색어를 단어별로 분리한다.
        
        $keywords = preg_split('/\\s+/u', $keywords);
        
        // 각 단어의 의미를 파악한다.
        
        foreach ($keywords as $id => $keyword)
        {
            // 키워드가 "지하" 또는 한글 1글자인 경우 건너뛴다. ("읍", "면"은 예외)
            
            if ($keyword !== '읍' && $keyword !== '면' && ($keyword === '지하' || (mb_strlen($keyword, 'UTF-8') < 2 && !ctype_alnum($keyword))))
            {
                continue;
            }
            
            // 첫 번째 구성요소가 시도인지 확인한다.
            
            if ($id == 0 && count($keywords) > 1)
            {
                if (isset(Postcodify_Server_Areas::$sido[$keyword]))
                {
                    $q->sido = Postcodify_Server_Areas::$sido[$keyword];
                    $q->use_area = true;
                    continue;
                }
            }
            
            // 이미 건물명이 나온 경우 건물명만 계속 검색한다.
            
            if (count($q->buildings))
            {
                $keyword = preg_replace('/(?:[0-9a-z-]+|^[가나다라마바사])[동층호]?$/u', '', $keyword);
                if ($keyword !== '' && !in_array($keyword, $q->buildings))
                {
                    $q->buildings[] = preg_replace('/(?:아파트|a(?:pt)?|@)$/', '', $keyword);
                    continue;
                }
                else
                {
                    break;
                }
            }
            
            // 시군구읍면을 확인한다.
            
            if (preg_match('/.*([시군구읍면])$/u', $keyword, $matches))
            {
                if ($matches[1] === '읍' || $matches[1] === '면')
                {
                    if (!$q->sigungu && preg_match('/^(.+)군([읍면])$/u', $keyword, $gun) &&
                        in_array($gun[1] . '군', Postcodify_Server_Areas::$sigungu))
                    {
                        $q->sigungu = $gun[1] . '군';
                        $q->eupmyeon = $gun[1] . $gun[2];
                    }
                    elseif ($q->sigungu && ($keyword === '읍' || $keyword === '면'))
                    {
                        $q->eupmyeon = preg_replace('/군$/u', $keyword, $q->sigungu);
                    }
                    else
                    {
                        $q->eupmyeon = $keyword;
                    }
                    $q->use_area = true;
                    continue;
                }
                elseif (!$q->sigungu && in_array($keyword, Postcodify_Server_Areas::$sigungu))
                {
                    $q->sigungu = $keyword;
                    $q->use_area = true;
                    continue;
                }
                elseif (!$q->ilbangu && in_array($keyword, Postcodify_Server_Areas::$ilbangu))
                {
                    $q->ilbangu = $keyword;
                    $q->use_area = true;
                    continue;
                }
                else
                {
                    if (count($keywords) > $id + 1) continue;
                }
            }
            elseif (!$q->sigungu && in_array($keyword . '시', Postcodify_Server_Areas::$sigungu))
            {
                $q->sigungu = $keyword . '시';
                $q->use_area = true;
                continue;
            }
            elseif (!$q->sigungu && in_array($keyword . '군', Postcodify_Server_Areas::$sigungu))
            {
                $q->sigungu = $keyword . '군';
                $q->use_area = true;
                continue;
            }
            
            // 도로명+건물번호를 확인한다.
            
            if (preg_match('/^(.+[로길])((?:지하)?([0-9]+(?:-[0-9]+)?)(?:번지?)?)?$/u', $keyword, $matches))
            {
                $q->road = $matches[1];
                $q->sort = 'JUSO';
                if (isset($matches[3]) && $matches[3])
                {
                    $q->numbers = $matches[3];
                    break;
                }
                continue;
            }
            
            // 동리+지번을 확인한다.
            
            if (preg_match('/^(.{1,5}(?:[0-9]가|[동리가]))(산?([0-9]+(?:-[0-9]+)?)(?:번지?)?)?$/u', $keyword, $matches))
            {
                $q->dongri = $matches[1];
                $q->sort = 'JIBEON';
                if (isset($matches[3]) && $matches[3])
                {
                    $q->dongri = preg_replace(array('/[0-9]+동/u', '/[0-9]+가/u'), array('동', ''), $q->dongri);
                    $q->numbers = $matches[3];
                    break;
                }
                continue;
            }
            
            // 사서함을 확인한다.
            
            if (preg_match('/^(.*사서함)(([0-9]+(?:-[0-9]+)?)번?)?$/u', $keyword, $matches))
            {
                $q->pobox = $matches[1];
                $q->sort = 'POBOX';
                if (isset($matches[3]) && $matches[3])
                {
                    $q->numbers = $matches[3];
                    break;
                }
                continue;
            }
            
            // 건물번호, 지번, 사서함 번호를 따로 적은 경우를 확인한다.
            
            if (preg_match('/^(?:산|지하)?([0-9]+(?:-[0-9]+)?)(?:번지?)?$/u', $keyword, $matches))
            {
                if ($q->dongri)
                {
                    $q->dongri = preg_replace(array('/[0-9]+동/u', '/[0-9]+가/u'), array('동', ''), $q->dongri);
                }
                $q->numbers = $matches[1];
                break;
            }
            
            // 그 밖의 키워드는 건물명으로 취급하되, 동·층·호수는 취급하지 않는다.
            
            if (!preg_match('/(?:[0-9a-z-]+|^[가나다라마바사])[동층호]?$/u', $keyword))
            {
                $q->buildings[] = preg_replace('/(?:아파트|a(?:pt)?|@)$/', '', $keyword);
                continue;
            }
            
            // 그 밖의 키워드가 나오면 그만둔다. 
            
            break;
        }
        
        // 건물번호 또는 지번을 주번과 부번으로 분리한다.
        
        if (isset($q->numbers))
        {
            $q->numbers = explode('-', $q->numbers);
            if (!isset($q->numbers[1])) $q->numbers[1] = null;
        }
        else
        {
            $q->numbers = array(null, null);
        }
        
        // 쿼리 객체를 반환한다.
        
        return $q;
    }
    
    // 객체를 문자열로 변환할 경우 모든 내용을 붙여서 반환한다.
    
    public function __toString()
    {
        $result = array();
        if ($this->sido !== null) $result[] = $this->sido;
        if ($this->sigungu !== null) $result[] = $this->sigungu;
        if ($this->ilbangu !== null) $result[] = $this->ilbangu;
        if ($this->eupmyeon !== null) $result[] = $this->eupmyeon;
        if ($this->dongri !== null) $result[] = $this->dongri;
        if ($this->road !== null) $result[] = $this->road;
        if ($this->pobox !== null) $result[] = $this->pobox;
        if (isset($this->numbers[0])) $result[] = $this->numbers[0] .
            (isset($this->numbers[1]) ? ('-' . $this->numbers[1]) : '');
        if (count($this->buildings)) $result[] = implode(' ', $this->buildings);
        return implode(' ', $result);
    }
}
