
-- 이미 존재하는 테이블을 삭제한다.

DROP TABLE IF EXISTS postcodify_addresses;
DROP TABLE IF EXISTS postcodify_roads;
DROP TABLE IF EXISTS postcodify_keywords;
DROP TABLE IF EXISTS postcodify_english;
DROP TABLE IF EXISTS postcodify_numbers;
DROP TABLE IF EXISTS postcodify_buildings;
DROP TABLE IF EXISTS postcodify_pobox;
DROP TABLE IF EXISTS postcodify_oldaddr;
DROP TABLE IF EXISTS postcodify_ranges_roads;
DROP TABLE IF EXISTS postcodify_ranges_jibeon;
DROP TABLE IF EXISTS postcodify_ranges_oldcode;
DROP TABLE IF EXISTS postcodify_settings;

-- 주소 정보를 저장하는 메인 테이블.

CREATE TABLE postcodify_addresses (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    address_id NUMERIC(25),
    postcode5 CHAR(5),
    postcode6 CHAR(6),
    road_id NUMERIC(14),
    num_major SMALLINT(5) UNSIGNED,
    num_minor SMALLINT(5) UNSIGNED,
    is_basement TINYINT(1) DEFAULT 0,
    dongri_ko VARCHAR(80),
    dongri_en VARCHAR(80),
    jibeon_major SMALLINT(5) UNSIGNED,
    jibeon_minor SMALLINT(5) UNSIGNED,
    is_mountain TINYINT(1) DEFAULT 0,
    building_name VARCHAR(80),
    other_addresses VARCHAR(2000),
    updated NUMERIC(8)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;

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
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;

-- 한글 검색 키워드 테이블.

CREATE TABLE postcodify_keywords (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    address_id INT UNSIGNED NOT NULL,
    keyword_crc32 INT(10) UNSIGNED
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;

-- 영문 검색 키워드 테이블.

CREATE TABLE postcodify_english (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ko VARCHAR(40),
    ko_crc32 INT(10) UNSIGNED,
    en VARCHAR(40),
    en_crc32 INT(10) UNSIGNED
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;

-- 지번 및 건물번호 검색 테이블.

CREATE TABLE postcodify_numbers (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    address_id INT UNSIGNED NOT NULL,
    num_major SMALLINT(5) UNSIGNED,
    num_minor SMALLINT(5) UNSIGNED
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;

-- 건물명 검색 키워드 테이블.

CREATE TABLE postcodify_buildings (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    address_id INT UNSIGNED NOT NULL,
    keyword VARCHAR(120)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;

-- 사서함 검색 키워드 테이블.

CREATE TABLE postcodify_pobox (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    address_id INT UNSIGNED NOT NULL,
    keyword VARCHAR(40),
    range_start_major SMALLINT(5) UNSIGNED,
    range_start_minor SMALLINT(5) UNSIGNED,
    range_end_major SMALLINT(5) UNSIGNED,
    range_end_minor SMALLINT(5) UNSIGNED
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;

-- 우편번호 범위 테이블 (도로명주소).

CREATE TABLE postcodify_ranges_roads (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    sido_ko VARCHAR(40),
    sido_en VARCHAR(40),
    sigungu_ko VARCHAR(40),
    sigungu_en VARCHAR(40),
    ilbangu_ko VARCHAR(40),
    ilbangu_en VARCHAR(40),
    eupmyeon_ko VARCHAR(40),
    eupmyeon_en VARCHAR(40),
    road_name_ko VARCHAR(80),
    road_name_en VARCHAR(80),
    range_start_major SMALLINT(5) UNSIGNED,
    range_start_minor SMALLINT(5) UNSIGNED,
    range_end_major SMALLINT(5) UNSIGNED,
    range_end_minor SMALLINT(5) UNSIGNED,
    range_type TINYINT(1) DEFAULT 0,
    is_basement TINYINT(1) DEFAULT 0,
    postcode5 CHAR(5)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;

-- 우편번호 범위 테이블 (지번주소).

CREATE TABLE postcodify_ranges_jibeon (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    sido_ko VARCHAR(40),
    sido_en VARCHAR(40),
    sigungu_ko VARCHAR(40),
    sigungu_en VARCHAR(40),
    ilbangu_ko VARCHAR(40),
    ilbangu_en VARCHAR(40),
    eupmyeon_ko VARCHAR(40),
    eupmyeon_en VARCHAR(40),
    dongri_ko VARCHAR(80),
    dongri_en VARCHAR(80),
    range_start_major SMALLINT(5) UNSIGNED,
    range_start_minor SMALLINT(5) UNSIGNED,
    range_end_major SMALLINT(5) UNSIGNED,
    range_end_minor SMALLINT(5) UNSIGNED,
    range_type TINYINT(1) DEFAULT 0,
    is_mountain TINYINT(1) DEFAULT 0,
    postcode5 CHAR(5)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;

-- 우편번호 범위 테이블 (구 우편번호).

CREATE TABLE postcodify_ranges_oldcode (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    sido_ko VARCHAR(40),
    sido_en VARCHAR(40),
    sigungu_ko VARCHAR(40),
    sigungu_en VARCHAR(40),
    ilbangu_ko VARCHAR(40),
    ilbangu_en VARCHAR(40),
    eupmyeon_ko VARCHAR(40),
    eupmyeon_en VARCHAR(40),
    dongri_ko VARCHAR(80),
    dongri_en VARCHAR(80),
    range_start_major SMALLINT(5) UNSIGNED,
    range_start_minor SMALLINT(5) UNSIGNED,
    range_end_major SMALLINT(5) UNSIGNED,
    range_end_minor SMALLINT(5) UNSIGNED,
    is_mountain TINYINT(1) DEFAULT 0,
    island_name VARCHAR(80),
    building_name VARCHAR(80),
    building_num_start SMALLINT(5) UNSIGNED,
    building_num_end SMALLINT(5) UNSIGNED,
    postcode6 CHAR(6)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;

-- 각종 설정을 저장하는 테이블.

CREATE TABLE postcodify_settings (
    k VARCHAR(20) PRIMARY KEY,
    v VARCHAR(40)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci;
