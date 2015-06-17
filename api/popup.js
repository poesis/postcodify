
/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (클라이언트측 팝업 API)
 * 
 *  Copyright (c) 2014-2015, Poesis <root@poesis.kr>
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
    
    var info = { version : "2.5.0" };
    
    // Postcodify 메인 플러그인과 팝업 레이어를 위한 스타일시트를 로딩한다.
    
    $(function() {
        var cdnPrefix = navigator.userAgent.match(/MSIE [56]\./) ? "http:" : "";
        var cdnStylesheet = document.createElement("link");
        cdnStylesheet.rel = "stylesheet";
        cdnStylesheet.type = "text/css";
        cdnStylesheet.href = cdnPrefix + "//cdn.poesis.kr/post/popup.css";
        document.body.appendChild(cdnStylesheet);
        if (typeof $.fn.postcodify === "undefined") {
            var cdnScript = document.createElement("script");
            cdnScript.type = "text/javascript";
            cdnScript.src = cdnPrefix + "//cdn.poesis.kr/post/search.min.js";
            document.body.appendChild(cdnScript);
        }
    });
    
    // 플러그인 함수를 선언한다.
    
    $.fn.postcodifyPopUp = function(options) {
        
        // jQuery 플러그인 관례대로 each()의 결과를 반환하도록 한다.
        
        return this.each(function() {
            
            // 설정을 초기화한다.
            
            var closePopUpLayer;
            var container;
            var initializePostcodify;
            var onSelect;
            
            options = typeof options !== "undefined" ? options : {};
            onSelect = typeof options.onSelect !== "undefined" ? options.onSelect : function(){};
            
            // <input>을 검색할 범위를 설정한다.
            
            if (options.container || options.inputParent) {
                container = $(options.container ? options.container : options.inputParent);
            } else {
                container = $(document.body);
            }
            
            // 팝업 레이어와 배경을 생성한다.
            
            var background = $('<div class="postcodify_popup_background"></div>');
            var layer = $('<div class="postcodify_popup_layer" data-version="' + info.version + '"></div>');
            if (navigator.userAgent.match(/MSIE 6\./)) {
                background.addClass("ie6fix");
                layer.addClass("ie6fix");
            }
            
            // 기본적인 태그들을 입력한다.
            
            layer.append('<div class="postcodify_controls"></div>');
            layer.append('<div class="postcodify_results"></div>');
            
            // 닫기 버튼과 로고를 생성한다.
            
            var close_button = $('<button class="close_button">&times;</button>');
            var logo = $('<div class="postcodify_logo">Powered by <a href="http://postcodify.poesis.kr/">Postcodify</a></div>');
            
            // 검색 요령 및 주의사항을 입력한다.
            
            var help1 = $('<ul></ul>');
            help1.append('<li>도로명주소 검색 : 도로명과 건물번호를 입력하세요. 예: <u>세종대로 110</u></li>');
            help1.append('<li>지번주소 검색 : "동" 또는 "리" 이름과 번지수를 입력하세요. 예: <u>연산동 1000</u></li>');
            help1.append('<li>건물명 검색 : 빌딩 또는 아파트 이름을 입력하세요. 예: <u>방배동 래미안</u>, <u>수곡동 주공3차</u></li>');
            help1.append('<li>사서함 검색 : 사서함 이름과 번호를 입력하세요. 예: <u>광화문우체국사서함 123-4</u></li>');
            
            var help2 = $('<ul></ul>');
            help2.append('<li>시·군·구·읍·면 등은 쓰지 않아도 되지만, 만약 쓰실 경우 반드시 띄어쓰기를 해 주세요.</li>');
            help2.append('<li>도로명에 "××번길" 등이 포함되어 있는 경우에도 잊지 말고 써 주세요.</li>');
            help2.append('<li>건물명보다는 도로명주소 또는 지번 주소로 검색하시는 것이 빠르고 정확합니다.</li>');
            
            var divhelp = $('<div class="postcodify_help"></div>');
            divhelp.append('<p>우편번호 검색 요령</p>');
            divhelp.append(help1);
            divhelp.append('<p>더 정확하게 검색하시려면</p>');
            divhelp.append(help2);
            divhelp.appendTo(layer);
            
            if (options.requireExactQuery) {
                divhelp.find("li:contains('건물명')").remove();
            }
            
            logo.appendTo(layer);
            
            // 팝업 레이어와 배경을 DOM에 추가한다.
            
            background.hide().appendTo(document.body);
            layer.hide().appendTo(document.body);
            
            // Postcodify 검색 폼을 생성하는 함수.
            
            initializePostcodify = function() {
                layer.data("initialized", "Y");
                layer.find("div.postcodify_results").postcodify($.extend({
                    controls : layer.find("div.postcodify_controls"),
                    insertDbid : container.find(".postcodify_address_id"),
                    insertPostcode6 : container.find(".postcodify_postcode6"),
                    insertPostcode5 : container.find(".postcodify_postcode5"),
                    insertAddress : container.find(".postcodify_address"),
                    insertDetails : container.find(".postcodify_details"),
                    insertExtraInfo : container.find(".postcodify_extra_info"),
                    insertJibeonAddress : container.find(".postcodify_jibeon_address"),
                    insertEnglishAddress : container.find(".postcodify_english_address"),
                    insertEnglishJibeonAddress : container.find(".postcodify_english_jibeon_address"),
                    mapLinkProvider : "daum",
                    hideOldAddresses : false,
                    afterSelect : function(entry) {
                        container.find(".postcodify_postcode6_1").val(entry.data("code6").substr(0, 3));
                        container.find(".postcodify_postcode6_2").val(entry.data("code6").substr(4, 3));
                        onSelect();
                        closePopUpLayer();
                    }
                }, options));
                close_button.appendTo(layer.find("div.postcodify_search_controls"));
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
                layer.find("input.keyword").width(layer.width() - 130);
            }).triggerHandler("resize");
            
            // 검색 단추 클릭시 팝업 레이어를 보여주도록 설정한다.
            
            $(this).click(function() {
                if (layer.data("initialized") != "Y") initializePostcodify();
                background.show();
                layer.show();
                layer.find("input.keyword").width(layer.width() - 130).focus();
            });
            
            // 팝업 레이어를 감추는 함수.
            
            closePopUpLayer = function() {
                background.hide();
                layer.hide();
            };
            
            // 닫기 단추를 누르면 팝업 레이어를 감추도록 설정한다.
            
            close_button.click(function() {
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
