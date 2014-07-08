
/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (클라이언트측 API)
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
    
    if (typeof $.fn.postcodify !== "undefined") return;
    
    // API 클라이언트 버전을 선언한다.
    
    var info = { version : "1.8.2", location : "" };
    
    // API 클라이언트를 로딩한 경로를 파악한다.
    
    $("script").each(function() {
        var src = $(this).attr("src"); if (!src) return;
        var matches = src.match(/^(https?:)?\/\/([^:\/]+)(?=[:\/]).*\/search\.(min\.)?js(?=\?|$)/);
        if (matches) info.location = matches[2];
    });
    
    // 플러그인 함수를 선언한다.
    
    $.fn.postcodify = function(options) {
        
        // jQuery 플러그인 관례대로 each()의 결과를 반환하도록 한다.
        
        return this.each(function() {
            
            // 기본 설정을 정의한다.
            
            var settings = $.extend({
                api : info.freeAPI.defaultUrl,
                apiBackup : null,
                callBackupFirst : false,
                controls : this,
                results : this,
                language : "ko",
                searchButtonContent : null,
                mapLinkProvider : false,
                mapLinkContent : null,
                insertDbid : null,
                insertPostcode5 : null,
                insertPostcode6 : null,
                insertAddress : null,
                insertJibeonAddress : null,
                insertEnglishAddress : null,
                insertEnglishJibeonAddress : null,
                insertDetails : null,
                insertExtraInfo : null,
                timeout : 2400,
                timeoutBackup : 7200,
                beforeSearch : function(keywords) { },
                afterSearch : function(keywords, results) { },
                beforeSelect : function(selectedEntry) { },
                afterSelect : function(selectedEntry) { },
                onReady : function() { },
                onSuccess : function() { },
                onBackup : function() { },
                onError : function() { },
                onComplete : function() { },
                focusKeyword : true,
                focusDetails : true,
                hideOldAddresses : true,
                useFullJibeon : false
            }, options);
            
            settings.language = settings.language.toLowerCase();
            if (settings.api === info.freeAPI.defaultUrl && settings.apiBackup === null) {
                settings.apiBackup = info.freeAPI.backupUrl;
            }
            if (settings.searchButtonContent === null) {
                settings.searchButtonContent = info.translations[settings.language].msgSearch;
            }
            
            // 검색 컨트롤을 생성한다.
            
            var results = $(settings.results);
            var controls = $('<div class="postcode_search_controls"></div>');
            var keywordInput = $('<input type="text" class="keyword" value="" />').appendTo(controls);
            var searchButton = $('<button type="button" class="search_button"></button>').html(settings.searchButtonContent).appendTo(controls);
            controls.prependTo(settings.controls);
            
            // 단시간내 중복 검색을 방지하기 위해 직전 검색어를 기억하는 변수.
            
            var previousSearch = "";
            
            // 키워드 입력란이 포커스를 잃거나 엔터키를 누르면 즉시 검색을 수행한다.
            // 검색 단추를 누를 때까지 기다리는 것보다 검색 속도가 훨씬 빠르게 느껴진다.
            
            keywordInput.blur(function(event) {
                searchButton.trigger("click");
            });
            
            keywordInput.keypress(function(event) {
                if (event.which == 13) {
                    event.preventDefault();
                    searchButton.trigger("click");
                }
            });
            
            // 실제 검색을 수행하는 이벤트를 등록한다.
            
            searchButton.click(function(event) {
                
                // 다른 이벤트를 취소한다.
                
                event.preventDefault();
                
                // 검색어가 직전과 동일한 경우 중복 검색을 방지한다.
                
                var keywords = $.trim(keywordInput.val());
                if (keywords === previousSearch) return;
                previousSearch = keywords;
                
                // 검색 결과창의 내용을 비운다.
                
                results.find("div.postcode_search_result,div.postcode_search_status").remove();
                
                // 검색어가 없거나 너무 짧은 경우 네트워크 연결을 하지 않도록 한다.
                
                if (keywords.length < 3) {
                    $('<div class="postcode_search_status too_short"></div>').html(info.translations[settings.language].errorTooShort.replace("\n", "<br>")).appendTo(results);
                    return;
                }
                
                // 검색전 콜백 함수를 실행한다.
                
                if (settings.beforeSearch(keywords) === false) return;
                
                // 스크롤 위치를 기억한다.
                
                var prevScrollTop = $(window).scrollTop();
                
                // 이미 검색이 진행 중일 때는 검색 단추를 다시 클릭하지 못하도록 하고, "검색" 라벨을 간단한 애니메이션으로 대체한다.
                
                searchButton.attr("disabled", "disabled");
                var searchButtonAnimation;
                if (navigator.userAgent && navigator.userAgent.match(/MSIE [5-8]\./)) {
                    searchButton.text(".");
                    searchButtonAnimation = setInterval(function() {
                        switch (searchButton.text()) {
                            case ".": searchButton.text(".."); break;
                            case "..": searchButton.text("..."); break;
                            case "...": searchButton.text("...."); break;
                            case "....": searchButton.text("."); break;
                        }
                    }, 160);
                } else {
                    searchButton.html('<img class="searching" alt="' + info.translations[settings.language].msgSearch + '" src="' + info.searchProgress + '" />');
                    searchButtonAnimation = false;
                }
                
                // AJAX 요청 관련 함수들을 선언한다.
                
                var ajaxStartTime;
                var ajaxSuccess;
                var ajaxErrorInitial;
                var ajaxErrorFinal;
                var ajaxCall = function(url, timeout, errorCallback) {
                    ajaxStartTime = new Date().getTime();
                    settings.currentRequestUrl = url;
                    $.ajax({
                        url : url,
                        data : {
                            v : info.version,
                            q : keywords,
                            ref : window.location.hostname,
                            cdn : info.location
                        },
                        dataType : "jsonp",
                        jsonpCallback : "postcodify" + ajaxStartTime,
                        processData : true,
                        cache : false,
                        timeout : timeout,
                        success : ajaxSuccess,
                        error : errorCallback,
                        complete : function() {
                            $(window).scrollTop(prevScrollTop);
                            if (searchButtonAnimation !== false) clearTimeout(searchButtonAnimation);
                            searchButton.removeAttr("disabled").html(settings.searchButtonContent);
                        }
                    });
                };
                
                // AJAX 요청 성공시 실행할 함수를 정의한다.
                
                ajaxSuccess = function(data, textStatus, jqXHR) {
                    
                    // 네트워크 왕복 시간을 포함한 총 소요시간을 계산한다.
                    
                    var searchTotalTime = (new Date().getTime() - ajaxStartTime) / 1000;
                    
                    // 백업 API로 검색에 성공했다면 이후에도 백업 API만 사용하도록 설정한다.
                    
                    if (settings.currentRequestUrl === settings.apiBackup) {
                        settings.callBackupFirst = true;
                    }
                    
                    // 검색후 콜백 함수를 실행한다.
                    
                    if (settings.afterSearch(keywords, data.results) === false) return;
                    
                    // API 서버에서 데이터베이스 오류가 발생한 경우 백업 서버에서 검색을 다시 시도한다.
                    
                    if (data.error && data.error.toLowerCase().indexOf("database") > -1) {
                        if (settings.currentRequestUrl === settings.api && settings.apiBackup && settings.api !== settings.apiBackup) {
                            settings.onBackup();
                            ajaxCall(settings.apiBackup, settings.timeoutBackup, ajaxErrorFinal);
                        }
                    }
                    
                    // 무료 API 서버의 일일 검색 허용 횟수를 초과한 경우...
                    
                    else if (data.error && data.error.toLowerCase().indexOf("quota") > -1) {
                        $('<div class="postcode_search_status quota"></div>').html(info.translations[settings.language].errorQuota.replace("\n", "<br>")).appendTo(results);
                    }
                    
                    // 그 밖의 에러 발생시...
                    
                    else if (data.error) {
                        $('<div class="postcode_search_status error"></div>').html(info.translations[settings.language].errorError.replace("\n", "<br>")).appendTo(results);
                        previousSearch = "";
                    }
                    
                    // 정상 처리되었지만 검색 결과가 없는 경우...
                    
                    else if (data.count === 0) {
                        $('<div class="postcode_search_status empty"></div>').html(info.translations[settings.language].errorEmpty.replace("\n", "<br>")).appendTo(results);
                    }
                    
                    // 정상 처리되었지만 검색 서버의 버전이 맞지 않는 경우...
                    
                    else if (typeof data.results[0].other === "undefined") {
                        $('<div class="postcode_search_status error"></div>').html(info.translations[settings.language].errorVersion.replace("\n", "<br>")).appendTo(results);
                    }
                    
                    // 검색 결과가 있는 경우...
                    
                    else {
                        
                        // 검색 결과의 언어를 파악한다.
                        
                        var resultLanguage;
                        if (typeof data.lang !== "undefined" && data.lang === "EN") {
                            resultLanguage = "en";
                        } else {
                            resultLanguage = "ko";
                        }
                        
                        for (var i = 0; i < data.count; i++) {
                            
                            // 검색 결과 항목을 작성한다.
                            
                            var result = data.results[i];
                            var option = $('<div class="postcode_search_result"></div>');
                            option.data("dbid", result.dbid);
                            option.data("code6", result.code6);
                            option.data("code5", result.code5);
                            option.data("address", result.address.base + " " + result.address["new"]);
                            option.data("jibeon_address", result.address["base"] + " " + result.address["old"]);
                            option.data("english_address", (result.english["new"] === "" ? "" : (result.english["new"] + ", ")) + result.english["base"]);
                            option.data("english_jibeon_address", (result.english["old"] === "" ? "" : (result.english["old"] + ", ")) + result.english["base"]);
                            option.data("extra_info_long", result.other["long"]);
                            option.data("extra_info_short", result.other["short"]);
                            option.data("extra_info_nums", data.nums);
                            
                            // 반환된 데이터의 언어, 정렬 방법에 따라 클릭할 링크를 생성한다.
                            
                            var mainText;
                            var extraText;
                            
                            if (resultLanguage === "en") {
                                if (typeof data.sort !== "undefined" && data.sort === "JIBEON") {
                                    mainText = option.data("english_jibeon_address");
                                    extraText = result.english["new"];
                                } else {
                                    mainText = option.data("english_address");
                                    extraText = result.english["old"];
                                }
                            } else {
                                if (typeof data.sort !== "undefined" && data.sort === "JIBEON") {
                                    mainText = result.address["base"] + " " + result.address["old"];
                                    extraText = result.address["new"];
                                } else {
                                    mainText = result.address["base"] + " " + result.address["new"];
                                    extraText = result.other["long"];
                                }
                            }
                            
                            var selector = $('<a class="selector" href="#"></a>');
                            selector.append($('<span class="address_info"></span>').text(mainText));
                            if (extraText !== null && extraText !== "") {
                                selector.append($('<span class="extra_info"></span>').append("(" + extraText + ")"));
                            }
                            
                            // 우편번호, 기초구역번호, 주소 등을 항목에 추가한다.
                            
                            $('<div class="code6"></div>').text(result.code6).appendTo(option);
                            $('<div class="code5"></div>').text(result.code5).appendTo(option);
                            $('<div class="address"></div>').append(selector).appendTo(option);
                            
                            // 예전 주소 및 검색어 목록을 추가한다.
                            
                            if (typeof data.lang !== "undefined" && data.lang === "EN") {
                                result.other["others"] = result.other["others"].replace(/산([0-9]+)/g, "San $1");
                                result.other["others"] = $.trim(result.other["others"].replace(/[^0-9a-zA-Z\x20.,-]/g, "").replace(/\s+/g, " "));
                            }
                            
                            if (result.other["others"] !== "") {
                                var oldAddrLink = $('<a href="#" class="show_old_addresses">▼</a>').attr("title", info.translations[resultLanguage].msgShowOthers);
                                oldAddrLink.appendTo(option.find("div.address"));
                                var oldAddrDiv = $('<div class="old_addresses"></div>').text(result.other["others"]);
                                if (settings.hideOldAddresses) oldAddrDiv.css("display", "none");
                                oldAddrDiv.appendTo(option);
                            }
                            
                            // 지도 링크를 추가한다.
                            
                            if (settings.mapLinkProvider) {
                                var mapurl;
                                if (typeof info.mapProviders[settings.mapLinkProvider] !== "undefined") {
                                    mapurl = info.mapProviders[settings.mapLinkProvider];
                                } else {
                                    mapurl = settings.mapLinkProvider;
                                }
                                mapurl = mapurl.replace("$JUSO", encodeURIComponent(result.address["base"] + " " + result.address["new"]).replace(/%20/g, '+'));
                                mapurl = mapurl.replace("$JIBEON", encodeURIComponent(result.address["base"] + " " + result.address["old"]).replace(/%20/g, '+'));
                                var mapLinkContent = (settings.mapLinkContent !== null) ? settings.mapLinkContent : info.translations[resultLanguage].msgMap;
                                var maplink = $('<a target="_blank"></a>').attr("href", mapurl).html(mapLinkContent);
                                $('<div class="map_link"></div>').append(maplink).appendTo(option);
                            }
                            
                            option.appendTo(results);
                        }
                        
                        // 검색 결과 요약을 작성한다.
                        
                        var summary = $('<div class="postcode_search_status summary"></div>');
                        summary.append('<div class="result_count">' + info.translations[resultLanguage].msgResultCount + ': ' +
                            '<span>' + data.count + '</span></div>');
                        summary.append('<div class="search_time">' + info.translations[resultLanguage].msgSearchTime + ': ' +
                            '<span>' + Math.round(data.time * 1000) + 'ms</span></div>');
                        summary.append('<div class="network_time">' + info.translations[resultLanguage].msgNetworkTime + ': ' +
                            '<span>' + Math.round((searchTotalTime - parseFloat(data.time)) * 1000) + 'ms</span></div>');
                        summary.appendTo(results);
                        
                        // 검색 결과가 너무 많아 일부만 표시한 경우 그 사실을 알린다.
                        
                        if (data.count >= 100) {
                            $('<div class="postcode_search_status too_many"></div>').html(info.translations[resultLanguage].errorTooMany.replace("\n", "<br>")).prependTo(results);
                        }
                    }
                    
                    // 검색 성공 콜백 함수를 실행한다.
                    
                    settings.onSuccess();
                    settings.onComplete();
                };
                
                // AJAX 요청 1차 실패시 실행할 함수를 정의한다.
                
                ajaxErrorInitial = function(jqXHR, textStatus, errorThrown) {
                    
                    // 백업 API가 있는 경우 다시 시도하고, 그 밖의 경우 최종 실패로 취급한다.
                    
                    if (settings.apiBackup) {
                        settings.onBackup();
                        ajaxCall(settings.apiBackup, settings.timeoutBackup, ajaxErrorFinal);
                    } else {
                        ajaxErrorFinal(jqXHR, textStatus, errorThrown);
                    }
                };
                
                // AJAX 요청 최종 실패시 실행할 함수를 정의한다.
                
                ajaxErrorFinal = function(jqXHR, textStatus, errorThrown) {
                    
                    // 오류 메시지를 보여준다.
                    
                    results.find("div.postcode_search_status.error").show();
                    previousSearch = "";
                    
                    // 검색 실패 콜백 함수를 실행한다.
                    
                    settings.onError();
                    settings.onComplete();
                };
                
                // 검색 서버로 AJAX 요청을 전송한다.
                
                if (settings.apiBackup && settings.callBackupFirst) {
                    ajaxCall(settings.apiBackup, settings.timeoutBackup, ajaxErrorFinal);
                } else {
                    ajaxCall(settings.api, settings.timeout, ajaxErrorInitial);
                }
            });
            
            // 검색 결과를 클릭할 경우 사용자가 지정한 입력란에 해당 정보가 입력되도록 한다.
            
            results.on("click", "div.code6,div.code5,div.old_addresses", function(event) {
                event.preventDefault();
                event.stopPropagation();
                $(this).parent().find("a.selector").click();
            });
            
            results.on("click", "a.selector", function(event) {
                event.preventDefault();
                
                // 클릭한 주소를 구한다.
                
                var entry = $(this).parents("div.postcode_search_result");
                
                // 선택전 콜백을 실행한다.
                
                if (settings.beforeSelect(entry) === false) return;
                
                // 사서함 주소인 경우 정확한 번호로 치환한다.
                
                var koAddrNew = entry.data("address");
                var koAddrOld = entry.data("jibeon_address");
                var enAddrNew = entry.data("english_address");
                var enAddrOld = entry.data("english_jibeon_address");
                
                if (entry.data("extra_info_nums"))
                {
                    var poboxNums = entry.data("extra_info_nums");
                    var poboxRegexp = /[0-9]+(-[0-9]+)? ~ [0-9]+(-[0-9]+)?/;
                    koAddrNew = koAddrNew.replace(poboxRegexp, poboxNums);
                    koAddrOld = koAddrOld.replace(poboxRegexp, poboxNums);
                    enAddrNew = enAddrNew.replace(poboxRegexp, poboxNums);
                    enAddrOld = enAddrOld.replace(poboxRegexp, poboxNums);
                }
                
                // 사용자가 지정한 입력칸에 데이터를 입력한다.
                
                if (settings.insertDbid) $(settings.insertDbid).val(entry.data("dbid"));
                if (settings.insertPostcode6) $(settings.insertPostcode6).val(entry.data("code6"));
                if (settings.insertPostcode5) $(settings.insertPostcode5).val(entry.data("code5"));
                if (settings.insertAddress) $(settings.insertAddress).val(koAddrNew);
                if (settings.insertJibeonAddress) $(settings.insertJibeonAddress).val(koAddrOld);
                if (settings.insertEnglishAddress) $(settings.insertEnglishAddress).val(enAddrNew);
                if (settings.insertEnglishJibeonAddress) $(settings.insertEnglishJibeonAddress).val(enAddrOld);
                if (settings.insertExtraInfo) {
                    var extra_info = settings.useFullJibeon ? entry.data("extra_info_long") : entry.data("extra_info_short");
                    if (extra_info.length) extra_info = "(" + extra_info + ")";
                    if (settings.insertExtraInfo === settings.insertAddress) {
                        $(settings.insertExtraInfo).val($(settings.insertExtraInfo).val() + "\n" + extra_info);
                    } else {
                        $(settings.insertExtraInfo).val(extra_info);
                    }
                }
                
                // 선택후 콜백을 실행한다.
                
                if (settings.afterSelect(entry) === false) return;
                
                // 상세주소를 입력하는 칸으로 포커스를 이동한다.
                
                if (settings.insertDetails && settings.focusDetails) {
                    $(settings.insertDetails).focus();
                }
            });
            
            // 예전 주소 및 검색어 목록을 보였다가 숨기는 기능을 만든다.
            
            results.on("click", "a.show_old_addresses", function(event) {
                event.preventDefault();
                var oldAddrDiv = $(this).parent().siblings(".old_addresses");
                if (oldAddrDiv.is(":visible")) {
                    $(this).html("&#9660;");
                    oldAddrDiv.hide();
                } else {
                    $(this).html("&#9650;");
                    oldAddrDiv.show();
                }
            });
            
            // 키워드 입력란에 포커스를 준다.
            
            if (settings.focusKeyword) keywordInput.focus();
            
            // 셋팅 완료 콜백을 호출한다.
            
            settings.onReady();
            
            // jQuery 관례에 따라 this를 반환한다.
            
            return this;
        });
    };
    
    // 무료 API 경로 설정.
    
    info.freeAPI = {
        defaultUrl : "//api.poesis.kr/post/search.php",
        backupUrl : "//backup.api.poesis.kr/post/search.php"
    };

    // 지도 링크 설정.
    
    info.mapProviders = {
        daum : "http://map.daum.net/?map_type=TYPE_MAP&urlLevel=3&q=$JUSO",
        naver : "http://map.naver.com/?mapMode=0&dlevel=12&query=$JUSO",
        google : "http://www.google.com/maps/place/대한민국+$JUSO"
    };
    
    // 언어 설정.
    
    info.translations = {
        ko : {
            errorError : "검색 서버와 통신 중 오류가 발생하였습니다.\n잠시 후 다시 시도해 주시기 바랍니다.",
            errorEmpty : "검색 결과가 없습니다. 주소가 정확한지 다시 확인해 주십시오.\n띄어쓰기에 유의하시기 바랍니다.",
            errorQuota : "일일 허용 쿼리수를 초과하였습니다.\n관리자에게 문의해 주시기 바랍니다.",
            errorVersion : "검색 서버의 버전이 낮아 이 검색창과 호환되지 않습니다.",
            errorTooShort : "검색어는 3글자 이상 입력해 주십시오.",
            errorTooMany : "검색 결과가 너무 많아 100건까지만 표시합니다.\n행정구역명, 번지수 등을 사용하여 좀더 자세히 검색해 주시기 바랍니다.",
            msgResultCount : "검색 결과",
            msgSearchTime : "소요 시간",
            msgNetworkTime : "통신 지연",
            msgSeeOthers : "관련지번 보기",
            msgSearch : "검색",
            msgMap : "지도"
        },
        en : {
            errorError : "An error occurred while communicating to the search server.\nPlease try again later.",
            errorEmpty : "No addresses matched your search.\nPlease check if your search terms are accurately spelled.",
            errorQuota : "This website and/or your IP address has exceeded its daily search quota.\nPlease contact the administrator.",
            errorVersion : "The version of the search server is not compatible with this search function.",
            errorTooShort : "Please enter at least 3 characters.",
            errorTooMany : "Your search returned too many results. Only the first 100 items are shown below.\nPlease narrow down your search by adding the street number(s).",
            msgResultCount : "Results",
            msgSearchTime : "Time taken",
            msgNetworkTime : "Network delay",
            msgSeeOthers : "See related addresses",
            msgSearch : "Search",
            msgMap : "Map"
        }
    };
    
    // 로딩중임을 표시하는 GIF 애니메이션 파일.
    // 불필요한 요청을 줄이기 위해 base64 인코딩하여 여기에 직접 저장한다.
    
    info.searchProgress = "data:image/gif;base64,R0lGODlhEAALAPQAAP///yIiIt7" +
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
