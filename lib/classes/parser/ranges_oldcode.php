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

class Postcodify_Parser_Ranges_OldCode extends Postcodify_ZipReader
{
    // 한 줄을 읽어 반환한다.
    
    public function read_line($delimiter = '|')
    {
        // 데이터를 읽는다.
        
        $line = fgetcsv($this->_fp);
        if ($line === false || count($line) < 15) return false;
        if (!ctype_digit($line[0])) return true;
        
        // 상세 데이터를 읽어들인다.
        
        $sido = trim($line[2]);
        $sigungu = trim($line[3]); if ($sigungu === '') $sigungu = null;
        $eupmyeon = trim($line[4]); if ($eupmyeon === '') $eupmyeon = null;
        $dongri = trim($line[5]); if ($dongri === '') $dongri = null;
        $island_name = trim($line[6]); if ($island_name === '') $island_name = null;
        $is_mountain = trim($line[7]) === '산' ? 1 : 0;
        $range_start_major = trim($line[8]); if (!$range_start_major) $range_start_major = null;
        $range_start_minor = trim($line[9]); if (!$range_start_minor) $range_start_minor = null;
        $range_end_major = trim($line[10]); if (!$range_end_major) $range_end_major = null;
        $range_end_minor = trim($line[11]); if (!$range_end_minor) $range_end_minor = null;
        $building_name = trim($line[12]); if ($building_name === '') $building_name = null;
        $building_num_start = trim($line[13]); if (!$building_num_start) $building_num_start = null;
        $building_num_end = trim($line[14]); if (!$building_num_end) $building_num_end = null;
        
        // 특별시/광역시 아래의 자치구와 행정시 아래의 일반구를 구분한다.
        
        if (($pos = strpos($sigungu, ' ')) !== false)
        {
            $ilbangu = substr($sigungu, $pos + 1);
            $sigungu = substr($sigungu, 0, $pos);
        }
        else
        {
            $ilbangu = null;
        }
        
        // 읍면과 동을 구분한다.
        
        if (preg_match('/[동로가]$/u', $eupmyeon) && $dongri === null)
        {
            $dongri = $eupmyeon;
            $eupmyeon = null;
        }
        
        // 데이터를 정리하여 반환한다.
        
        return (object)array(
            'postcode6' => trim($line[0]),
            'sido' => $sido,
            'sigungu' => $sigungu,
            'ilbangu' => $ilbangu,
            'eupmyeon' => $eupmyeon,
            'dongri' => $dongri,
            'range_start_major' => $range_start_major,
            'range_start_minor' => $range_start_minor,
            'range_end_major' => $range_end_major,
            'range_end_minor' => $range_end_minor,
            'is_mountain' => $is_mountain,
            'island_name' => $island_name,
            'building_name' => $building_name,
            'building_num_start' => $building_num_start,
            'building_num_end' => $building_num_end,
        );
    }
}
