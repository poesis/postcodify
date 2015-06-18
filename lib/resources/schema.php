<?php

return array(
    
    // 도로 정보 테이블.
    
    'postcodify_roads' => array(
        'road_id' => 'NUMERIC(14) PRIMARY KEY',
        'road_name_ko' => 'VARCHAR(80)',
        'road_name_en' => 'VARCHAR(120)',
        'sido_ko' => 'VARCHAR(20)',
        'sido_en' => 'VARCHAR(40)',
        'sigungu_ko' => 'VARCHAR(20)',
        'sigungu_en' => 'VARCHAR(40)',
        'ilbangu_ko' => 'VARCHAR(20)',
        'ilbangu_en' => 'VARCHAR(40)',
        'eupmyeon_ko' => 'VARCHAR(20)',
        'eupmyeon_en' => 'VARCHAR(40)',
        'updated' => 'NUMERIC(8)',
        '_indexes' => array('sido_ko', 'sigungu_ko', 'ilbangu_ko', 'eupmyeon_ko'),
    ),
    
    // 주소 정보 테이블.
    
    'postcodify_addresses' => array(
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'postcode5' => 'CHAR(5)',
        'postcode6' => 'CHAR(6)',
        'road_id' => 'NUMERIC(14)',
        'num_major' => 'SMALLINT(5)',
        'num_minor' => 'SMALLINT(5)',
        'is_basement' => 'TINYINT(1) DEFAULT 0',
        'dongri_ko' => 'VARCHAR(80)',
        'dongri_en' => 'VARCHAR(80)',
        'jibeon_major' => 'SMALLINT(5)',
        'jibeon_minor' => 'SMALLINT(5)',
        'is_mountain' => 'TINYINT(1) DEFAULT 0',
        'building_name' => 'VARCHAR(80)',
        'building_num' => 'VARCHAR(40)',
        'other_addresses' => 'TEXT',
        'updated' => 'NUMERIC(8)',
        '_interim' => array('road_id', 'num_major', 'num_minor', 'is_basement'),
        '_indexes' => array('postcode5', 'postcode6'),
    ),
    
    // 행자부 건물관리번호 테이블.
    
    'postcodify_codes' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'address_id' => 'INT NOT NULL',
        'building_code' => 'NUMERIC(25)',
        'building_num' => 'VARCHAR(40)',
        '_indexes' => array('address_id', 'building_code'),
    ),
    
    // 한글 검색 키워드 테이블.
    
    'postcodify_keywords' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'address_id' => 'INT NOT NULL',
        'keyword_crc32' => 'INT',
        '_indexes' => array('address_id', 'keyword_crc32'),
    ),
    
    // 지번 및 건물번호 검색 테이블.
    
    'postcodify_numbers' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'address_id' => 'INT NOT NULL',
        'num_major' => 'SMALLINT(5)',
        'num_minor' => 'SMALLINT(5)',
        '_indexes' => array('address_id', 'num_major', 'num_minor'),
    ),
    
    // 건물명 검색 키워드 테이블.
    
    'postcodify_buildings' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'address_id' => 'INT NOT NULL',
        'keyword' => 'VARCHAR(120)',
        '_indexes' => array('address_id'),
    ),
    
    // 사서함 검색 키워드 테이블.
    
    'postcodify_pobox' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'address_id' => 'INT NOT NULL',
        'keyword' => 'VARCHAR(40)',
        'range_start_major' => 'SMALLINT(5)',
        'range_start_minor' => 'SMALLINT(5)',
        'range_end_major' => 'SMALLINT(5)',
        'range_end_minor' => 'SMALLINT(5)',
        '_indexes' => array('address_id', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor'),
    ),
    
    // 영문 검색 키워드 테이블.
    
    'postcodify_english' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'ko' => 'VARCHAR(40)',
        'ko_crc32' => 'INT',
        'en' => 'VARCHAR(40)',
        'en_crc32' => 'INT',
        '_indexes' => array('ko', 'ko_crc32', 'en', 'en_crc32'),
    ),
    
    // 우편번호 범위 테이블 (도로명주소).
    
    'postcodify_ranges_roads' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'sido_ko' => 'VARCHAR(40)',
        'sido_en' => 'VARCHAR(40)',
        'sigungu_ko' => 'VARCHAR(40)',
        'sigungu_en' => 'VARCHAR(40)',
        'ilbangu_ko' => 'VARCHAR(40)',
        'ilbangu_en' => 'VARCHAR(40)',
        'eupmyeon_ko' => 'VARCHAR(40)',
        'eupmyeon_en' => 'VARCHAR(40)',
        'road_name_ko' => 'VARCHAR(80)',
        'road_name_en' => 'VARCHAR(80)',
        'range_start_major' => 'SMALLINT(5)',
        'range_start_minor' => 'SMALLINT(5)',
        'range_end_major' => 'SMALLINT(5)',
        'range_end_minor' => 'SMALLINT(5)',
        'range_type' => 'TINYINT(1) DEFAULT 0',
        'is_basement' => 'TINYINT(1) DEFAULT 0',
        'postcode5' => 'CHAR(5)',
        '_indexes' => array('sido_ko', 'sigungu_ko', 'ilbangu_ko', 'eupmyeon_ko', 'road_name_ko', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor', 'range_type', 'postcode5'),
    ),
    
    // 우편번호 범위 테이블 (지번주소).
    
    'postcodify_ranges_jibeon' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'sido_ko' => 'VARCHAR(40)',
        'sido_en' => 'VARCHAR(40)',
        'sigungu_ko' => 'VARCHAR(40)',
        'sigungu_en' => 'VARCHAR(40)',
        'ilbangu_ko' => 'VARCHAR(40)',
        'ilbangu_en' => 'VARCHAR(40)',
        'eupmyeon_ko' => 'VARCHAR(40)',
        'eupmyeon_en' => 'VARCHAR(40)',
        'dongri_ko' => 'VARCHAR(80)',
        'dongri_en' => 'VARCHAR(80)',
        'range_start_major' => 'SMALLINT(5)',
        'range_start_minor' => 'SMALLINT(5)',
        'range_end_major' => 'SMALLINT(5)',
        'range_end_minor' => 'SMALLINT(5)',
        'is_mountain' => 'TINYINT(1) DEFAULT 0',
        'admin_dongri' => 'VARCHAR(80)',
        'postcode5' => 'CHAR(5)',
        '_indexes' => array('sido_ko', 'sigungu_ko', 'ilbangu_ko', 'eupmyeon_ko', 'dongri_ko', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor', 'admin_dongri', 'postcode5'),
    ),
    
    // 우편번호 범위 테이블 (구 우편번호).
    
    'postcodify_ranges_oldcode' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'sido_ko' => 'VARCHAR(40)',
        'sido_en' => 'VARCHAR(40)',
        'sigungu_ko' => 'VARCHAR(40)',
        'sigungu_en' => 'VARCHAR(40)',
        'ilbangu_ko' => 'VARCHAR(40)',
        'ilbangu_en' => 'VARCHAR(40)',
        'eupmyeon_ko' => 'VARCHAR(40)',
        'eupmyeon_en' => 'VARCHAR(40)',
        'dongri_ko' => 'VARCHAR(80)',
        'dongri_en' => 'VARCHAR(80)',
        'range_start_major' => 'SMALLINT(5)',
        'range_start_minor' => 'SMALLINT(5)',
        'range_end_major' => 'SMALLINT(5)',
        'range_end_minor' => 'SMALLINT(5)',
        'is_mountain' => 'TINYINT(1) DEFAULT 0',
        'island_name' => 'VARCHAR(80)',
        'building_name' => 'VARCHAR(80)',
        'building_num_start' => 'SMALLINT(5)',
        'building_num_end' => 'SMALLINT(5)',
        'postcode6' => 'CHAR(6)',
        '_indexes' => array('sido_ko', 'sigungu_ko', 'ilbangu_ko', 'eupmyeon_ko', 'dongri_ko', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor', 'postcode6'),
    ),
    
    // 각종 설정을 저장하는 테이블.
    
    'postcodify_settings' => array(
        'k' => 'VARCHAR(20) PRIMARY KEY',
        'v' => 'VARCHAR(40)',
        '_indexes' => array('k'),
    ),
    
);
