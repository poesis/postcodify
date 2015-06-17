<?php

/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (인덱서)
 * 
 *  Copyright (c) 2014-2015, Poesis <root@poesis.kr>
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

class Postcodify_Parser_Updated_Address extends Postcodify_TextFileReader
{
    // 생성자에서 문자셋을 지정한다.
    
    public function __construct()
    {
        $this->_charset = 'CP949';
    }
    
    // 변경사유코드 목록.
    
    const CODE_NEW = 31;
    const CODE_CHANGED_JUSO = 34;
    const CODE_CHANGED_JIBEON = 51;
    const CODE_CHANGED_POSTCODE = 70;
    const CODE_CHANGED_BUILDING_NAME = 71;
    const CODE_DELETED = 63;
    
    // 한 줄을 읽어 반환한다.
    
    public function read_line($delimiter = '|')
    {
        // 데이터를 읽는다.
        
        $line = parent::read_line($delimiter);
        if ($line === false || count($line) < 27) return false;
        
        // 동·리 정보를 정리한다.
        
        $dongri = trim($line[4]);
        if ($dongri === '') $dongri = trim($line[3]);
        $dongri = preg_replace('/\\(.+\\)/', '', $dongri);
        if (preg_match('/[읍면]$/u', $dongri)) $dongri = null;
        
        $admin_dongri = trim($line[18]);
        if (!preg_match('/[동리]$/u', $admin_dongri)) $admin_dongri = null;
        
        // 건물명을 정리한다.
        
        $building_names = array();
        if (($building = trim($line[13])) !== '') $building_names[] = Postcodify_Utility::get_canonical($building);
        if (($building = trim($line[14])) !== '') $building_names[] = Postcodify_Utility::get_canonical($building);
        if (($building = trim($line[21])) !== '') $building_names[] = Postcodify_Utility::get_canonical($building);
        if (($building = trim($line[25])) !== '') $building_names[] = Postcodify_Utility::get_canonical($building);
        
        // 데이터를 정리하여 반환한다.
        
        return (object)array(
            'address_id' => trim($line[15]),
            'postcode6' => trim($line[19]),
            'road_id' => trim($line[8]),
            'road_section' => str_pad(trim($line[16]), 2, '0', STR_PAD_LEFT),
            'num_major' => $line[11] ? (int)$line[11] : null,
            'num_minor' => $line[12] ? (int)$line[12] : null,
            'is_basement' => (int)$line[10],
            'admin_dongri' => $admin_dongri,
            'dongri' => $dongri,
            'jibeon_major' => $line[6] ? (int)$line[6] : null,
            'jibeon_minor' => $line[7] ? (int)$line[7] : null,
            'is_mountain' => (int)$line[5],
            'building_names' => array_unique($building_names),
            'common_residence_name' => intval(trim($line[26])) > 0 ? trim($line[13]) : null,
            'change_date' => trim($line[23]),
            'change_code' => intval($line[22]),
        );
    }
}
