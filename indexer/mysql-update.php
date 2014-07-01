<?php

// -------------------------------------------------------------------------------------------------
// 우편번호 DB 업데이트 프로그램.
// -------------------------------------------------------------------------------------------------

ini_set('display_errors', 'on');
ini_set('memory_limit', '1024M');
date_default_timezone_set('UTC');
error_reporting(-1);
gc_enable();

// 시작한 시각을 기억한다.

$start_time = time();

// 설정과 함수 파일을 인클루드한다.

define('INDEXER_VERSION', '1.5.1');
require dirname(__FILE__) . '/config.php';
require dirname(__FILE__) . '/functions.php';
echo "\n";

// 어디까지 업데이트했는지 찾아본다.

$updated_query = get_db()->query('SELECT v FROM postcodify_metadata WHERE k = \'updated\'');
$updated = $updated_query->fetchColumn();
$updated_query->closeCursor();

// -------------------------------------------------------------------------------------------------
// 전체 도로명 코드 및 행정구역의 영문 명칭을 읽어들인다.
// -------------------------------------------------------------------------------------------------

if (!file_exists(TXT_DIRECTORY . '/도로명코드_전체분.zip'))
{
    echo '[ERROR] 도로명코드_전체분.zip 파일을 찾을 수 없습니다.' . "\n\n";
    exit(1);
}

echo '[Step 1/3] 도로 목록 및 영문 명칭을 메모리에 읽어들이는 중 ... ' . "\n\n";

$english_cache = array();
$roads_count = 0;

// 파일을 연다.

$filename = TXT_DIRECTORY . '/도로명코드_전체분.zip';
echo '  -->  ' . basename($filename) . ' ... ' . str_repeat(' ', 10);

$zip = new ZipArchive;
$zip->open($filename);
$fp = $zip->getStream($zip->getNameIndex(0));

while ($line = trim(fgets($fp)))
{
    // 한 줄을 읽어 UTF-8로 변환하고, | 문자를 기준으로 데이터를 쪼갠다.
    
    $line = explode('|', iconv('CP949', 'UTF-8', $line));
    if (count($line) < 17 || !ctype_digit($line[0])) continue;
    
    // 도로ID, 도로명, 통과하는 읍면동별 구간번호, 소속 행정구역을 읽어들인다.
    
    $road_id = trim($line[0]);
    $road_name = trim($line[1]);
    $road_section = str_pad(trim($line[3]), 2, '0', STR_PAD_LEFT);
    $sido = trim($line[4]);
    $sigungu = trim($line[6]);
    $eupmyeon = trim($line[8]);
    
    // 동 정보는 여기서 기억할 필요가 없다.
    
    $eupmyeon_suffix = strlen($eupmyeon) > 3 ? substr($eupmyeon, strlen($eupmyeon) - 3) : null;
    if ($eupmyeon_suffix !== '읍' && $eupmyeon_suffix !== '면')
    {
        $eupmyeon = '';
    }
    
    // 영문 주소를 읽어들인다.
    
    $english = array();
    $english[] = $english_cache[$road_name] = trim($line[2]);
    if ($eupmyeon !== '') $english[] = $english_cache[trim($line[8])] = trim($line[9]);
    $english[] = $english_cache[trim($line[6])] = trim($line[7]);
    $english[] = $english_cache[$sido] = str_replace('-si', '', trim($line[5]));
    $english = str_replace(', , ', ', ', implode(', ', $english));
    
    // 상태를 표시한다.
    
    if ($roads_count % 1024 == 0)
    {
        echo "\033[10D" . str_pad(number_format($roads_count, 0), 10, ' ', STR_PAD_LEFT);
    }
    
    $roads_count++;
    
    // 가비지 컬렉션.
    
    unset($road_info);
    unset($line);
}

// 마무리...

echo "\033[10D" . str_pad(number_format($roads_count, 0), 10, ' ', STR_PAD_LEFT) . "\n\n";
$zip->close();
unset($zip);

// -------------------------------------------------------------------------------------------------
// 새로 생긴 도로명의 영문 명칭을 읽어들인다.
// -------------------------------------------------------------------------------------------------

echo '[Step 2/3] 신규 도로명의 영문 명칭을 읽어들이는 중 ... ' . "\n\n";

$files = @glob(TXT_DIRECTORY . '/Updates/AlterD.JUSUZC.*');
if (!$files) $files = array();
foreach ($files as $filename)
{
    // 파일을 연다.
    
    echo '  -->  ' . basename($filename) . ' ... ';
    $count = 0;
    
    $fp = fopen($filename, 'r');
    while ($line = trim(fgets($fp)))
    {
        // 한 줄을 읽어 UTF-8로 변환하고, | 문자를 기준으로 데이터를 쪼갠다.
        
        $line = explode('|', iconv('CP949', 'UTF-8', $line));
        if (count($line) < 20 || !ctype_digit($line[0])) continue;
        
        // 상세 데이터를 읽어들인다.
        
        $road_name_ko = trim($line[2]);
        $road_name_en = trim($line[3]);
        $english_cache[$road_name_ko] = $road_name_en;
        $count++;
    }
    
    fclose($fp);
    echo str_pad(number_format($count, 0), 10, ' ', STR_PAD_LEFT) . "\n";
}

echo "\n";

// -------------------------------------------------------------------------------------------------
// 업데이트된 주소 목록을 적용한다.
// -------------------------------------------------------------------------------------------------

echo '[Step 3/3] 주소 목록을 업데이트하는 중 ... ' . "\n\n";

function do_updates()
{
    // 준비.

    $db = get_db();

    $ps_address_select1 = $db->prepare('SELECT postcode5, road_id, road_section FROM postcodify_addresses ' .
        'WHERE road_id = ? AND road_section = ? AND (num_major % 2) = ? LIMIT 1');
    $ps_address_select2 = $db->prepare('SELECT postcode5, road_id, road_section FROM postcodify_addresses ' .
        'WHERE postcode6 = ? LIMIT 1');
    $ps_address_insert = $db->prepare('INSERT INTO postcodify_addresses ' .
        '(id, postcode5, postcode6, road_id, road_section, road_name, ' .
        'num_major, num_minor, is_basement, sido, sigungu, ilbangu, eupmyeon, ' .
        'dongri, jibeon, building_name, english_address, other_addresses, updated) ' .
        'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $ps_keyword_juso_delete = $db->prepare('DELETE FROM postcodify_keywords_juso ' .
        'WHERE (address_id = ? OR address_id = ?) ' .
        'AND keyword_crc32 = ? AND num_major = ? AND num_minor = ?');
    $ps_keyword_juso_insert = $db->prepare('INSERT INTO postcodify_keywords_juso ' .
        '(address_id, keyword_crc32, num_major, num_minor) ' .
        'VALUES (?, ?, ?, ?)');
    $ps_keyword_juso_update = $db->prepare('UPDATE postcodify_keywords_juso ' .
        'SET address_id = ? WHERE address_id = ?');
    $ps_keyword_jibeon_delete = $db->prepare('DELETE FROM postcodify_keywords_jibeon ' .
        'WHERE (address_id = ? OR address_id = ?) ' .
        'AND keyword_crc32 = ? AND num_major = ? AND num_minor = ?');
    $ps_keyword_jibeon_insert = $db->prepare('INSERT INTO postcodify_keywords_jibeon ' .
        '(address_id, keyword_crc32, num_major, num_minor) ' .
        'VALUES (?, ?, ?, ?)');
    $ps_keyword_jibeon_update = $db->prepare('UPDATE postcodify_keywords_jibeon ' .
        'SET address_id = ? WHERE address_id = ?');
    $ps_keyword_building_delete = $db->prepare('DELETE FROM postcodify_keywords_building ' .
        'WHERE (address_id = ? OR address_id = ?) ' .
        'AND keyword = ?');
    $ps_keyword_building_insert = $db->prepare('INSERT INTO postcodify_keywords_building ' .
        '(address_id, keyword) ' .
        'VALUES (?, ?)');
    $ps_keyword_building_update = $db->prepare('UPDATE postcodify_keywords_building ' .
        'SET address_id = ? WHERE address_id = ?');
    
    $dongs = array();
    $code5s = array();
    $dels = array();
    
    // 트랜잭션을 시작한다.
    
    $db->beginTransaction();
    global $updated;
    global $english_cache;
    
    // 업데이트 파일 목록을 구한다.
    
    $files = @glob(TXT_DIRECTORY . '/Updates/AlterD.JUSUBH.*');
    if (!$files) $files = array();
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
            
            $line = explode('|', iconv('CP949', 'UTF-8', $line));
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
            
            // 도로명의 '똠'자가 이상하게 표현된 경우를 파악한다.
            
            if ($sido === '전라남도' && preg_match('/^(.+)(.c|\\?|？)길$/u', $road_name, $matches))
            {
                $road_name = $matches[1] . '똠길';
            }
            
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
                
                $q = $db->query('SELECT * FROM postcodify_addresses WHERE id = \'' . $address_id . '\' LIMIT 1');
                if ($row = $q->fetch(PDO::FETCH_ASSOC))
                {
                    $code5s[$row['road_id'] . $row['road_section']] = $row['postcode5'];
                    $dels[$address_id] = $row;
                }
                $q->closeCursor();
                
                $db->query('DELETE FROM postcodify_addresses WHERE id = \'' . $address_id . '\'');
                $db->query('DELETE FROM postcodify_keywords_juso WHERE address_id = \'' . $address_id . '\'');
                $db->query('DELETE FROM postcodify_keywords_jibeon WHERE address_id = \'' . $address_id . '\'');
                $db->query('DELETE FROM postcodify_keywords_building WHERE address_id = \'' . $address_id . '\'');
            }
            
            // 새 주소가 추가된 경우...
            
            elseif ($change_reason == 31)
            {
                // 이미 존재하는 주소일 경우 건너뛴다. (실제로는 이런 일이 발생하면 안됨)
                
                $q = $db->query('SELECT 1 FROM postcodify_addresses WHERE id = \'' . $address_id . '\' LIMIT 1');
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
                    $ps_address_select1->execute(array($road_id, $road_section, $num_major % 2));
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
                
                // 영문 주소를 생성한다.
                
                $english = ($is_basement ? 'Jiha ' : '') . $num_major . ($num_minor ? ('-' . $num_minor) : '') . ', ' . $english_cache[$road_name] . ', ';
                if ($eupmyeon) $english .= $english_cache[$eupmyeon] . ', ';
                if ($sigungu)
                {
                    if ($ilbangu)
                    {
                        $english .= $english_cache[$sigungu . ' ' . $ilbangu] . ', ';
                    }
                    else
                    {
                        $english .= $english_cache[$sigungu] . ', ';
                    }
                }
                if ($sido)
                {
                    $english .= $english_cache[$sido];
                }
                
                // 건물명을 조합한다.
                
                $buildings = array();
                if ($building1 !== '') $buildings[] = $building1;
                if ($building2 !== '') $buildings[] = $building2;
                if ($building3 !== '') $buildings[] = $building3;
                if ($building4 !== '') $buildings[] = $building4;
                $buildings = array_unique($buildings);
                natsort($buildings);
                
                // postcodify_addresses 테이블에 삽입한다.
                
                $ps_address_insert->execute(array(
                    $address_id, $postcode5, $postcode6,
                    $road_id, $road_section, $road_name, $num_major, $num_minor, $is_basement,
                    $sido, $sigungu, $ilbangu, $eupmyeon, $dongri,
                    ($jibeon_major ? ($jibeon_major . ($jibeon_minor ? ('-' . $jibeon_minor) : '')) : null),
                    $building_name, $english, implode('; ', $buildings),
                    $filename_date
                ));
                
                // postcodify_keywords_juso 테이블에 도로명주소 키워드를 저장한다.
                
                $keywords = get_variations_of_road_name(get_canonical($road_name));
                
                foreach ($keywords as $keyword)
                {
                    $ps_keyword_juso_insert->execute(array($address_id, crc32_x64($keyword), $num_major, $num_minor));
                }
                
                // postcodify_keywords_jibeon 테이블에 지번주소 키워드를 저장한다.
                
                $keywords = get_variations_of_dongri($dongri, $dongs);
                if ($admin_dong) $keywords += get_variations_of_dongri($admin_dong, $dongs);
                $keywords = array_unique($keywords);
                
                foreach ($keywords as $keyword)
                {
                    $ps_keyword_jibeon_insert->execute(array($address_id, crc32_x64($keyword), $jibeon_major, $jibeon_minor));
                }
                
                // postcodify_keywords_building 테이블에 건물명 키워드를 저장한다.
                
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
                
                foreach ($keywords as $keyword)
                {
                    if (isset($keywords_dongs[$keyword])) continue;
                    $ps_keyword_building_insert->execute(array($address_id, $keyword));
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
                
                $q = $db->query('SELECT * FROM postcodify_addresses WHERE id = \'' . $address_id . '\' OR id = \'' . $old_address_id . '\' ORDER BY id LIMIT 1');
                $row = $q->fetch(PDO::FETCH_ASSOC);
                $q->closeCursor();
                if ($row)
                {
                    $code5s[$row['road_id'] . $row['road_section']] = $row['postcode5'];
                    $q = $db->query('DELETE FROM postcodify_addresses WHERE id = \'' . $address_id . '\' OR id = \'' . $old_address_id . '\'');
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
                        $ps_address_select1->execute(array($road_id, $road_section, $num_major % 2));
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
                
                // 영문 주소를 생성한다.
                
                $english = ($is_basement ? 'Jiha ' : '') . $num_major . ($num_minor ? ('-' . $num_minor) : '') . ', ' . $english_cache[$road_name] . ', ';
                if ($eupmyeon) $english .= $english_cache[$eupmyeon] . ', ';
                if ($sigungu)
                {
                    if ($ilbangu)
                    {
                        $english .= $english_cache[$sigungu . ' ' . $ilbangu] . ', ';
                    }
                    else
                    {
                        $english .= $english_cache[$sigungu] . ', ';
                    }
                }
                if ($sido)
                {
                    $english .= $english_cache[$sido];
                }
                
                // 건물명을 조합한다.
                
                $buildings = array();
                if ($building1 !== '') $buildings[] = $building1;
                if ($building2 !== '') $buildings[] = $building2;
                if ($building3 !== '') $buildings[] = $building3;
                if ($building4 !== '') $buildings[] = $building4;
                $buildings = array_unique($buildings);
                natsort($buildings);
                
                // postcodify_addresses 테이블에 새 정보를 삽입한다.
                
                $ps_address_insert->execute(array(
                    $address_id, ($row ? $row['postcode5'] : $postcode5), $postcode6,
                    $road_id, $road_section, $road_name, $num_major, $num_minor, $is_basement,
                    $sido, $sigungu, $ilbangu, $eupmyeon, $dongri,
                    ($jibeon_major ? ($jibeon_major . ($jibeon_minor ? ('-' . $jibeon_minor) : '')) : null),
                    $building_name, $english, ($row ? $row['other_addresses'] : implode('; ', $buildings)),
                    $filename_date
                ));
                
                // postcodify_keywords_juso 테이블에 도로명주소 키워드를 저장한다.
                
                $keywords = get_variations_of_road_name(get_canonical($road_name));
                
                foreach ($keywords as $keyword)
                {
                    $ps_keyword_juso_delete->execute(array($address_id, $old_address_id, crc32_x64($keyword), $num_major, $num_minor));
                    $ps_keyword_juso_insert->execute(array($address_id, crc32_x64($keyword), $num_major, $num_minor));
                }
                
                // postcodify_keywords_jibeon 테이블에 지번주소 키워드를 저장한다.
                
                $keywords = get_variations_of_dongri($dongri, $dongs);
                if ($admin_dong) $keywords += get_variations_of_dongri($admin_dong, $dongs);
                $keywords = array_unique($keywords);
                
                foreach ($keywords as $keyword)
                {
                    $ps_keyword_jibeon_delete->execute(array($address_id, $old_address_id, crc32_x64($keyword), $jibeon_major, $jibeon_minor));
                    $ps_keyword_jibeon_insert->execute(array($address_id, crc32_x64($keyword), $jibeon_major, $jibeon_minor));
                }
                
                // postcodify_keywords_building 테이블에 건물명 키워드를 저장한다.
                
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
                
                foreach ($keywords as $keyword)
                {
                    if (isset($keywords_dongs[$keyword])) continue;
                    $ps_keyword_building_delete->execute(array($address_id, $old_address_id, $keyword));
                    $ps_keyword_building_insert->execute(array($address_id, $keyword));
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
        
        fclose($fp);
        echo str_pad(number_format($count, 0), 10, ' ', STR_PAD_LEFT) . "\n";
    }
    
    // 어디까지 업데이트했는지 기록한다.
    
    if (isset($filename_date) && $filename_date > $updated)
    {
        $db->query('UPDATE postcodify_metadata SET v = \'' . $filename_date . '\' WHERE k = \'updated\'');
    }
    else
    {
        echo '  -->  업데이트가 없습니다.' . "\n";
    }
    
    // 트랜잭션을 마친다.
    
    $db->commit();
    echo "\n";
}

do_updates();
