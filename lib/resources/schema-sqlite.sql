
-- 주소 정보를 저장하는 메인 테이블.

CREATE TABLE postcodify_addresses (
    id INTEGER PRIMARY KEY,
    address_id NUMERIC(25),
    postcode5 CHAR(5),
    postcode6 CHAR(6),
    road_id NUMERIC(14),
    num_major INTEGER(5),
    num_minor INTEGER(5),
    is_basement INTEGER(1) DEFAULT 0,
    dongri_ko VARCHAR(80),
    dongri_en VARCHAR(80),
    jibeon_major INTEGER(5),
    jibeon_minor INTEGER(5),
    is_mountain INTEGER(1) DEFAULT 0,
    building_name VARCHAR(80),
    other_addresses VARCHAR(2000),
    updated NUMERIC(8)
);

-- 도로 정보를 저장하는 테이블.

CREATE TABLE postcodify_roads (
    road_id NUMERIC(14) PRIMARY KEY,
    road_name_ko VARCHAR(40),
    road_name_en VARCHAR(40),
    sido_ko VARCHAR(40),
    sido_en VARCHAR(40),
    sigungu_ko VARCHAR(40),
    sigungu_en VARCHAR(40),
    ilbangu_ko VARCHAR(40),
    ilbangu_en VARCHAR(40),
    eupmyeon_ko VARCHAR(40),
    eupmyeon_en VARCHAR(40)
);

-- 한글 검색 키워드 테이블.

CREATE TABLE postcodify_keywords (
    seq INTEGER PRIMARY KEY,
    address_id INTEGER NOT NULL,
    keyword_crc32 INTEGER(10)
);

-- 영문 검색 키워드 테이블.

CREATE TABLE postcodify_english (
    seq INTEGER PRIMARY KEY,
    ko VARCHAR(40),
    ko_crc32 INTEGER(10),
    en VARCHAR(40),
    en_crc32 INTEGER(10)
);

-- 지번 및 건물번호 검색 테이블.

CREATE TABLE postcodify_numbers (
    seq INTEGER PRIMARY KEY,
    address_id INTEGER NOT NULL,
    num_major INTEGER(5),
    num_minor INTEGER(5)
);

-- 건물명 검색 키워드 테이블.

CREATE TABLE postcodify_buildings (
    seq INTEGER PRIMARY KEY,
    address_id INTEGER NOT NULL,
    keyword VARCHAR(40)
);

-- 사서함 검색 키워드 테이블.

CREATE TABLE postcodify_pobox (
    seq INTEGER PRIMARY KEY,
    address_id INTEGER NOT NULL,
    keyword VARCHAR(40),
    range_start_major INTEGER(5),
    range_start_minor INTEGER(5),
    range_end_major INTEGER(5),
    range_end_minor INTEGER(5)
);

-- 각종 설정을 저장하는 테이블.

CREATE TABLE postcodify_settings (
    k VARCHAR(20) PRIMARY KEY,
    v VARCHAR(40)
);
