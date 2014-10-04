<?php

/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (인덱서)
 * 
 *  Copyright (c) 2014, Kijin Sung <root@poesis.kr>
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

class Postcodify_Indexer_Download
{
    // 상수 선언 부분.
    
    const RELATIVE_DOMAIN = 'http://www.juso.go.kr';
    const LIST_URL = 'http://www.juso.go.kr/notice/OpenArchivesList.do?currentPage=1&countPerPage=20&noticeKd=26&type=matching';
    const POBOX_URL = 'http://www.epost.go.kr/search/zipcode/newaddr_pobox_DB.zip';
    const JIBEON_URL = 'http://www.epost.go.kr/search/zipcode/koreapost_zipcode_DB.zip';
    const ENGLISH_URL = 'http://storage.poesis.kr/downloads/english/english_aliases_DB.zip';
    const FIND_ENTRIES_REGEXP = '#<td class="subject">(.+)</td>#isU';
    const FIND_LINKS_IN_ENTRY_REGEXP = '#<a href="([^"]+)">#iU';
    const FIND_DATA_DATE_REGEXP = '#\\((20[0-9][0-9])년 ([0-9]+)월 ([0-9]+)일 기준\\)#uU';
    const FIND_DOWNLOAD_REGEXP = '#<a href="(/dn\\.do\\?[^"]+)">([^<]+\\.zip)</a>#iU';
    
    // 엔트리 포인트.
    
    public function start()
    {
        // 다운로드할 경로가 존재하는지 확인한다.
        
        $download_path = dirname(POSTCODIFY_LIB_DIR) . '/data';
        
        if ((!file_exists($download_path) || !is_dir($download_path)) && !@mkdir($download_path, 0755))
        {
            echo '[ERROR] 다운로드 대상 경로(' . $download_path . ')가 존재하지 않습니다.' . PHP_EOL;
            exit(2);
        }
        
        // 게시물 목록을 다운로드한다.
        
        $html = Postcodify_Utility::download(self::LIST_URL);
        
        // 필요한 게시물들을 찾는다.
        
        $articles = array(
            '도로명코드_전체분' => '',
            '주소' => '',
            '지번' => '',
            '부가정보' => '',
            '상세건물명' => '',
            '날짜' => '',
        );
        
        preg_match_all(self::FIND_ENTRIES_REGEXP, $html, $article_tags, PREG_SET_ORDER);
        
        foreach ($article_tags as $article_tag)
        {
            if (strpos($article_tag[0], '개선안') !== false && strpos($article_tag[0], '도로명코드 전체분') !== false)
            {
                if (preg_match(self::FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
                {
                    $articles['도로명코드_전체분'] = self::RELATIVE_DOMAIN . $matches[1];
                    $articles['날짜'] = preg_match(self::FIND_DATA_DATE_REGEXP, $article_tag[0], $matches) ? $matches : '';
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
                if (preg_match(self::FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
                {
                    $articles['주소'] = self::RELATIVE_DOMAIN . htmlspecialchars_decode($matches[1]);
                }
            }
            if (strpos($article_tag[0], '개선안') !== false && strpos($article_tag[0], '지번') !== false && strpos($article_tag[0], $articles['날짜'][0]) !== false)
            {
                if (preg_match(self::FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
                {
                    $articles['지번'] = self::RELATIVE_DOMAIN . htmlspecialchars_decode($matches[1]);
                }
            }
            if (strpos($article_tag[0], '개선안') !== false && strpos($article_tag[0], '부가정보') !== false && strpos($article_tag[0], $articles['날짜'][0]) !== false)
            {
                if (preg_match(self::FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
                {
                    $articles['부가정보'] = self::RELATIVE_DOMAIN . htmlspecialchars_decode($matches[1]);
                }
            }
            if (strpos($article_tag[0], '상세건물명') !== false && strpos($article_tag[0], $articles['날짜'][0]) !== false)
            {
                if (preg_match(self::FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
                {
                    $articles['상세건물명'] = self::RELATIVE_DOMAIN . htmlspecialchars_decode($matches[1]);
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
                echo '[ERROR] 다운로드할 파일의 일부 또는 전부를 찾을 수 없습니다.' . PHP_EOL;
                exit(2);
            }
        }
        
        $downloaded_files = 0;
        
        // 모든 파일을 다운로드한다.
        
        foreach ($articles as $key => $url)
        {
            if ($key === '날짜') continue;
            
            $html = Postcodify_Utility::download($url);
            
            preg_match_all(self::FIND_DOWNLOAD_REGEXP, $html, $downloads, PREG_SET_ORDER);
            
            foreach ($downloads as $download)
            {
                $link = self::RELATIVE_DOMAIN . htmlspecialchars_decode($download[1]);
                $filename = $download[2];
                $filepath = $download_path . '/' . $filename;
                
                if (strpos($filename, $key) !== false)
                {
                    Postcodify_Utility::print_message('다운로드: ' . $filename);
                    $result = Postcodify_Utility::download($link, $filepath);
                    if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
                    {
                        Postcodify_Utility::print_error();
                        exit(2);
                    }
                    else
                    {
                        Postcodify_Utility::print_ok();
                        $downloaded_files++;
                    }
                }
            }
        }
        
        // 우체국 사서함 및 지번우편번호 파일을 다운로드한다.
        
        Postcodify_Utility::print_message('다운로드: ' . basename(self::POBOX_URL));
        $result = Postcodify_Utility::download(self::POBOX_URL, $download_path . '/' . basename(self::POBOX_URL));
        if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
        {
            Postcodify_Utility::print_error();
            exit(2);
        }
        else
        {
            Postcodify_Utility::print_ok();
            $downloaded_files++;
        }
        
        Postcodify_Utility::print_message('다운로드: ' . basename(self::JIBEON_URL));
        $result = Postcodify_Utility::download(self::JIBEON_URL, $download_path . '/' . basename(self::JIBEON_URL));
        if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
        {
            Postcodify_Utility::print_error();
            exit(2);
        }
        else
        {
            Postcodify_Utility::print_ok();
            $downloaded_files++;
        }
        
        // 영문 동 명칭을 다운로드한다.
        
        Postcodify_Utility::print_message('다운로드: ' . basename(self::ENGLISH_URL));
        $result = Postcodify_Utility::download(self::ENGLISH_URL, $download_path . '/' . basename(self::ENGLISH_URL));
        if (!$result || !file_exists($filepath) || filesize($filepath) < 1024)
        {
            Postcodify_Utility::print_error();
            exit(2);
        }
        else
        {
            Postcodify_Utility::print_ok();
            $downloaded_files++;
        }
        
        // 파일 수가 맞는지 확인한다.
        
        if ($downloaded_files < 47)
        {
            echo '[ERROR] 다운로드한 파일 수가 일치하지 않습니다.' . PHP_EOL;
            exit(2);
        }
        
        // 기준일을 기록한다.
        
        file_put_contents($download_path . '/도로명코드_기준일.txt', $articles['날짜']);
    }
}
