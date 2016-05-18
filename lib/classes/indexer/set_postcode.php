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

class Postcodify_Indexer_Set_Postcode
{
    // 엔트리 포인트.
    
    public function start($args)
    {
        // 주어진 옵션을 확인한다.
        
        $valid_options = false;
        if (count($args->args) === 2 && ctype_digit($args->args[0]) && ctype_digit($args->args[1]) && (strlen($args->args[1]) === 5 || strlen($args->args[1]) === 6))
        {
            $address_id = $args->args[0];
            $postcode = $args->args[1];
        }
        else
        {
            echo 'Usage: php indexer.php set-postcode <address-id> <postcode>' . PHP_EOL;
            exit(1);
        }
        
        // DB 연결을 확인한다.
        
        Postcodify_Utility::print_message('Postcodify Indexer ' . POSTCODIFY_VERSION);
        Postcodify_Utility::print_newline();
        
        if (!($db = Postcodify_Utility::get_db()))
        {
            echo '[ERROR] MySQL DB에 접속할 수 없습니다.' . PHP_EOL;
            exit(1);
        }
        
        // 주어진 주소가 존재하는지 확인한다.
        
        $ps_select = $db->prepare('SELECT pa.*, pr.* FROM postcodify_addresses pa JOIN postcodify_roads pr ON pa.road_id = pr.road_id WHERE pa.id = ? ORDER BY id LIMIT 1');
        $ps_select->execute(array($address_id));
        $entry = $ps_select->fetchObject();
        
        if (!$entry)
        {
            echo '[ERROR] "' . $address_id . '" 레코드를 찾을 수 없습니다.' . PHP_EOL;
            exit(1);
        }
        
        // 우편번호를 업데이트한다.
        
        $column_name = strlen($postcode) === 6 ? 'postcode6' : 'postcode5';
        $ps_update = $db->prepare('UPDATE postcodify_addresses SET ' . $column_name . ' = ? WHERE id = ?');
        $ps_update->execute(array($postcode, $entry->id));
        $entry->$column_name = $postcode;
        
        // 변경 내역을 표시한다.
        
        echo '  #' . $entry->id . ' ' . str_pad($entry->postcode6, 6, ' ') . ' ' . str_pad($entry->postcode5, 5, ' ') . ' ' .
            $this->format_address($entry) . PHP_EOL;
    }
    
    // 디버깅을 위해 주소를 포맷하는 메소드.
    
    public function format_address($entry)
    {
        $result = $entry->sido_ko . ' ' . $entry->sigungu_ko . ' ' . $entry->ilbangu_ko . ' ' . $entry->eupmyeon_ko . ' ' .
            $entry->road_name_ko . ' ' . $entry->num_major . ($entry->num_minor ? ('-' . $entry->num_minor) : '');
        return preg_replace('/\s+/', ' ', $result);
    }
}
