
### 저작권 및 라이선스 ###

[포에시스](http://www.poesis.kr/)에서 만든 [새주소 검색](http://newaddress.kr/) API를
직접 구현하는 데 필요한 DB 생성 스크립트와 서버측 검색 API, 클라이언트 API 일체를 오픈소스로 공개합니다.

라이선스는 LGPLv3을 따릅니다. 누구나 자유롭게 사용, 변형, 배포, 상용 프로그램에 포함하여 판매하실 수 있으나,
기능을 개선하신 경우 가능하면 GitHub을 통해 공개하셔서
더 많은 사람들이 개선된 프로그램을 사용할 수 있도록 해주시면 감사하겠습니다.

버그 신고, 그 밖의 문의, 기술지원 상담은 root@poesis.kr로 연락 주시기 바랍니다.

### 클라이언트 API 사용법 ###

클라이언트 API는 [jQuery](http://www.jquery.com/) 플러그인으로 제공됩니다.

우편번호 검색 기능이 필요한 웹페이지에 아래와 같이 `<div>`를 생성한 후
최근 버전의 jQuery와 `api/search.js` 파일을 로딩하면 됩니다.

    <div id="postcodify"></div>
    <script src="//api.poesis.kr/common/jquery-1.11.1.min.js" charset="UTF-8"></script>
    <script src="//api.poesis.kr/post/search.js" charset="UTF-8"></script>
    <script type="text/javascript">
        $("#postcodify").postcodify();
    </script>

위의 예제는 기본 사용법입니다. `postcodify()`를 호출하면 즉시 검색 기능을 사용할 수 있으나,
검색 결과를 폼에 입력하려면 아래와 같이 설정을 변경하여
어떤 `<input>`에 어떻게 입력할지 지정해 주셔야 합니다.

    $("#검색란을_표시할_div의_id").postcodify({
        api : "search.php",  // 서버측 검색 API를 직접 설치하신 경우에만 설정
        apiBackup : "백업 API의 주소",  // 서버 접속 실패시 재시도할 다른 서버의 주소
        callBackupFirst : false,  // 백업 API를 먼저 호출할지 여부
        controls : "#키워드_입력란을_표시할_div의_id",
        searchButtonContent : "검색",  // 검색 단추에 표시할 내용 (HTML 사용 가능)
        hideOldAddresses : true,  // 기존 주소 목록을 숨길지 여부 (숨길 경우 화살표 클릭하면 표시)
        mapLinkProvider : "google",  // 지도 링크를 표시할지 여부 (daum, naver, google, 또는 false)
        mapLinkContent : "지도",  // 지도 링크에 표시할 내용 (HTML 사용 가능)
        insertDbid : "#안행부_관리번호를_입력할_input의_id",
        insertPostcode5 : "#기초구역번호를_입력할_input의_id",
        insertPostcode6 : "#우편번호를_입력할_input의_id",
        insertAddress : "#도로명주소를_입력할_input의_id",
        insertDetails : "#상세주소를_입력할_input의_id",
        insertExtraInfo : "#참고항목을_입력할_input의_id",
        insertEnglishAddress : "#영문주소를_입력할_input의_id",
        insertJibeonAddress : "#지번주소를_입력할_input의_id",
        timeout : 3000,  // 검색 타임아웃 (1/1000초 단위)
        timeoutBackup : 6000,  // 백업 API 검색 타임아웃 (1/1000초 단위)
        ready : function() {
            // Postcodify 셋팅 완료시 호출할 콜백 
        },
        beforeSearch : function(keywords) {
            // 검색 직전에 호출할 콜백
        },
        afterSearch : function(keywords, results) {
            // 검색 완료 직후에 호출할 콜백
        },
        beforeSelect : function(selectedEntry) {
            // 선택한 주소를 input에 입력하기 직전에 호출할 콜백
        },
        afterSelect : function(selectedEntry) {
            // 선택한 주소를 input에 입력한 직후에 호출할 콜백
        },
        onSuccess : function() {
            // 검색 성공시 호출할 콜백
        },
        onBackup : function() {
            // 검색에 실패하여 백업 API로 재시도할 경우 호출할 콜백
        },
        onError : function() {
            // 검색에 실패한 경우 호출할 콜백
        },
        onComplete : function() {
            // 검색 완료 후 호출할 콜백 (성공 여부와 무관함)
        },
        focusKeyword : true,  // 페이지 로딩 직후 키워드 입력란으로 포커스 이동 여부
        focusDetails : true,  // 주소 선택 후 상세주소 입력란으로 포커스 이동 여부
        useFullJibeon : true  // false인 경우 참고항목에 법정동과 공동주택명만 표시
                              // true인 경우 대표지번도 표시 (택배 등의 편의를 위해)
            // 익스플로러 호환성을 위해 마지막 항목 뒤에는 쉼표(,) 입력 금지
    });

콜백 함수를 사용하면 더욱 다양한 기능을 구현할 수 있습니다.
`beforeSearch`, `afterSearch`, `beforeSelect`, `afterSelect` 콜백에서 `false`를 반환할 경우
검색을 중지시키거나 주소 선택을 취소할 수 있습니다.
`onSuccess`, `onBackup`, `onError`, `onComplete` 콜백을 사용하면
API 호출의 각 단계에서 추가로 프로그램을 실행하거나,
기다리고 있는 사용자에게 적절한 메시지를 보여줄 수 있습니다.

백업 서버 호출 기능은 1.7 버전부터 지원됩니다.
검색 서버 연결 도중 오류가 발생하거나 타임아웃에 걸리면
자동으로 백업 서버에 동일한 요청을 보내고,
이 때 백업 서버 호출에 성공하면 현재 검색은 물론이고
해당 페이지에서의 추가 검색도 모두 백업 서버로 자동 전달됩니다.

지도 링크 기능은 1.6 버전부터 지원되며, 다음, 네이버, 구글 지도 중 선택할 수 있습니다.
지도 링크시에는 해당 서비스 제공자의 서비스 약관을 유념해야 합니다.
구글 지도는 여주군 → 여주시 등 국내 행정구역 변경 사항이 반영되는 데
1년 이상 걸리기도 하므로 주의하시기 바랍니다.

디자인 커스터마이징은 `example` 폴더 내의 HTML과 CSS를 참조하시면 됩니다.
F12 키를 눌러 웹브라우저의 개발자도구를 사용하시면
자바스크립트가 생성하는 태그들의 자세한 구조를 찾아보실 수 있습니다.

#### 무료 API 사용시 유의사항 ###

무료 API는 도메인당 하루 1천 회, 클라이언트 IP당 하루 1천 회 내외의 쿼리수 제한이 있습니다.
이 제한은 예고 없이 변경될 수 있으며, 지속적으로 과부하를 일으키는 경우 차단될 수도 있습니다.

하루 1천 회 이상의 검색을 필요로 하는 사이트에서는
아래의 서버측 검색 API와 인덱서를 사용하여 직접 검색서버를 구축하시기 바랍니다.

웹사이트에서 검색 기능을 제공할 때는 읍/면/동 또는 도로명은 물론이고,
번지수 또는 건물번호까지 자세히 입력하도록 사용자들에게 적절한 설명을 제공하시기 바랍니다.
도로명주소의 특성상, 기존 방식처럼 읍/면/동 또는 도로명까지만 입력할 경우
검색 결과가 너무 많이 나와서 사용자 입장에서도 불편하고 검색 속도도 느려지게 됩니다.

버전 1.7.1부터는 무료 API 서버가 다운될 경우 자동으로 백업 API 서버를 호출합니다.
백업서버 자동 전환 기능을 사용하려면 포함된 예제를 참고하시기 바랍니다.

#### 검색서버 직접 구축시 유의사항 ####

검색 서버를 직접 구축하신 경우에는 `api` 설정을 변경하시고,
백업 서버까지 구축하신 경우에는 `apiBackup` 설정도 변경하시기 바랍니다.

검색 서버를 직접 구축하였으나 가끔 오류 발생시 무료 API를 백업 서버로 사용하기를 원하시는 경우,
직접 구축하신 검색 서버의 주소를 `api` 설정에 입력하시고
무료 API 서버의 주소를 `apiBackup` 설정에 입력하시면 됩니다.

무료 API를 사용하시는 경우에는 위의 설정을 변경하지 않아도 됩니다.

### 서버측 검색 API 사용법 ###

인덱서 프로그램을 사용하여 우편번호 DB를 생성하거나 아래의 덤프를 다운받아
서버측 검색 API와 연동하면 직접 검색서버를 구축하실 수 있습니다.

서버측 검색 API는 PHP 클래스 형태로 제공됩니다.
`search.php`를 직접 호출하면 클라이언트 API와 호환되는 JSON 또는 JSONP 포맷으로 검색 결과를 출력합니다.
검색 결과를 직접 출력하지 않고 다른 어플리케이션에서 사용하시려면
`postcodify.class.php`에서 제공하는 클래스를 아래와 같이 활용할 수 있습니다.

    $results = Postcodify::search('검색 키워드 123-4');

서버 API를 구동하려면 PHP 5.2 이상, MySQL 또는 SQLite, 그리고 mbstring 모듈이 필요합니다.
(그 이하의 버전에서도 실행이 가능할 수 있으나, 성능 및 호환성을 보장할 수 없습니다.)

EUC-KR (CP949) 환경의 서버에서 검색하려면 아래와 같이 문자셋을 지정해 주면 됩니다.

    $results = Postcodify::search('검색 키워드 123-4', 'CP949');

사용 가능한 DB는 MySQL과 SQLite입니다.
아래에서 설명하는 인덱서는 MySQL DB를 생성하기 위한 프로그램이며,
SQLite DB를 직접 생성하려면 일단 MySQL DB를 생성한 후
`sqlite-convert.php` 쉘스크립트를 사용하여 SQLite로 변환하는 작업이 필요합니다.

MySQL에 연결하는 방식은 PDO, MySQLi, MySQL을 모두 지원하며, 서버 환경에 따라 자동으로 선택합니다.
빠른 검색을 위해 InnoDB의 버퍼 크기를 최소 500MB, 가능하면 1GB 이상으로 설정하시기 바랍니다.
메모리 용량이 1GB 미만인 서버를 사용하는 경우 검색 성능이 낮아질 수 있습니다.

SQLite를 사용하려면 PDO가 필요합니다.

서버 API를 사용하실 때는 `api/config-example.php` 파일을 `config.php`로 복사하여
DB 접속 정보를 입력한 후, 클라이언트 API의 `api` 설정을 변경하여
직접 구축하신 검색서버의 `search.php`를 호출하시기 바랍니다.

#### DB 덤프 다운로드 ####

직접 인덱서를 구동하여 DB를 생성하시기가 불편하다면 이미 생성된 DB 덤프를 다운로드받아 사용하실 수 있습니다.
DB 덤프는 아래의 주소에 부정기적으로 (1년에 몇 차례) 업로드할 예정입니다.
가장 최근 버전의 파일만 다운받으시면 됩니다.

http://storage.poesis.kr/downloads/post/

MySQL 덤프는 MariaDB 5.5 이상의 버전에서 생성하며, 실제 DB에 복구하여 사용하셔야 합니다.
SQLite DB는 SQLite 3.8 이상의 버전에서 생성하며, SQLite 3.x 버전이 설치되어 있다면 압축만 풀어서 그대로 사용 가능합니다.
모든 파일은 용량을 최소화하기 위해 xz로 압축하여 제공합니다.
리눅스 환경에서는 `xz -d` 명령으로 압축을 풀 수 있고, 윈도우 환경에서는 7-Zip 등의 무료 유틸리티를 사용하시면 됩니다.

#### 인덱서 ####

인덱서(indexer)는
[안전행정부](http://www.juso.go.kr/notice/OpenArchivesList.do?noticeKd=26&type=matching)에서 제공하는
도로명코드/주소/지번/부가정보 파일과
[우체국](http://www.epost.go.kr/search/zipcode/newAddressDown.jsp)에서 제공하는 사서함 정보를
분석하고 검색 키워드를 추출하여 MySQL DB에 입력하는 프로그램입니다.

Postcodify의 인덱서는 아래와 같은 장점이 있습니다.

1. 곧 제공이 중단될 매칭테이블이 아닌 "유통자료개선" (도로명코드/주소/지번/부가정보) 형태의 자료를 사용합니다.
   또한 정부에서 제공하는 데이터 구조를 그대로 `LOAD DATA INFILE`하지 않고
   검색 결과 표시에 최적화된 형태로 가공하여 관련지번, 건물명 등을 한눈에 볼 수 있도록 합니다.
2. 현행 우편번호와 2015년 도입될 기초구역번호를 모두 제공하여 미리 대비할 수 있도록 해 드립니다. (사서함 제외)
3. 안행부에서 매일 업로드하는 [변경분](http://www.juso.go.kr/notice/OpenArchivesList.do?noticeKd=27&type=archives) 자료를
   그때그때 간단히 적용할 수 있도록 하여, 정기적으로 업데이트 스크립트를 실행해 주기만 하면
   새로 생긴 도로명주소까지 빠짐없이 검색되도록 합니다.
4. 가능한 많은 검색어를 추출하여 별도의 테이블에 저장하여, 도로명, 법정동, 대표지번 등을 정확하게 입력하지 않아도
   단 한 번의 인덱스 스캔으로 정확한 결과를 얻을 수 있도록 합니다.

한편, 아래와 같은 단점도 있습니다.

1. 검색어 테이블이 큰 용량을 차지합니다. 지번 관련 검색어는 1500만 건, 도로명 관련 검색어는 1000만 건 내외를 추출합니다.
   주소를 저장하는 메인 테이블과 인덱스 용량까지 합치면 5-6GB의 공간을 차지합니다.
2. 최초 DB 생성에 긴 시간이 걸립니다. 시도별로 멀티쓰레딩을 사용하는 현재의 스크립트의 경우
   일반 듀얼코어 PC + 4GB 메모리 + HDD/SSD 환경에서는 2-3시간,
   최신 16코어 서버 + 32GB 메모리 + 램디스크 환경에서는 20-30분이 걸립니다.
3. 업데이트의 경우 안전행정부 제공 자료에는 기초구역번호 등 일부 정보가 누락되어 있으므로
   완전한 DB를 유지하기 위해서는 정기적으로 최신 "유통자료개선" 데이터를 다운로드받아 DB를 다시 생성해야 합니다.

### 인덱서 사용법 ###

#### 1단계 : 구동 환경 확인 ####

인덱서 스크립트를 실행하려면 PHP 5.3 이상이 필요합니다.

PHP는 웹서버가 아닌 터미널(CLI)에서 실행할 수 있어야 하며, `Zip`, `PDO`, `iconv`, `mbstring` 모듈이 필요합니다.
DB 접속은 `mysql_connect()` 함수가 아닌 PDO를 통해 이루어지므로 반드시 `PDO_mysql` 드라이버가 있어야 하며,
테이블 형태는 InnoDB를 사용합니다. InnoDB를 지원하지 않는 경우 DB 생성에 매우 긴 시간이 걸릴 수 있습니다.

인덱서 스크립트는 유닉스 환경에서만 테스트되었습니다. 윈도우에서는 정상 작동을 보장할 수 없습니다.

인덱서 스크립트는 시간 절약을 위해 PHP 자체의 멀티쓰레딩 (`pcntl_fork`) 기능을 사용하므로
해당 함수들이 컴파일되지 않았거나 php.ini에서 사용금지해 둔 경우에는 오류가 발생합니다.
일부 리눅스 배포판은 php.ini에서 `pcntl_fork` 함수를 금지해 둔 경우가 많으니 반드시 해제하고 사용하시기 바랍니다.

비교적 최근 버전의 [데비안](http://www.debian.org/) 또는 [우분투](http://www.ubuntu.com/) 배포판에서
인덱서 스크립트를 실행할 환경을 구축하려면 아래와 같이 하면 됩니다.

    sudo apt-get update
    sudo apt-get install mysql-server mysql-client php5-cli php5-json php5-mysql php5-readline
    sudo sed -i -r 's/^disable_functions/;disable_functions/' /etc/php5/cli/php.ini

우분투 일부 버전은 php5-json, php5-readline 패키지가 필요하지 않을 수도 있으므로
해당 패키지가 없다는 오류가 나올 경우 무시하셔도 됩니다.

#### 2단계 : 우편번호 파일 다운로드 ####

위의 안전행정부와 우체국 링크에서 최신 우편번호 파일들을 다운로드하여 `data` 폴더에 넣습니다.
압축파일은 인덱서 프로그램이 압축 상태에서 그대로 읽어 사용하므로 미리 압축을 해제할 필요가 없습니다.

  - 안전행정부 : **도로명코드_전체분.zip** (변경분이 아니라 반드시 전체분 사용)
  - 안전행정부 : **상세건물명.zip** (2014년 3월부터 제공)
  - 안전행정부 : **주소_서울특별시.zip**, **주소_부산광역시.zip** 등 (총 14개)
  - 안전행정부 : **지번_서울특별시.zip**, **지번_부산광역시.zip** 등 (총 14개)
  - 안전행정부 : **부가정보_서울특별시.zip**, **부가정보_부산광역시.zip** 등 (총 14개)
  - 우체국 : **newaddr_pobox_DB.zip** (사서함 정보)

위와 같이 총 45개의 파일이 필요합니다. **"매칭테이블"이라고 되어 있는 파일은 사용하시면 안됩니다.**

※ 간단하게 테스트하기를 원하시는 경우에는 일부 시도의 파일만 넣고 사용하셔도 됩니다.
세종시, 울산광역시, 대전광역시 등 용량이 얼마 되지 않는 시도의 파일로 테스트하시면 편리합니다.
단, 원하시는 시도의 주소/지번/부가정보 파일 3개는 꼭 넣으셔야 합니다.

※ 인덱서에 포함된 `download-start.php` 스크립트를 사용하면
안정행정부와 우체국에서 최신 DB 파일을 한꺼번에 다운로드할 수 있습니다.
수십 개의 파일을 일일이 다운로드하기가 불편하시다면 이 스크립트를 사용하셔도 됩니다.
단, 해당 기관에서 웹사이트를 개편하여 링크 주소가 달라질 경우 다운로드 스크립트가 작동하지 않을 수도 있습니다.

#### 3단계 : 설정 ####

`indexer/config-example.php` 파일을 `config.php`로 복사하여
파일을 다운로드해 둔 경로를 입력하고, DB 접속 정보를 넣습니다.

#### 4단계 : DB 생성 스크립트 실행 ####

`indexer` 폴더로 이동한 후, 터미널에서 `php mysql-start.php`를 실행합니다.
서버 사양에 따라 최소 30분에서 최대 6시간 정도가 걸릴 수 있습니다.
실행되는 동안에는 아래와 같은 메시지가 출력됩니다.

정부에서 제공한 월별 데이터에는 최종 변경 날짜가 누락되어 있으므로
나중에 정확하게 업데이트를 적용하려면 기준일을 직접 입력해 주어야 합니다.
현재는 매월 23~25일 기준 데이터가 제공되고 있습니다.
(위에서 언급한 다운로드 스크립트를 사용할 경우 기준일이 자동으로 입력됩니다.)

    $ php mysql-start.php
    
    [Step 1/8] 테이블과 프로시저를 생성하는 중 ... 
    
    데이터 기준일을 입력해 주십시오. 예: 2014년 4월 25일 = 20140425 : 20140425
    
    [Step 2/8] 도로 목록 및 영문 명칭을 메모리에 읽어들이는 중 ... 
    
      -->  도로명코드_전체분.zip ...    350,092
    
    [Step 3/8] 상세건물명 데이터를 메모리에 읽어들이는 중 ... 
    
      -->  상세건물명.zip ...     71,798
    
    [Step 4/8] 쓰레드를 사용하여 "주소" 파일을 로딩하는 중 ... 
    
      -->  주소_강원도.zip 쓰레드 시작 ... 
      -->  주소_경기도.zip 쓰레드 시작 ... 
      -->  주소_경상남북도.zip 쓰레드 시작 ... 
      -->  주소_광주광역시.zip 쓰레드 시작 ... 
      -->  주소_대구광역시.zip 쓰레드 시작 ... 
      -->  주소_대전광역시.zip 쓰레드 시작 ... 
      -->  주소_부산광역시.zip 쓰레드 시작 ... 
      -->  주소_서울특별시.zip 쓰레드 시작 ... 
      -->  주소_세종특별자치시.zip 쓰레드 시작 ... 
      -->  주소_울산광역시.zip 쓰레드 시작 ... 
      -->  주소_인천광역시.zip 쓰레드 시작 ... 
      -->  주소_전라남북도.zip 쓰레드 시작 ... 
      -->  주소_제주특별자치도.zip 쓰레드 시작 ... 
      -->  주소_충청남북도.zip 쓰레드 시작 ... 
    
      <--  주소_세종특별자치시.zip 쓰레드 종료. 13 쓰레드 남음.
      <--  주소_울산광역시.zip 쓰레드 종료. 12 쓰레드 남음.
      <--  주소_대전광역시.zip 쓰레드 종료. 11 쓰레드 남음.
      <--  주소_제주특별자치도.zip 쓰레드 종료. 10 쓰레드 남음.
      <--  주소_광주광역시.zip 쓰레드 종료. 9 쓰레드 남음.
      <--  주소_인천광역시.zip 쓰레드 종료. 8 쓰레드 남음.
      <--  주소_대구광역시.zip 쓰레드 종료. 7 쓰레드 남음.
      <--  주소_강원도.zip 쓰레드 종료. 6 쓰레드 남음.
      <--  주소_부산광역시.zip 쓰레드 종료. 5 쓰레드 남음.
      <--  주소_서울특별시.zip 쓰레드 종료. 4 쓰레드 남음.
      <--  주소_충청남북도.zip 쓰레드 종료. 3 쓰레드 남음.
      <--  주소_경기도.zip 쓰레드 종료. 2 쓰레드 남음.
      <--  주소_전라남북도.zip 쓰레드 종료. 1 쓰레드 남음.
      <--  주소_경상남북도.zip 쓰레드 종료. 0 쓰레드 남음.
    
    [Step 5/8] 쓰레드를 사용하여 "지번" 파일을 로딩하는 중 ... 
    
      -->  지번_강원도.zip 쓰레드 시작 ... 
      -->  지번_경기도.zip 쓰레드 시작 ... 
      -->  지번_경상남북도.zip 쓰레드 시작 ... 
      -->  지번_광주광역시.zip 쓰레드 시작 ... 
      -->  지번_대구광역시.zip 쓰레드 시작 ... 
      -->  지번_대전광역시.zip 쓰레드 시작 ... 
      -->  지번_부산광역시.zip 쓰레드 시작 ... 
      -->  지번_서울특별시.zip 쓰레드 시작 ... 
      -->  지번_세종특별자치시.zip 쓰레드 시작 ... 
      -->  지번_울산광역시.zip 쓰레드 시작 ... 
      -->  지번_인천광역시.zip 쓰레드 시작 ... 
      -->  지번_전라남북도.zip 쓰레드 시작 ... 
      -->  지번_제주특별자치도.zip 쓰레드 시작 ... 
      -->  지번_충청남북도.zip 쓰레드 시작 ... 
    
      <--  지번_세종특별자치시.zip 쓰레드 종료. 13 쓰레드 남음.
      <--  지번_울산광역시.zip 쓰레드 종료. 12 쓰레드 남음.
      <--  지번_대전광역시.zip 쓰레드 종료. 11 쓰레드 남음.
      <--  지번_제주특별자치도.zip 쓰레드 종료. 10 쓰레드 남음.
      <--  지번_광주광역시.zip 쓰레드 종료. 9 쓰레드 남음.
      <--  지번_인천광역시.zip 쓰레드 종료. 8 쓰레드 남음.
      <--  지번_대구광역시.zip 쓰레드 종료. 7 쓰레드 남음.
      <--  지번_부산광역시.zip 쓰레드 종료. 6 쓰레드 남음.
      <--  지번_강원도.zip 쓰레드 종료. 5 쓰레드 남음.
      <--  지번_서울특별시.zip 쓰레드 종료. 4 쓰레드 남음.
      <--  지번_충청남북도.zip 쓰레드 종료. 3 쓰레드 남음.
      <--  지번_경기도.zip 쓰레드 종료. 2 쓰레드 남음.
      <--  지번_전라남북도.zip 쓰레드 종료. 1 쓰레드 남음.
      <--  지번_경상남북도.zip 쓰레드 종료. 0 쓰레드 남음.
    
    [Step 6/8] 쓰레드를 사용하여 "부가정보" 파일을 로딩하는 중 ... 
    
      -->  부가정보_강원도.zip 쓰레드 시작 ... 
      -->  부가정보_경기도.zip 쓰레드 시작 ... 
      -->  부가정보_경상남북도.zip 쓰레드 시작 ... 
      -->  부가정보_광주광역시.zip 쓰레드 시작 ... 
      -->  부가정보_대구광역시.zip 쓰레드 시작 ... 
      -->  부가정보_대전광역시.zip 쓰레드 시작 ... 
      -->  부가정보_부산광역시.zip 쓰레드 시작 ... 
      -->  부가정보_서울특별시.zip 쓰레드 시작 ... 
      -->  부가정보_세종특별자치시.zip 쓰레드 시작 ... 
      -->  부가정보_울산광역시.zip 쓰레드 시작 ... 
      -->  부가정보_인천광역시.zip 쓰레드 시작 ... 
      -->  부가정보_전라남북도.zip 쓰레드 시작 ... 
      -->  부가정보_제주특별자치도.zip 쓰레드 시작 ... 
      -->  부가정보_충청남북도.zip 쓰레드 시작 ... 
    
      <--  부가정보_세종특별자치시.zip 쓰레드 종료. 13 쓰레드 남음.
      <--  부가정보_울산광역시.zip 쓰레드 종료. 12 쓰레드 남음.
      <--  부가정보_대전광역시.zip 쓰레드 종료. 11 쓰레드 남음.
      <--  부가정보_광주광역시.zip 쓰레드 종료. 10 쓰레드 남음.
      <--  부가정보_제주특별자치도.zip 쓰레드 종료. 9 쓰레드 남음.
      <--  부가정보_인천광역시.zip 쓰레드 종료. 8 쓰레드 남음.
      <--  부가정보_대구광역시.zip 쓰레드 종료. 7 쓰레드 남음.
      <--  부가정보_강원도.zip 쓰레드 종료. 6 쓰레드 남음.
      <--  부가정보_부산광역시.zip 쓰레드 종료. 5 쓰레드 남음.
      <--  부가정보_서울특별시.zip 쓰레드 종료. 4 쓰레드 남음.
      <--  부가정보_충청남북도.zip 쓰레드 종료. 3 쓰레드 남음.
      <--  부가정보_경기도.zip 쓰레드 종료. 2 쓰레드 남음.
      <--  부가정보_전라남북도.zip 쓰레드 종료. 1 쓰레드 남음.
      <--  부가정보_경상남북도.zip 쓰레드 종료. 0 쓰레드 남음.
    
    [Step 7/8] 사서함 데이터를 로딩하는 중 ... 
    
      -->  newaddr_pobox_DB.zip ... 
    
    데이터 입력을 마쳤습니다. 경과 시간 : 37분 44초
    
    [Step 8/8] 인덱스를 생성하는 중. 긴 시간이 걸릴 수 있습니다 ... 
    
      -->  postcode_addresses 쓰레드 시작 ... 
      -->  postcode_keywords_juso 쓰레드 시작 ... 
      -->  postcode_keywords_jibeon 쓰레드 시작 ... 
      -->  postcode_keywords_building 쓰레드 시작 ... 
      -->  postcode_keywords_pobox 쓰레드 시작 ... 

      <--  postcode_keywords_pobox 쓰레드 종료. 4 쓰레드 남음.
      <--  postcode_keywords_building 쓰레드 종료. 3 쓰레드 남음.
      <--  postcode_keywords_juso 쓰레드 종료. 2 쓰레드 남음.
      <--  postcode_addresses 쓰레드 종료. 1 쓰레드 남음.
      <--  postcode_keywords_jibeon 쓰레드 종료. 0 쓰레드 남음.
    
    작업을 모두 마쳤습니다. 경과 시간 : 40분 39초

작업이 끝나면 아래와 같이 6개의 테이블이 생성되고,

>   <img src="indexer/resources/tables.png" alt="Screenshot" title="" />

검색을 위한 10개의 프로시저가 생성됩니다.

    postcode_search_building 
    postcode_search_building_in_area 
    postcode_search_building_with_dongri 
    postcode_search_building_with_dongri_in_area 
    postcode_search_jibeon 
    postcode_search_jibeon_in_area 
    postcode_search_juso 
    postcode_search_juso_in_area 
    postcode_search_pobox 
    postcode_search_pobox_in_area 

만약 생성된 DB의 구조나 용량이 위의 스크린샷과 크게 다르다면 문제가 있을 가능성이 높습니다.

#### 5단계 : 업데이트 적용 ####

최신 업데이트를 원하시는 경우, 안전행정부에서 업데이트 파일들을 다운로드하여 `data/Updates` 폴더에 넣으시면 됩니다.
업데이트에 사용할 파일명은 아래와 유사합니다.

  - AlterD.JUSUBH.20140337.MatchingTable.TXT
  - AlterD.JUSUZC.20140327.TI_SPRD_STRET.TXT

**반드시 TI_SPRD_STRET와 MatchingTable 파일을 모두 사용하셔야 합니다.**

※ 인덱서에 포함된 `download-update.php` 스크립트를 사용하면
필요한 업데이트 파일을 한꺼번에 다운로드할 수 있습니다.

다운로드받은 업데이트를 적용하려면 터미널에서 `php mysql-update.php`를 실행하면 됩니다.

1단계에서 다운로드한 도로명코드/주소/지번/부가정보 파일의 기준일 이전 업데이트는 필요하지 않습니다.
예를 들어 나머지 파일들의 기준일이 3월 25일이라면 3월 26일분 업데이트부터 적용하시면 됩니다.

안전행정부 제공 업데이트 파일에는 기초구역번호, 대표지번 외의 지번 목록 등 일부 정보가 누락되어 있으므로
한 달에 한 번 정도는 최신 "유통자료개선" 데이터를 다운로드받아 DB를 다시 생성하는 것이 좋습니다.

#### 6단계 : 테스트 ####

서버 API 스크립트는 웹이 아닌 터미널에서도 쉽게 테스트할 수 있습니다.
`api` 폴더로 이동한 후 아래와 같이 검색 테스트를 하시면 됩니다.

    php search.php "검색할 주소"

DB를 방금 생성하였거나 백업/복구한 경우 인덱스가 아직 버퍼에 올라오지 않아 검색이 느릴 수 있습니다.
도로명주소, 지번 주소, 건물명 등 다양한 형태의 검색을 몇 번 하시면 버퍼가 채워지면서 검색 속도가 향상됩니다.
여러 번 검색한 후에도 검색 속도가 계속 1초 이상 나오는 경우
서버 사양이 부족하거나 DB 설정이 잘못되었을 수 있습니다.

#### SQLite 변환 ####

MySQL DB를 SQLite로 변환하여 사용할 수 있습니다.

    php sqlite-convert.php filename.db

이렇게 쉘에서 실행하면 filename.db라는 명칭의 SQLite DB가 생성됩니다.

#### DB 백업 및 복구시 주의사항 ####

일반적인 `mysqldump` 명령에 별다른 옵션을 주지 않고 DB를 백업하면
검색에 반드시 필요한 stored procedure가 포함되지 않아서 나중에 복구할 경우 문제가 생깁니다.
필요한 모든 정보가 백업에 포함되도록 반드시 `--opt --routines` 옵션을 사용하시기 바랍니다.

Stored procedure의 경우 덤프를 생성한 사용자와 복구하는 사용자가 다르면
`DEFINER` 부분이 오류를 일으킬 수 있습니다.
만약 이런 문제가 발생한다면 덤프 파일에서 `DEFINER` 부분을 제거하거나
원래와 동일한 사용자 계정을 사용해야 합니다.

첨부한 `mysqldump.sh` 스크립트는 위의 문제들을 우회할 수 있도록 작성했으니
이 스크립트를 참조하여 백업하시면 편리합니다.
