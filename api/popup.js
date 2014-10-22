
/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (클라이언트측 팝업 API)
 * 
 *  Copyright (c) 2014, Kijin Sung <root@poesis.kr>
 *  
 *  이 프로그램은 자유 소프트웨어입니다. 이 소프트웨어의 피양도자는 자유
 *  소프트웨어 재단이 공표한 GNU 약소 일반 공중 사용 허가서 (GNU LGPL) 제3판
 *  또는 그 이후의 판을 임의로 선택하여, 그 규정에 따라 이 프로그램을
 *  개작하거나 재배포할 수 있습니다.
 * 
 *  이 프로그램은 유용하게 사용될 수 있으리라는 희망에서 배포되고 있지만,
 *  특정한 목적에 맞는 적합성 여부나 판매용으로 사용할 수 있으리라는 묵시적인
 *  보증을 포함한 어떠한 형태의 보증도 제공하지 않습니다. 보다 자세한 사항에
 *  대해서는 GNU 약소 일반 공중 사용 허가서를 참고하시기 바랍니다.
 * 
 *  GNU 약소 일반 공중 사용 허가서는 이 프로그램과 함께 제공됩니다.
 *  만약 허가서가 누락되어 있다면 자유 소프트웨어 재단으로 문의하시기 바랍니다.
 */

(function($) {
    
    // 같은 플러그인을 2번 인클루드한 경우 무시하도록 한다.
    
    if (typeof $.fn.postcodifyPopUp !== "undefined") return;
    
    // 팝업 스크립트 버전을 선언한다.
    
    var info = { version : "2.1.0" };
    
    // Postcodify 메인 플러그인과 팝업 레이어를 위한 스타일시트를 로딩한다.
    
    $(document.body).append('<script src="//cdn.poesis.kr/post/search.min.js"><\/script>');
    $(document.body).append('<link rel="stylesheet" href="//cdn.poesis.kr/post/popup.css" />');
    
    // 플러그인 함수를 선언한다.
    
    $.fn.postcodifyPopUp = function(options) {
        
        // jQuery 플러그인 관례대로 each()의 결과를 반환하도록 한다.
        
        return this.each(function() {
            
            // 설정을 초기화한다.
            
            var closePopUpLayer;
            var initializePostcodify;
            
            options = typeof options !== "undefined" ? options : {};
            
            // 팝업 레이어와 배경을 생성한다.
            
            var background = $('<div class="postcodify_popup_background"></div>');
            var layer = $('<div class="postcodify_popup_layer"></div>');
            if (navigator.userAgent.match(/MSIE 6\./)) layer.addClass("ie6fix");
            
            // 기본적인 태그들을 입력한다.
            
            layer.append('<div class="postcodify_title"><span>도로명주소 &amp; 지번주소</span> <span>우편번호 검색</span></div>');
            layer.append('<div class="postcodify_controls"></div>');
            layer.append('<div class="postcodify_results"></div>');
            
            // 검색 요령 및 주의사항을 입력한다.
            
            var help1 = $('<ul></ul>');
            help1.append('<li>도로명주소 검색 : 도로명과 건물번호를 입력하세요. 예: 세종대로 110</li>');
            help1.append('<li>지번주소 검색 : "동" 또는 "리" 이름과 번지수를 입력하세요. 예: 연산동 1000</li>');
            help1.append('<li>건물명 검색 : 빌딩 또는 아파트 이름을 입력하세요. 예: 방배동 래미안, 수곡동 주공3차</li>');
            help1.append('<li>사서함 검색 : 사서함 이름과 번호를 입력하세요. 예: 광화문우체국사서함 123-4</li>');
            
            var help2 = $('<ul></ul>');
            help2.append('<li>시·군·구·읍·면 등은 쓰지 않아도 되지만, 만약 쓰실 경우 반드시 띄어쓰기를 해 주세요.</li>');
            help2.append('<li>도로명에 "××번길" 등이 포함되어 있는 경우에도 잊지 말고 써 주세요.</li>');
            help2.append('<li>건물명보다는 도로명주소 또는 지번 주소로 검색하시는 것이 빠르고 정확합니다.</li>');
            
            var divhelp = $('<div class="postcodify_help"></div>');
            divhelp.append('<p>검색 요령</p>');
            divhelp.append(help1);
            divhelp.append('<p>주의사항</p>');
            divhelp.append(help2);
            divhelp.appendTo(layer);
            
            if (options.requireExactQuery) {
                divhelp.find("li:contains('건물명')").remove();
            }
            
            // 닫기 버튼을 생성한다.
            
            var buttons = $('<div class="postcodify_buttons"></div>');
            buttons.append('<button>닫기</button>');
            buttons.appendTo(layer);
            
            // 팝업 레이어와 배경을 DOM에 추가한다.
            
            background.hide().appendTo(document.body);
            layer.hide().appendTo(document.body);
            
            // Postcodify 검색 폼을 생성하는 함수.
            
            initializePostcodify = function() {
                layer.data("initialized", "Y");
                layer.find("div.postcodify_results").postcodify({
                    controls : layer.find("div.postcodify_controls"),
                    insertDbid : ".postcodify_address_id",
                    insertPostcode6 : ".postcodify_postcode6",
                    insertPostcode5 : ".postcodify_postcode5",
                    insertAddress : ".postcodify_address",
                    insertDetails : ".postcodify_details",
                    insertExtraInfo : ".postcodify_extra_info",
                    insertJibeonAddress : ".postcodify_jibeon_address",
                    insertEnglishAddress : ".postcodify_english_address",
                    insertEnglishJibeonAddress : ".postcodify_english_jibeon_address",
                    mapLinkProvider : (options.mapLinkProvider ? options.mapLinkProvider : "daum"),
                    hideOldAddresses : (options.hideOldAddresses === true ? true : false),
                    hideSummary : (options.hideSummary === true ? true : false),
                    requireExactQuery : (options.requireExactQuery === true ? true : false),
                    useFullJibeon : (options.useFullJibeon === true ? true : false),
                    afterSelect : function(entry) {
                        $(".postcodify_postcode6_1").val(entry.data("code6").substr(0, 3));
                        $(".postcodify_postcode6_2").val(entry.data("code6").substr(4, 3));
                        closePopUpLayer();
                    }
                });
            };
            
            // 화면 크기에 따라 팝업 레이어의 크기를 자동으로 조절한다.
            
            $(window).resize(function() {
                var width = $(window).width();
                var height = $(window).height();
                layer.removeClass("fill_horizontally").removeClass("fill_vertically").removeClass("full_screen");
                if (width <= 660) layer.addClass("fill_horizontally").addClass("fill_vertically");
                if (height <= 660) layer.addClass("fill_vertically");
                if ("ontouchstart" in window && (layer.hasClass("fill_horizontally") || layer.hasClass("fill_vertically"))) {
                    layer.addClass("full_screen");
                }
                layer.find("input.keyword").width(layer.width() - 100);
            }).triggerHandler("resize");
            
            // 검색 단추 클릭시 팝업 레이어를 보여주도록 설정한다.
            
            $(this).click(function() {
                if (layer.data("initialized") != "Y") initializePostcodify();
                background.show();
                layer.show();
                layer.find("input.keyword").width(layer.width() - 100).focus();
            });
            
            // 팝업 레이어를 감추는 함수.
            
            closePopUpLayer = function() {
                background.hide();
                layer.hide();
            };
            
            // 닫기 단추를 누르면 팝업 레이어를 감추도록 설정한다.
            
            layer.find("div.postcodify_buttons button").click(function() {
                closePopUpLayer();
            });
            
            // 배경을 클릭하면 팝업 레이어를 감추도록 설정한다.
            
            background.click(function() {
                closePopUpLayer();
            });
            
            // ESC 키를 누르면 팝업 레이어를 감추도록 설정한다.
            
            $(window).keyup(function(event) {
                if (event.keyCode == 27 && layer.is(":visible")) {
                    closePopUpLayer();
                }
            });
        });
    };
} (jQuery));
