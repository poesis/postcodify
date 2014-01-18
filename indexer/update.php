<?php

// -------------------------------------------------------------------------------------------------
// 우편번호 DB 업데이트 프로그램.
// -------------------------------------------------------------------------------------------------

function do_updates()
{
    // 준비.

    $db = get_db();

    $ps_address_select1 = $db->prepare('SELECT postcode5, road_id, road_section FROM postcode_addresses ' .
        'WHERE road_id = ? AND road_section = ? LIMIT 1');
    $ps_address_select2 = $db->prepare('SELECT postcode5, road_id, road_section FROM postcode_addresses ' .
        'WHERE postcode6 = ? LIMIT 1');
    $ps_address_insert = $db->prepare('INSERT INTO postcode_addresses ' .
        '(id, postcode5, postcode6, road_id, road_section, road_name, ' .
        'num_major, num_minor, is_basement, sido, sigungu, ilbangu, eupmyeon, ' .
        'dongri, jibeon, building_name, other_addresses) ' .
        'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $ps_keyword_juso_delete = $db->prepare('DELETE FROM postcode_keywords_juso ' .
        'WHERE (address_id = ? OR address_id = ?) ' .
        'AND keyword_crc32 = ? AND num_major = ? AND num_minor = ?');
    $ps_keyword_juso_insert = $db->prepare('INSERT INTO postcode_keywords_juso ' .
        '(address_id, keyword_crc32, num_major, num_minor) ' .
        'VALUES (?, ?, ?, ?)');
    $ps_keyword_juso_update = $db->prepare('UPDATE postcode_keywords_juso ' .
        'SET address_id = ? WHERE address_id = ?');
    $ps_keyword_jibeon_delete = $db->prepare('DELETE FROM postcode_keywords_jibeon ' .
        'WHERE (address_id = ? OR address_id = ?) ' .
        'AND keyword_crc32 = ? AND num_major = ? AND num_minor = ?');
    $ps_keyword_jibeon_insert = $db->prepare('INSERT INTO postcode_keywords_jibeon ' .
        '(address_id, keyword_crc32, num_major, num_minor) ' .
        'VALUES (?, ?, ?, ?)');
    $ps_keyword_jibeon_update = $db->prepare('UPDATE postcode_keywords_jibeon ' .
        'SET address_id = ? WHERE address_id = ?');
    $ps_keyword_building_delete = $db->prepare('DELETE FROM postcode_keywords_building ' .
        'WHERE (address_id = ? OR address_id = ?) ' .
        'AND keyword = ? AND dongri_crc32_1 = ? AND dongri_crc32_2 = ?');
    $ps_keyword_building_insert = $db->prepare('INSERT INTO postcode_keywords_building ' .
        '(address_id, keyword, dongri_crc32_1, dongri_crc32_2, dongri_crc32_3, dongri_crc32_4) ' .
        'VALUES (?, ?, ?, ?, ?, ?)');
    $ps_keyword_building_update = $db->prepare('UPDATE postcode_keywords_building ' .
        'SET address_id = ? WHERE address_id = ?');
    
    $dongs = array();
    $code5s = array();
    $dels = array();
    
    // 트랜잭션을 시작한다.
    
    $db->beginTransaction();
    
    // 어디까지 업데이트했는지 찾아본다.
    
    $updated_query = $db->query('SELECT v FROM postcode_metadata WHERE k = \'updated\'');
    $updated = $updated_query->fetchColumn();
    $updated_query->closeCursor();
    
    // 업데이트 파일 목록을 구한다.
    
    $files = glob(TXT_DIRECTORY . '/Updates/AlterD.*');
    foreach ($files as $filename)
    {
        // 이미 적용한 업데이트는 건너뛴다.
        
        $filename_date = substr(basename($filename), 14, 8);
        if (!ctype_digit($filename_date)) continue;
        if ($filename_date <= $updated) continue;
        
        // 파일을 연다.
        
        echo '  -->  ' . basename($filename) . ' ... ';
        $count = 0;
        
        $fp = fopen($filename, 'r');
        while ($line = trim(fgets($fp)))
        {
            // 한 줄을 읽어 UTF-8로 변환하고, | 문자를 기준으로 데이터를 쪼갠다.
            
            $line = explode('|', iconv('EUC-KR', 'UTF-8', $line));
            if (count($line) < 27 || !ctype_digit($line[0])) continue;
            
            // 상세 데이터를 읽어들인다.
            
            $address_id = trim($line[15]);
            if (!preg_match('/^[0-9]{25}$/', $address_id)) continue;
            
            $postcode5 = null;
            $postcode6 = trim($line[19]);
            $road_id = trim($line[8]);
            $road_section = trim($line[16]);
            $road_name = trim($line[9]);
            $num_major = (int)trim($line[11]); if (!$num_major) $num_major = null;
            $num_minor = (int)trim($line[12]); if (!$num_minor) $num_minor = null;
            $is_basement = (int)trim($line[10]);
            
            $sido = trim($line[1]);
            $sigungu = trim($line[2]); if ($sigungu === '') $sigungu = null;
            $eupmyeon = trim($line[3]); if ($eupmyeon === '') $eupmyeon = null;
            $dongri = trim($line[4]); if ($dongri === '') $dongri = null;
            if ($dongri === null && !preg_match('/[읍면]$/u', $eupmyeon))
            {
                $dongri = $eupmyeon;
                $eupmyeon = null;
            }
            $jibeon_major = (int)trim($line[6]); if (!$jibeon_major) $jibeon_major = null;
            $jibeon_minor = (int)trim($line[7]); if (!$jibeon_minor) $jibeon_minor = null;
            $is_mountain = (int)trim($line[5]);
            
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
            
            // 행정동 및 건물명을 구한다.
            
            $admin_dong = trim($line[18]); if (!$admin_dong) $admin_dong = null;
            $building1 = trim($line[13]);
            $building2 = trim($line[14]);
            $building3 = trim($line[21]);
            $building4 = trim($line[25]);
            $is_common_residence = (int)trim($line[26]);
            
            // 공동주택명을 구한다.
            
            $building_name = ($is_common_residence && $building1 !== '') ? $building1 : null;
            if ($building_name !== null && preg_match('/^(.+)[0-9a-zA-Z-]+동$/uU', $building_name, $matches))
            {
                $building_name = trim($matches[1]);
            }
            
            // 변경사유와 이전 주소를 구한다.
            
            $change_reason = (int)trim($line[22]);
            $old_address_id = trim($line[24]);
            if (!preg_match('/^[0-9]{25}$/', $old_address_id)) $old_address_id = null;
            
            // 삭제된 경우...
            
            if ($change_reason == 61 || $change_reason == 62 || $change_reason == 63)
            {
                // 다시 추가할 경우에 대비하여 기초구역번호와 나머지 레코드를 저장해 둔다.
                
                $q = $db->query('SELECT * FROM postcode_addresses WHERE id = \'' . $address_id . '\' LIMIT 1');
                if ($row = $q->fetch(PDO::FETCH_ASSOC))
                {
                    $code5s[$row['road_id'] . $row['road_section']] = $row['postcode5'];
                    $dels[$address_id] = $row;
                }
                $q->closeCursor();
                
                $db->query('DELETE FROM postcode_addresses WHERE id = \'' . $address_id . '\'');
                $db->query('DELETE FROM postcode_keywords_juso WHERE address_id = \'' . $address_id . '\'');
                $db->query('DELETE FROM postcode_keywords_jibeon WHERE address_id = \'' . $address_id . '\'');
                $db->query('DELETE FROM postcode_keywords_building WHERE address_id = \'' . $address_id . '\'');
            }
            
            // 새 주소가 추가된 경우...
            
            elseif ($change_reason == 31)
            {
                // 이미 존재하는 주소일 경우 건너뛴다. (실제로는 이런 일이 발생하면 안됨)
                
                $q = $db->query('SELECT 1 FROM postcode_addresses WHERE id = \'' . $address_id . '\' LIMIT 1');
                if ($q->fetchColumn())
                {
                    unset($q);
                    continue;
                }
                
                // 삭제되었던 레코드인 경우 기존 정보를 활용한다.
                
                if (isset($dels[$address_id]))
                {
                    $postcode5 = $dels[$address_id]['postcode5'];
                    if (!$sido) $sido = $dels[$address_id]['sido'];
                    if (!$sigungu) $sido = $dels[$address_id]['sigungu'];
                    if (!$ilbangu) $sido = $dels[$address_id]['ilbangu'];
                    if (!$eupmyeon) $sido = $dels[$address_id]['eupmyeon'];
                    if (!$dongri) $sido = $dels[$address_id]['dongri'];
                    if (!$jibeon_major && !$jibeon_minor)
                    {
                        $jibeons = explode('-', $dels[$address_id]['jibeon']);
                        $jibeon_major = isset($jibeons[0]) ? $jibeons[0] : null;
                        $jibeon_minor = isset($jibeons[1]) ? $jibeons[1] : null;
                    }
                    if (!$num_major && !$num_minor)
                    {
                        $num_major = $dels[$address_id]['num_major'];
                        $num_minor = $dels[$address_id]['num_minor'];
                    }
                    unset($dels[$address_id]);
                }
                
                // 현행 매칭테이블에는 기초구역번호가 없으므로, 가장 가까운 기초구역번호를 구한다.
                
                elseif (isset($code5s[$road_id . $road_section]))
                {
                    $postcode5 = $code5s[$road_id . $road_section];
                }
                else
                {
                    $postcode5 = null;
                    $ps_address_select1->execute(array($road_id, $road_section));
                    if ($c5row = $ps_address_select1->fetch(PDO::FETCH_NUM))
                    {
                        $postcode5 = $c5row[0];
                    }
                    $ps_address_select1->closeCursor();
                    if ($c5row === null)
                    {
                        $ps_address_select2->execute(array($postcode6));
                        if ($c5row = $ps_address_select2->fetch(PDO::FETCH_NUM))
                        {
                            $postcode5 = $c5row[0];
                        }
                        $ps_address_select2->closeCursor();
                    }
                }
                
                // 건물명을 조합한다.
                
                $buildings = array();
                if ($building1 !== '') $buildings[] = $building1;
                if ($building2 !== '') $buildings[] = $building2;
                if ($building3 !== '') $buildings[] = $building3;
                if ($building4 !== '') $buildings[] = $building4;
                $buildings = array_unique($buildings);
                natsort($buildings);
                
                // postcode_addresses 테이블에 삽입한다.
                
                $ps_address_insert->execute(array(
                    $address_id, $postcode5, $postcode6,
                    $road_id, $road_section, $road_name, $num_major, $num_minor, $is_basement,
                    $sido, $sigungu, $ilbangu, $eupmyeon, $dongri,
                    ($jibeon_major ? ($jibeon_major . ($jibeon_minor ? ('-' . $jibeon_minor) : '')) : null),
                    $building_name, implode('; ', $buildings)
                ));
                
                // postcode_keywords_juso 테이블에 도로명주소 키워드를 저장한다.
                
                $keywords = get_variations_of_road_name(get_canonical($road_name));
                
                foreach ($keywords as $keyword)
                {
                    $ps_keyword_juso_insert->execute(array($address_id, crc32_x64($keyword), $num_major, $num_minor));
                }
                
                // postcode_keywords_jibeon 테이블에 지번주소 키워드를 저장한다.
                
                $keywords = get_variations_of_dongri($dongri, $dongs);
                if ($admin_dong) $keywords += get_variations_of_dongri($admin_dong, $dongs);
                $keywords = array_unique($keywords);
                
                foreach ($keywords as $keyword)
                {
                    $ps_keyword_jibeon_insert->execute(array($address_id, crc32_x64($keyword), $jibeon_major, $jibeon_minor));
                }
                
                // postcode_keywords_building 테이블에 건물명 키워드를 저장한다.
                
                $keywords = array();
                if ($building1 !== '')
                {
                    $keywords = $keywords + get_variations_of_building_name(get_canonical($building1));
                }
                if ($building2 !== '')
                {
                    $keywords = $keywords + get_variations_of_building_name(get_canonical($building2));
                }
                if ($building3 !== '')
                {
                    $keywords = $keywords + get_variations_of_building_name(get_canonical($building3));
                }
                if ($building4 !== '')
                {
                    $keywords = $keywords + get_variations_of_building_name(get_canonical($building4));
                }
                $keywords = array_unique($keywords);
                
                $dongris = array();
                if ($legal_dong = $dongri) $dongris[] = crc32_x64($legal_dong);
                if ($admin_dong)
                {
                    $dongris2 = get_variations_of_dongri($admin_dong, $dongs[$filename]);
                    foreach ($dongris2 as $dongri2) $dongris[] = crc32_x64($dongri2);
                    $dongris = array_values(array_unique($dongris));
                }
                $dongris[] = null; $dongris[] = null; $dongris[] = null; $dongris[] = null;
                
                foreach ($keywords as $keyword)
                {
                    if (isset($keywords_dongs[$keyword])) continue;
                    $ps_keyword_building_insert->execute(array($address_id, $keyword, $dongris[0], $dongris[1], $dongris[2], $dongris[3]));
                }
            }
            
            // 변경된 경우...
            
            else
            {
                // 기존의 데이터가 있는지 확인한다. (있어야 정상이지만 현행 매칭테이블이 워낙 엉망이라 없을 수도 있다.)
                // 있는 경우 삭제하되, 기초구역번호와 기타 지번 정보 등은 재사용할 수 있도록 저장해 둔다.
                
                if ($old_address_id === null)
                {
                    $old_address_id = $address_id;
                    $old_address_id_is_different = true;
                }
                else
                {
                    $old_address_id_is_different = false;
                }
                
                $q = $db->query('SELECT * FROM postcode_addresses WHERE id = \'' . $address_id . '\' OR id = \'' . $old_address_id . '\' ORDER BY id LIMIT 1');
                $row = $q->fetch(PDO::FETCH_ASSOC);
                $q->closeCursor();
                if ($row)
                {
                    $code5s[$row['road_id'] . $row['road_section']] = $row['postcode5'];
                    $q = $db->query('DELETE FROM postcode_addresses WHERE id = \'' . $address_id . '\' OR id = \'' . $old_address_id . '\'');
                }
                
                // 기존의 데이터가 없는 경우 가장 가까운 기초구역번호를 구한다.
                
                else
                {
                    if (isset($code5s[$road_id . $road_section]))
                    {
                        $postcode5 = $code5s[$road_id . $road_section];
                    }
                    else
                    {
                        $postcode5 = null;
                        $ps_address_select1->execute(array($road_id, $road_section));
                        if ($c5row = $ps_address_select1->fetch(PDO::FETCH_NUM))
                        {
                            $postcode5 = $c5row[0];
                        }
                        $ps_address_select1->closeCursor();
                        if ($c5row === null)
                        {
                            $ps_address_select2->execute(array($postcode6));
                            if ($c5row = $ps_address_select2->fetch(PDO::FETCH_NUM))
                            {
                                $postcode5 = $c5row[0];
                            }
                            $ps_address_select2->closeCursor();
                        }
                    }
                }
                
                // 건물명을 조합한다.
                
                $buildings = array();
                if ($building1 !== '') $buildings[] = $building1;
                if ($building2 !== '') $buildings[] = $building2;
                if ($building3 !== '') $buildings[] = $building3;
                if ($building4 !== '') $buildings[] = $building4;
                $buildings = array_unique($buildings);
                natsort($buildings);
                
                // postcode_addresses 테이블에 새 정보를 삽입한다.
                
                $ps_address_insert->execute(array(
                    $address_id, ($row ? $row['postcode5'] : $postcode5), $postcode6,
                    $road_id, $road_section, $road_name, $num_major, $num_minor, $is_basement,
                    $sido, $sigungu, $ilbangu, $eupmyeon, $dongri,
                    ($jibeon_major ? ($jibeon_major . ($jibeon_minor ? ('-' . $jibeon_minor) : '')) : null),
                    $building_name, ($row ? $row['other_addresses'] : implode('; ', $buildings))
                ));
                
                // postcode_keywords_juso 테이블에 도로명주소 키워드를 저장한다.
                
                $keywords = get_variations_of_road_name(get_canonical($road_name));
                
                foreach ($keywords as $keyword)
                {
                    $ps_keyword_juso_delete->execute(array($address_id, $old_address_id, crc32_x64($keyword), $num_major, $num_minor));
                    $ps_keyword_juso_insert->execute(array($address_id, crc32_x64($keyword), $num_major, $num_minor));
                }
                
                // postcode_keywords_jibeon 테이블에 지번주소 키워드를 저장한다.
                
                $keywords = get_variations_of_dongri($dongri, $dongs);
                if ($admin_dong) $keywords += get_variations_of_dongri($admin_dong, $dongs);
                $keywords = array_unique($keywords);
                
                foreach ($keywords as $keyword)
                {
                    $ps_keyword_jibeon_delete->execute(array($address_id, $old_address_id, crc32_x64($keyword), $jibeon_major, $jibeon_minor));
                    $ps_keyword_jibeon_insert->execute(array($address_id, crc32_x64($keyword), $jibeon_major, $jibeon_minor));
                }
                
                // postcode_keywords_building 테이블에 건물명 키워드를 저장한다.
                
                $keywords = array();
                if ($building1 !== '')
                {
                    $keywords = $keywords + get_variations_of_building_name(get_canonical($building1));
                }
                if ($building2 !== '')
                {
                    $keywords = $keywords + get_variations_of_building_name(get_canonical($building2));
                }
                if ($building3 !== '')
                {
                    $keywords = $keywords + get_variations_of_building_name(get_canonical($building3));
                }
                if ($building4 !== '')
                {
                    $keywords = $keywords + get_variations_of_building_name(get_canonical($building4));
                }
                $keywords = array_unique($keywords);
                
                $dongris = array();
                if ($legal_dong = $dongri) $dongris[] = crc32_x64($legal_dong);
                if ($admin_dong)
                {
                    $dongris2 = get_variations_of_dongri($admin_dong, $dongs[$filename]);
                    foreach ($dongris2 as $dongri2) $dongris[] = crc32_x64($dongri2);
                    $dongris = array_values(array_unique($dongris));
                }
                $dongris[] = null; $dongris[] = null; $dongris[] = null; $dongris[] = null;
                
                /*
                if ($address_id == '1120011400106560292007878')
                {
                    var_dump($dongri);
                    var_dump($admin_dong);
                    var_dump(get_variations_of_dongri($admin_dong, $dongs[$filename]));
                    exit;
                }
                */
                
                foreach ($keywords as $keyword)
                {
                    if (isset($keywords_dongs[$keyword])) continue;
                    $ps_keyword_building_delete->execute(array($address_id, $old_address_id, $keyword, $dongris[0], $dongris[1]));
                    $ps_keyword_building_insert->execute(array($address_id, $keyword, $dongris[0], $dongris[1], $dongris[2], $dongris[3]));
                }
                
                // 기존의 관리번호로 연결되는 키워드들을 새 관리번호로 연결한다.
                
                if ($old_address_id_is_different)
                {
                    $ps_keyword_juso_update->execute(array($address_id, $old_address_id));
                    $ps_keyword_jibeon_update->execute(array($address_id, $old_address_id));
                    $ps_keyword_building_update->execute(array($address_id, $old_address_id));
                }
            }
            
            // 통계에 반영한다.
            
            $count++;
            
            // 가비지 컬렉션.
            
            if (isset($buildings)) unset($buildings);
            if (isset($keywords)) unset($keywords);
            if (isset($jibeons)) unset($jibeons);
            if (isset($row)) unset($row);
            if (isset($dongris)) unset($dongris);
            if (isset($dongris2)) unset($dongris2);
            unset($line);
        }
        
        echo str_pad(number_format($count, 0), 10, ' ', STR_PAD_LEFT) . "\n";
    }
    
    // 어디까지 업데이트했는지 기록한다.
    
    if (isset($filename_date) && $filename_date > $updated)
    {
        $db->query('UPDATE postcode_metadata SET v = \'' . $filename_date . '\' WHERE k = \'updated\'');
    }
    else
    {
        echo '  -->  업데이트가 없습니다.' . "\n";
    }
    
    // 트랜잭션을 마친다.
    
    $db->commit();
    echo "\n";
}

// -------------------------------------------------------------------------------------------------
// update.php를 직접 실행한 경우에는 아래의 코드를 실행한다.
// -------------------------------------------------------------------------------------------------

if (!defined('CONVERTING'))
{
    ini_set('display_errors', 'On');
    ini_set('memory_limit', '1024M');
    date_default_timezone_set('UTC');
    error_reporting(-1);
    gc_enable();

    require dirname(__FILE__) . '/config.php';
    require dirname(__FILE__) . '/functions.php';
    
    do_updates();
}
