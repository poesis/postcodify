<?php

// -------------------------------------------------------------------------------------------------
// 우편번호 DB 생성에 필요한 파일들을 다운로드하는 스크립트.
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
define('LIST_URL', 'http://www.juso.go.kr/notice/OpenArchivesList.do?currentPage=1&countPerPage=20&noticeKd=26&type=matching');
define('POBOX_URL', 'http://www.epost.go.kr/search/zipcode/newaddr_pobox_DB.zip');
define('ENGLISH_URL', 'http://storage.poesis.kr/downloads/english/english_aliases_DB.zip');
define('FIND_ENTRIES_REGEXP', '#<td class="subject">(.+)</td>#isU');
define('FIND_LINKS_IN_ENTRY_REGEXP', '#<a href="([^"]+)">#iU');
define('FIND_DATA_DATE_REGEXP', '#\\((20[0-9][0-9])년 ([0-9]+)월 ([0-9]+)일 기준\\)#uU');
define('FIND_DOWNLOAD_REGEXP', '#<a href="(/dn\\.do\\?[^"]+)">([^<]+\\.zip)</a>#iU');
define('DOWNLOAD_PATH', TXT_DIRECTORY);

// 게시물 목록을 다운로드한다.

$html = download(LIST_URL);

// 필요한 게시물들을 찾는다.

$articles = array(
    '도로명코드_전체분' => '',
    '주소' => '',
    '지번' => '',
    '부가정보' => '',
    '상세건물명' => '',
    '날짜' => '',
);

preg_match_all(FIND_ENTRIES_REGEXP, $html, $article_tags, PREG_SET_ORDER);

foreach ($article_tags as $article_tag)
{
    if (strpos($article_tag[0], '개선안') !== false && strpos($article_tag[0], '도로명코드 전체분') !== false)
    {
        if (preg_match(FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
        {
            $articles['도로명코드_전체분'] = RELATIVE_DOMAIN . $matches[1];
            $articles['날짜'] = preg_match(FIND_DATA_DATE_REGEXP, $article_tag[0], $matches) ? $matches : '';
            break;
        }
        else
        {
            continue;
        }
    }
}

foreach ($article_tags as $article_tag)
{
    if (strpos($article_tag[0], '개선안') !== false && strpos($article_tag[0], '주소') !== false && strpos($article_tag[0], $articles['날짜'][0]) !== false)
    {
        if (preg_match(FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
        {
            $articles['주소'] = RELATIVE_DOMAIN . htmlspecialchars_decode($matches[1]);
        }
    }
    if (strpos($article_tag[0], '개선안') !== false && strpos($article_tag[0], '지번') !== false && strpos($article_tag[0], $articles['날짜'][0]) !== false)
    {
        if (preg_match(FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
        {
            $articles['지번'] = RELATIVE_DOMAIN . htmlspecialchars_decode($matches[1]);
        }
    }
    if (strpos($article_tag[0], '개선안') !== false && strpos($article_tag[0], '부가정보') !== false && strpos($article_tag[0], $articles['날짜'][0]) !== false)
    {
        if (preg_match(FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
        {
            $articles['부가정보'] = RELATIVE_DOMAIN . htmlspecialchars_decode($matches[1]);
        }
    }
    if (strpos($article_tag[0], '상세건물명') !== false && strpos($article_tag[0], $articles['날짜'][0]) !== false)
    {
        if (preg_match(FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
        {
            $articles['상세건물명'] = RELATIVE_DOMAIN . htmlspecialchars_decode($matches[1]);
        }
    }
}

// 데이터 기준일을 YYYYMMDD 포맷으로 정리한다.

$articles['날짜'] = sprintf('%04d%02d%02d', $articles['날짜'][1], $articles['날짜'][2], $articles['날짜'][3]);

// 진행하기 전에 데이터를 확인한다.

foreach ($articles as $value)
{
    if ($value === '')
    {
        echo 'ERROR' . "\n";
        exit;
    }
}

$downloaded_files = 0;

// 모든 파일을 다운로드한다.

foreach ($articles as $key => $url)
{
    if ($key === '날짜') continue;
    
    $html = download($url);
    
    preg_match_all(FIND_DOWNLOAD_REGEXP, $html, $downloads, PREG_SET_ORDER);

    foreach ($downloads as $download)
    {
        $link = RELATIVE_DOMAIN . htmlspecialchars_decode($download[1]);
        $filename = $download[2];
        $filepath = DOWNLOAD_PATH . '/' . $filename;
        
        if (strpos($filename, $key) !== false)
        {
            echo 'Downloading ' . $filename . ' ... ';
            $result = download($link, $filepath);
            if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
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

// 우체국 사서함 파일을 다운로드한다.

echo 'Downloading ' . basename(POBOX_URL) . ' ... ';
$result = download(POBOX_URL, DOWNLOAD_PATH . '/' . basename(POBOX_URL));
if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
{
    echo '[ERROR]' . "\n";
    exit;
}
else
{
    echo '[SUCCESS]' . "\n";
    $downloaded_files++;
}

// 영문 동 명칭을 다운로드한다.

echo 'Downloading ' . basename(ENGLISH_URL) . ' ... ';
$result = download(ENGLISH_URL, DOWNLOAD_PATH . '/' . basename(ENGLISH_URL));
if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
{
    echo '[ERROR]' . "\n";
    exit;
}
else
{
    echo '[SUCCESS]' . "\n";
    $downloaded_files++;
}

// 파일 수가 맞는지 확인한다.

if ($downloaded_files < 46)
{
    echo '[ERROR] 다운로드한 파일 수가 일치하지 않습니다.' . "\n";
    exit;
}

// 기준일을 기록한다.

file_put_contents(DOWNLOAD_PATH . '/도로명코드_기준일.txt', $articles['날짜']);
