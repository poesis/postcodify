
-- 주소 정보를 저장하는 메인 테이블.

CREATE TABLE postcode_addresses (
    id CHAR(25) PRIMARY KEY,
    postcode5 CHAR(5),
    postcode6 CHAR(6),
    road_id CHAR(12),
    road_section CHAR(2),
    road_name VARCHAR(80),
    num_major INTEGER,
    num_minor INTEGER,
    is_basement INTEGER DEFAULT 0,
    sido VARCHAR(20),
    sigungu VARCHAR(20),
    ilbangu VARCHAR(20),
    eupmyeon VARCHAR(20),
    dongri VARCHAR(20),
    jibeon VARCHAR(10),
    building_name VARCHAR(40),
    english_address VARCHAR(300),
    other_addresses VARCHAR(600),
    updated CHAR(8)
);

-- 도로명주소 검색을 위한 키워드 테이블.

CREATE TABLE postcode_keywords_juso (
    seq INTEGER PRIMARY KEY,
    address_id CHAR(25) NOT NULL,
    keyword_crc32 INTEGER,
    num_major INTEGER,
    num_minor INTEGER
);

-- 지번 검색을 위한 키워드 테이블.

CREATE TABLE postcode_keywords_jibeon (
    seq INTEGER PRIMARY KEY,
    address_id CHAR(25) NOT NULL,
    keyword_crc32 INTEGER,
    num_major INTEGER,
    num_minor INTEGER
);

-- 건물명 검색을 위한 키워드 테이블.

CREATE TABLE postcode_keywords_building (
    seq INTEGER PRIMARY KEY,
    address_id CHAR(25) NOT NULL,
    keyword VARCHAR(40)
);

-- 사서함 검색을 위한 키워드 테이블.

CREATE TABLE postcode_keywords_pobox (
    seq INTEGER PRIMARY KEY,
    address_id CHAR(25) NOT NULL,
    keyword VARCHAR(40),
    range_start_major INTEGER,
    range_start_minor INTEGER,
    range_end_major INTEGER,
    range_end_minor INTEGER
);

-- 대체 키워드 테이블.

CREATE TABLE postcode_keywords_replace (
    seq INTEGER PRIMARY KEY,
    original_crc32 INTEGER,
    replaced_crc32 INTEGER
);

-- 각종 설정을 저장하는 테이블.

CREATE TABLE postcode_metadata (
    k VARCHAR(20) PRIMARY KEY,
    v VARCHAR(40)
);
