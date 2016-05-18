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

class Postcodify_Server_Cache
{
    // 캐시 핸들과 드라이버명.
    
    protected $_driver;
    protected $_handle;
    protected $_ttl;
    
    // 생성자.
    
    public function __construct($driver, $host, $port, $ttl)
    {
        // Memcached 사용시.
        
        if (strtolower($driver) === 'memcached')
        {
            if (class_exists('Memcached'))
            {
                $this->_driver = 'memcached';
                $this->_handle = new Memcached;
                $this->_handle->addServer($host, $port);
                $this->_ttl = intval($ttl);
            }
            elseif (class_exists('Memcache'))
            {
                $this->_driver = 'memcache';
                $this->_handle = new Memcache;
                $this->_handle->addServer($host, $port);
                $this->_ttl = intval($ttl);
            }
            else
            {
                throw new Exception('Cache driver not supported: memcached');
            }
        }
        
        // Redis 사용시.
        
        if (strtolower($driver) === 'redis')
        {
            if (class_exists('Redis'))
            {
                $this->_driver = 'redis';
                $this->_handle = new Redis;
                $this->_handle->connect($host, $port);
                $this->_ttl = intval($ttl);
            }
            else
            {
                throw new Exception('Cache driver not supported: redis');
            }
        }
    }
    
    // GET 메소드.
    
    public function get($key)
    {
        $prefix = 'Postcodify:' . POSTCODIFY_VERSION . ':CACHE:';
        $data = $this->_handle->get($prefix . $key);
        if ($data)
        {
            return json_decode($data);
        }
        else
        {
            return array(null, null, null);
        }
    }
    
    // SET 메소드.
    
    public function set($key, $rows, $search_type)
    {
        $prefix = 'Postcodify:' . POSTCODIFY_VERSION . ':CACHE:';
        $data = json_encode(array($rows, $search_type, null));
        switch ($this->_driver)
        {
            case 'memcached':
                return $this->_handle->set($prefix . $key, $data, $this->_ttl);
            case 'memcache':
                return $this->_handle->set($prefix . $key, $data, 0, $this->_ttl);
            case 'redis':
                return $this->_handle->setex($prefix . $key, $this->_ttl, $data);
        }
    }
}
