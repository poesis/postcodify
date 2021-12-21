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

class Postcodify_Server_Result
{
    public function __construct($error = '')
    {
        $this->version = POSTCODIFY_VERSION;
        $this->error = $error;
    }
    
    public $version = '';
    public $error = '';
    public $msg = '';
    public $count = 0;
    public $time = 0;
    public $lang = 'KO';
    public $sort = 'JUSO';
    public $type = '';
    public $nums = '';
    public $cache = 'MISS';
    public $results = array();
}

class Postcodify_Server_Record { }

class Postcodify_Server_Record_v17 extends Postcodify_Server_Record
{
    public $code6;
    public $code5;
    public $address;
    public $canonical;
    public $extra_info_long;
    public $extra_info_short;
    public $english_address;
    public $jibeon_address;
    public $other;
    public $dbid;
}

class Postcodify_Server_Record_v18 extends Postcodify_Server_Record
{
    public $code6;
    public $code5;
    public $address;
    public $english;
    public $other;
    public $dbid;
}

class Postcodify_Server_Record_v3 extends Postcodify_Server_Record
{
    public $postcode5;
    public $postcode6;
    public $ko_common;
    public $ko_doro;
    public $ko_jibeon;
    public $en_common;
    public $en_doro;
    public $en_jibeon;
    public $address_id;
    public $building_id;
    public $building_name;
    public $building_nums;
    public $other_addresses;
    public $road_id;
    public $internal_id;
}
