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

class Postcodify_Indexer_Parser_Road_List extends Postcodify_Indexer_ZipReader
{
    // 생성자에서 문자셋을 지정한다.
    
    public function __construct()
    {
        $this->_charset = 'CP949';
    }
    
    // 한 줄을 읽어 반환한다.
    
    public function read_line($delimiter = '|')
    {
        // 데이터를 읽는다.
        
        $line = parent::read_line($delimiter);
        if ($line === false || count($line) < 17) return false;
        
        // 주소의 각 구성요소를 파악한다.
        
        $road_id = trim($line[0]);
        $road_section = str_pad(trim($line[3]), 2, '0', STR_PAD_LEFT);
        $road_name = trim($line[1]);
        $road_name_english = trim($line[2]);
        $sido = trim($line[4]);
        $sido_english = str_replace('-si', '', trim($line[5]));
        $sigungu = trim($line[6]);
        $sigungu_english = trim($line[7]);
        $eupmyeon = trim($line[8]);
        $eupmyeon_english = trim($line[9]);
        
        // 동 정보는 여기서 기억할 필요가 없다.
        
        if ($eupmyeon === '' || preg_match('/동$/u', $eupmyeon))
        {
            $eupmyeon = null;
            $eupmyeon_english = null;
        }
        
        // 특별시/광역시 아래의 자치구와 행정시 아래의 일반구를 구분한다.
        
        if (($pos = strpos($sigungu, ' ')) !== false)
        {
            $ilbangu = substr($sigungu, $pos + 1);
            $sigungu = substr($sigungu, 0, $pos);
            if (($engpos = strpos($sigungu_english, ',')) !== false)
            {
                $sigungu_english = trim(substr($sigungu_english, $engpos + 1));
                $ilbangu_english = trim(substr($sigungu_english, 0, $engpos));
            }
            else
            {
                $ilbangu_english = null;
            }
        }
        else
        {
            $ilbangu = null;
            $ilbangu_english = null;
        }
        
        // 데이터를 정리하여 반환한다.
        
        return array(
            'road_id' => $road_id,
            'road_section' => $road_section,
            'road_name' => $road_name,
            'road_name_english' => $road_name_english,
            'sido' => $sido,
            'sido_english' => $sido_english,
            'sigungu' => $sigungu,
            'sigungu_english' => $sigungu_english,
            'ilbangu' => $ilbangu,
            'ilbangu_english' => $ilbangu_english,
            'eupmyeon' => $eupmyeon,
            'eupmyeon_english' => $eupmyeon_english,
        );
    }
}
