<?php

// -------------------------------------------------------------------------------------------------
// 우편번호 DB 업데이트에 필요한 파일들을 다운로드하는 스크립트.
// -------------------------------------------------------------------------------------------------

ini_set('display_errors', 'on');
ini_set('memory_limit', '1024M');
date_default_timezone_set('UTC');
error_reporting(-1);
gc_enable();

// 설정과 함수 파일을 인클루드한다.

require dirname(__FILE__) . '/config.php';
require dirname(__FILE__) . '/functions.php';

// 주소사이트 검색에 사용할 정규식 및 URL 목록.

define('CURL_USER_AGENT', 'Mozilla/5.0 (Compatible; Postcodify Downloader)');
define('RELATIVE_DOMAIN', 'http://www.juso.go.kr');
define('LIST_URL', 'http://www.juso.go.kr/notice/OpenArchivesList.do?currentPage=1&countPerPage=%d&noticeKd=27&type=archives');
define('FIND_ENTRIES_REGEXP', '#<td class="subject">(.+)</td>#isU');
define('FIND_LINKS_IN_ENTRY_REGEXP', '#<a href="([^"]+)">#iU');
define('FIND_DOWNLOAD_REGEXP', '#<a href="(/dn\\.do\\?[^"]+)">([^<]+\\.TXT)</a>#iU');
define('DOWNLOAD_PATH', TXT_DIRECTORY . '/Updates');

// 어디까지 업데이트했는지 찾아본다.

$updated_query = get_db()->query('SELECT v FROM postcode_metadata WHERE k = \'updated\'');
$updated = $updated_query->fetchColumn();
$updated_query->closeCursor();
if (!preg_match('/^20[0-9]{6}$/', $updated))
{
    echo "기존 DB의 데이터 기준일을 찾을 수 없습니다.\n";
    exit;
}

$updated_time = strtotime(substr($updated, 0, 4) . '-' . substr($updated, 4, 2) . '-' . substr($updated, 6, 2));
if ($updated_time < time() - (86400 * 90))
{
    echo "마지막 업데이트로부터 90일 이상이 경과하였습니다. DB를 새로 생성하시기 바랍니다.\n";
    exit;
}
if ($updated_time >= time())
{
    echo "업데이트가 필요하지 않습니다.\n";
    exit;
}

// 게시물 목록을 다운로드한다.

$count_records = max(10, ceil((time() - $updated_time - 86400) / (86400 * 10)) * 10);
$html = download(sprintf(LIST_URL, $count_records));

// 필요한 게시물들을 찾는다.

$articles = array();
preg_match_all(FIND_ENTRIES_REGEXP, $html, $article_tags, PREG_SET_ORDER);

foreach ($article_tags as $article_tag)
{
    if (strpos($article_tag[0], '도로명주소') !== false && strpos($article_tag[0], '변경분') !== false)
    {
        if (preg_match(FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
        {
            if (preg_match('/_([0-9]{1,2}).([0-9]{1,2})\\)/', $article_tag[0], $date_matches))
            {
                if (intval(substr($updated, 4, 2), 10) < 5 && intval($date_matches[1], 10) > 9)
                {
                    $article_date = (substr($updated, 0, 4) - 1) . $date_matches[1] . $date_matches[2];
                }
                else
                {
                    $article_date = substr($updated, 0, 4) . $date_matches[1] . $date_matches[2];
                }
                if ($article_date >= $updated)
                {
                    $articles[$article_date] = RELATIVE_DOMAIN . $matches[1];
                }
            }
        }
    }
}

$articles = array_reverse($articles, true);

// 다운로드 대상 폴더를 생성한다.

$downloaded_files = 0;
if (!file_exists(DOWNLOAD_PATH)) mkdir(DOWNLOAD_PATH);

// 모든 파일을 다운로드한다.

foreach ($articles as $url)
{
    $html = download($url);
    
    preg_match_all(FIND_DOWNLOAD_REGEXP, $html, $downloads, PREG_SET_ORDER);

    foreach ($downloads as $download)
    {
        $link = RELATIVE_DOMAIN . htmlspecialchars_decode($download[1]);
        $filename = $download[2];
        
        if (preg_match('/^(.+)\\.TXT/i', $filename, $matches))
        {
            echo 'Downloading ' . $matches[1] . '.TXT' . ' ... ';
            $filepath = DOWNLOAD_PATH . '/' . $matches[1] . '.TXT';
            $result = download($link, $filepath);
            if (!$result || !file_exists($filepath) || filesize($filepath) < 100)
            {
                echo '[ERROR]' . "\n";
                exit;
            }
            else
            {
                echo '[SUCCESS]' . "\n";
                $downloaded_files++;
            }
        }
    }
}
