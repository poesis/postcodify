
### Postcodify 소개

Postcodify는 웹 페이지에서 새주소(<a href="http://www.juso.go.kr/">도로명주소</a>),
기존의 지번주소, 영문주소 등을 편리하게 검색할 수 있도록 도와 주는 프로그램입니다.

6백만 건이 넘는 새주소 우편번호 DB를 직접 구축하거나 관리할 필요도 없고, 어렵게 검색 알고리듬을 개발할 필요도 없습니다.
새주소 검색이 필요한 웹 페이지에 아래의 내용을 붙여넣기만 하면 Postcodify가 검색창을 뚝딱 만들어 드립니다.

    <div id="postcodify"></div>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
    <script src="//d1p7wdleee1q2z.cloudfront.net/post/search.min.js"></script>
    <script type="text/javascript">
            $("#postcodify").postcodify();
    </script>

자세한 사용법, 기능 소개, 커스터마이징 방법은 [공식 사이트](http://postcodify.poesis.kr/)의 매뉴얼을 참조하시기 바랍니다.

  - [구현 예제](http://postcodify.poesis.kr/guide/example)
  - [무료 API 활용 안내](http://postcodify.poesis.kr/guide/freeapi)
  - [jQuery 플러그인 매뉴얼](http://postcodify.poesis.kr/guide/jquery_plugin)
  - [검색서버 구축 안내](http://postcodify.poesis.kr/guide/owndb)

### 라이센스

DB 생성 스크립트와 서버측 검색 API, 클라이언트 API 등
Postcodify의 모든 구성요소는 LGPLv3 라이센스 하에 오픈소스로 공개되어 있습니다.

개인, 기업, 단체, 공공기관 등 누구나 무료로 사용하실 수 있으며
상용 프로그램에 포함하여 판매하셔도 되지만,
버그를 수정하거나 기능을 개선하신 경우 가능하면 GitHub을 통해 공개하셔서
더 많은 사람들이 개선된 프로그램을 사용할 수 있도록 해주시면 감사하겠습니다.

### 기타

무료 API는 과부하를 막기 위해 도메인당 하루 1천 회, 방문자 IP당 하루 1천 회 내외의 쿼리수 제한을 두고 있습니다.
쿼리수가 많은 사이트라면 매뉴얼을 참조하여 검색서버를 직접 구축하시거나,
API 운영비를 후원하는 [스폰서가 되어 주시기 바랍니다](http://postcodify.poesis.kr/guide/sponsor).

Postcodify는 새주소 전환을 돕고 웹마스터 여러분의 수고를 덜어드리기 위해 만든 프로그램입니다.
개발자는 Postcodify와 관련하여 저작권료, 사용료, 자문료 등 어떠한 이윤도 추구하지 않으며, 무료 API도 자비와 후원금으로 운영중입니다.
따라서 불필요한 쿼리가 발생하지 않도록 사용자들에게 검색 요령을 잘 안내해 주시기 바랍니다.

버그 신고, 그 밖의 문의, 기술지원 상담은 [root@poesis.kr](mailto:root@poesis.kr?subject=Postcodify)로 연락 주시기 바랍니다.
