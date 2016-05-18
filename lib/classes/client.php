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

class Postcodify_Client
{
    // 무료 API 경로 및 기타 설정 기본값들.
    
    const FREEAPI_MAIN_URL = '//api.poesis.kr/post/search.php';
    const FREEAPI_BACKUP_URL = '//api.poesis.kr/post/search.php';
    const MAIN_TIMEOUT = 1000;
    const BACKUP_TIMEOUT = 2000;
    const USER_AGENT = 'Postcodify Client %s';
    
    // 현재 인스턴스 설정.
    
    protected $config = array();
    
    // 사용할 문자셋을 지정한다.
    
    public function set_charset($charset)
    {
        if (strtoupper($charset) === 'UTF-8') return;
        $this->config['charset'] = $charset;
    }
    
    // 도메인을 지정한다.
    
    public function set_domain($domain)
    {
        $this->config['domain'] = $domain;
        $this->config['domain_is_valid'] = preg_match('/^[^.|_:\/$#~*]+(\.[^.|_:\/$#~*]+)+$/', $domain);
    }
    
    // API 경로를 지정한다.
    
    public function set_api_url($url)
    {
        $this->config['main_url'] = $url;
    }
    
    // API 백업서버 경로를 지정한다.
    
    public function set_backup_api_url($url)
    {
        $this->config['backup_url'] = $url;
    }
    
    // API 타임아웃을 지정한다. 단위는 밀리초(ms)이다.
    
    public function set_timeout($ms)
    {
        $this->config['main_timeout'] = intval($ms, 10);
    }
    
    // API 백업서버 타임아웃을 지정한다. 단위는 밀리초(ms)이다.
    
    public function set_backup_timeout($ms)
    {
        $this->config['backup_timeout'] = intval($ms, 10);
    }
    
    // User-Agent 값을 지정한다.
    
    public function set_user_agent($ua)
    {
        $this->config['user_agent'] = trim($ua);
    }
    
    // SSL을 사용하도록 설정한다.
    
    public function use_ssl()
    {
        $this->config['use_ssl'] = true;
    }
    
    // 검색을 수행한다.
    
    public function search($keywords)
    {
        // 도메인이 지정되었는지 확인한다.
        
        if (!isset($this->config['domain']) || !$this->config['domain_is_valid'])
        {
            throw new Exception('Please set a valid domain.');
        }
        
        // 검색 파라미터를 정리한다.
        
        $params = http_build_query(array(
            'v' => POSTCODIFY_VERSION,
            'q' => isset($this->config['charset']) ? iconv($this->config['charset'], 'UTF-8', $keywords) : $keywords,
            'ref' => $this->config['domain'],
            'cdn' => '',
        ));
        
        // 메인서버 접속 정보를 정리한다.
        
        $config = array(
            'params' => $params,
            'url' => isset($this->config['main_url']) ? $this->config['main_url'] : ((isset($this->config['use_ssl']) ? 'https:' : 'http:') . self::FREEAPI_MAIN_URL),
            'timeout' => isset($this->config['main_timeout']) ? $this->config['main_timeout'] : self::MAIN_TIMEOUT,
            'user_agent' => isset($this->config['user_agent']) ? $this->config['user_agent'] : sprintf(self::USER_AGENT, POSTCODIFY_VERSION),
        );
        
        // 메인서버에서 검색을 시도한다.
        
        $result = $this->send_curl_request($config);
        
        // 검색 성공시 검색 결과를 반환한다.
        
        if ($result !== null && $result !== false) return $result;
        
        // 검색 실패시 백업서버 경로를 구한다.
        
        if (!isset($this->config['backup_url']) && !isset($this->config['main_url']))
        {
            $this->config['backup_url'] = (isset($this->config['use_ssl']) ? 'https:' : 'http:') . self::FREEAPI_BACKUP_URL;
        }
        
        // 백업서버가 없는 경우 여기서 검색을 그만두고 false를 반환한다.
        
        if (!isset($this->config['backup_url'])) return false;
        
        // 백업서버 접속 정보를 정리한다.
        
        $config['timeout'] = isset($this->config['backup_timeout']) ? $this->config['backup_timeout'] : self::MAIN_TIMEOUT;
        $config['url'] = $this->config['backup_url'];
        
        // 백업서버에서 검색을 시도한다.
        
        $result = $this->send_curl_request($config);
        
        // 검색 성공시 검색 결과를 반환하고, 실패시 false를 반환한다.
        
        if ($result !== null && $result !== false) return $result;
        return false;
    }
    
    // cURL 요청을 보내는 메소드.
    
    protected function send_curl_request($config)
    {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $config['url'] . '?' . $config['params'],
            CURLOPT_CONNECTTIMEOUT => $config['timeout'],
            CURLOPT_USERAGENT => $config['user_agent'],
            CURLOPT_RETURNTRANSFER => 1,
        ));
        
        $response = strval(curl_exec($ch));
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);        
        curl_close($ch);
        
        if ($status == 200 && $response !== '' && $response[0] === '{')
        {
            return @json_decode($response);
        }
        else
        {
            return false;
        }
    }
}
