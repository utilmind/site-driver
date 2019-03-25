<? // TODO: это ВСЁ должно быть оформленно КЛАССОМ.

// CONFIG
$GLOBALS['NOCACHE_HEADERS_PATH']	= ROOT.'/inc/nocache.php';

/* The Core GLOBALS
// ==========================

1. We suppose to already have following global vars:
$def_language		= 'en';		// default language (eg 'ua' for the FAVOR.com.ua)
$site_languages		= array();	// keys of the array is list of supported languages.
$site_aliases		= '';		// all possible site aliases
$no_language_dirs	= '';		// list of directories from which language redirect should be disabled. If empty (or value not found) -- all language redirects are disabled. So you need to specify at least 1 folder where redirection shouldn't work.

// optional (not necessary)
$site_url	= '';      // if not specified, we detecting it.
$site_root	= '';      // if have some path after the domain.

2. Now we're setting following vars:
  'ISAJAX'
  'lang'
  'lang_url'
  'site_url'
  'this_php'		// path including language but without http query string
  'this_php_query'	// path including language with http query string
  'pure_doc'		// path without language without query
  'pure_doc_query'	// path without language with query
  'canonical_url'	// $this_php_query but only with canonical parameters. (TODO)
  'cur_dir'             // last folder in $pure_doc.

3. To disable language redirect -- set global variable $HTTP_NOCACHE or $HTTP_ERROR_CODE to any non-false value.
 */

// ===============================
// COOKIES (yes, it's part of URI)
function mysetcookie($name, $value, $expire = 31536000, $path = false) { // 31536000 = 1 year, 21600000 = (60*60*24*250) 250 days
  if (($name != 'uin') && isset($_COOKIE[$name]) && ($_COOKIE[$name] == $value)) return;
  if ($expire > 0) $expire+=time();

  if (!$path) $path = '/';
  if (headers_sent()) { // we can't modify cookies anymore, so let's use JavaScript. But of course, it will NOT work when JavaScript is turned off. So it's not 100% reliable.
    $expire = date('r', $expire);
    print <<<END
<script>
// <![CDATA[
document.cookie="$name=$value; expires=$expire; path=$path;"
console.log("Cookie '$name' set after header starts. Expires $expire.")
// ]]>
</script>

END;
  }else
    setcookie($name, $value, $expire, $path);
  return $_COOKIE[$name] = $value; // в detect_user_device() мы хотим результат
}

function clear_cookie($name, $just_disable = false, $path = false) {
  if (!isset($_COOKIE[$name])) return;

  if ($just_disable) {// just set '-' at beginning
    if ($_COOKIE[$name] && ($_COOKIE[$name]{0} != '-'))
      mysetcookie($name, '-'.$_COOKIE[$name], 31536000, $path);
    return;
  }
  mysetcookie($name, false, -999, $path);
}

function mygetcookie($name, $def = false) {
  return isset($_COOKIE[$name]) && ($_COOKIE[$name] !== false) ? $_COOKIE[$name] : $def;
}

function clear_cookies($except = false, $path = false) {
  if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    if ($except)
      $except = explode(',', $except);
    foreach($cookies as $cookie) {
      $parts = explode('=', $cookie);
      $name = trim($parts[0]);
      $skip = false;
      foreach ($except as $x)
        if (($name == $x) || (substr($name, 0, 3) == 'PHP')) {
          $skip = true;
          break;
        }

      if (!$skip)
        clear_cookie($name, false, $path);
    }
  }
}

function setmodifier($id, $v=1) { // todo: maybe switch to sessions? But it works good as is.
  mysetcookie('mod'.$id, $v, time()+60);
}

function is_modified($id) {
  if ($v = mygetcookie('mod'.$id)) {
    clear_cookie('mod'.$id);
    return $v;
  }
  return false;
}


// =============================
// URLs
function strip_protocol_prefix($url) {
  if (($i = strpos($url, '//')) !== false) // was '://' before 12.08.2018.
    return substr($url, $i+2);
  return $url;
}

// quick check without disk operations, without is_file(), is_dir() etc.
function is_local_url($url) {
  if ((strlen($url) < 2) || $url[0] == '#' || $url[0] == '%') // avoid tags like %PRIZE_ARTURL%
    return 1;

  if ($url[0] == '/')
    return $url[1] == '/' ? 0 : 1; // "/..." is certainly local, "//" is certainly not (BUT for sure check out domain name too, maybe it's the name of localhost?).

  // До 12.08.2018 были разные проверки протокола, https?://, ftp://, mailto: и пр. Но на самом деле всё проще. Если есть ":" и перед ним нет "?", то это внешняя ссылка. Если нет ":" то локальная.
  if (($c = strpos($url, ':')) === false)
    return 1; // no ":" = local url.

  // UPD. How about Windows pathes like "d:/path/..."?
  if (($c == 1) && isset($url[2]) && (($url[2] == '\\') || ($url[2] == '/' && isset($url[3]) && $url[3] != '/'))) // this is local path of Windows!
    return 1;

  // have ":". now check "?".
  return (($q = strpos($url, '?')) === false) || ($q > $c) ? 0 : 1; // no "?" or "?" is after ":" = external. Otherwise = local.
}

function is_external_url($url, $check_local_site = true) {
  if (is_local_url($url))
    return false; // local

  // Actually "is_local_url()" is self-sufficiant. But further we maybe want to check the domain name after "//", to make sure that it's not our "local" domain name.
  if ($check_local_site) {
    global $is_local, $site_url, $site_aliases;

    $url = strip_protocol_prefix(strtolower($url));
    if (is_array($site_aliases))
      foreach ($site_aliases as $i)
        if (strpos($url, $i) === 0)
          return false;

    if ($is_local && (strpos($url, strip_protocol_prefix($site_url)) === 0))
      return false;
  }

  return true;
}

function strip_local_site_url($url) { // избавляемся от префикса с корневым URL'ом сайта
  global $site_aliases;

  $url = strip_protocol_prefix($url); // AK до 27.04.2018 было ещё strtolower($url). Так вот никогда не надо это делать. Как насчёт ссылки вида "favor.com.ua/stuff/ann_favor_XV_A4.jpg"?
  if (is_array($site_aliases))
    foreach ($site_aliases as $i)
      if (strpos($url, $i) === 0) {
        if (!$url = substr($url, strlen($i)))
          $url = '/'; // совсем пустым путь быть не может.
        break;
      }
  return $url;
}

function get_local_lang_from_uri($uri) {
  global $site_languages;

  $len = strlen($uri);
  if (($len >= 3) && ($uri[0] == '/') && (($len == 3) || ($uri[3] == '/')) &&
      ($i = strtolower(substr($uri, 1, 2))) && isset($site_languages[$i]))
    return $i;

  return false;
}

function strip_local_lang_uri($uri) { // избавляемся от префикса /ru/ или /en/. (Но здесь входящий URL уже должен быть только локальным.)

  if ($uri && $uri[0] == '/')
    if (get_local_lang_from_uri($uri))
      return '/'.substr($uri, 4);
    else
      return $uri; // No language, but URI looks good. Return as is.

  return '/'.$uri; // We ALWAYS have result. At least "/". There is no empty result. Even if $uri is empty we will return "/".
}

function replace_link_lang($url, $lng = false, $site_link = true, // $site_link < 0 -- put canonical $main_url instead. (eg www.favor.com.ua in case if access by ip http://91.223.223.144)
    $allow_anchors = false) { // сделать ссылку чистой, без префикса языка, а затем подставить префикс нужного языка
  global $lang, $site_url, $main_url;

  if ($lng === false) $lng = $lang;

  if ($allow_anchors && (strlen($url) > 0) && ($url[0] == '#')) // allow internal anchors? (eg for email)
    return $url;

  if (($url = strip_local_site_url($url)) && !is_external_url($url, false)) {
    $nuri = strip_local_lang_uri($url); // избавляемся от префикса /ru/ или /en/. Результат как минимум "/".

    if ($site_link > 0)
      $url = $site_url;
    elseif ($site_link < 0)
      $url = $main_url ? $main_url : $site_url;
    else
      $url = '';

    if ((strlen($nuri) > 1) && is_nolang_uri($nuri)) // AK 25.02.2019: это кажется лишним. Но если что — верни: ($nuri{1} != '#')
      $lng = ''; // no language prefix for some internal dirs

    if ($lng) $lng = lang_dir($lng);
    $url.= $lng.$nuri;
  }

  return $url;
}

function fix_local_links($t) { // adds $site_url to each link without site_prefix
  global $site_url;
  return preg_replace("/<(a|link)([^>]+?)href=(\s*?)(\"|')\/([^\\4]*?)(\\4[^>]*?)>/is", "<$1$2href=$3$4$site_url/$5$6>", $t);
}

function make_global_links($t, $lng = false, $allow_anchors = false) { // fixes the local links and changes the language section
  return preg_replace_callback("/<(a|link)([^>]+?)href=(\s*?)(\"|')([^\\4]*?)(\\4[^>]*?)>/is", function($m) use($lng, $allow_anchors) {
      return "<$m[1]$m[2]href=$m[3]$m[4]".replace_link_lang($m[5],$lng,1,!!$allow_anchors).$m[6].'>';
    }, $t);
}

/* Это чисто для логинов.
   Задача: получить адрес страницы на которой был юзер до перехода на страницу логина.
   При этом ИСКЛЮЧИТЬ:
     1. внешние сайты;
     2. скрипты, которые могли перекинуть юзера на login, из "/inc/". Так бывает при неудачной попытке залогиниться через ВКонтакте, из "/inc/user/oauth/oauth.php".
        Когда мы должны перекинуть юзера на "/user/", но из-за того что попытка логина не удалась, его перекидывает на "/login/". Так вот, не давать там referer на "/inc/...".
 */
function get_local_page_referer() {
  if (isset($_SERVER['HTTP_REFERER']) && ($url = $_SERVER['HTTP_REFERER'])) {
    $url = strip_local_site_url($url);
    if (is_external_url($url) || // и внешние сайты...
        (substr($url, 0, 5) == '/inc/')) return false; // и внутренние редиректы для нас одинаково бесполезны.
    return strip_local_lang_uri($url);
  }
}



// =============================
// URLs / GET-queries
function strip_empty_uri_params($q) {
  return preg_replace('/(\&?\w*\=+)+($|\?|(\&(?!amp;)))/', '', $q); // TEST "/invoice/?year=&amp;year=&logerror=captcha&year="
}

function uri2arr($q, $separator = false) {
  if (strpos($q, '&amp;'))
    $q = str_replace('&amp;', '&', $q);

  if (($i = strpos($q, '?')) !== false)
     $q = substr($q, $i); // chop

  if (!$separator) // auto-detecting the separator. If & not found -- trying to find comma (,).
    $separator = (strpos($q, '&') === false) && (strpos($q, ',') !== false) ? ',' : '&';

  $arr = false;
  $names = explode($separator, $q);
  foreach ($names as $name) {
    @list($key, $val) = explode('=', $name);
    $arr[$key] = isset($val) ? $val : ''; // always non-false value, empty is ok.
  }

  return $arr;
}

// update $_SERVER['QUERY_STRING'] and all $_GET['...'] parameters.
function update_server_query_string($query_string, $unset_existing_gets = false) {
  global $ISAJAX;
  $_SERVER['QUERY_STRING'] = $query_string;

  if ($q = uri2arr($query_string))
    $_GET = $unset_existing_gets ? $q : array_merge($_GET, $q);
}

// Of course, we CAN use $_GET[] for the most cases. But what if we would like to get some parameter from custom query, different than $_SERVER['QUERY_STRING'].
function get_uri_param($param_name, $q = false) { // if $q is FALSE (not empty), we're using $_SERVER['QUERY_STRING'].
  if ($q === false)
    $q = $_SERVER['QUERY_STRING'];

  if (!$q || !$param_name) return false;

  if (($i = strpos($q, '?')) !== false)
    $q = substr($q, $i+1);

  $params = explode('&', str_ireplace('&amp;', '&', $q)); // don't remove &amp;!
  foreach($params as $param => $p)
    if (strpos($p, '=')) {
      list($n, $v) = explode('=', $p);
      if ($n == $param_name)
        return $v; // DON'T DO strtolower($v)!
    }

  return false;
}

/* Trick #1: if you want to add canonical parameter to QUERY_STRING / $this_php_query, just use call like "set_uri_params('param='.$val)". All variables including $_SERVER['QUERY_STRING'] will be updated.

   Warning: if you would like to specify the query string as URI -- don't forget to add "?" parameter in beginning, like "?$_SERVER[QUERY_STRING]". Otherwise URI will be recognized as FULL URI.
*/
function set_uri_params(
    $new_params,       // , or &-separated list or array is allowed. All new values OVERRIDE previous. All EMPTY values will be CLEARED.
    $uri = false,      // if FALSE (false, not ""), $_SERVER['QUERY_STRING'] is used. (ONLY "QUERY_STRING"! NOT FULL URL!)
                       // ADDITIONALLY: All variables (including $this_php_query, $pure_doc_query etc) will be automatically updated.
                       //    -1 = same as '?'.$_SERVER['QUERY_STRING']. Without updating of URI-variables.
    $amp_str = false,  // converts in return value & to &amp;. (This is correct upon preparing href="..." values.) Also remember about "urlencoding"...
    $add_quest = false,// add "?" in beginning of non-empty query string.
    $is_params_is_allowed_param_names = false
   ) {

  if (($do_update_vars = ($uri === false)) || ($uri === -1)) {
    $uri = '';
    $query = $_SERVER['QUERY_STRING'];
  }else
    @list($uri, $query) = explode('?', $uri);

  $query = uri2arr($query);
  if (!is_array($new_params))
    $new_params = uri2arr($new_params);

  if (!$is_params_is_allowed_param_names)
    if ($query) {
      if ($new_params)
        $query = array_merge($query, $new_params);
    }elseif ($new_params)
      $query = $new_params;
    else
      $query = false; // no parameters

  // reassembling the query
  $r = '';
  if ($query)
    foreach ($query as $key => $val) {
      if ($is_params_is_allowed_param_names) {
        if (!isset($new_params[$key]))
          continue; // skip
      }

      if ($val != '') // all empty values will be cleared! "0" values are allowed.
        $r.= ($r ? '&'.($amp_str && !$do_update_vars ? 'amp;' : '') : '').$key.'='.$val;
    }

  if ($do_update_vars) {
    if ($r != $_SERVER['QUERY_STRING']) {
      update_server_query_string($r);
      uri_init_vars(1);
    }
    if ($amp_str)
      $r = amp_str($r);
  }

  if ($uri && $r)
    return $uri.'?'.$r;

  return $r ? ($add_quest ? '?' : '').$r : $uri;
}

// Adds or modifies only single parameter, if it's value other than default. (If equal to defalut, value will not set.)
function set_query_param($param_name,	// single parameter
  $val = 0, $def_val = 0,		// If $val equals to $def_val, $param will be removed from $query_string.
  $query_string = false,		// If FALSE, $_SERVER['QUERY_STRING'] will be used.
  $amp_str = false,			// converts in return value & to &amp;. (This is correct upon preparing href="..." links.) Also remember about "urlencoding"...
  $add_quest = true) {			// add "?" in beginning of non-empty result.

  if ($query_string === false)
    $query_string = $_SERVER['QUERY_STRING'];

  return set_uri_params($param_name.($val === $def_val ? '' : '='.$val), // if default -- remove paramter from query string.
     '?'.$query_string, $amp_str, $add_quest);
}

function strip_unallowed_params_from_uri(
    $allowed_params = false,		// , or &-separated list or array is allowed. This is the list of parameters which will remain in the query string. All others will be removed.
    $uri = false,			// If FALSE, $_SERVER['QUERY_STRING'] will be used + all variables will be reset so only allowed parameters will remain. Or use -1 like in set_uri_params().
    $amp_str = false,			// converts in return value & to &amp;. (This is correct upon preparing href="..." links.) Also remember about "urlencoding"...
    $add_quest = true) {		// add "?" in beginning of non-empty query string.

  return set_uri_params($allowed_params, $uri, $amp_str, $add_quest, true);
}



// =============================
// MISC UTILS
function pgvar($val, $def = false) {
  return isset($_POST[$val]) && $_POST[$val] != '' ? $_POST[$val] :
         (isset($_GET[$val]) && $_GET[$val] != '' ? $_GET[$val] : $def);
}

// see also pvarq() for htmlquoted version of pvar(), in "strings.php".
function pvar($var, $def = false) {
  return isset($_POST[$var]) && ($def === false || $_POST[$var] != '') ? $_POST[$var] : $def;
}

function is_local_surf() {
  if (isset($_SERVER['HTTP_REFERER'])) {
    $r = stripos($_SERVER['HTTP_REFERER'], '//'.$_SERVER['SERVER_NAME']); // don't check protocol. It's incorrectly detected if using Nginx-->Apache proxy.
    if ($r !== false && $r < 7) // this is our host
      return true;
  }
  return false;
}


// =============================
// LANGUAGE
function is_nolang_uri($uri) {
  global $no_language_dirs;

  if (!$no_language_dirs) return true; // all language redirects disabled.

  $uri = substr($uri, 1).'/'; // removing 1st slash + adding extra slash just for sure, that last char is slash.
  foreach ($no_language_dirs as $i) // no language prefix for some internal dirs
    if (strpos($uri, $i.'/') === 0)
      return true;

  return false;
}

function lang_dir($lang) {
  global $def_language;
  return $lang && $lang != $def_language ? '/'.$lang : '';
}

// This is an ugly function for kludges (when we need to switch language on the fly + switch it back again). Try to avoid it. It's still here due to some legacy code.
function setlang($l, $save_lang_cookie = false) {
  global $lang, $lang_url, $site_languages, $def_language;

  if ($l == $lang) return false;
  if (!isset($site_languages[$l])) $l = $def_language;

  $lang_url = lang_dir($lang = $l);
  if ($save_lang_cookie)
    mysetcookie('lang', $lang);

  return true;
}

function detect_redirect_lang($allowed_languages, $def_lang = 'en', $detect_browser_lang = true, $cookie_token = 'lang') {
  global $lang;

  // Use in ONLY upon visit by direct link from some OUTER URL! (Maybe even the first visit ever)
  // ----------------------------------------------------------
  // Do we have pre-saved language? (Don't do any redirect if cookies not supported.)
  if (($cookie_lang = mygetcookie($cookie_token)) && isset($allowed_languages[$cookie_lang]))
    return $cookie_lang;

  // If there is no previously saved language, and this is visit to the MAIN page.
  if ($detect_browser_lang && // Let's try to detect current language by browser settings!
      isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {

    $first_prefered_lang = false;

    $l = explode(';', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    foreach ($l as $key => $i) {
      if (strpos($i, ',') !== false) {
        $i = explode(',', $i, 2);
        $i = $i[1];
      }
      $i = trim(strtolower($i));
      if (isset($allowed_languages[$cur = substr($i, 0, 2)])) { // first 2 bytes of any line
        if ($cur == $lang) return $cur; // we already use one of the prefered language
        if (!$first_prefered_lang)
          $first_prefered_lang = $cur;
      }

      if (strpos($i, '-') !== false) {
        $i = explode('-', $i, 2);
        if (isset($allowed_languages[$cur = $i[1]])) { // some lang id after "-". Eg "ru-UA". We'll detect "ua" in case if it supported.
          if ($cur == $lang) return $cur; // we already use one of the prefered language
          if (!$first_prefered_lang)
            $first_prefered_lang = $cur;
        }
      }
    }

    return $first_prefered_lang;
  }

  return false;
}

function uri_init_vars(
    $read_query_string = false,	// TRUE -- get parameters from $_SERVER['QUERY_STRING'] instead of $_SERVER['REQUEST_URI'] and override parameters of $custom_uri.
                                // If additionally $custom_uri is provided (and $custom_uri has paramters), parameters will be merged. $custom_uri parameters have higher priority.
    $custom_uri = false,	// *without language prefix* and only after the language is detected.
    $setup_lang = false		// detect language by URL, parameters, cookie, browser settings.
  ) {

  global $lang, $lang_url, $this_php, $this_php_query, $pure_doc, $pure_doc_query,
    $ISAJAX, $HTTP_ERROR_CODE, $HTTP_NOCACHE,
    $cur_dir, $canonical_url, $og_share_url,
    $is_print_version, $query_print,
    // pre-defined:
    $site_url, $site_root, $site_languages, $def_language;

  if (!$site_url) // detect $site_url. But better it should be pre-setup. (IMPORTANT! $_SERVER's protocol can not be determined if script called internally, eg by crontab, OR may return wrong value if called via proxy Nginx -> Apache.)
    $site_url =  'http'.(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 's' : '').'://'.$_SERVER['SERVER_NAME'];

  if ($custom_uri) {
    $this_php_query = $lang_url.$custom_uri;
    /* Мы должны следить за этим снаружи, не допускать двойных слешей в $custom_uri.)
    if (strpos($t, '//') !== false) // remove odd slashes
      $this_php_query = preg_replace("/\/+/", '/', $this_php_query);
     */

  }elseif (isset($_GET['ajaxgo']) && (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (basename($_SERVER['PHP_SELF']) == 'ajax.php'))) { // AJAX call?
    $ISAJAX = $local_is_ajax_request = true;

    // strip hostname from URL. WATCH CAREFULLY! $this_php_query should start with "/". But I don't want fixing it here. It should be correct from the client side.
    $this_php_query = preg_replace('/^[\w\:]*?\/\/[\w\.]+\//', '/', $_GET['ajaxgo']);
  }else
    $this_php_query = isset($_SERVER['REQUEST_URI']) ? strtolower($_SERVER['REQUEST_URI']) : '';

  if ($site_root && (strpos($this_php_query, $site_root) == 0)) // strip site_root prefix from the path.
    $this_php_query = substr($this_php_query, strlen($site_root));

  // determinate language by current path AND pure document path without language
  if ($i = get_local_lang_from_uri($this_php_query)) {
    if ($setup_lang) $lang = $i;
    $pure_doc_query = '/'.substr($this_php_query, 4);
  }else {
    if ($setup_lang) $lang = $def_language;
    $pure_doc_query = $this_php_query;

    if ((strlen($this_php_query) >= 2) && ($this_php_query[0] == '/') && is_nolang_uri($this_php_query))
      $no_lang_redirect = 1;
  }
  @list($pure_doc, $query_string) = explode('?', $pure_doc_query);

  if (isset($local_is_ajax_request)) { // only once at 1st request
    update_server_query_string($query_string);

  }elseif ($read_query_string && ($query_string != $_SERVER['QUERY_STRING'])) { // Do we need to update vars by updated QUERY_STRING?

    // do we need to MERGE parameters?
    if ($custom_uri)
      update_server_query_string(set_uri_params($query_string, -1)); /// add $query_string to $_SERVER['QUERY_STRING']. ($query_string has higher priority.)

    // reassembling with modified $query_string...
    $pure_doc_query = $pure_doc.'?'.($query_string = $_SERVER['QUERY_STRING']);
  }

  if ($setup_lang) { // first initializaton -- always TRUE.
    // force language by $_GET or $_POST vars? --- OVERRIDE language without redirection!
    if (($i = pgvar('lang')) && // passed as $_GET[] or $_POST[] paramter
        ($i = strtolower($i)) &&
        isset($site_languages[$i])) {
      $lang = $i;
      $save_lang = 1;
    }else {
      /* Reasons to redirect:
           1. Language was not set as $_POST or $_GET parameter.
           2. This is the pass from EXTERNAL, OUTER site. (No referer or outer referer.)
           3. ...and... Detected prefered language.
                3.1. By cookie ...OR.. by browser settings (only on the main page of the website).
       */
      if (is_local_surf())
        $save_lang = 1;
      elseif (!isset($no_lang_redirect) && !$ISAJAX &&
              !$HTTP_ERROR_CODE && !$HTTP_NOCACHE && // no language redirect in case of any HTTP error. BTW 404 page generates $HTTP_NOCACHE code.
              ($l = detect_redirect_lang($site_languages, $def_language, strlen($pure_doc) <= 1, 'lang')) &&
              ($l != $lang)) { // only if detected language different from what we're use now.
        $lang_url = lang_dir($l);
        // Let's redirect to correct landing... to PREVIOUSLY known language.
        http_response_code(302);
        header('Location: '.$site_url.$lang_url.$pure_doc_query); // this is our correct target location, based on pre-saved language.
        exit;
      }
    }

    if (isset($save_lang))
      mysetcookie('lang', $lang); // save language for future use!

    // finalize with language...
    $lang_url = lang_dir($lang);
  }

  // reassembling $this_php with correct language prefix and correct query string.
  $this_php = $lang_url.$pure_doc;
  $this_php_query = $lang_url.$pure_doc_query;

  // last folder in path...
  $cur_dir = ($path_folders = explode('/', trim($pure_doc, '/'))) ? end($path_folders) : '';

  // all miscellaneous...
  $query_print = $this_php_query.($query_string ? '&amp;' : '?').'print=y'; // вообще-то это лишняя хрень. Но используется в head.php... и возможно при генерации ajax'овых страниц...
  $is_print_version = isset($_GET['print']);

  /* Чем отличается $canonical_url от $pure_doc_query? И $og_share_url?

     $canonical_url -- лендинг для поисковиков (группируем дубликаты одной страницы), а $pure_doc_query -- ХЗ для чего. До 16.08.2018 было для социального шеринга, og:url.
     Хотя при этом, в панели шеринга мы старались дать именно $canonical_url, да ещё и вырезали из неё пагинатор комментов 'cpage', чтобы максимально группировать страницы,
     собирая максимум лайков для одной шеринговой кнопки.

     Короче, og:url — это именно то, что мы должны давать в share_panel. До 16.08.2018 мы совали в og:url $pure_doc_query, а в кнопки шеринга $canonical_url.
     С 16.08.2018 мы ужесточили авто-шеринг. Специально для этого ввёлся $og_share_url. Генерится только здесь, автоматом и даже без пагинатора комментов.

     Ещё их отличие в том, что $og_share_url идёт без префикса сайта и языка. (Потому что мы может хотим расшарить на каком-то указанном языке.)
     А $canonical_url идёт сразу со всеми префиксами.

     До 4.03.2019 мы убирали все параметры из каноничных URL'ов. Но это точно ошибка. TODO: разбирать ЗДЕСЬ каноничные параметры.
   */
  $og_share_url = $pure_doc_query;
  $canonical_url = $site_url.$this_php_query; // todo: оставить лишь каноничные параметры!
}

function uri_update_http_status() {

  /* Don't move above, don't execute this too early. Because $HTTP_ERROR_CODE/$HTTP_NOCACHE/$HTTP_ROBOTS/$NOINDEX may be initialized later.
     This code should be executed right before the output of HTML-headers.

     UPD. В то же время НИ В КОЕМ СЛУЧАЕ НЕ переноси сюда $ALLOWED_LEVELS. Мы должны проверять привилегии доступа на самом раннем этапе,
     буквально после чтения уровня привилегий из базы. И не генерить лишний контент для не имеющих привилегий.
   */

  global $HTTP_ERROR_CODE, $HTTP_NOCACHE, $HTTP_ROBOTS, $NOINDEX;

  // ERRORS or NOCACHE?
  if ($HTTP_ERROR_CODE) {
    $HTTP_NOCACHE = 1;
    $HTTP_ROBOTS = 'none';
    http_response_code((int)$HTTP_ERROR_CODE);
  }

  if ($HTTP_NOCACHE) {
    $HTTP_ROBOTS = 'none';
    require_once($GLOBALS['NOCACHE_HEADERS_PATH']);
  }

  if ($HTTP_ROBOTS) {
    header('X-Robots-Tag: '.$HTTP_ROBOTS);
    $NOINDEX = $HTTP_ROBOTS != 'noarchive';

    if (function_exists('inline_script'))
      inline_script(HEAD_META, '<meta name="robots" content="'.$HTTP_ROBOTS.'" id="h-robots" />');

  }else {
    $HTTP_ROBOTS = 'all';
    $NOINDEX = false;
  }

  return $HTTP_ERROR_CODE;
}
