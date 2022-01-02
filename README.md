
### Postcodify 소개

Postcodify는 웹 페이지에서 도로명주소, 지번주소, 영문주소 등을 편리하게 검색할 수 있도록 해주는 프로그램입니다.

6백만 건이 넘는 도로명주소 DB를 직접 구축하거나 관리할 필요도 없고, 어렵게 검색 알고리듬을 개발할 필요도 없습니다.
우편번호 검색이 필요한 웹 페이지에 몇 줄의 jQuery 코드를 붙여넣기만 하면 검색창을 뚝딱 만들어 드립니다.

    <div id="postcodify"></div>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
    <script src="//d1p7wdleee1q2z.cloudfront.net/post/search.min.js"></script>
    <script type="text/javascript">
            $("#postcodify").postcodify();
    </script>

자세한 사용법, 기능 소개, 커스터마이징 방법은
[공식 사이트](https://www.poesis.org/postcodify/)의 매뉴얼을 참조하시기 바랍니다.

  - [구현 예제](https://www.poesis.org/postcodify/guide/example)
  - [무료 API 및 CDN 활용 안내](https://www.poesis.org/postcodify/guide/freeapi)
  - [jQuery 팝업창 매뉴얼](https://www.poesis.org/postcodify/guide/jquery_popup)
  - [jQuery 플러그인 매뉴얼](https://www.poesis.org/postcodify/guide/jquery_plugin)
  - [검색서버 구축 안내](https://www.poesis.org/postcodify/guide/owndb)
  - [API 에뮬레이션 기능 안내](https://www.poesis.org/postcodify/guide/emulation)

### 라이센스

DB 생성 스크립트와 서버측 검색 API, 클라이언트 API 등
Postcodify의 모든 구성요소는 LGPLv3 라이센스 하에 오픈소스로 공개되어 있습니다.

개인, 기업, 단체, 공공기관 등 누구나 무료로 사용하실 수 있으며
상용 프로그램에 포함하여 판매하셔도 되지만,
버그를 수정하거나 기능을 개선하신 경우 가능하면 GitHub을 통해 공개하셔서
더 많은 사람들이 개선된 프로그램을 사용할 수 있도록 해주시면 감사하겠습니다.

### 다른 구현물

다른 어플리케이션이나 프로그래밍 언어에서 Postcodify와 연동할 수 있도록 구현한 프로그램들입니다.
Postcodify에서 공식적으로 지원하지는 않으며, 버전에 따라 호환성에 차이가 있을 수 있습니다.

  - Excel: http://blog.naver.com/lastingchild/220315968310
  - Perl: https://github.com/aanoaa/p5-postcodify

### 기타

Postcodify는 새주소 전환을 돕고 웹마스터 여러분의 수고를 덜어드리기 위해 만든 프로그램입니다.
개발자는 Postcodify와 관련하여 저작권료, 사용료, 자문료 등 어떠한 이윤도 추구하지 않으며,
무료 API도 자비와 후원금으로 운영중입니다.
따라서 검색서버에 불필요한 부담이 발생하지 않도록 사용자들에게 검색 요령을 잘 안내해 주시기 바랍니다. 

무료 API는 검색서버의 원활한 운영과 공평한 사용을 위해
도메인당, 방문자 IP당 [일일 쿼리수 제한](https://www.poesis.org/postcodify/guide/quota)을 두고 있으며,
검색 형태에 따라 가중치를 부여합니다.
쿼리수가 많은 사이트라면 매뉴얼을 참조하여 검색서버를 직접 구축하시거나,
API 운영비를 [후원](https://www.poesis.org/postcodify/guide/sponsor)해 주시면 감사하겠습니다.

Postcodify는 무료 API를 통해 수집한 주소 정보를 절대 광고에 사용하거나 제3자에게 판매하지 않습니다.

1.8, 2.0, 3.0 버전에서 많은 변화가 있었습니다.
버전별 변경 내역은 [여기](https://www.poesis.org/postcodify/guide/changelog)를 참조하시기 바랍니다.

버그 신고, 그 밖의 문의, 기술지원 상담은 [kijin@poesis.org](mailto:kijin@poesis.org?subject=Postcodify)로 연락 주시기 바랍니다.
