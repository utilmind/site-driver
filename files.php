<?
function add_trailing_slash($d) {
  // return rtrim($d, '/').'/';
  // if (substr($d, -1) != '/') $d.= '/';
  return (!$l = strlen($d)) || ($d[$l-1] != '/') ? $d.'/' : $d;
}

function cache_dname($i) {
  return floor($i / 1000) * 1000;
}

function safe_path($path) {
  if (!strpos($path, '"') && (strpos($path, ' ') || strpos($path, '/'))) // no quotes + spaces or /
    $path = '"'.$path.'"';
  return $path;
}

/* Ещё была read_file($fn), обёртка file_get_contents(). Но я не увидел никаких преимуществ юза функи, юзающей функу.
   Это другое. gz_read_file() читает и обычный файл и .gz при необходимости, если нет обычного. Но обычный первоочередной.
   - Возвращает FALSE если файл не найден или не удалось прочитать. Не "", а именно FALSE.

   ВАЖНО. Функа юзает file_get_contents. Если на хостинге закрыт доступ к внешним URL'ам, ini_get('allow_url_fopen')==FALSE, то для внешних файлов стоит юзать curl_init, как в TCPPDF.
 */
function gz_read_file($fn,
    $check_gz = true) { // 2 = unpack .gz into plain file and keep it to disk. Works only for local filenames.

  // If this is local file, check imediately, maybe it's hidden .gzip?
  if ($is_local = is_local_url($fn)) {
    if (($is_gzip = !file_exists($fn)) &&
        (!$check_gz || !file_exists($gz = $fn.'.gz')))
      return false;
  }else {
    $is_gzip = false;
    // add protocol name to incomplete URL
    if ($fn[0] == '/' && $fn[1] == '/') {
      global $site_proto;
      $fn = ($site_proto ? $site_proto : 'http:').$fn;
    }
  }

  /* @ здесь возможно временная заглушка.
     Чтобы при экспорте PDF'ов в файле не появились сообщения о PHP-ошибках 404, если в PDF подтягивается картинка по битой ссылке.
   */

  $opts = array(
    'http' => array(
      'header' => "Accept-Encoding: gzip, deflate\r\n", // gzip apreciated
    )
  );

  if (!$data = @file_get_contents($is_gzip ? $gz : $fn, false, stream_context_create($opts)))
    return false; // FAILED to read/download file.

  if (!$is_local && isset($http_response_header)) // check, maybe it's gzipped contents?
    foreach ($http_response_header as $a)
      if ($a) {
        $a = explode(':', strtolower($a), 2);
        if (isset($a[1]) &&
            (trim($a[1]) == 'gzip') &&
            (trim($a[0]) == 'content-encoding')) {
          $is_gzip = 1;
          break;
        }
      }

  if ($is_gzip) {
    if (!$data = @gzdecode($data)) // same as gzinflate(substr($data, 10, -8)))
      return false; // FAILED to gunzip.
    if ($is_local && ($check_gz === 2)) // keep unpacked file in disk. BUT WATCH DIRECTORY PERMISSION!
      write_file($fn, $data);
  }

  return $data;
}

function write_file($fn, $s, $mode = 'w') { // set mode to 'a' for append
  mkdirr(dirname($fn)); // unfortunately standard mkdir() doesn't creates correct permission
  if ($f = fopen($fn, $mode)) { // remember about directory permissions in PHP's safe mode!
    flock($f, LOCK_EX);
    $r = fwrite($f, $s);
    flock($f, LOCK_UN);
    fclose($f);
    @chmod($fn, 0777);
    return $r == strlen($s) ? $r : false;
  }
  return false;
}

function dbdata_path($id, $altdir = false) {
  global $filedata_dir, $filedata_split;
  return ($altdir ? $altdir : $filedata_dir.($id > $filedata_split ? '2' : '')).'/'.cache_dname($id).'/'.$id;
}

function dbdata_size($id) {
  return ($s = @filesize(dbdata_path($id))) ? $s : false;
}

function dbdata_delete($id) {
  @unlink(dbdata_path($id));
}

function dbdata_read($id) {
  return file_get_contents(dbdata_path($id)); // здесь нам не надо читать .gz. Мы же читаем здесь оригиналы из файлового хранилища, а не кеш.
}

function dbdata_write($id, $data) {
  return write_file(dbdata_path($id), $data);
}

function dbdata_copy($src, $target, $move = false, $no_overwrite = false) { // if move == 2 -- move_uploaded(..). Returns target file name
  if ($target) {
    if (is_numeric($src)) $src = dbdata_path($src);
    if (is_numeric($target)) $target = dbdata_path($target);
    mkdirr(dirname($target));

    if ($move === 2){
      if (@move_uploaded_file($src, $target)) {
        @chmod($target, 0666);
        return $target;
      }
    }elseif (file_exists($src)) {
      if ($move) {
        if (@rename($src, $target)) { // will be overwriten even if target exists
          @chmod($target, 0666);
          return $target;
        }
      }else
        return (file_exists($target) ?
          (($d = file_get_contents($src)) && write_file($target, $d)) : @copy($src, $target)) ? $target : false;
    }
  }
  return false;
}

function dbdata_wh($id, &$width, &$height) {
  @list($width, $height, $t) = getimagesize(dbdata_path($id));
  return $t;
}

/* !! WARNING: Use it carefully and only for directories with temporary files.
   In case if you don't plan to delete files inside with crontab -- use mkdir() intead.
*/
function mkdirr($dir, $mode = 0777) { // recursive mkdir. Unfortunately mkdir doesn't set correct directory permissions.
  if (!is_dir($dir)) {
    $m = umask(0); // save rights
    $r = @mkdir($dir, $mode, 1);
    umask($m); // restore rights
    return $r;
  }
  return true; // it's already there
}

function rmdirr($dir, $expire_time = false /* in minutes */, $recreate = false, $mode = 0777, $subdirs_only = false) { // if $expire_time specified, only files created N seconds ago will be deleted
  if (strlen($dir) < 6) // AK: !!WARNING!! I have killed the half of my HDD 7.sep.2012 and hardly restored with "FreeUndelete" utility!
    myexit('Dude, I just saved your life!');

  if (file_exists(add_trailing_slash($dir))) {
    $pass = false;
    if ($objs = scandir($dir))
      foreach($objs as $obj) {
        if ($obj === '.' || $obj === '..') continue;
        $o = $dir.$obj;
        if ($expire_time && (@filemtime($o) > time() - $expire_time * 60)) { // AK. До 23.07.2017 было filectime(). Но это затрудняло тестирование.
          $pass = 1;
          continue;
        }
        if (is_dir($o))
          rmdirr($o, $expire_time, false);
        elseif (!$subdirs_only)
          @unlink($o);
      }
    if (!$pass)
      @rmdir($dir);
  }

  if ($recreate)
    mkdirr($dir, $mode);

  return $dir;
}

function rmdirr_lang($dir, $subdirs_only = false) {
  rmdirr(ROOT.$dir, false, false, 0777, $subdirs_only);
  rmdirr(ROOT.'/ru'.$dir, false, false, 0777, $subdirs_only);
  rmdirr(ROOT.'/en'.$dir, false, false, 0777, $subdirs_only);
}

function unlink_lang($fn, $wildcard = false) {
  if ($wildcard) {
    unlink_wild(ROOT.$fn);
    unlink_wild(ROOT.'/ru'.$fn);
    unlink_wild(ROOT.'/en'.$fn);
  }else {
    @unlink(ROOT.$fn);
    @unlink(ROOT.'/ru'.$fn);
    @unlink(ROOT.'/en'.$fn);
  }
}

function unlink_wild($fn) {
  if ($fn = glob($fn))
    foreach($fn as $f) @unlink($f);
}

// creates temporary directory (recursively create the full path) and clears all expired content inside specified directory
function mktempdir($dir, $expire_time = false /* in minutes */, $prefix = '', $mode = 0777) {
  global $temp_dir;
  if (!$temp_dir) die('Unable to locate temporary directory.');
  $dir = $temp_dir.'/'.$dir;

  if ($expire_time) // remove expired files and sub-directories
    rmdirr($dir, $expire_time, true);

  $dir = add_trailing_slash($dir);
  do{
    $path = $dir.$prefix.mt_rand(0, 9999999);
  }while (!mkdirr($path, $mode)); // ok here.

  if (!is_dir($path)) die('Unable to create temporary directory.');
  return $path;
}

function mktempfile($dir = false, $prefix = false, $expire_time = 20 /* in minutes */, $mode = 0777) {
  global $temp_dir;
  if (!$temp_dir) die('Unable to locate temporary directory.');
  $dir = $temp_dir.'/'.$dir;

  if ($expire_time)
    rmdirr($dir, $expire_time, true); // always create
  else
    mkdirr($dir, $mode);
  return tempnam($dir, $prefix);
}

function get_uploaded_file($fld, $max_size = false) {
  return isset($_FILES[$fld]) && ($_FILES[$fld]['size'] > 0) && ($_FILES[$fld]['error'] == 0) && (!$max_size || ($_FILES[$fld]['size'] <= $max_size)) ? mysql_real_escape_string(file_get_contents($_FILES[$fld]['tmp_name'])) : false;
}

function filesizeinfo($fs, $nbsp = true) {
  global $lang;
  $iec = ($ruua = ($lang == 'ru') || ($lang == 'ua')) ?
	array('байт', 'Кб', 'Мб', 'Гб', 'Тб', 'Пб', ($lang == 'ua' ? 'Еб' : 'Эб'), 'Зб', 'Йб') :
	array('byte', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb', 'Eb', 'Zb', 'Yb');
  $i = 0;
  while (($fs/1024) >= 1){
    $fs /= 1024;
    ++$i;
  }

  $str = $iec[$i];
  if ($i == 0) {
    if (!function_exists('get_number_declansion_shorter'))
      require(ROOT.'/inc/utils/declansion.php');
    $str.= get_number_declansion_shorter($fs, false, 1);
  }

  return round($fs, 1).($nbsp ? '&nbsp;' : ' ').$str;
}

function outload($fn, $content_type = false, $allow_cache = true, $outfilename = false, $plain = false) {
  global $dblink;
  if ($dblink) @mysql_close($dblink); // close dblink

  if (!file_exists($fn)) {
    http_response_code(404);
    print '<h1>404 Not Found</h1>';
    exit;
  }

  if (!$fh = @fopen($fn, 'rb')) {
    http_response_code(403);
    print '<h1>403 Forbidden</h1>';
    exit;
  }

  if (!$outfilename) $outfilename = mybasename($fn);
  $bufsize = 20480;
  $fsize = filesize($fn);
  $is_partial = isset($_SERVER['HTTP_RANGE']);

  if ($is_partial) // Partial download
    if (preg_match("/^bytes=(\\d+)-(\\d*)$/", $_SERVER['HTTP_RANGE'], $matches)) { // parsing Range header
      $from = $matches[1];
      $to = $matches[2];
      if (empty($to))
        $to = $fsize;
      http_response_code(206); // Partial Content
      header("Content-Range: bytes $from-$to/".$fsize);
      $fsize = $to-$from;
    }else {
      @fclose($fh);
      http_response_code(500);
      print '<h1>500 Internal Server Error</h1>';
      exit;
    }
  else
    http_response_code(200);

  if (ini_get('zlib.output_compression'))
    ini_set('zlib.output_compression', 'Off');

  // determinate MIME type
  if (!$content_type) {
    if (!function_exists('filename2mime'))
      require(ROOT.'/inc/utils/mime.php');
    $content_type = filename2mime($outfilename);
  }

  // HEADERS
  header('Accept-Ranges: bytes');
  header('Content-Type: '.$content_type);
  header('Content-Length: '.$fsize);
  if (!$plain)
    header("Content-Disposition: attachment; filename=\"$outfilename\"");
  header('Content-Transfer-Encoding: binary');
  if ($allow_cache)
    header('Last-Modified: '.date('r', filemtime($fn)));
  else {
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: '.date('r'));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
  }

  // CONTENT
  if ($is_partial)
    fseek($fh, $from);
  while ($buf = fread($fh, $bufsize))
    print $buf;

  @fclose($fh);
  exit; // dblink already closed
}

function port_by_scheme($scheme) {
  switch ($scheme) {
    case 'http': return 80;
    case 'https': return 443;
    case 'ftp': return 21;
    case 'ftps': return 990;
  }
  return false;
}

function url_exists($url) {
  if (!$url) return false;

  global $IDN, $utfConverter;
  if (is_null($IDN)) { // AK 25.09.2016 - support of cyrillic domains, like "http://золотой-сфинкс.com".
    require_once(ROOT.'/inc/utils/idna-convert/UnicodeTranscoderInterface.php');
    require_once(ROOT.'/inc/utils/idna-convert/UnicodeTranscoder.php');
    require_once(ROOT.'/inc/utils/idna-convert/NamePrepDataInterface.php');
    require_once(ROOT.'/inc/utils/idna-convert/NamePrepData.php');
    require_once(ROOT.'/inc/utils/idna-convert/PunycodeInterface.php');
    require_once(ROOT.'/inc/utils/idna-convert/Punycode.php');
    require_once(ROOT.'/inc/utils/idna-convert/IdnaConvert.php');
    require_once(ROOT.'/inc/utils/utf8/utf8.class.php');
    $IDN = new IdnaConvert();
  }
  try {
    $url = $IDN->encode($utfConverter->StrToUTF8($url));
  }catch(Exception $e) {
    return false;
  }

  if ((!$a_url = @parse_url($url)) || !isset($a_url['scheme'])) return false; // bad url
  if (!isset($a_url['port']) && (!$a_url['port'] = port_by_scheme($a_url['scheme']))) return false; // unknown protocol

  if (isset($a_url['host'])) {// && ($a_url['host']!=@gethostbyname($a_url['host']))) {
    if (!$fid = @fsockopen(($a_url['scheme'] == 'https' ? 'ssl://' : '').$a_url['host'], $a_url['port'], $errno, $errstr, 30))
      return false; // host unreachable. See "errno"

    $page = (isset($a_url['path']) ? $a_url['path'] : '').
            (isset($a_url['query']) ? '?'.$a_url['query'] : '');
    if (!$page || ($page{0} == '?')) $page = '/'.$page;


    $req = "GET $page HTTP/1.1\r\n".
	"Host: $a_url[host]\r\n".
	"User-Agent: Mozilla/5.0 Firefox/3.6.12\r\n".
	"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*"."/*;q=0.8\r\n".
	"Accept-Language: en-us,en;q=0.5\r\n".
	"Accept-Encoding: deflate\r\n".
	"Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7\r\n".
	"Connection: close\r\n".
	"\r\n";

    fputs($fid, $req); // HEAD doesn't works with Wikipedia. ("HEAD $page HTTP/1.0\r\nConnection: close\r\nHost: $a_url[host]\r\n\r\n");
    $head = fread($fid, 80); // 4096

    fclose($fid);

    if (preg_match('/^HTTP\/\S*\s+([0-9]+)\s/i', $head, $m) && isset($m[1]) && $m[1])
      return $m[1];

    return 1; // no HTTP header?
  }
  return false; // bad url
}

function is_bad_url($code) {
  return $code == 1 || $code == 200 || $code >= 301 || $code <= 303 ? 'N' : 'Y';
}

function mybasename($f) {
  return ($p = strrpos($f, '/')) === false ? $f : substr($f, $p+1); // todo: test in Windows with backslash path.
}

function file_ext($fn, $nodot = false, $keep_case = false, $last_ext = false) { // lowercase extension by default. But you may set $keep_case to TRUE.
  if (!$fn = mybasename($fn))
    return false;

  if (($i = strpos($fn, '?')) !== false) // strip everything after "?" sign
    $fn = substr($fn, 0, $i);

  if (($i = strrpos($fn, '.')) === false)
    return false; // no dot, no extension

  // AK 14.08.2018: EXT.gz kludge.
  $lext = strtolower($ext = substr($fn, $i+1));
  if (!$last_ext && ($lext == 'gz') &&
       ($i = file_ext(substr($fn, 0, $i), $nodot, $keep_case))) // find another dot prior to "gz".
    return $i.'.'.($keep_case ? $ext : $lext);

  return ($nodot ? '' : '.').($keep_case ? $ext : $lext);
}

function basename_noext($f) {
  return ($ext = file_ext($f = mybasename($f))) ? substr($f, 0, -strlen($ext)) : $f;
}

function strip_file_ext($f) { // strips file extension preserving the path
  return ((($d = dirname($f)) && $d != '.') ? $d.'/' : '').basename_noext($f); // главное чтобы не добавился путь ./ если пути нет.
}

function win1251cp866($t) { // Support of cyrillic on Linux file system.
  if (stristr(PHP_OS, 'WIN')) return $t; // don't need to convert it on Windows.

  // fixing for Ukrainian "Ii" and quotes.
  $t = str_replace('і', 'i',
       str_replace('І', 'I',
       str_replace('"', '', // quotes can't be unzipped with correct path :(
       str_replace('«', '',
       str_replace('»', '',
       $t)))));
  return iconv('Windows-1251', 'cp866', $t);
}