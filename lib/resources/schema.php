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
        'deleted' => 'NUMERIC(8)',
        '_indexes' => array('sido_ko', 'sigungu_ko', 'ilbangu_ko', 'eupmyeon_ko'),
        '_count' => 320000,
    ),
    
    // 주소 정보 테이블.
    
    'postcodify_addresses' => array(
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'postcode5' => 'CHAR(5)',
        'postcode6' => 'CHAR(6)',
        'road_id' => 'NUMERIC(14)',
        'num_major' => 'SMALLINT(5) UNSIGNED',
        'num_minor' => 'SMALLINT(5) UNSIGNED',
        'is_basement' => 'TINYINT(1) DEFAULT 0',
        'dongri_id' => 'NUMERIC(10)',
        'dongri_ko' => 'VARCHAR(80)',
        'dongri_en' => 'VARCHAR(80)',
        'jibeon_major' => 'SMALLINT(5) UNSIGNED',
        'jibeon_minor' => 'SMALLINT(5) UNSIGNED',
        'is_mountain' => 'TINYINT(1) DEFAULT 0',
        'building_id' => 'NUMERIC(25)',
        'building_name' => 'VARCHAR(80)',
        'building_nums' => 'VARCHAR(240)',
        'other_addresses' => 'TEXT',
        'updated' => 'NUMERIC(8)',
        'deleted' => 'NUMERIC(8)',
        '_interim' => array('road_id', 'num_major', 'num_minor'),
        '_indexes' => array('postcode5', 'postcode6', 'dongri_id'),
        '_count' => 5800000,
    ),
    
    // 한글 검색 키워드 테이블.
    
    'postcodify_keywords' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'address_id' => 'INT NOT NULL',
        'keyword_crc32' => 'INT UNSIGNED',
        '_indexes' => array('address_id', 'keyword_crc32'),
        '_count' => 21000000,
    ),
    
    // 지번 및 건물번호 검색 테이블.
    
    'postcodify_numbers' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'address_id' => 'INT NOT NULL',
        'num_major' => 'SMALLINT(5) UNSIGNED',
        'num_minor' => 'SMALLINT(5) UNSIGNED',
        '_indexes' => array('address_id', 'num_major', 'num_minor'),
        '_count' => 12000000,
    ),
    
    // 건물명 검색 키워드 테이블.
    
    'postcodify_buildings' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'address_id' => 'INT NOT NULL',
        'keyword' => 'VARCHAR(5000)',
        '_indexes' => array('address_id'),
        '_count' => 600000,
    ),
    
    // 사서함 검색 키워드 테이블.
    
    'postcodify_pobox' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'address_id' => 'INT NOT NULL',
        'keyword' => 'VARCHAR(40)',
        'range_start_major' => 'SMALLINT(5) UNSIGNED',
        'range_start_minor' => 'SMALLINT(5) UNSIGNED',
        'range_end_major' => 'SMALLINT(5) UNSIGNED',
        'range_end_minor' => 'SMALLINT(5) UNSIGNED',
        '_indexes' => array('address_id', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor'),
        '_count' => 2000,
    ),
    
    // 영문 검색 키워드 테이블.
    
    'postcodify_english' => array(
        'seq' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'ko' => 'VARCHAR(40)',
        'ko_crc32' => 'INT UNSIGNED',
        'en' => 'VARCHAR(40)',
        'en_crc32' => 'INT UNSIGNED',
        '_indexes' => array('ko', 'ko_crc32', 'en', 'en_crc32'),
        '_count' => 130000,
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
        'range_start_major' => 'SMALLINT(5) UNSIGNED',
        'range_start_minor' => 'SMALLINT(5) UNSIGNED',
        'range_end_major' => 'SMALLINT(5) UNSIGNED',
        'range_end_minor' => 'SMALLINT(5) UNSIGNED',
        'range_type' => 'TINYINT(1) DEFAULT 0',
        'is_basement' => 'TINYINT(1) DEFAULT 0',
        'postcode5' => 'CHAR(5)',
        '_initial' => array('sido_ko', 'sigungu_ko', 'ilbangu_ko', 'eupmyeon_ko', 'road_name_ko', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor', 'range_type', 'postcode5'),
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
        'range_start_major' => 'SMALLINT(5) UNSIGNED',
        'range_start_minor' => 'SMALLINT(5) UNSIGNED',
        'range_end_major' => 'SMALLINT(5) UNSIGNED',
        'range_end_minor' => 'SMALLINT(5) UNSIGNED',
        'is_mountain' => 'TINYINT(1) DEFAULT 0',
        'admin_dongri' => 'VARCHAR(80)',
        'postcode5' => 'CHAR(5)',
        '_initial' => array('sido_ko', 'sigungu_ko', 'ilbangu_ko', 'eupmyeon_ko', 'dongri_ko', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor', 'admin_dongri', 'postcode5'),
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
        'range_start_major' => 'SMALLINT(5) UNSIGNED',
        'range_start_minor' => 'SMALLINT(5) UNSIGNED',
        'range_end_major' => 'SMALLINT(5) UNSIGNED',
        'range_end_minor' => 'SMALLINT(5) UNSIGNED',
        'is_mountain' => 'TINYINT(1) DEFAULT 0',
        'island_name' => 'VARCHAR(80)',
        'building_name' => 'VARCHAR(80)',
        'building_num_start' => 'SMALLINT(5) UNSIGNED',
        'building_num_end' => 'SMALLINT(5) UNSIGNED',
        'postcode6' => 'CHAR(6)',
        '_initial' => array('sido_ko', 'sigungu_ko', 'ilbangu_ko', 'eupmyeon_ko', 'dongri_ko', 'range_start_major', 'range_start_minor', 'range_end_major', 'range_end_minor', 'postcode6'),
    ),
    
    // 각종 설정을 저장하는 테이블.
    
    'postcodify_settings' => array(
        'k' => 'VARCHAR(20) PRIMARY KEY',
        'v' => 'VARCHAR(40)',
        '_indexes' => array('k'),
    ),
    
);
