<? // only canonical cross-project utilitiles from AK's toolbox.

// ============================
// LOGIC
// проверяем есть ли поле в массиве, и какое там булевское значение. 0 или N = FALSE, остальное считаем TRUE.
function bool_opt($arr, $field, $def = false) {
  return $def ?
    // TRUE by default
    !isset($arr[$field]) || ($arr[$field] && (strtoupper($arr[$field]) != 'N')) :
    // FALSE by default
    isset($arr[$field]) && $arr[$field] && (strtoupper($arr[$field]) != 'N');
}

// Есть ли индекс в массиве. Если нет –– берём по умолчанию. (Используется в сообщениях о привязке к компании.)
function ifset($arr, $index, $def = false) { // если $index нет, мы попробуем взять $def. Если $def нет или '' или 0, мы попробуем взять $arr['0'].
  return isset($arr[$index]) && $arr[$index] ? $arr[$index] :
      (isset($arr[$def]) ? $arr[$def] : $def);
}

function if0($val, $def) {
  return $val ? $val : $def;
}


// ============================
// DATA
function arr2list($q, $use_values = false, $sep = ',') { // use '","' if you need quoted values.
  return implode($sep, $use_values ? array_values($q) : array_keys($q));
}

function id_list($q, $id = '', $def = false, $allow0 = false, $sep = ',') {
  return ($id || ($id === '')) && ($q = id_array($q, $id, false, $allow0)) ? implode($sep, array_keys($q)) : $def; // set $def to 0 if you never want this list empty
}

function is_in_id_array($a, $id) {
  if (is_array($a))
    foreach ($a as $b => $c)
      if ($b == $id) return true;
  return false;
}

function chars_as_mysql_set($chars) {
  $r = '';
  for ($i=0; $i<strlen($chars); ++$i)
    $r.= '"'.$chars[$i].'",';
  return rtrim($r, ',');
}



// ============================
// STRINGS
function htmlquotes($s) {
  return str_replace('\'', '&apos;', // DO NOT set "&rsquo;" here! It’s apostroph, not right-single quote yet! We don't want to break the passwords!
    str_replace('"', '&quot;',
    str_replace('<', '&lt;', // for canonical html, to avoid errors in "hidden" input fields
    str_replace('>', '&gt;', $s))));
}

function nl1br($t) { // от многих смежных переносов строки, то останется лишь один <br />.
  return preg_replace("/(\r\n)+|(\n|\r)+/", '<br />', $t);
}

function br2nl($t) {
  return preg_replace('/<br\\s*?\/??>/i', "\n", $t);
}

function strip_nl($t) {
  return str_replace("\r", '', str_replace("\n", '', $t));
}

function is_valid_email($s) { // see also "cemail()" in "common.js". it should work better
  return filter_var($s, FILTER_VALIDATE_EMAIL); // it believes that cyrilic domains are invalid. But commented code below does the same.
  // return preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,12})$/i", $s); // the longest domain extension in 2016 was ".photography"
}

function fix_url($url) {
  if ((!$url = trim($url)) || strpos($url, ' ')) return false;
  $r = strtolower($url);
  if (($r == 'http://') || ($r == 'https://')) return ''; // trim is required here!

  if ((substr($r, 0, 7) != 'http://') && (substr($r, 0, 8) != 'https://') && $r[0] != '/')
    return 'http://'.$url;

  return preg_replace('/(http(s?):\/\/)+/', 'http\\2://', // something like http://http://
         preg_replace('/:\/\/+/', '://', $url));   // something like ":////"
}

function split_fl_name($name, &$first, &$last, $maxlen = 40) {
  if ($i = strrpos($name = trim($name), ' '))
    $last = substr($name, $i+1, $maxlen);
  else
    $last = '';
  $first = substr($name, 0, !$i || $i > $maxlen ? $maxlen : $i);
}

function str_replace_avoid_tags($search, $replace, $t) {
  if (strpos($t, $search) === false)
    return $t;

  if (strpos($t, '<') !== false) { // has html tags
    $search = str_replace('\'', '\\\'', $search);
    return preg_replace_callback('/((?<=^|>)([^><]+?)(?=<|$))/s',
      function($m) use($search, $replace) {
        return str_replace($search, $replace, $m[2]);
      }, $t);
  }
  return str_replace($search, $replace, $t);
}

/* amp_str() is replacement of "htmlspecialchars()".
   Can be replaced by "htmlspecialchars($s, ENT_QUOTES | ENT_HTML401, 'cp1251', false)". ("double_encode" should be FALSE!)
   However, "htmlspecialchars()" converts <,> to the &lt;,&gt; too.
 */
function amp_str($t, $html_quotes = false, $double_encode = false) {
  if ($html_quotes) // this is not the same as "htmlquotes()". It doesn't converts <,>  to &lt;,&gt;.
    $t = str_replace_avoid_tags('\'', '&apos;', // DO NOT set "&rsquo;" here! It’s apostroph, not right-single quote yet! We don’t want to break the passwords!
         str_replace_avoid_tags('"',  '&quot;', $t));

  if (strpos($t, '&') === false) return $t;
  if ($double_encode) return str_replace('&', '&amp;', $t); // for RSS
  return preg_replace('/&(?!([A-Za-z]+|#[0-9]+);)/', '&amp;', $t);
}

function leave_numbers($s, $allow_latin_chars = false) {
  return preg_replace('/[^0-9'.($allow_latin_chars ? 'A-Za-z' : '').']/', '', $s);
}

function my_number_format($n, $decimals = 2, /*	<0 returns price w/o cents if there's no cents. (Used in Flat Menu.)
						 Use -99 to just strip trailing 0's with 5 digits after floating point */
    $thousand_sep = false, $dec_point = false,
    $strip_trailing0 = false) {			// make it TRUE, if you want to use this function just like "round()" -- format to N digits, but then strip all unnecessary zeros at the end.

  if ($n === '') return ''; // AK 3.11.2018: мы всё-таки вернём "0,00" если значение 0.

  if ($decimals < 0)
    if ($decimals <= -99) {
      $decimals = 5;
      $strip_trailing0 = 1;
    }else
      $decimals = ($n * 100 % 100) ? abs($decimals) : 0; // целое ли число? без плавающей запятой при целом числе.

  if (!$dec_point) {
    global $S_DECIMALPOINT;
    $dec_point = $S_DECIMALPOINT;
  }

  $n = number_format($n, $decimals, $dec_point, $thousand_sep); // Floating point in English, Плавающая запятая по-русски & рухома кома українською.

  if ($strip_trailing0 && (strpos($n, $dec_point) !== false)) {
    $l = strlen($n);
    do {
      --$l;
      if ($n{$l} == $dec_point) {
        --$l;
        break;
      }
    }while ($n{$l} == '0');
    $n = substr($n, 0, $l+1);
  }
  return $n;
}



// ======================
// ESCAPING
function strip_tabs($s) {
  return str_replace('&#65279;', '',
         str_replace(chr(160), ' ',
         str_replace("\t", ' ',
         str_replace("\r", '', $s))));
}

function prepare_esc($s,
  $maxlen = false, $check_empty = false,
  $perfect_quotes = false, $notrim = false) {

  // strip all posted garbage
  $s = str_replace('&#65279;', '',
       str_replace(chr(160), ' ',
       str_replace("\t", ' ',          // We don't accept TAB characters with this function! Use custom functionality if you need TABs.
       str_replace("\r", '', $s))));

  if (!$notrim)
    $s = trim($s);

  if (strlen($s) == 0) return false;

  if ($perfect_quotes)
    $s = html_perfect_quotes($s, $perfect_quotes, false/*no need to trim, it’s done above*/);

  if ($check_empty && !trim(str_replace('&nbsp;', '', strip_tags($s)))) // test posting without tags.
    return false;

  if ($maxlen && (strlen($s) > $maxlen))
    return substr($s, 0, $maxlen-1);

  return $s;
}

// Warning! It requires mySQL connection!
function esc($s, $maxlen = false, $check_empty = false, $perfect_quotes = false, $notrim = false) {
  return mysql_real_escape_string(prepare_esc($s, $maxlen, $check_empty, $perfect_quotes, $notrim));
}

// esc() + strip_tags.
function notags($s, $maxlen = false, $perfect_quotes = false, $allowed_tags = false) {
  return esc(strip_tags($s, $allowed_tags), $maxlen, false, $perfect_quotes);
}

// same as notags(pvar(
function notags_post($var, $maxlen = false, $def = false, $perfect_quotes = false, $allowed_tags = false) {
  if ($r = pvar($var, $def)) // yes $def will be processed and escaped too.
    return notags($r, $maxlen, $perfect_quotes, $allowed_tags);
  return $r;
}

// It's notags_post(), but without mysql_real_escape(), which require mySQL. No default value here. If you need default, use if0().
function postv($var, $maxlen = false, $def = false, $perfect_quotes = false, $allowed_tags = false) {
  if ($r = pvar($var, $def)) // yes $def will be processed and escaped too.
    return prepare_esc(strip_tags($r, $allowed_tags), $maxlen, false, $perfect_quotes);
  return $r;
}

// POST/GET. See also pgvar() and pvar() in "uri.php"
function pvarq($var, $def = false, $perfect_quotes = false) {
  if ((!$r = pvar($var, $def)) || is_numeric($r))
    return $r;

  if ($perfect_quotes)
    $r = perfect_quotes($r);

  return htmlquotes($r); // htmlquotes() after the perfect_quotes().
}



// ======================
// CUTS...
function cut_tags($s, $tag) { // strip_tags() specifies allowed tags. Here we specify tags which should be removed.
  return preg_replace("/<\/?$tag(\s+?[^>]*?)?>/is", '', $s);
}

function cuta($t1, $t2, $t, $replace = false, $ignore_case = false, $limit = false) {
  return preg_replace('/'.preg_quote($t1, '/').'.*?'.preg_quote($t2, '/').'/s'.($ignore_case ? 'i' : ''), $replace, $t, $limit ? $limit : -1);
}

// This is legacy function from 2007. Should be rewritten from scratch.
function cutb($t1, $t2, $t, $replace = false, $once = false, $ignore_case = false, // warning! "stripos()" doesn't completely supports cyrillic!
   $replace_once = false, $moveto = false, $return_cut_data = false,
   $look_else = false, $bool_else = false,
   $highlight_embrace = false /*color*/, $highlight_hint = false /*text*/) {

  $cut_data = false;
  while ((($a = ($ignore_case ? stripos($t, $t1) : strpos($t, $t1))) !== false) && (!$t2 || (($b = ($ignore_case ? stripos($t, $t2, $a) : strpos($t, $t2, $a))) !== false))) {

    $t1l = strlen($t1);

    if ($look_else || $return_cut_data) {
      $cut_data = substr($t, $a + $t1l, $b - $a - $t1l);
      if ($has_else = ($look_else && (($i = strpos($cut_data, $look_else)) !== false)))  // has %ELSE...% between %IF...% and %ENDIF...%?
        $replace = substr($cut_data, $bool_else ? 0 : $i + strlen($look_else), $bool_else ? $i : strlen($cut_data));
      else
        $replace = $bool_else || $highlight_embrace ? $cut_data : false;

      if ($highlight_embrace)
        $replace = '<div style="border: 4px dotted '.($highlight_embrace ? $highlight_embrace : 'orange').';">'.
          ($highlight_hint ? '<div style="float: right; margin-left: 2px; padding: 6px; color: #FFF; background-color: '.($highlight_embrace ? $highlight_embrace : 'orange').';">'.$highlight_hint.($has_else ? '<div style="font-size:.8em;">(+else)</div>' : '').'</div>' : '').
          '<div style="padding: 4px 6px">'.$replace.'</div></div>';
    }

    if ($moveto) $movetext = substr($t, $a + $t1l, $b-$a-$t1l);
    $t = substr($t, 0, $a).$replace.substr($t, ($t2 ? $b+strlen($t2) : $a+$t1l));
    if ($once) break;
    if ($replace_once) $replace = false;
  }

  if ($moveto && isset($movetext))
    $t = str_replace($moveto, $movetext, $t);

  return $return_cut_data ? array($t, $cut_data) : $t;
}



// ============================
// DATE/TIME
function fixtime($time) {
  global $TIMEZONE;
  if (!$TIMEZONE) return $time;
  $server = date('O') / 100;
  return ($TIMEZONE == $server) ? $time : $time + (3600 * $TIMEZONE) - (3600 * $server);
}

function mytime($time = false) {
  global $TIMEZONE;

  if (!$time) $time = $_SERVER['REQUEST_TIME'];
  elseif ($time == 1) $time = time();

  if ($TIMEZONE) $time = fixtime($_SERVER['REQUEST_TIME']);

  return $time;
}

function quarter_by_month($m) { // see also "last_day_of_quarter()": mktime(0, 0, 0, floor($q*3), $q == 1 || $q == 4 ? 31 : 30)
  return ceil($m/3);
}

function quarter_roman($q) {
  static $QROMAN = array(1=>'I',2=>'II',3=>'III',4=>'IV');
  return $QROMAN[$q];
}

function current_year() {
  static $y;
  return $y > 0 ? $y : $y = (int)date('Y', mytime());
}

function mkdate_mmdd($s, $year = false) { // $s is raw date in "MMDD" or "YYYYMMDD" format.
  if (strlen($s) == 8) { // date already includes year
    $year = substr($s, 0, 4);
    $s = substr($s, 4);
  }elseif (!$year) {
    $year = current_year();
  }

  return mktime(0, 0, 0, substr($s, 0, 2), substr($s, 2, 2), $year);
}

function htmldate($f, $t, $plain_text = false) {
  $f = date($f, $t);
  if ($plain_text > 0)
    return $f;
  if ($plain_text < 0)
    $f = str_replace(' ', '&nbsp;', $f);
  return '<time datetime="'.date('Y-m-d\TH:i', $t).'">'.$f.'</time>';
}

function full_date_notime($t, $postfix='', $plain_text = false, $full_date = true, $html5 = true, $salt = false, $add_time_sep = false) {
  global $sfull_date, $sfull_time, $sshort_date, $smonths, $sdtsep;

  if (!$t) return false;

  $full_date = $full_date ? $sfull_date : $sshort_date;
  if ($plain_text)
    $full_date = str_replace('\&\n\b\s\p\;', ' ', $full_date);
  $r = sprintf(date($full_date, $t), $smonths[date('n', $t)]).$postfix;

  if ($add_time_sep !== false)
    $r.= $add_time_sep.date($sfull_time, $t);

  if ($html5 && $plain_text <= 0) {
    if ($plain_text < 0)
      $r = str_replace(' ', '&nbsp;', $r);
    $r = '<time datetime="'.date('Y-m-d\TH:i', $t).'"'.($salt ? ' '.$salt : '').'>'.$r.'</time>';
  }

  return $r;
}

function full_date_dow($t, $salt = false) {
  global $dweek, $S_YEARPOSTFIX;
  return '<time datetime="'.date('Y-m-d\TH:i', $t).'"'.($salt ? ' '.$salt : '').'>'.$dweek[date('w', $t)].', '.full_date_notime($t, $S_YEARPOSTFIX, false, 1, 0).'</time>';
}

function full_date($t, $postfix=false, $sep = false, $plain_text = false, $html5 = true) {
  return full_date_notime($t, $postfix, $plain_text, 1, $html5, false, $sep /*separator between date and time*/);
}

function date_noyear($t, $plain_text = false, $html5 = true) {
  return full_date_notime($t, '', $plain_text, false, $html5);
}

function age_by_date($y, $m = false, $d = false, $floor = false, $req_date = false) { // floor: if months is unknown, we getting minimal age, otherwise -- age that turns in specified year.
  if ($y == 0)
    return 0;

  $t = $req_date ? $req_date : mytime();
  $cy = date('Y', $t);
  $cm = date('n', $t);

  $age = $cy-$y;

  if (($m == 0) && $floor)
    --$age;
  elseif ($cm <= $m) {
    $cd = date('j', $t);
    if (($cm < $m) || ($cd < $d))
      --$age;
  }
  return $age;
}

function strhrsago($n) {
  global $shrago1, $shrago2, $shrago3, $timeago;
  while ($n >= 100) $n = $n % 100;
  $k = $n;
  while ($k > 10) $k = $k % 10;
  if (($n % 10 == 0) || (($n >= 10) && ($n < 21)) || ($k >= 5))
    return $n.' '.$shrago3.' '.$timeago;
  return $n.' '.(substr($n, strlen($n) - 1, 1) == 1 ? $shrago1 : $shrago2).' '.$timeago;
}

function last_log_plain($time, $showtime = 1, // 0 -- never, 1 -- always, 2 -- only for today and yesterday.
    $at = false,
    $dformat = false, $tformat = false, $online = 0,
    $justnow = false) { // "online" in minutes. not used if 0
  global $TIMEZONE, $sjustnow, $sat, $stoday, $syesterday, $sminago, $shrago1, $shrago2, $shrago3,
    $sdef_datef, /*$sdef_datef_noy,*/ $sdef_timef, $smonths;

  if ($time <= 0) return false;

  if (!$tformat) $tformat = $sdef_timef;
  if (!$at) $at = str_replace('&nbsp;', ' ', $sat);
  if (!$justnow) $justnow = $sjustnow;

  $time  = fixtime($time);
  $ctime = mytime(1); // always get CURRENT time here. Otherwise we can display some future date for record posted after $_SERVER['REQUEST_TIME'].
  $day = 86400; // 24 * 60 * 60;
  $online *= 60;

  if (($online != 0) && (($time + $online > $ctime) && ($time < $ctime + $online)))
    return $justnow;

  $stime = $showtime ? $at.date($tformat, $time) : '';

  if (($TIMEZONE !== false) && ($time <= $ctime) && // not the future
      ($time > $ctime - $day * 3)) { // not earlier than 72 hours ago

    if (($time + 60) > $ctime) // less than minute ago
//      return floor($ctime - $time).' sec ago';
      return $justnow;
    if (($time + 60 * 60) > $ctime) // less than hour ago
      return floor(($ctime - $time) / 60).$sminago;
    if (($time + 60 * 60 * 7) > $ctime) { // less than 7 hours ago
      $i = floor(($ctime - $time) / (60 * 60));
      return strhrsago($i);
    }

    $d = date('j', $time);
    if ($d == date('j', $ctime)) // today
      return $stoday.$stime;
    if ($d == date('d', $ctime - $day)) // yesterday
      return $syesterday.$stime;
  }

  $fulldate = $dformat == 2;// || $dformat == 3;
  if (!$dformat || $fulldate) $dformat = /*$fulldate && $dformat == 3 ? $sdef_datef_noy :*/ $sdef_datef;
  return sprintf(date($dformat, $time), $fulldate ? $smonths[date('n', $time)] : substr($smonths[date('n', $time)], 0, 3)).($showtime == 2 ? '' : $stime);
}

function last_log($time, $showtime = 1, $at = false, $dformat = false, $tformat = false, $online = 0, $justnow = false) {
  if (($i = last_log_plain($time, $showtime, $at, $dformat, $tformat, $online, $justnow)) == $justnow)
    return $i;
  return '<time datetime="'.date('Y-m-d\TH:i').'"'.(is_numeric($i) ? '' : ' class="nobr"').'>'.$i.'</time>';
}



// ============================
// IP
function IPv4To6($ip) {
  static $mask = '::ffff:'; // This tells IPv6 it has an IPv4 address
  $IPv6 = (strpos($ip, '::') === 0);
  $IPv4 = (strpos($ip, '.') > 0);

  if (!$IPv4 && !$IPv6) return false;
  if ($IPv6 && $IPv4) $ip = substr($ip, strrpos($ip, ':')+1); // Strip IPv4 Compatibility notation
  elseif (!$IPv4) return $ip; // Seems to be IPv6 already?

  $ip = array_pad(explode('.', $ip), 4, 0);
  if (count($ip) > 4) return false;
  for ($i=0; $i<4; ++$i) if ($ip[$i] > 255) return false;

  $P7 = base_convert(($ip[0] * 256) + $ip[1], 10, 16);
  $P8 = base_convert(($ip[2] * 256) + $ip[3], 10, 16);
  return $mask.$P7.':'.$P8;
}

function ExpandIPv6Notation($ip) {
  if (strpos($ip, '::') !== false)
    $ip = str_replace('::', str_repeat(':0', 8 - substr_count($ip, ':')).':', $ip);
  if (strpos($ip, ':') === 0) $ip = '0'.$ip;
  return $ip;
}

function IPv6ToLong($ip, $dbparts = 2) {
  $ip = ExpandIPv6Notation($ip);
  $parts = explode(':', $ip);
  $ip = array('', '');
  for ($i=0; $i<4; ++$i)
    $ip[0].= str_pad(base_convert($parts[$i], 16, 2), 16, 0, STR_PAD_LEFT);
  for ($i=4; $i<8; ++$i)
    $ip[1].= str_pad(base_convert($parts[$i], 16, 2), 16, 0, STR_PAD_LEFT);

  return ($dbparts == 2) ?
    array(base_convert($ip[0], 2, 10), base_convert($ip[1], 2, 10)) :
    base_convert($ip[0], 2, 10) + base_convert($ip[1], 2, 10);
}

function get_real_ip($ipv6 = false, $fordb = false, $dbparts = 2) {
  $ip = false;

  if (isset($_SERVER['HTTP_CLIENT_IP']) && ($_SERVER['HTTP_CLIENT_IP']{0} != 'u') && (strlen($_SERVER['HTTP_CLIENT_IP']) > 4))
    $ip = $_SERVER['HTTP_CLIENT_IP'];
  elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && ($_SERVER['HTTP_X_FORWARDED_FOR']{0} != 'u') &&
         ($ip = $_SERVER['HTTP_X_FORWARDED_FOR']) && (strlen($ip) > 4) &&
         ($ip = trim(preg_replace('/^(.*?),/', '', $_SERVER['HTTP_X_FORWARDED_FOR']))) && (strlen($ip) > 4)) {
    // ok. Remember of IPs like '192.168.0.108, 193.84.72.90'
  }else
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 0;

  if ($ip) {
    if (($p = strpos($ip, ',')) > 0)
      $ip = substr($ip, 0, $p-1);
  }else
    $ip = '0.0.0.0';

  if ($ipv6) {
    $ip = IPv4To6($ip);
    return $fordb ? IPv6ToLong($ip, $dbparts) : $ip;
  }
  return $ip;
}

function ip2dec($ip = false) {
  if (!$i = $ip ? $ip : get_real_ip())
    return 0;

  if ($r = ip2long($i)) {
    if ($r > 2147483647)
      $r-= 4294967296;
    return $r; // ok
  }

  if (!isset($_SERVER['REMOTE_ADDR']) || ($i == $_SERVER['REMOTE_ADDR'])) return 0; // probably this is the crontab or bad IP.
  return $ip ? 0 : ip2dec($_SERVER['REMOTE_ADDR']); // recursive 2nd try
}




// =======================
// DEBUG
function gen_debug_backtrace() {
  $out = false;
  if ($trace = debug_backtrace())
    foreach ($trace as $trace_key => $trace_val)
      if (is_array($trace_val)) {
        foreach ($trace_val as $func_key => $func_val)
          if (is_array($func_val)) {
            foreach ($func_val as $param_key => $param_val)
              $out.= "$trace_key.$func_key.$param_key: ".(is_array($param_val) ? '<i>array</i>' : $param_val)."<br />\n";
          }else {
            if ($func_key == 'function' || $func_key == 'line')
              $func_val = "<b>$func_val</b>";
            $out.= "$trace_key.$func_key: $func_val<br />\n";
          }
      }else
        $out.= "$trace_key: $trace_val<br />\n";
  return $out;
}
