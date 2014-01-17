
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
    num_major INT(10),
    num_minor INT(10),
    is_basement INT(1) DEFAULT 0,
    sido VARCHAR(20),
    sigungu VARCHAR(40),
    ilbangu VARCHAR(40),
    eupmyeon VARCHAR(40),
    dongri VARCHAR(80),
    jibeon VARCHAR(20),
    building_name VARCHAR(80),
    other_addresses VARCHAR(1000)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;

CREATE TABLE postcode_keywords_juso (
    seq INT(10) PRIMARY KEY AUTO_INCREMENT,
    address_id NUMERIC(25) NOT NULL,
    keyword VARCHAR(80),
    num_major INT(10),
    num_minor INT(10)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;

CREATE TABLE postcode_keywords_jibeon (
    seq INT(10) PRIMARY KEY AUTO_INCREMENT,
    address_id NUMERIC(25) NOT NULL,
    keyword VARCHAR(80),
    num_major INT(10),
    num_minor INT(10)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;

CREATE TABLE postcode_keywords_building (
    seq INT(10) PRIMARY KEY AUTO_INCREMENT,
    address_id NUMERIC(25) NOT NULL,
    keyword VARCHAR(80),
    admin_dongri VARCHAR(80),
    legal_dongri VARCHAR(80)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;

CREATE TABLE postcode_keywords_pobox (
    seq INT(10) PRIMARY KEY AUTO_INCREMENT,
    address_id NUMERIC(25) NOT NULL,
    keyword VARCHAR(80),
    range_start_major INT(10),
    range_start_minor INT(10),
    range_end_major INT(10),
    range_end_minor INT(10)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;

CREATE TABLE postcode_metadata (
    k VARCHAR(20) PRIMARY KEY,
    v VARCHAR(80)
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci;
INSERT INTO postcode_metadata (k, v) VALUES ('version', '4.0');
INSERT INTO postcode_metadata (k, v) VALUES ('updated', '00000000');

CREATE PROCEDURE postcode_search_juso(IN keyword VARCHAR(80), IN num1 INT, IN num2 INT)
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_juso AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT(keyword, '%')
    AND (num1 IS NULL OR pk.num_major = num1)
    AND (num2 IS NULL OR pk.num_minor = num2)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_juso_in_area(IN keyword VARCHAR(80), IN num1 INT, IN num2 INT,
    IN area1 VARCHAR(20), IN area2 VARCHAR(20), IN area3 VARCHAR(20), IN area4 VARCHAR(20))
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_juso AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT(keyword, '%')
    AND (num1 IS NULL OR pk.num_major = num1)
    AND (num2 IS NULL OR pk.num_minor = num2)
    AND (area1 IS NULL OR pa.sido = area1)
    AND (area2 IS NULL OR pa.sigungu = area2)
    AND (area3 IS NULL OR pa.ilbangu = area3)
    AND (area4 IS NULL OR pa.eupmyeon = area4)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_jibeon(IN keyword VARCHAR(80), IN num1 INT, IN num2 INT)
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_jibeon AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT(keyword, '%')
    AND (num1 IS NULL OR pk.num_major = num1)
    AND (num2 IS NULL OR pk.num_minor = num2)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_jibeon_in_area(IN keyword VARCHAR(80), IN num1 INT, IN num2 INT,
    IN area1 VARCHAR(20), IN area2 VARCHAR(20), IN area3 VARCHAR(20), IN area4 VARCHAR(20))
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_jibeon AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT(keyword, '%')
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

CREATE PROCEDURE postcode_search_building_with_dongri(IN keyword VARCHAR(80), IN dongri VARCHAR(80))
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_building AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT('%', keyword, '%')
    AND (pk.admin_dongri = dongri OR pk.legal_dongri = dongri)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_building_with_dongri_in_area(IN keyword VARCHAR(80), IN dongri VARCHAR(80),
    IN area1 VARCHAR(20), IN area2 VARCHAR(20), IN area3 VARCHAR(20), IN area4 VARCHAR(20))
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_building AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT('%', keyword, '%')
    AND (pk.admin_dongri = dongri OR pk.legal_dongri = dongri)
    AND (area1 IS NULL OR pa.sido = area1)
    AND (area2 IS NULL OR pa.sigungu = area2)
    AND (area3 IS NULL OR pa.ilbangu = area3)
    AND (area4 IS NULL OR pa.eupmyeon = area4)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_pobox(IN keyword VARCHAR(80), IN num1 INT, IN num2 INT)
BEGIN
    SELECT DISTINCT pa.* FROM postcode_addresses AS pa
    INNER JOIN postcode_keywords_pobox AS pk ON pa.id = pk.address_id
    WHERE pk.keyword LIKE CONCAT(keyword, '%')
    AND (num1 IS NULL OR num1 BETWEEN pk.range_start_major AND pk.range_end_major)
    AND (num2 IS NULL OR num2 BETWEEN pk.range_start_minor AND pk.range_end_minor)
    ORDER BY pa.sido, pa.sigungu, pa.road_name, pa.num_major, pa.num_minor
    LIMIT 100;
END;

CREATE PROCEDURE postcode_search_pobox_in_area(IN keyword VARCHAR(80), IN num1 INT, IN num2 INT,
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
