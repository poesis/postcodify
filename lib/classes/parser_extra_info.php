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

class Postcodify_Parser_Extra_Info extends Postcodify_ZipReader
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
        if ($line === false || count($line) < 9) return false;
        
        // 행정동명을 파악한다.
        
        $admin_dongri = trim($line[2]);
        if (!preg_match('/[동리]$/u', $admin_dongri)) $admin_dongri = null;
        
        // 건물명을 정리한다.
        
        $building_names = array();
        if (($building = trim($line[5])) !== '') $building_names[] = Postcodify_Utility::get_canonical($building);
        if (($building = trim($line[6])) !== '') $building_names[] = Postcodify_Utility::get_canonical($building);
        if (($building = trim($line[7])) !== '') $building_names[] = Postcodify_Utility::get_canonical($building);
        
        // 데이터를 정리하여 반환한다.
        
        return (object)array(
            'address_id' => trim($line[0]),
            'postcode6' => trim($line[3]),
            'admin_dongri' => $admin_dongri,
            'building_names' => array_unique($building_names),
            'common_residence_name' => intval(trim($line[8])) > 0 ? trim($line[6]) : null,
        );
    }
}
