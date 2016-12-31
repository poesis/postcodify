
/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (클라이언트측 API)
 * 
 *  Copyright (c) 2014-2016, Poesis <root@poesis.kr>
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
    
    var info = { version : "3.4.1", location : "" };
    
    // API 클라이언트를 로딩한 경로를 파악한다.
    
    $("script").each(function() {
        var src = $(this).attr("src"); if (!src) return;
        var matches = src.match(/^(https?:)?\/\/([^:\/]+)(?=[:\/]).*\/search\.(min\.)?js(?=\?|$)/);
        if (matches) info.location = matches[2];
    });
    
    // 팝업 레이어를 위한 스타일시트를 로딩한다.
    
    $(function() {
        if (typeof window.POSTCODIFY_NO_CSS === "undefined") {
            var cdnProtocol = navigator.userAgent.match(/MSIE [56]\./) ? "http:" : "";
            if (cdnProtocol === "" && !window.location.protocol.match(/^https?/)) cdnProtocol = "http:";
            var cdnHostname = info.location.match(/\.(poesis\.kr|cloudfront\.net)$/) ? info.location : "d1p7wdleee1q2z.cloudfront.net";
            var cdnStylesheet = document.createElement("link");
            cdnStylesheet.rel = "stylesheet";
            cdnStylesheet.type = "text/css";
            cdnStylesheet.href = cdnProtocol + "//" + cdnHostname + "/post/search.css?v=" + info.version;
            document.body.appendChild(cdnStylesheet);
        }
    });
    
    // 메인 플러그인을 생성한다.
    
    $.fn.postcodify = function(options) {
        
        // jQuery 플러그인 관례대로 each()의 결과를 반환하도록 한다.
        
        return this.each(function() {
            
            // 기본 설정을 정의한다.
            
            var settings = $.extend({
                api : info.freeAPI.defaultUrl,
                apiBackup : null,
                timeout : 3000,
                timeoutBackup : 8000,
                callBackupFirst : false,
                controls : this,
                results : this,
                language : "ko",
                autoSelect : false,
                requireExactQuery : false,
                searchButtonContent : null,
                mapLinkProvider : false,
                mapLinkContent : null,
                overrideDomain : null,
                insertDbid : null,
                insertPostcode : null,
                insertPostcode5 : null,
                insertPostcode6 : null,
                insertAddress : null,
                insertJibeonAddress : null,
                insertEnglishAddress : null,
                insertEnglishJibeonAddress : null,
                insertDetails : null,
                insertExtraInfo : null,
                insertBuildingId : null,
                insertBuildingName : null,
                insertBuildingNums : null,
                insertOtherAddresses : null,
                beforeSearch : function(keywords) { },
                afterSearch : function(keywords, results, lang, sort) { },
                beforeSelect : function(selectedEntry) { },
                afterSelect : function(selectedEntry) { },
                onReady : function() { },
                onSuccess : function() { },
                onBackup : function() { },
                onError : function() { },
                onComplete : function() { },
                forceDisplayPostcode5 : false,
                forceDisplayPostcode6 : false,
                forceUseSSL : false,
                focusKeyword : true,
                focusDetails : true,
                hideBuildingNums : false,
                hideOldAddresses : true,
                hideSummary : false,
                useFullJibeon : false,
                useAlert : false,
                useCors : true
            }, options);
            
            settings.language = settings.language.toLowerCase();
            if (settings.api === info.freeAPI.defaultUrl && settings.apiBackup === null) {
                settings.apiBackup = info.freeAPI.backupUrl;
            }
            if (settings.api && settings.api.substr(0, 2) === "//") {
                if (navigator.userAgent.match(/MSIE [56]\./)) {
                    settings.api = "http:" + settings.api;
                } else if (settings.forceUseSSL) {
                    settings.api = "https:" + settings.api;
                } else if (!window.location.protocol.match(/^https?/)) {
                    settings.api = "http:" + settings.api;
                }
            }
            if (settings.apiBackup && settings.apiBackup.substr(0, 2) === "//") {
                if (navigator.userAgent.match(/MSIE [56]\./)) {
                    settings.apiBackup = "http:" + settings.apiBackup;
                } else if (settings.forceUseSSL) {
                    settings.apiBackup = "https:" + settings.apiBackup;
                } else if (!window.location.protocol.match(/^https?/)) {
                    settings.api = "http:" + settings.api;
                }
            }
            if (settings.insertBuildingId === null && settings.insertDbid !== null) {
                settings.insertBuildingId = settings.insertDbid;
            }
            if (settings.searchButtonContent === null) {
                settings.searchButtonContent = info.translations[settings.language].msgSearch;
            }
            
            // 검색 컨트롤을 생성한다.
            
            var results = $(settings.results).postcodifyAddClass("search_form");
            var controls = $('<div></div>').postcodifyAddClass("search_controls");
            var uniqueId = "postcodify_" + new Date().getTime().toString() + Math.random().toString().substr(2, 4);
            var keywordLabel = $('<label></label>').attr("for", uniqueId).text(info.translations[settings.language].msgKeywords).hide().appendTo(controls);
            var keywordInput = $('<input type="text" class="keyword" value="" />').attr("id", uniqueId).appendTo(controls);
            var searchButton = $('<button type="button" class="search_button"></button>').attr("id", uniqueId + "_button").html(settings.searchButtonContent).appendTo(controls);
            controls.prependTo(settings.controls);
            
            // 단시간내 중복 검색을 방지하기 위해 직전 검색어를 기억하는 변수.
            
            var previousSearch = null;
            var isSearching = false;
            
            // 키워드 입력란에서 엔터키를 누르거나 검색 단추로 포커스를 이동하면 즉시 검색을 수행한다.
            // 검색 단추를 누를 때까지 기다리는 것보다 검색 속도가 훨씬 빠르게 느껴진다.
            
            keywordInput.keypress(function(event) {
                if (event.which == 13) {
                    event.preventDefault();
                    searchButton.trigger("click");
                }
            });
            
            searchButton.focusin(function(event) {
                searchButton.trigger("click");
            });
            
            // 실제 검색을 수행하는 이벤트를 등록한다.
            
            searchButton.click(function(event) {
                
                // 다른 이벤트를 취소한다.
                
                event.preventDefault();
                
                // 검색어가 직전과 동일한 경우 중복 검색을 방지한다.
                
                var keywords = $.trim(keywordInput.val());
                if (keywords === previousSearch) {
                    if (isSearching || results.find("div.postcodify_search_result").filter(":visible").size()) {
                        return;
                    }
                }
                previousSearch = keywords;
                
                // 검색 결과창의 내용을 비운다.
                
                results.find("div.postcodify_search_result,div.postcodify_search_status").remove();
                
                // 검색어가 없거나 너무 짧은 경우 네트워크 연결을 하지 않도록 한다.
                
                if (keywords.length < 3) {
                    if (settings.useAlert) {
                        alert(info.translations[settings.language].errorTooShort);
                    } else {
                        $('<div class="too_short"></div>').postcodifyAddClass("search_status").html(info.translations[settings.language].errorTooShort.replace(/\n/g, "<br>")).appendTo(results);
                    }
                    return;
                }
                
                // 정확한 검색어를 필요로 하는 경우 여기서 체크한다.
                
                if (settings.requireExactQuery) {
                    if (!keywords.match(/(사서함|[동리가로길])\s*([0-9]+)(-[0-9]+)?(번지?)?($|,|\s)/) &&
                        !keywords.match(/^p\.?\s?o\.?\s?box\.?\s*([0-9]+)(-[0-9]+)?($|,|\s)/i) &&
                        !keywords.match(/^([0-9]+)(-[0-9]+)?,?\s*[a-z0-9-\x20]+?(dong|ri|ga|ro|gil)($|,|\s)/i)) {
                        if (settings.useAlert) {
                            alert(info.translations[settings.language].errorExactQuery);
                        } else {
                            $('<div class="too_short"></div>').postcodifyAddClass("search_status").html(info.translations[settings.language].errorExactQuery.replace(/\n/g, "<br>")).appendTo(results);
                        }
                        return;
                    }
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
                
                var err;
                var ajaxStartTime;
                var ajaxSuccess;
                var ajaxErrorInitial;
                var ajaxErrorFinal;
                var ajaxCall = function(url, timeout, errorCallback) {
                    ajaxStartTime = new Date().getTime();
                    settings.currentRequestUrl = url;
                    var ajaxOptions = {
                        url : url,
                        data : {
                            v : info.version,
                            q : keywords,
                            ref : settings.overrideDomain ? settings.overrideDomain : window.location.hostname,
                            cdn : info.location
                        },
                        processData : true,
                        cache : false,
                        timeout : timeout,
                        type : "GET",
                        success : ajaxSuccess,
                        error : errorCallback,
                        complete : function() {
                            $(window).scrollTop(prevScrollTop);
                            if (searchButtonAnimation !== false) clearTimeout(searchButtonAnimation);
                            searchButton.removeAttr("disabled").html(settings.searchButtonContent);
                        }
                    };
                    if (settings.useCors && typeof XMLHttpRequest !== "undefined" && "withCredentials" in new XMLHttpRequest()) {
                        ajaxOptions.dataType = "json";
                    } else {
                        ajaxOptions.dataType = "jsonp";
                        ajaxOptions.jsonpCallback = "postcodify_" + ajaxStartTime.toString() + Math.random().toString().substr(2, 4);
                    }
                    $.ajax(ajaxOptions);
                };
                
                // AJAX 요청 성공시 실행할 함수를 정의한다.
                
                ajaxSuccess = function(data, textStatus, jqXHR) {
                    
                    // 네트워크 왕복 시간을 포함한 총 소요시간을 계산한다.
                    
                    var searchTotalTime = (new Date().getTime() - ajaxStartTime) / 1000;
                    isSearching = false;
                    
                    // 백업 API로 검색에 성공했다면 이후에도 백업 API만 사용하도록 설정한다.
                    
                    if (settings.currentRequestUrl === settings.apiBackup) {
                        settings.callBackupFirst = true;
                    }
                    
                    // 검색후 콜백 함수를 실행한다.
                    
                    if (settings.afterSearch(keywords, data.results, data.lang, data.sort) === false) return;
                    
                    // API 서버에서 데이터베이스 오류가 발생한 경우 백업 서버에서 검색을 다시 시도한다.
                    
                    if (data.error && data.error.toLowerCase().indexOf("database") > -1) {
                        if (settings.currentRequestUrl === settings.api && settings.apiBackup && settings.api !== settings.apiBackup) {
                            settings.onBackup();
                            ajaxCall(settings.apiBackup, settings.timeoutBackup, ajaxErrorFinal);
                        }
                    }
                    
                    // 무료 API 서버의 일일 검색 허용 횟수를 초과한 경우...
                    
                    else if (data.error && data.error.toLowerCase().indexOf("quota") > -1) {
                        if (settings.useAlert) {
                            alert(info.translations[settings.language].errorQuota);
                        } else {
                            err = $('<div class="quota"></div>').postcodifyAddClass("search_status");
                            err.html(info.translations[settings.language].errorQuota.replace(/\n/g, "<br>"));
                            err.appendTo(results);
                        }
                    }
                    
                    // 그 밖의 에러 발생시...
                    
                    else if (data.error) {
                        if (settings.useAlert) {
                            alert(info.translations[settings.language].errorError);
                        } else {
                            err = $('<div class="error"></div>').postcodifyAddClass("search_status");
                            err.html(info.translations[settings.language].errorError.replace(/\n/g, "<br>"));
                            err.appendTo(results);
                        }
                        previousSearch = null;
                    }
                    
                    // 정상 처리되었지만 검색 결과가 없는 경우...
                    
                    else if (data.count < 1) {
                        if (settings.useAlert) {
                            alert(info.translations[settings.language].errorEmpty);
                        } else {
                            err = $('<div class="empty"></div>').postcodifyAddClass("search_status");
                            err.html(info.translations[settings.language].errorEmpty.replace(/\n/g, "<br>"));
                            err.appendTo(results);
                        }
                    }
                    
                    // 정상 처리되었지만 검색 서버의 버전이 맞지 않는 경우...
                    
                    else if (typeof data.results[0].postcode5 === "undefined" && typeof data.results[0].other === "undefined") {
                        if (settings.useAlert) {
                            alert(info.translations[settings.language].errorVersion);
                        } else {
                            err = $('<div class="error"></div>').postcodifyAddClass("search_status");
                            err.html(info.translations[settings.language].errorVersion.replace(/\n/g, "<br>"));
                            err.appendTo(results);
                        }
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
                            
                            // 구 버전 서버에서 작성한 검색 결과인 경우 새 버전으로 변환한다.
                            
                            var result = data.results[i];
                            if (typeof result.postcode5 === "undefined" && typeof result.code5 !== "undefined") {
                                result.postcode5 = result.code5;
                                result.postcode6 = result.code6.replace(/[^0-9]/, "");
                                result.ko_common = result.address["base"];
                                result.ko_doro = result.address["new"];
                                result.ko_jibeon = result.address["old"];
                                result.en_common = result.english["base"];
                                result.en_doro = result.english["new"];
                                result.en_jibeon = result.english["old"];
                                result.building_id = result.dbid;
                                result.building_name = result.address.building;
                                result.building_nums = result.other.bldnum;
                                result.other_addresses = result.other.others;
                                result.road_id = result.other.roadid;
                            }
                            if (!result.postcode6) {
                                result.postcode6 = "000000";
                            }
                            
                            // 검색 결과 항목을 작성한다.
                            
                            var option = $('<div></div>').postcodifyAddClass("search_result");
                            
                            // 구 버전과의 호환성을 위한 속성들을 입력한다.
                            
                            option.data("dbid", result.building_id);
                            option.data("code5", result.postcode5);
                            option.data("code6", result.postcode6.substr(0, 3) + "-" + result.postcode6.substr(3, 3));
                            
                            // 현재 버전이 공식적으로 지원하는 속성들을 입력한다.
                            
                            option.data("postcode5", result.postcode5);
                            option.data("postcode6", result.postcode6.substr(0, 3) + "-" + result.postcode6.substr(3, 3));
                            option.data("address", result.ko_common + " " + result.ko_doro);
                            option.data("jibeon_address", result.ko_common + " " + result.ko_jibeon);
                            option.data("english_address", (result.en_doro === "" ? "" : (result.en_doro + ", ")) + result.en_common);
                            option.data("english_jibeon_address", (result.en_jibeon === "" ? "" : (result.en_jibeon + ", ")) + result.en_common);
                            option.data("building_id", result.building_id);
                            option.data("building_name", result.building_name);
                            option.data("building_nums", result.building_nums);
                            option.data("extra_info_long", result.ko_jibeon + (result.building_name === "" ? "" : (", " + result.building_name)));
                            option.data("extra_info_short", result.ko_jibeon.replace(/\s.+$/, "") + (result.building_name === "" ? "" : (", " + result.building_name)));
                            option.data("extra_info_nums", data.nums);
                            option.data("other_addresses", result.other_addresses);
                            option.data("road_id", result.road_id);
                            
                            // 반환된 데이터의 언어, 정렬 방법에 따라 클릭할 링크를 생성한다.
                            
                            var mainText;
                            var extraText;
                            
                            if (resultLanguage === "en") {
                                if (typeof data.sort !== "undefined" && data.sort === "JIBEON") {
                                    mainText = option.data("english_jibeon_address");
                                    extraText = result.en_doro;
                                } else {
                                    mainText = option.data("english_address");
                                    extraText = result.en_jibeon;
                                }
                            } else {
                                if (typeof data.sort !== "undefined" && data.sort === "JIBEON") {
                                    mainText = option.data("jibeon_address");
                                    extraText = result.ko_doro;
                                } else {
                                    mainText = option.data("address");
                                    extraText = result.ko_jibeon;
                                }
                                if (result.building_name !== "" && result.building_name !== null) {
                                    extraText += ", " + result.building_name;
                                    if (!settings.hideBuildingNums && result.building_nums !== "") {
                                        extraText += " " + result.building_nums;
                                    }
                                }
                            }
                            
                            var selector = $('<a class="selector" href="#"></a>');
                            selector.append($('<span class="address_info"></span>').text(mainText));
                            if (extraText !== null && extraText !== "") {
                                selector.append($('<span class="extra_info"></span>').append("(" + extraText + ")"));
                            }
                            
                            // 우편번호, 기초구역번호, 주소 등을 항목에 추가한다.
                            
                            if (settings.forceDisplayPostcode5) {
                                $('<div class="code"></div>').text(option.data("postcode5")).appendTo(option);
                            } else if (settings.forceDisplayPostcode6) {
                                $('<div class="code"></div>').text(option.data("postcode6")).appendTo(option);
                            } else {
                                $('<div class="code6"></div>').text(option.data("postcode6")).appendTo(option);
                                $('<div class="code5"></div>').text(option.data("postcode5")).appendTo(option);
                            }
                            $('<div class="address"></div>').append(selector).appendTo(option);
                            
                            // 예전 주소 및 검색어 목록을 추가한다.
                            
                            var other_addresses = option.data("other_addresses");
                            if (other_addresses !== "") {
                                var oldAddrLink = $('<a href="#" class="show_old_addresses">▼</a>');
                                oldAddrLink.attr("title", info.translations[resultLanguage].msgShowOthers);
                                if (!settings.hideOldAddresses) oldAddrLink.css("display", "none");
                                oldAddrLink.appendTo(option.find("div.address"));
                                var oldAddrDiv = $('<div class="old_addresses"></div>').text(other_addresses);
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
                                mapurl = mapurl.replace("$JUSO", encodeURIComponent(option.data("address")).replace(/%20/g, '+'));
                                mapurl = mapurl.replace("$JIBEON", encodeURIComponent(option.data("jibeon_address")).replace(/%20/g, '+'));
                                var mapLinkContent = (settings.mapLinkContent !== null) ? settings.mapLinkContent : info.translations[resultLanguage].msgMap;
                                var maplink = $('<a target="_blank"></a>').attr("href", mapurl).html(mapLinkContent);
                                $('<div class="map_link"></div>').append(maplink).appendTo(option);
                            }
                            
                            option.appendTo(results);
                        }
                        
                        // 검색 결과 요약을 작성한다.
                        
                        if (!settings.hideSummary) {
                            var summary = $('<div class="summary"></div>').postcodifyAddClass("search_status");
                            summary.append('<div class="result_count">' + info.translations[resultLanguage].msgResultCount + ': ' +
                                '<span>' + data.count + '</span></div>');
                            summary.append('<div class="search_time">' + info.translations[resultLanguage].msgSearchTime + ': ' +
                                '<span>' + Math.round(data.time * 1000) + 'ms</span></div>');
                            summary.append('<div class="network_time">' + info.translations[resultLanguage].msgNetworkTime + ': ' +
                                '<span>' + Math.round((searchTotalTime - parseFloat(data.time)) * 1000) + 'ms</span></div>');
                            summary.appendTo(results);
                        }
                        
                        // 검색 결과가 너무 많아 일부만 표시한 경우 그 사실을 알린다.
                        
                        if (data.count >= 100) {
                            if (settings.useAlert) {
                                alert(info.translations[resultLanguage].errorTooMany);
                            } else {
                                err = $('<div class="too_many"></div>').postcodifyAddClass("search_status");
                                err.html(info.translations[resultLanguage].errorTooMany.replace(/\n/g, "<br>"));
                                err.insertBefore(results.find("div.postcodify_search_result").first());
                            }
                        }
                    }
                    
                    // 그 밖에 서버에서 전달할 메시지가 있는 경우 검색창 맨 위에 표시한다.
                    
                    if (typeof data.msg !== "undefined" && data.msg !== "") {
                        var msg = $('<div class="message"></div>').postcodifyAddClass("search_status");
                        msg.text(data.msg);
                        if (results.find("div.too_many").size()) {
                            msg.insertBefore(results.find("div.too_many").first());
                        } else {
                            msg.insertBefore(results.find("div.postcodify_search_result").first());
                        }
                    }
                    
                    // 검색 성공 콜백 함수를 실행한다.
                    
                    if (!data.error) {
                        settings.onSuccess();
                    }
                    
                    // 검색 완료 콜백 함수를 실행한다.
                    
                    settings.onComplete();
                    
                    // 검색 결과가 1개이고 autoSelect가 true인 경우 자동으로 선택한다.
                    
                    if (!data.error && data.count == 1 && data.nums && settings.autoSelect) {
                        results.find("div.postcodify_search_result a.selector").first().trigger("click");
                    }
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
                    
                    results.find("div.postcodify_search_status.error").show();
                    previousSearch = null;
                    isSearching = false;
                    
                    // 검색 실패 콜백 함수를 실행한다.
                    
                    settings.onError();
                    settings.onComplete();
                };
                
                // 검색 서버로 AJAX 요청을 전송한다.
                
                isSearching = true;
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
                
                var entry = $(this).parents("div.postcodify_search_result");
                
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
                
                if (settings.insertPostcode6) $(settings.insertPostcode6).val(entry.data("code6"));
                if (settings.insertPostcode5) $(settings.insertPostcode5).val(entry.data("code5"));
                if (settings.insertPostcode) $(settings.insertPostcode).val(entry.data("code5"));
                if (settings.insertAddress) $(settings.insertAddress).val(koAddrNew);
                if (settings.insertJibeonAddress) $(settings.insertJibeonAddress).val(koAddrOld);
                if (settings.insertEnglishAddress) $(settings.insertEnglishAddress).val(enAddrNew);
                if (settings.insertEnglishJibeonAddress) $(settings.insertEnglishJibeonAddress).val(enAddrOld);
                if (settings.insertBuildingId) $(settings.insertBuildingId).val(entry.data("building_id"));
                if (settings.insertBuildingName) $(settings.insertBuildingName).val(entry.data("building_name"));
                if (settings.insertBuildingNums) $(settings.insertBuildingNums).val(entry.data("building_nums"));
                if (settings.insertOtherAddresses) $(settings.insertOtherAddresses).val(entry.data("other_addresses"));
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
    
    // 팝업 레이어 플러그인을 생성한다.
    
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
            
            var placeholder = $('<div class="postcodify_placeholder"></div>').text('검색할 주소를 여기에 입력해 주세요');
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
                    insertPostcode : container.find(".postcodify_postcode"),
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
                        onSelect(entry);
                        closePopUpLayer();
                    }
                }, options));
                
                placeholder.prependTo(layer.find("div.postcodify_search_controls")).on("click", function() {
                    layer.find("input.keyword").focus();
                });
                layer.find("input.keyword").on("focusin focusout keydown keyup keypress input", function() {
                    if ($(this).val() === "") {
                        placeholder.show();
                    } else {
                        placeholder.hide();
                    }
                });
                
                curve_slice.appendTo(layer.find("div.postcodify_controls"));
                button_area.appendTo(layer.find("div.postcodify_controls"));
                layer.find("button.search_button").detach().prependTo(button_area);
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
                placeholder.text(width >= 540 ? "검색할 주소를 여기에 입력해 주세요" : "검색할 주소 입력");
                displays.height(layer.height() - 73);
            });
            
            // 검색 단추 클릭시 팝업 레이어를 보여주도록 설정한다.
            
            $(this).click(function() {
                if (layer.data("initialized") != "Y") initializePostcodify();
                background.show();
                layer.show().find("input.keyword").focus();
                $(window).triggerHandler("resize");
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
            
            // jQuery 관례에 따라 this를 반환한다.
            
            return this;
        });
    };
    
    // 클래스 추가 플러그인을 생성한다. (내부 호출 전용, 하위 호환성 보장)
    
    $.fn.postcodifyAddClass = function(class_name) {
        return this.addClass("postcodify_" + class_name).addClass("postcode_" + class_name);
    };
    
    // 무료 API 경로 설정.
    
    info.freeAPI = {
        defaultUrl : "//api.poesis.kr/post/search.php",
        backupUrl : "//api.poesis.co.kr/post/search.php"
    };

    // 지도 링크 설정.
    
    info.mapProviders = {
        daum : "http://map.daum.net/?map_type=TYPE_MAP&urlLevel=3&q=$JUSO",
        naver : "http://map.naver.com/?mapMode=0&dlevel=12&query=$JUSO",
        google : "http://www.google.com/maps/place/" + encodeURIComponent("대한민국") + "+$JUSO"
    };
    
    // 언어 설정.
    
    info.translations = {
        ko : {
            errorExactQuery : "정확한 도로명+건물번호 또는 동·리+번지로 검색해 주십시오.\n예: 세종대로 110, 연지동 219-2, 사서함 123-45",
            errorError : "검색 서버와 통신 중 오류가 발생하였습니다.\n잠시 후 다시 시도해 주시기 바랍니다.",
            errorEmpty : "검색 결과가 없습니다.\n정확한 도로명+건물번호 또는 동·리+번지로 검색해 주시고,\n다른 검색어 사용시 띄어쓰기에 유의하십시오.",
            errorQuota : "일일 허용 쿼리수를 초과하였습니다.\n관리자에게 문의해 주시기 바랍니다.",
            errorVersion : "검색 서버의 버전이 낮아 이 검색창과 호환되지 않습니다.",
            errorTooShort : "검색어는 3글자 이상 입력해 주십시오.",
            errorTooMany : "검색 결과가 너무 많아 100건만 표시합니다.\n정확한 도로명과 건물번호 또는 동·리와 번지로 검색해 주시기 바랍니다.",
            msgResultCount : "검색 결과",
            msgSearchTime : "소요 시간",
            msgNetworkTime : "통신 지연",
            msgSeeOthers : "관련지번 보기",
            msgKeywords : "검색 키워드",
            msgSearch : "검색",
            msgMap : "지도"
        },
        en : {
            errorExactQuery : "Please enter the exact name of your street, as well as the number(s).\nExample: 110 Sejong-daero, 219-2 Yeonji-dong, P.O.Box 123-45",
            errorError : "An error occurred while communicating to the search server.\nPlease try again later.",
            errorEmpty : "No addresses matched your search.\nPlease enter the exact legal name of your street, as well as the number(s).",
            errorQuota : "This website and/or your IP address has exceeded its daily search quota.\nPlease contact the administrator.",
            errorVersion : "The version of the search server is not compatible with this search function.",
            errorTooShort : "Please enter at least 3 characters.",
            errorTooMany : "Your search returned too many results. Only the first 100 items are shown below.\nPlease narrow down your search by adding the number(s).",
            msgResultCount : "Results",
            msgSearchTime : "Time taken",
            msgNetworkTime : "Network delay",
            msgSeeOthers : "See related addresses",
            msgKeywords : "Search keywords",
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
