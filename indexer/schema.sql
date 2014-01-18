
DROP TABLE IF EXISTS postcode_addresses;
DROP TABLE IF EXISTS postcode_keywords_juso;
DROP TABLE IF EXISTS postcode_keywords_jibeon;
DROP TABLE IF EXISTS postcode_keywords_building;
DROP TABLE IF EXISTS postcode_keywords_pobox;
DROP TABLE IF EXISTS postcode_metadata;

DROP PROCEDURE IF EXISTS postcode_search_juso;
DROP PROCEDURE IF EXISTS postcode_search_juso_in_area;
DROP PROCEDURE IF EXISTS postcode_search_jibeon;
DROP PROCEDURE IF EXISTS postcode_search_jibeon_in_area;
DROP PROCEDURE IF EXISTS postcode_search_building;
DROP PROCEDURE IF EXISTS postcode_search_building_in_area;
DROP PROCEDURE IF EXISTS postcode_search_building_with_dongri;
DROP PROCEDURE IF EXISTS postcode_search_building_with_dongri_in_area;
DROP PROCEDURE IF EXISTS postcode_search_pobox;
DROP PROCEDURE IF EXISTS postcode_search_pobox_in_area;

CREATE TABLE postcode_addresses (
    id NUMERIC(25) PRIMARY KEY,
    postcode5 CHAR(5),
    postcode6 CHAR(6),
    road_id NUMERIC(12),
    road_section CHAR(2),
    road_name VARCHAR(80),
    num_major SMALLINT(5) UNSIGNED,
    num_minor SMALLINT(5) UNSIGNED,
    is_basement TINYINT(1) DEFAULT 0,
    sido VARCHAR(20),
    sigungu VARCHAR(20),
    ilbangu VARCHAR(20),
    eupmyeon VARCHAR(20),
    dongri VARCHAR(20),
    jibeon VARCHAR(10),
    building_name VARCHAR(40),
    other_addresses VARCHAR(600)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;

CREATE TABLE postcode_keywords_juso (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    address_id NUMERIC(25) NOT NULL,
    keyword_crc32 INT(10) UNSIGNED,
    num_major SMALLINT(5) UNSIGNED,
    num_minor SMALLINT(5) UNSIGNED
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;

CREATE TABLE postcode_keywords_jibeon (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    address_id NUMERIC(25) NOT NULL,
    keyword_crc32 INT(10) UNSIGNED,
    num_major SMALLINT(5) UNSIGNED,
    num_minor SMALLINT(5) UNSIGNED
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;

CREATE TABLE postcode_keywords_building (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    address_id NUMERIC(25) NOT NULL,
    keyword VARCHAR(40),
    admin_dongri INT(10) UNSIGNED,
    legal_dongri INT(10) UNSIGNED
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;

CREATE TABLE postcode_keywords_pobox (
    seq INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    address_id NUMERIC(25) NOT NULL,
    keyword VARCHAR(40),
    range_start_major SMALLINT(5) UNSIGNED,
    range_start_minor SMALLINT(5) UNSIGNED,
    range_end_major SMALLINT(5) UNSIGNED,
    range_end_minor SMALLINT(5) UNSIGNED
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;

CREATE TABLE postcode_metadata (
    k VARCHAR(20) PRIMARY KEY,
    v VARCHAR(40)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;
INSERT INTO postcode_metadata (k, v) VALUES ('version', '4.1');
INSERT INTO postcode_metadata (k, v) VALUES ('updated', '00000000');

CREATE PROCEDURE postcode_search_juso(IN keyword_crc32 INT UNSIGNED, IN num1 SMALLINT UNSIGNED, IN num2 SMALLINT UNSIGNED)
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_juso AS pk ON pa.id = pk.address_id
    WHERE pk.keyword_crc32 = keyword_crc32
    AND (num1 IS NULL OR pk.num_major = num1)
    AND (num2 IS NULL OR pk.num_minor = num2)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_juso_in_area(IN keyword_crc32 INT UNSIGNED, IN num1 SMALLINT UNSIGNED, IN num2 SMALLINT UNSIGNED,
    IN area1 VARCHAR(20), IN area2 VARCHAR(20), IN area3 VARCHAR(20), IN area4 VARCHAR(20))
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_juso AS pk ON pa.id = pk.address_id
    WHERE pk.keyword_crc32 = keyword_crc32
    AND (num1 IS NULL OR pk.num_major = num1)
    AND (num2 IS NULL OR pk.num_minor = num2)
    AND (area1 IS NULL OR pa.sido = area1)
    AND (area2 IS NULL OR pa.sigungu = area2)
    AND (area3 IS NULL OR pa.ilbangu = area3)
    AND (area4 IS NULL OR pa.eupmyeon = area4)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_jibeon(IN keyword_crc32 INT UNSIGNED, IN num1 SMALLINT UNSIGNED, IN num2 SMALLINT UNSIGNED)
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_jibeon AS pk ON pa.id = pk.address_id
    WHERE pk.keyword_crc32 = keyword_crc32
    AND (num1 IS NULL OR pk.num_major = num1)
    AND (num2 IS NULL OR pk.num_minor = num2)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_jibeon_in_area(IN keyword_crc32 INT UNSIGNED, IN num1 SMALLINT UNSIGNED, IN num2 SMALLINT UNSIGNED,
    IN area1 VARCHAR(20), IN area2 VARCHAR(20), IN area3 VARCHAR(20), IN area4 VARCHAR(20))
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_jibeon AS pk ON pa.id = pk.address_id
    WHERE pk.keyword_crc32 = keyword_crc32
    AND (num1 IS NULL OR pk.num_major = num1)
    AND (num2 IS NULL OR pk.num_minor = num2)
    AND (area1 IS NULL OR pa.sido = area1)
    AND (area2 IS NULL OR pa.sigungu = area2)
    AND (area3 IS NULL OR pa.ilbangu = area3)
    AND (area4 IS NULL OR pa.eupmyeon = area4)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_building(IN keyword VARCHAR(80))
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_building AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT(keyword, '%')
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_building_in_area(IN keyword VARCHAR(80),
    IN area1 VARCHAR(20), IN area2 VARCHAR(20), IN area3 VARCHAR(20), IN area4 VARCHAR(20))
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_building AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT(keyword, '%')
    AND (area1 IS NULL OR pa.sido = area1)
    AND (area2 IS NULL OR pa.sigungu = area2)
    AND (area3 IS NULL OR pa.ilbangu = area3)
    AND (area4 IS NULL OR pa.eupmyeon = area4)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_building_with_dongri(IN keyword VARCHAR(80), IN dongri_crc32 INT UNSIGNED)
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_building AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT('%', keyword, '%')
    AND (pk.admin_dongri = dongri_crc32 OR pk.legal_dongri = dongri_crc32)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_building_with_dongri_in_area(IN keyword VARCHAR(80), IN dongri_crc32 INT UNSIGNED,
    IN area1 VARCHAR(20), IN area2 VARCHAR(20), IN area3 VARCHAR(20), IN area4 VARCHAR(20))
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_building AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT('%', keyword, '%')
    AND (pk.admin_dongri = dongri_crc32 OR pk.legal_dongri = dongri_crc32)
    AND (area1 IS NULL OR pa.sido = area1)
    AND (area2 IS NULL OR pa.sigungu = area2)
    AND (area3 IS NULL OR pa.ilbangu = area3)
    AND (area4 IS NULL OR pa.eupmyeon = area4)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_pobox(IN keyword VARCHAR(80), IN num1 SMALLINT UNSIGNED, IN num2 SMALLINT UNSIGNED)
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_pobox AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT(keyword, '%')
    AND (num1 IS NULL OR num1 BETWEEN pk.range_start_major AND pk.range_end_major)
    AND (num2 IS NULL OR num2 BETWEEN pk.range_start_minor AND pk.range_end_minor)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_pobox_in_area(IN keyword VARCHAR(80), IN num1 SMALLINT UNSIGNED, IN num2 SMALLINT UNSIGNED,
    IN area1 VARCHAR(20), IN area2 VARCHAR(20), IN area3 VARCHAR(20), IN area4 VARCHAR(20))
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_pobox AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT(keyword, '%')
    AND (num1 IS NULL OR num1 BETWEEN pk.range_start_major AND pk.range_end_major)
    AND (num2 IS NULL OR num2 BETWEEN pk.range_start_minor AND pk.range_end_minor)
    AND (area1 IS NULL OR pa.sido = area1)
    AND (area2 IS NULL OR pa.sigungu = area2)
    AND (area3 IS NULL OR pa.ilbangu = area3)
    AND (area4 IS NULL OR pa.eupmyeon = area4)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;
