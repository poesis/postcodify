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

class Postcodify_Parser_Road_List extends Postcodify_ZipReader
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
        if ($line === false || count($line) < 20) return false;
        
        // 주소의 각 구성요소를 파악한다.
        
        $road_id = trim($line[0]) . trim($line[1]);
        $road_section = str_pad(trim($line[4]), 2, '0', STR_PAD_LEFT);
        $road_name = trim($line[2]);
        $road_name_en = trim($line[3]);
        $sido = trim($line[5]);
        $sido_en = str_replace('-si', '', trim($line[15]));
        $sigungu = trim($line[6]);
        $sigungu_en = trim($line[16]);
        $eupmyeon = trim($line[9]);
        $eupmyeon_en = trim($line[17]);
        $parent_road_id = $line[10] === '' ? null : (trim($line[0]) . trim($line[10]));
        $previous_road_id = $line[14] === '' ? null : trim($line[14]);
        $change_reason = $line[13] === '' ? null : intval($line[13]);
        $updated = trim($line[18]);
        
        // 동 정보는 여기서 기억할 필요가 없다.
        
        if ($eupmyeon === '' || !preg_match('/[읍면]$/u', $eupmyeon))
        {
            $eupmyeon = null;
            $eupmyeon_en = null;
        }
        
        // 특별시/광역시 아래의 자치구와 행정시 아래의 일반구를 구분한다.
        
        if (($pos = strpos($sigungu, ' ')) !== false)
        {
            $ilbangu = substr($sigungu, $pos + 1);
            $sigungu = substr($sigungu, 0, $pos);
            if (($engpos = strpos($sigungu_en, ',')) !== false)
            {
                $ilbangu_en = trim(substr($sigungu_en, 0, $engpos));
                $sigungu_en = trim(substr($sigungu_en, $engpos + 1));
            }
            else
            {
                $ilbangu_en = null;
            }
        }
        else
        {
            $ilbangu = null;
            $ilbangu_en = null;
        }
        
        // 시군구가 없는 경우(세종시)를 처리한다.
        
        if ($sigungu === '')
        {
            $sigungu = null;
            $sigungu_en = null;
        }
        
        // 데이터를 정리하여 반환한다.
        
        return (object)array(
            'road_id' => $road_id,
            'road_section' => $road_section,
            'road_name_ko' => $road_name,
            'road_name_en' => $road_name_en,
            'sido_ko' => $sido,
            'sido_en' => $sido_en,
            'sigungu_ko' => $sigungu,
            'sigungu_en' => $sigungu_en,
            'ilbangu_ko' => $ilbangu,
            'ilbangu_en' => $ilbangu_en,
            'eupmyeon_ko' => $eupmyeon,
            'eupmyeon_en' => $eupmyeon_en,
            'parent_road_id' => $parent_road_id,
            'previous_road_id' => $previous_road_id,
            'change_reason' => $change_reason,
            'updated' => $updated,
        );
    }
}
