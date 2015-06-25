
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
    
    var info = { version : "3.0.0" };
    
    // Postcodify 메인 플러그인과 팝업 레이어를 위한 스타일시트를 로딩한다.
    
    $(function() {
        var cdnPrefix = navigator.userAgent.match(/MSIE [56]\./) ? "http:" : "";
        if (cdnPrefix === "" && !window.location.protocol.match(/^https?/)) {
            cdnPrefix = "http:";
        }
        if (typeof window.POSTCODIFY_NO_CSS === "undefined") {
            var cdnStylesheet = document.createElement("link");
            cdnStylesheet.rel = "stylesheet";
            cdnStylesheet.type = "text/css";
            cdnStylesheet.href = cdnPrefix + "//cdn.poesis.kr/post/popup.css?v=" + info.version;
            document.body.appendChild(cdnStylesheet);
        }
        if (typeof $.fn.postcodify === "undefined") {
            var cdnScript = document.createElement("script");
            cdnScript.type = "text/javascript";
            cdnScript.src = cdnPrefix + "//cdn.poesis.kr/post/search.min.js?v=" + info.version;
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
            
            var controls = $('<div class="postcodify_controls"></div>').appendTo(layer);
            var displays = $('<div class="postcodify_displays"></div>').appendTo(layer);
            displays.append('<div class="postcodify_results"></div>');
            
            // 검색창 디자인과 닫기 버튼을 생성한다.
            
            var curve_slice = $('<div class="postcodify_curve_slice"></div>');
            var button_area = $('<div class="postcodify_button_area"></div>');
            var close_button = $('<button class="close_button">&times;</button>').appendTo(button_area);
            
            // Powered By 로고를 생성한다.
            
            var logo = $('<div class="postcodify_logo">P O W E R E D &nbsp; B Y &nbsp; <a href="http://postcodify.poesis.kr/">P O S T C O D I F Y</a></div>');
            logo.appendTo(layer);
            
            // 검색 요령 및 주의사항을 입력한다.
            
            var help1 = $('<table></table>');
            help1.append('<tr><th>구분</th><th>사용할 검색어</th><th>검색 예</th></tr>');
            help1.append('<tr><td>도로명주소</td><td>도로명 + 번호</td><td>세종대로 110</td></tr>');
            help1.append('<tr><td>지번주소</td><td>동·리 + 번지</td><td>부산 연산동 1000</td></tr>');
            help1.append('<tr class="postcodify_building_search"><td>건물명</td><td>빌딩 또는 아파트단지명</td><td>수곡동 주공3차</td></tr>');
            help1.append('<tr><td>사서함</td><td>사서함명 + 번호</td><td>광화문우체국사서함 123-4</td></tr>');
            
            var help2 = $('<ul></ul>');
            help2.append('<li><span>시·군·구·읍·면 등은 쓰지 않아도 되지만,</span> <span>쓰실 경우 반드시 띄어쓰기를 해 주세요.</span></li>');
            help2.append('<li><span>도로명에 포함된 "××번길" 등 숫자도</span> <span>잊지 말고 써 주세요.</span></li>');
            help2.append('<li><span>건물명보다는 번호가 포함된 정확한 주소로</span> <span>검색하는 것이 빠르고 정확합니다.</span></li>');
            
            var divhelp = $('<div class="postcodify_help"></div>');
            divhelp.append('<p>우편번호 검색 요령</p>');
            divhelp.append(help1);
            divhelp.append('<p>정확한 검색을 위한 팁</p>');
            divhelp.append(help2);
            divhelp.appendTo(displays);
            
            if (options.requireExactQuery) {
                divhelp.find("tr.postcodify_building_search").remove();
            }
            
            // 팝업 레이어와 배경을 DOM에 추가한다.
            
            background.hide().appendTo(document.body);
            layer.hide().appendTo(document.body);
            
            // Postcodify 검색 폼을 생성하는 함수.
            
            initializePostcodify = function() {
                layer.data("initialized", "Y");
                layer.find("div.postcodify_results").postcodify($.extend({
                    controls : layer.find("div.postcodify_controls"),
                    insertPostcode6 : container.find(".postcodify_postcode6"),
                    insertPostcode5 : container.find(".postcodify_postcode5"),
                    insertAddress : container.find(".postcodify_address"),
                    insertDetails : container.find(".postcodify_details"),
                    insertExtraInfo : container.find(".postcodify_extra_info"),
                    insertJibeonAddress : container.find(".postcodify_jibeon_address"),
                    insertEnglishAddress : container.find(".postcodify_english_address"),
                    insertEnglishJibeonAddress : container.find(".postcodify_english_jibeon_address"),
                    insertBuildingId : container.find(".postcodify_address_id,.postcodify_building_id"),
                    insertBuildingName : container.find(".postcodify_building_name"),
                    insertBuildingNums : container.find(".postcodify_building_nums"),
                    insertOtherAddresses : container.find(".postcodify_other_addresses"),
                    mapLinkProvider : "daum",
                    hideOldAddresses : false,
                    afterSelect : function(entry) {
                        container.find(".postcodify_postcode6_1").val(entry.data("code6").substr(0, 3));
                        container.find(".postcodify_postcode6_2").val(entry.data("code6").substr(4, 3));
                        onSelect();
                        closePopUpLayer();
                    }
                }, options));
                curve_slice.appendTo(layer.find("div.postcodify_controls"));
                layer.find("button.search_button").detach().appendTo(button_area);
                button_area.append(close_button).appendTo(layer.find("div.postcodify_controls"));
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
                displays.height(layer.height() - 73);
            }).triggerHandler("resize");
            
            // 검색 단추 클릭시 팝업 레이어를 보여주도록 설정한다.
            
            $(this).click(function() {
                if (layer.data("initialized") != "Y") initializePostcodify();
                background.show();
                layer.show().find("input.keyword").focus();
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
