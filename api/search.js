
/**
 * ---------------------------------------------------------------------------------------
 *  
 *  도로명주소 우편번호 검색 기능을 제공하는 jQuery 플러그인
 *  
 *  Version 1.1.6
 *  
 * ---------------------------------------------------------------------------------------
 * 
 *  저작권: (c) 2014 성기진
 *  라이선스: GNU General Public License, version 3
 *  문의: root@poesis.kr
 * 
 * ---------------------------------------------------------------------------------------
 * 
 *  기본 사용법: 검색창을 표시할 div를 생성한 후 아래와 같이 호출
 *  
 *      $("#검색란을_표시할_div의_id").postcodify();
 *
 * ---------------------------------------------------------------------------------------
 * 
 *  고급 사용법:
 * 
 *      $("#검색란을_표시할_div의_id").postcodify({
 *          controls : "#키워드_입력란을_표시할_div의_id",  // 지정하지 않으면 검색창에 함께 표시
 *          insertDbid : "#안행부_관리번호를_입력할_input의_id",  // 지정하지 않으면 입력하지 않음
 *          insertPostcode5 : "#기초구역번호를_입력할_input의_id",  // 지정하지 않으면 입력하지 않음
 *          insertPostcode6 : "#우편번호를_입력할_input의_id",  // 지정하지 않으면 입력하지 않음
 *          insertAddress : "#도로명주소를_입력할_input의_id",  // 지정하지 않으면 입력하지 않음
 *          insertDetails : "#상세주소를_입력할_input의_id",  // 지정하지 않으면 포커스 이동하지 않음
 *          insertExtraInfo : "#참고항목을_입력할_input의_id",  // 지정하지 않으면 입력하지 않음
 *          beforeSearch : function(keywords) {
 *              // 검색 직전에 호출할 콜백
 *          },
 *          afterSearch : function(keywords, results) {
 *              // 검색 완료 직후에 호출할 콜백
 *          },
 *          beforeSelect : function(selectedEntry) {
 *              // 선택한 주소를 input에 입력하기 직전에 호출할 콜백
 *          },
 *          afterSelect : function(selectedEntry) {
 *              // 선택한 주소를 input에 입력한 직후에 호출할 콜백
 *          },
 *          focusKeyword : true,  // 페이지 로딩 직후 키워드 입력란으로 포커스 이동
 *          useFullJibeon : true  // false인 경우 참고항목에 법정동과 공동주택명만 표시
 *                                // true인 경우 대표지번도 표시 (택배 등의 편의를 위해)
 *              // 익스플로러 호환성을 위해 마지막 항목 뒤에는 쉼표(,) 입력 금지
 *      });
 *
 * --------------------------------------------------------------------------------------- 
 */

(function($) {
    
    $.fn.postcodify = function(options) {
        
        return this.each(function() {
            
            // 기본 설정을 정의한다.
            
            var settings = $.extend({
                api : "//api.poesis.kr/post/search.php",
                controls : this,
                results : this,
                insertDbid : null,
                insertPostcode5 : null,
                insertPostcode6 : null,
                insertAddress : null,
                insertDetails : null,
                insertExtraInfo : null,
                beforeSearch : function(keywords) { },
                afterSearch : function(keywords, results) { },
                beforeSelect : function(selectedEntry) { },
                afterSelect : function(selectedEntry) { },
                focusKeyword : true,
                useFullJibeon : false
            }, options);
            
            // 검색 컨트롤을 생성한다.
            
            var controls = $('<div class="postcode_search_controls"></div>');
            var keyword_input = $('<input type="text" class="keyword" value="" />').appendTo(controls);
            var search_button = $('<button type="button" class="search_button">검색</button>').appendTo(controls);
            controls.prependTo(settings.controls);
            
            // 검색 결과창을 생성한다.
            
            var results = $(settings.results);
            $('<div class="postcode_search_status empty">검색 결과가 없습니다.</div>').appendTo(results).show();
            $('<div class="postcode_search_status error">검색 중 오류가 발생하였습니다.</div>').appendTo(results).hide();
            $('<div class="postcode_search_status quota">무료 API가 허용하는 일일 쿼리수를 초과하였습니다.</div>').appendTo(results).hide();
            $('<div class="postcode_search_status too_short">검색어는 3글자 이상 입력해 주시기 바랍니다.</div>').appendTo(results).hide();
            $('<div class="postcode_search_status too_many">검색 결과가 너무 많아 100건까지만 표시합니다.<br />' +
                '행정구역명, 번지수 등을 사용하여 좀더 자세히 검색해 주시기 바랍니다.</div>').appendTo(results).hide();
            
            var summary = $('<div class="postcode_search_status summary"></div>');
            summary.append('<div class="result_count">검색 결과: <span>0</span>건</div>');
            summary.append('<div class="search_time">검색 소요 시간: <span>0</span>초</div>');
            summary.appendTo(results).hide();
            
            // 키워드 입력란이 포커스를 잃거나 엔터키를 누르면 즉시 검색을 수행한다.
            // 검색 단추를 누를 때까지 기다리는 것보다 검색 속도가 훨씬 빠르게 느껴진다.
            
            keyword_input.blur(function(event) {
                search_button.click();
            });
            
            keyword_input.keypress(function(event) {
                if (event.which == 13) {
                    event.preventDefault();
                    search_button.click();
                }
            });
            
            // 실제 검색을 수행하는 이벤트를 등록한다.
            
            search_button.click(function(event) {
                
                event.preventDefault();
                
                // 검색어가 직전과 동일한 경우 중복 검색을 방지한다.
                
                var keywords = $.trim(keyword_input.val());
                if (keywords === $.fn.postcodify.previous) return;
                $.fn.postcodify.previous = keywords;
                
                // 검색 결과창의 내용을 비운다.
                
                results.find("div.postcode_search_result").remove();
                results.find("div.postcode_search_status").hide();
                
                // 검색어가 없거나 너무 짧은 경우 네트워크 연결을 하지 않도록 한다.
                
                if (keywords === "") {
                    results.find("div.postcode_search_status.empty").show();
                    return;
                }
                if (keywords.length < 3) {
                    results.find("div.postcode_search_status.too_short").show();
                    return;
                }
                
                // 검색전 콜백 함수를 실행한다.
                
                settings.beforeSearch(keywords);
                
                // 이미 검색이 진행 중일 때는 검색 단추를 다시 클릭하지 못하도록 하고,
                // "검색" 라벨을 간단한 GIF 이미지로 대체한다.
                
                search_button.attr("disabled", "disabled");
                search_button.html('<img alt="검색" src="' + $.fn.postcodify.gif + '" />');
                
                // 스크롤 위치와 검색 시작 시각을 기억한다.
                
                var ajax_request_time = new Date().getTime();
                var scroll_top = $(window).scrollTop();
                
                // 검색 서버로 AJAX (JSONP) 요청을 전송한다.
                
                $.ajax({
                    "url": settings.api,
                    "type": "get",
                    "data": { "v": "1.1", "q": keywords, "ref": window.location.hostname },
                    "dataType": "jsonp",
                    "processData": true,
                    "cache": false,
                    
                    // 요청이 성공한 경우 이 함수를 호출한다.
                    
                    "success": function(data, textStatus, jqXHR) {
                        
                        // 검색에 소요된 시간을 측정한다. 네트워크 왕복 시간이 추가되므로 그다지 정확하지는 않다.
                        
                        var ajax_elapsed_time = (new Date().getTime() - ajax_request_time) / 1000;
                        
                        // 검색후 콜백 함수를 실행한다.
                        
                        settings.afterSearch(keywords, data.results);
                        
                        // 서버가 오류를 반환한 경우...
                        
                        if (data.error && data.error.toLowerCase().indexOf("quota") > -1) {
                            results.find("div.postcode_search_status.quota").show();
                            $.fn.postcodify.previous = "";
                        }
                        else if (data.error) {
                            results.find("div.postcode_search_status.error").show();
                            $.fn.postcodify.previous = "";
                        }
                        
                        // 검색 결과가 없는 경우...
                        
                        else if (data.count == 0) {
                            results.find("div.postcode_search_status.empty").show();
                        }
                        
                        // 검색 결과가 있는 경우 DOM에 추가한다.
                        
                        else {
                            
                            for (var i = 0; i < data.count; i++) {
                                
                                // 검색 결과 항목을 작성한다.
                                
                                var result = data.results[i];
                                var option = $('<div class="postcode_search_result"></div>');
                                option.data("dbid", result.dbid);
                                option.data("code6", result.code6);
                                option.data("code5", result.code5);
                                option.data("address", result.address);
                                option.data("extra_info_long", result.extra_info_long);
                                option.data("extra_info_short", result.extra_info_short);
                                
                                // 클릭할 링크를 생성한다.
                                
                                var selector = $('<a class="selector" href="#"></a>').text(result.address);
                                if (result.extra_info_long) {
                                    selector.append($('<span class="extra_info"></span>').append("(" + result.extra_info_long + ")"));
                                }
                                
                                // 우편번호, 기초구역번호, 주소 등을 항목에 추가한다.
                                
                                $('<div class="code6"></div>').text(result.code6).appendTo(option);
                                $('<div class="code5"></div>').text(result.code5).appendTo(option);
                                $('<div class="address"></div>').append(selector).appendTo(option);
                                
                                // 예전 주소 및 검색어 목록을 추가한다.
                                
                                if (result.other) {
                                    var old_addresses_show = $('<a href="#" class="show_old_addresses" title="관련지번 보기">▼</a>');
                                    old_addresses_show.appendTo(option.find("div.address"));
                                    var old_addresses_div = $('<div class="old_addresses"></div>').text(result.other);
                                    old_addresses_div.appendTo(option);
                                }
                                
                                option.appendTo(results);
                            }
                            
                            // 검색 결과 요약을 작성한다.
                            
                            results.find("div.postcode_search_status.summary").detach().appendTo(results).show();
                            results.find("div.postcode_search_status.summary div.result_count span").text(data.count);
                            results.find("div.postcode_search_status.summary div.search_time span").text(ajax_elapsed_time);
                            
                            if (data.count >= 100) {
                                results.find("div.postcode_search_status.too_many").show();
                            }
                        }
                    },
                    
                    // 요청이 실패한 경우 이 함수를 호출한다.
                    
                    "error": function(jqXHR, textStatus, errorThrown) {
                        
                        // 오류 메시지를 보여준다.
                        
                        results.find("div.postcode_search_status.error").show();
                        $.fn.postcodify.previous = "";
                    },
                    
                    // 요청 후에는 이 함수를 호출한다.
                    
                    "complete": function(jqXHR, textStatus) {
                        
                        // 스크롤 위치를 복구한다.
                        
                        $(window).scrollTop(scroll_top);
                        
                        // 검색 단추를 다시 사용할 수 있도록 한다.
                        
                        search_button.removeAttr("disabled").text("검색");
                    }
                });
            });
            
            // 검색 결과를 클릭할 경우 사용자가 지정한 입력란에 해당 정보가 입력되도록 한다.
            
            results.on("click", "div.code6,div.code5,div.old_addresses", function(event) {
                event.preventDefault();
                $(this).parent().find("a.selector").click();
            });
            
            results.on("click", "a.selector", function(event) {
                event.preventDefault();
                
                // 클릭한 주소를 구한다.
                
                var entry = $(this).parents("div.postcode_search_result");
                
                // 선택전 콜백을 실행한다.
                 
                settings.beforeSelect(entry);
                
                // 사용자가 지정한 입력칸에 데이터를 입력한다.
                
                if (settings.insertDbid) $(settings.insertDbid).val(entry.data("dbid"));
                if (settings.insertPostcode6) $(settings.insertPostcode6).val(entry.data("code6"));
                if (settings.insertPostcode5) $(settings.insertPostcode5).val(entry.data("code5"));
                if (settings.insertAddress) $(settings.insertAddress).val(entry.data("address"));
                if (settings.insertExtraInfo) {
                    var extra_info = settings.useFullJibeon ? entry.data("extra_info_long") : entry.data("extra_info_short");
                    if (extra_info.length) extra_info = "(" + extra_info + ")";
                    $(settings.insertExtraInfo).val(extra_info);
                }
                
                // 선택후 콜백을 실행한다.
                
                settings.afterSelect(entry);
                
                // 상세주소를 입력하는 칸으로 포커스를 이동한다.
                
                if (settings.insertDetails) {
                    $(settings.insertDetails).focus();
                }
            });
            
            // 예전 주소 및 검색어 목록을 보였다가 숨기는 기능을 만든다.
            
            results.on("click", "a.show_old_addresses", function(event) {
                event.preventDefault();
                var old_addresses = $(this).parent().siblings(".old_addresses");
                if (old_addresses.is(":visible")) {
                    $(this).html("&#9660;");
                    old_addresses.hide();
                } else {
                    $(this).html("&#9650;");
                    old_addresses.show();
                }
            });
            
            // 키워드 입력란에 포커스를 준다.
            
            if (settings.focusKeyword) keyword_input.focus();
            
            // jQuery 관례에 따라 this를 반환한다.
            
            return this;
        });
    };
    
    // 단시간내 중복 검색을 방지하기 위해 직전 검색어를 기억하는 변수.
    
    $.fn.postcodify.previous = "";
    
    // 로딩중임을 표시하는 GIF 애니메이션 파일.
    // 불필요한 요청을 줄이기 위해 base64 인코딩하여 여기에 직접 저장한다.
    
    $.fn.postcodify.gif = "data:image/gif;base64,R0lGODlhEAALAPQAAP///yIiIt7" +
        "e3tbW1uzs7CcnJyIiIklJSZKSknV1dcPDwz8/P2JiYpmZmXh4eMbGxkJCQiUlJWVlZe" +
        "np6d3d3fX19VJSUuDg4PPz87+/v6ysrNDQ0PDw8AAAAAAAAAAAACH/C05FVFNDQVBFM" +
        "i4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCwAAACwAAAAA" +
        "EAALAAAFLSAgjmRpnqSgCuLKAq5AEIM4zDVw03ve27ifDgfkEYe04kDIDC5zrtYKRa2" +
        "WQgAh+QQJCwAAACwAAAAAEAALAAAFJGBhGAVgnqhpHIeRvsDawqns0qeN5+y967tYLy" +
        "icBYE7EYkYAgAh+QQJCwAAACwAAAAAEAALAAAFNiAgjothLOOIJAkiGgxjpGKiKMkbz" +
        "7SN6zIawJcDwIK9W/HISxGBzdHTuBNOmcJVCyoUlk7CEAAh+QQJCwAAACwAAAAAEAAL" +
        "AAAFNSAgjqQIRRFUAo3jNGIkSdHqPI8Tz3V55zuaDacDyIQ+YrBH+hWPzJFzOQQaeav" +
        "Wi7oqnVIhACH5BAkLAAAALAAAAAAQAAsAAAUyICCOZGme1rJY5kRRk7hI0mJSVUXJtF" +
        "3iOl7tltsBZsNfUegjAY3I5sgFY55KqdX1GgIAIfkECQsAAAAsAAAAABAACwAABTcgI" +
        "I5kaZ4kcV2EqLJipmnZhWGXaOOitm2aXQ4g7P2Ct2ER4AMul00kj5g0Al8tADY2y6C+" +
        "4FIIACH5BAkLAAAALAAAAAAQAAsAAAUvICCOZGme5ERRk6iy7qpyHCVStA3gNa/7txx" +
        "wlwv2isSacYUc+l4tADQGQ1mvpBAAIfkECQsAAAAsAAAAABAACwAABS8gII5kaZ7kRF" +
        "GTqLLuqnIcJVK0DeA1r/u3HHCXC/aKxJpxhRz6Xi0ANAZDWa+kEAA7AAAAAAAAAAAA";
        
} (jQuery));
