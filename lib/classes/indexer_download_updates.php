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

class Postcodify_Indexer_Download_Updates
{
    // 상수 선언 부분.
    
    const RELATIVE_DOMAIN = 'http://www.juso.go.kr';
    const LIST_URL = '/notice/OpenArchivesList.do?currentPage=1&countPerPage=%d&noticeKd=27&type=archives';
    const FIND_ENTRIES_REGEXP = '#<td class="subject">(.+)</td>#isU';
    const FIND_LINKS_IN_ENTRY_REGEXP = '#<a href="([^"]+)">#iU';
    const FIND_DOWNLOAD_REGEXP = '#<a href="(/dn\\.do\\?[^"]+)">([^<]+\\.TXT)</a>#iU';
    
    // 엔트리 포인트.
    
    public function start()
    {
        // 다운로드할 경로가 존재하는지 확인한다.
        
        $download_path = dirname(POSTCODIFY_LIB_DIR) . '/data/updates';
        
        if ((!file_exists($download_path) || !is_dir($download_path)) && !@mkdir($download_path, 0755, true))
        {
            echo '[ERROR] 다운로드 대상 경로(' . $download_path . ')가 존재하지 않습니다.' . PHP_EOL;
            exit(2);
        }
        
        // 어디까지 업데이트했는지 찾아본다.
        
        $db = Postcodify_Utility::get_db();
        $updated_query = $db->query('SELECT v FROM postcodify_settings WHERE k = \'updated\'');
        $updated = $updated_query->fetchColumn();
        $updated_query->closeCursor();
        if (!preg_match('/^20[0-9]{6}$/', $updated))
        {
            echo '[ERROR] 기존 DB의 데이터 기준일을 찾을 수 없습니다.' . PHP_EOL;
            exit(3);
        }
        
        $updated_time = mktime(1, 0, 0, substr($updated, 4, 2), substr($updated, 6, 2), substr($updated, 0, 4));
        if ($updated_time < time() - (86400 * 90))
        {
            echo '[ERROR] 마지막 업데이트로부터 90일 이상이 경과하였습니다. DB를 새로 생성하시기 바랍니다.' . PHP_EOL;
            exit(3);
        }
        if ($updated_time >= time())
        {
            echo '업데이트가 필요하지 않습니다.' . PHP_EOL;
            exit(0);
        }
        
        // 게시물 목록을 다운로드한다.
        
        $count_records = max(10, ceil((time() - $updated_time - 86400) / (86400 * 10)) * 10);
        $html = Postcodify_Utility::download(self::RELATIVE_DOMAIN . sprintf(self::LIST_URL, $count_records));
        
        // 필요한 게시물들을 찾는다.
        
        $articles = array();
        preg_match_all(self::FIND_ENTRIES_REGEXP, $html, $article_tags, PREG_SET_ORDER);
        
        foreach ($article_tags as $article_tag)
        {
            if (strpos($article_tag[0], '도로명주소') !== false && strpos($article_tag[0], '변경분') !== false)
            {
                if (preg_match(self::FIND_LINKS_IN_ENTRY_REGEXP, $article_tag[0], $matches))
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
                            $articles[$article_date] = self::RELATIVE_DOMAIN . $matches[1];
                        }
                    }
                }
            }
        }
        
        $articles = array_reverse($articles, true);
        $downloaded_files = 0;
        
        // 모든 파일을 다운로드한다.
        
        foreach ($articles as $url)
        {
            $html = Postcodify_Utility::download($url);
            preg_match_all(self::FIND_DOWNLOAD_REGEXP, $html, $downloads, PREG_SET_ORDER);
        
            foreach ($downloads as $download)
            {
                $link = self::RELATIVE_DOMAIN . htmlspecialchars_decode($download[1]);
                $filename = $download[2];
                
                if (preg_match('/^(.+)\\.TXT/i', $filename, $matches))
                {
                    Postcodify_Utility::print_message('다운로드: ' . $matches[1] . '.TXT');
                    $filepath = $download_path . '/' . $matches[1] . '.TXT';
                    $result = Postcodify_Utility::download($link, $filepath);
                    if (!$result || !file_exists($filepath))
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
    }
}
