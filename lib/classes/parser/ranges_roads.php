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

class Postcodify_Parser_Ranges_Roads extends Postcodify_ZipReader
{
    // 생성자에서 문자셋을 지정한다.
    
    public function __construct()
    {
        $this->_charset = 'UTF-8';
    }
    
    // 한 줄을 읽어 반환한다.
    
    public function read_line($delimiter = '|')
    {
        // 데이터를 읽는다.
        
        $line = parent::read_line($delimiter);
        if ($line === false || count($line) < 15) return false;
        if (!ctype_digit($line[0])) return true;
        
        // 상세 데이터를 읽어들인다.
        
        $sido_ko = trim($line[1]);
        $sido_en = str_replace('-si', '', trim($line[2]));
        $sigungu_ko = trim($line[3]); if (!$sigungu_ko) $sigungu_ko = null;
        $sigungu_en = trim($line[4]); if (!$sigungu_en) $sigungu_en = null;
        $eupmyeon_ko = trim($line[5]);
        $eupmyeon_en = trim($line[6]);
        $road_name_ko = trim($line[7]);
        $road_name_en = trim($line[8]);
        $is_basement = intval(trim($line[9])) ? 1 : 0;
        $range_start_major = trim($line[10]); if (!$range_start_major) $range_start_major = null;
        $range_start_minor = trim($line[11]); if (!$range_start_minor) $range_start_minor = null;
        $range_end_major = trim($line[12]); if (!$range_end_major) $range_end_major = null;
        $range_end_minor = trim($line[13]); if (!$range_end_minor) $range_end_minor = null;
        $range_type = intval(trim($line[14]));
        
        // 특별시/광역시 아래의 자치구와 행정시 아래의 일반구를 구분한다.
        
        if (($pos = strpos($sigungu_ko, ' ')) !== false)
        {
            $ilbangu_ko = substr($sigungu_ko, $pos + 1);
            $sigungu_ko = substr($sigungu_ko, 0, $pos);
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
            $ilbangu_ko = null;
            $ilbangu_en = null;
        }
        
        // 데이터를 정리하여 반환한다.
        
        return (object)array(
            'sido_ko' => $sido_ko,
            'sido_en' => $sido_en,
            'sigungu_ko' => $sigungu_ko,
            'sigungu_en' => $sigungu_en,
            'ilbangu_ko' => $ilbangu_ko,
            'ilbangu_en' => $ilbangu_en,
            'eupmyeon_ko' => $eupmyeon_ko,
            'eupmyeon_en' => $eupmyeon_en,
            'road_name_ko' => $road_name_ko,
            'road_name_en' => $road_name_en,
            'range_start_major' => $range_start_major,
            'range_start_minor' => $range_start_minor,
            'range_end_major' => $range_end_major,
            'range_end_minor' => $range_end_minor,
            'range_type' => $range_type,
            'is_basement' => $is_basement,
            'postcode5' => trim($line[0]),
        );
    }
}
