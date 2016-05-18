<?php

/**
 *  Postcodify - 도로명주소 우편번호 검색 프로그램 (서버측 API)
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

date_default_timezone_set('Asia/Seoul');
error_reporting(-1);
require_once dirname(__FILE__) . '/../autoload.php';

// GET 또는 터미널 파라미터로부터 검색 키워드를 조합한다.

if (isset($_GET['gugun']) && strlen($_GET['gugun']) && isset($_GET['q']) && strlen($_GET['q']))
{
    $_GET['q'] = $_GET['gugun'] . ' ' . $_GET['q'];
}

if (isset($_GET['sido']) && strlen($_GET['sido']) && isset($_GET['q']) && strlen($_GET['q']))
{
    $_GET['q'] = $_GET['sido'] . ' ' . $_GET['q'];
}

$keywords = isset($_GET['q']) ? trim($_GET['q']) : (isset($argv[1]) ? trim($argv[1], ' "\'') : '');

// 키워드의 한글 인코딩 방식이 EUC-KR인 경우 UTF-8로 변환한다.

if ((isset($_GET['charset']) && stripos($_GET['charset'], 'euc') !== false) || !mb_check_encoding($keywords, 'UTF-8'))
{
    $keywords = @mb_convert_encoding($keywords, 'UTF-8', 'CP949');
}

// JSONP 콜백 함수명과 클라이언트 버전을 구한다.

$callback = isset($_GET['callback']) ? $_GET['callback'] : null;
$client_version = isset($_GET['v']) ? trim($_GET['v']) : POSTCODIFY_VERSION;
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc()) $keywords = stripslashes($keywords);
if (preg_match('/[^a-zA-Z0-9_.]/', $callback)) $callback = null;

// 검색을 수행한다.

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

if (!isset($result) || !is_object($result))
{
    $server = new Postcodify_Server;
    $server->db_driver = POSTCODIFY_DB_DRIVER;
    $server->db_dbname = POSTCODIFY_DB_DBNAME;
    $server->db_host = POSTCODIFY_DB_HOST;
    $server->db_port = POSTCODIFY_DB_PORT;
    $server->db_user = POSTCODIFY_DB_USER;
    $server->db_pass = POSTCODIFY_DB_PASS;
    $result = $server->search($keywords, 'UTF-8');
}

// 검색 결과를 juso.sir.co.kr API와 같은 포맷으로 변환한다.

ob_start(); ?>
<li class="postcodify_search_result" onclick="put_data('%1$s', '%2$s', '%5$s', '(%7$s)', '%6$s')">
    <p style="font: 13px/1.0 'Segoe UI', Arial, sans-serif; color: #999; margin: 0; padding: 0">
        <a href="#" style="color: black" onclick="put_data('%1$s', '%2$s', '%5$s', '(%7$s)', '%6$s'); return false;">
        <span style="color: #b00">%3$s</span> &nbsp;/&nbsp;<span style="color: #00b">%4$s</span></a>
    </p>
    <p style="line-height: 1.5; margin: 8px 0 0 0; padding: 0">
        <a href="#" style="color: black" onclick="put_data('%1$s', '%2$s', '%5$s', '(%7$s)', '%6$s'); return false;">
        <span class="postcodify_address_line">%5$s</span> &nbsp;<span style="color:#678">(%8$s)</span></a>
    </p>
</li>
<?php $template = ob_get_clean();

if ($result->error)
{
    $json = array(
        'error' => "검색 서버와 통신 중 오류가 발생하였습니다.\n잠시 후 다시 시도해 주시기 바랍니다.",
        'juso' => $result->error,
    );
}
else
{
    $json = array('error' => '', 'juso' => array());
    $json['juso'][] = sprintf('<div class="result_msg">검색결과 <b>%d%s</b>' .
        '<div class="powered_by_postcodify" style="float:right;font:11px \'Segoe UI\', sans-serif;color:#bbb">' .
        'Powered by <a style="color:#bbb" href="http://postcodify.poesis.kr/" target="_blank">Postcodify</a></div>' .
        '</div>', $result->count, ($result->count === 100) ? '+' : '');
    if (!count($result->results))
    {
        $json['juso'][] = '<div class="result_msg" style="padding-top:0;font-weight:bold;color:#36f">' .
            '검색 결과가 없습니다.<br>정확한 도로명+건물번호 또는 동·리+번지로 검색해 주십시오.</div>';
    }
    elseif (count($result->results) === 100)
    {
        $json['juso'][] = '<div class="result_msg" style="padding-top:0;font-weight:bold;color:#36f">' .
            '검색 결과가 너무 많아 100건만 표시합니다.<br>정확한 도로명+건물번호 또는 동·리+번지로 검색해 주십시오.</div>';
    }
    $json['juso'][] = '<ul>';
    foreach ($result->results as $entry)
    {
        $code6 = array(substr($entry->postcode6, 0, 3), substr($entry->postcode6, 3, 3));
        $code5 = array(substr($entry->postcode5, 0, 3), substr($entry->postcode5, 3, 2));
        $juso = htmlspecialchars($entry->ko_common . ' ' . $entry->ko_doro, ENT_COMPAT, 'UTF-8');
        $jibeon = htmlspecialchars($entry->ko_common . ' ' . $entry->ko_jibeon, ENT_COMPAT, 'UTF-8');
        $extra_input = htmlspecialchars(preg_replace('/\s.+$/', '', $entry->ko_jibeon) . ($entry->building_name === '' ? '' : (', ' . $entry->building_name)), ENT_COMPAT, 'UTF-8');
        $extra_display = htmlspecialchars($entry->ko_jibeon . ($entry->building_name === '' ? '' : (', ' . $entry->building_name)) .
            ($entry->building_nums ? (' ' . $entry->building_nums) : ''), ENT_COMPAT, 'UTF-8');
        if (isset($_GET['pc']) && $_GET['pc'] === '6')
        {
            $json['juso'][] = sprintf($template, $code6[0], $code6[1], implode('-', $code6), $entry->postcode5, $juso, $jibeon, $extra_input, $extra_display);
        }
        else
        {
            $json['juso'][] = sprintf($template, $code5[0], $code5[1], $entry->postcode5, implode('-', $code6), $juso, $jibeon, $extra_input, $extra_display);
        }
    }
    $json['juso'][] = '</ul>';
    $json['juso'][] = '<style>';
    $json['juso'][] = 'li.postcodify_search_result { font: 13px/1.0 Dotum, sans-serif; cursor: pointer }';
    $json['juso'][] = 'li.postcodify_search_result:hover { background: #f6f9fc; }';
    $json['juso'][] = 'li.postcodify_search_result a { text-decoration: none; }';
    $json['juso'][] = 'li.postcodify_search_result a { text-decoration: none; }';
    $json['juso'][] = '</style>';
    $json['juso'] = implode("\n", $json['juso']);
}

$json_options = (PHP_SAPI === 'cli' && defined('JSON_PRETTY_PRINT')) ? 384 : 0;
echo ($callback ? ($callback . '(') : '') . json_encode($json, $json_options) . ($callback ? ');' : '') . "\n";

if (isset($_GET['merge']) && $_GET['merge'] !== 'N'): ?>

(function($) {
    
    if (typeof window.put_data_postcodify === "undefined") {
        window.put_data_postcodify = window.put_data;
        window.put_data = function(zip1, zip2, addr1, addr3, jibeon) {
            $(window.opener.document).find("input.postcodify_merged_zip").val(zip1 + (zip2.length > 2 ? "-" : "") + zip2);
            window.put_data_postcodify(zip1, zip2, addr1, addr3, jibeon);
        };
    }
    
    var form = null;
    var form_match = window.location.search.match(/frm_name=([^&]+)/);
    if (form_match) {
        form = $("form[name='" + form_match[1] + "']", window.opener.document)
    } else {
        return;
    }
    
    var container = form.find("input[name$='_zip1']").parent();
    if (container.size() < 1) {
        container = $(window.opener.document).find("input[name$='_zip1']").parent();
    }
    if (container.find("input.postcodify_merged_zip").size() > 0) {
        return;
    }
    
    var old_zip1 = container.find("input[name$='_zip1']").hide();
    var old_zip2 = container.find("input[name$='_zip2']").hide();
    container.find("label[for$='_zip1']").remove();
    container.find("label[for$='_zip2']").remove();
    container.contents().filter(function() { return this.nodeType === 3 && $.trim(this.nodeValue) === '-'; }).remove();
    
    var old_id = old_zip1.attr("id") ? old_zip1.attr("id") : "postcodify_replacement_zip1";
    var new_id = old_id.replace("zip1", "zip0");
    var new_zip = $('<input type="text" id="' + new_id + '" class="' + old_zip1.attr("class") + '" size="8" maxlength="7" />');
    new_zip.addClass("postcodify_merged_zip").attr("readonly", "readonly");
    new_zip.val(old_zip1.val() + (old_zip2.val().length > 2 ? "-" : "") + old_zip2.val());
    new_zip.prependTo(container);
    var new_label = $('<label for="' + new_id + '" class="sound_only"></label>').prependTo(container);
    
}(window.opener.jQuery));

<?php endif;
exit;
