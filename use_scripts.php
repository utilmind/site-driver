<?
/* Предполагалось, что дальнейшие скрипты нигде не нужны, кроме формирования первичной страницы. И что они типа не должны использоваться для AJAX'ов.
   Однако, практика показала, что мы генерим контент и запрашиваем внешние скрипты уже по ходу дела.
   Например, при вызове popup'ов, в тексте которых есть какой-то динамический код, медаль, например.
   В конце концов у нас комменты вида <!--use_scripts(--> парсятся с функой use_cached_scripts() независ независимо от того, AJAX это или нет.

   К сожалению, конструкция add_script пока не поддерживает ивенты onload, типа <script src="..." onload="...">. Это при необходимости нужно прописывать inline'ами.

   Мои исследованя async/defer, порядка их вызова:
     https://www.favor.com.ua/en/blogs/30160.html
   Наглядный пример загрузки: https://www.growingwiththeweb.com/2014/02/async-vs-defer-attributes.html
   То же, но по-русски тут: https://habr.com/post/323790/ -- здесь прямо рекомендуется самодостаточные скрипты, не зависящие от выполнения других выполнять с одним лишь async.
   Ещё:
     * https://alligator.io/html/defer-async/ говорит, что defer достаточно без async.
     * https://stackoverflow.com/questions/50615101/what-does-async-defer-do-when-used-together -- здесь разъяснили кратко по делу, в т.ч. об игнорировании defer/async в <body> 8-/
     * https://github.com/mattbasta/client-performance-handbook/blob/master/06-javascript/defer%2C_async%2C_both%2C_neither.md -- тоже толково

   Возможно TODO. Указывать приоритет для add_script(), чтобы обозначить порядок загрузки. Или указывать на имя связанного скрипта. Типа скрипт "B" должен выполняться только после скрипта "A".
 */
// --- for inline_script().
define('HEAD_META',	8);
define('HEAD_JS_INLINE',7);
define('BODY_JS_INLINE',6); // Не маньяч. Если знаешь, или даже допускаешь, что скрипт не попадает в этот раздел, то пиши простой <script>...
define('BODY_JS_END',   5); // сюда автоматически идут все async/defer-скрипты после нечала раздела <body>. Мы показыва
// --- for add_script().
define('HEAD_CSS',	4); // когда headers_sent(), это всё превращается в BODY_CSS / BODY_JS[_ASYNC].
define('HEAD_JS_ASYNC', 3);
define('HEAD_JS_DEFER', 2); // both async & defer
define('HEAD_JS',	1);
function inline_script($sect, $t,
    $priority = false, // max(higher)..-min(lower)
    $token = false) { // specified only by add_script(). We should be able to remove the script from queue by this token
  global $_SCRIPTS, $HEAD_FLUSHED;
  if (!$priority) $priority = 0;
  $t.= "\n";
  $_SCRIPTS[$sect][$priority][] = $token ? array($t, $token) : $t;

  if ($HEAD_FLUSHED) { // Если секция <head> уже закрыта — возвращаем прямым текстом в <body>. Вызывающая функа должна print'ануть результат.
    if ($sect == HEAD_JS_INLINE || $sect == BODY_JS_INLINE) {
      $t = <<<END
<script>
// <![CDATA[
$t
// ]]>
</script>

END;
    }
    return $t;
  }
}

function fix_local_script_url($sect, $url) {
  global $cdn_url;
  return $cdn_url.($url[0] == '/' ? '' : ($sect == HEAD_CSS ? '/css/' : '/js/')).$url;
}

function add_script($sect, // this func should be used only in 3 cases of $sect: HEAD_CSS, HEAD_JS_ASYNC or HEAD_JS. It should not be used for inline code.
    $t,
    $priority = false,    // max(higher)..-min(lower). External scripts already have +100 level priority (because we want to group local scripts)
    $async = false,       // Legacy and odd. Same as HEAD_JS_ASYNC. -1 = async + defer.
    $force_use = false) { // Some scripts can duplicate and usually we don't want them. So "//apis.google.com/js/platform.js" for us is the same as "//apis.google.com/js/platform.js?onload=XXX".
                          // However if we really want to use some script and don't want to check whether it already used — set $force_use to TRUE.
                          // 1 note although... If we notice 2 the same script calls, 1 with parameters and 2nd without, we will prefer to use that which HAVE some parameters. TODO: make priority levels?
  if (!$t = trim($t)) return;
  global $USED_SCRIPTS, // $USED_SCRIPTS is GLOBAL! We don't mind to check it outside of this function to see what is used.
    $HEAD_FLUSHED;

  /* Позволен такой формат для $t:
       'https://vk.com/js/api/share.js?94 charset="windows-1251"'
     или так:
       'myscript.js defer'
     В обоих случаях, извне или из дефолтной директории /js/ берётся скрипт, а после пробела прописывается параметр.
     В принципе можно указывать в качестве параметра и "async", но мы ведь кучкуем ассинхронные скрипты вместе, чтобы mod_pagespeed скомпоновал не-ассинхронные воедино.
     UPD 18.08.2018: defer прописывать нет смысла с появлением параметра HEAD_JS_DEFER. Но сами дополнительные параметры пока не убираем, из-за возможности указания того же charset'а.
   */
  if ($i = strpos($t, ' ')) {
    $salt = substr($t, $i);
    $t = substr($t, 0, $i);
  }else
    $salt = '';

  if (($i = strpos($t, '?')) !== false) { // only filename without paramters after "?".
    $params = substr($t, $i); // including "?"
    $t = substr($t, 0, $i);
  }else
    $params = '';

  // Нам нужно сравнивать именно конечный URL. Потому что некоторые ссылки могут быть уже полностью собраны, некоторые без пути, но всё запускать их нужно по 1 разу.
  if ($is_local = is_local_url($t))
    $t = fix_local_script_url($sect, $t);
  else
    $priority+= 100; // external scripts is higher priority than locals

  if (!$force_use && isset($USED_SCRIPTS[$t])) // already seen before
    if (!$HEAD_FLUSHED && (($tmp_sect = $USED_SCRIPTS[$t][0]) > $sect)) { // but if new script have higher priority -- leave it
      global $_SCRIPTS;
      foreach ($_SCRIPTS[$tmp_sect] as $p_idx => $priorities)
        foreach ($priorities as $l_idx => $l)
          if ($l[1] == $t) {
            unset($_SCRIPTS[$tmp_sect][$p_idx][$l_idx]); // cut the script
            break;
          }
    }elseif (!$params || ($USED_SCRIPTS[$t][1] != '')) // THIS script has no params, just skip it.
      return; // already used. Don't duplicate.
      // But if we noticed this script without parameters -- let's go, put it to queue, override previous.

  $USED_SCRIPTS[$script_url = $t] = array($sect, $params);
  if ($params)
    $t.= $params; // reintroduce params

  if ($sect == HEAD_CSS) {
    $t = '<link rel="stylesheet" type="text/css" href="'.$t.'"'.$salt.' />';
    /* Теоретически можно было ввести понятие ассинхронных CSSок. Как я сделал с Gismeteo, по мотивам https://www.filamentgroup.com/lab/async-css.html
         <link rel="preload" as="style" href="url.css" onload="this.rel='stylesheet'" />
         <noscript><link rel="stylesheet" type="text/css" href="url.css" /></noscript>
       Но я решил пока не делать. Все некритичные CSSки грузятся лишь зареганым юзерам, а там скорость не так важна.
     */
  }else {
    if ($sect == HEAD_JS_ASYNC)
      $async = 1;
    elseif ($sect == HEAD_JS_DEFER)
      $async = -1;
    elseif (($sect == HEAD_JS) && $async)
      $sect = $async > 0 ? HEAD_JS_ASYNC : HEAD_JS_DEFER;
    if ($async) // пусть эта директива будет даже если мы в <body>
      $salt.= $async < 0 ? ' defer' : ' async';
    $t = '<script src="'.$t.'"'.$salt.'></script>';
  }

  if ($HEAD_FLUSHED) {
    /* async/defer is useless if they're outside of <head> section. We don't need them in <body>.
       Proof:
         * https://stackoverflow.com/questions/50615101/what-does-async-defer-do-when-used-together
         * https://flaviocopes.com/javascript-async-defer/

       AK 6.10.18: Я не согласен с этим. Как минимум defer отрабатывается в Хроме чётко, скрипт выполняется по завершению документа.
       Однако, gtmetrix сообщает о блокировке рендеринга из-за выполнения скрипта в body (и предлагает прописать код inline'ом). Независимо от того async или defer.
       Да, можно бы попытаться менять async на defer в <body> (так реально оптимальнее, как минимум не блокируется контент из-за выполнения).
       Но точно надёжнее запустить всё вконце.

       So if this is regular, NON-ASYNC script we should return it imediately. (And caller function should print the result.)
       If this is asynchronous script (no matter anymore, async or defer), we should invoke it as in the very bottom of your <body>.
     */
    if ($async)
      $sect = BODY_JS_END;
    else
      return $t."\n";
  }

  // $t.= '<!--~priority: '.(int)$priority.($is_local ? ' (local)' : ' (EXTERNAL)').'-->';

  // всё что дальше — только HEAD-секция. Там не бывает BODY.
  inline_script($sect, $t, $priority, $script_url);
}

function add_scripts($sect, $t, $priority = false, $async = false) {
  if (strpos($t, ',') !== false) {
    $t = explode(',', $t);
    $r = '';
    foreach ($t as $v)
      $r.= add_script($sect, $v, $priority, $async);
    return $r;
  }
  return add_script($sect, $t, $priority, $async);
}

function list_scripts($sect) {
  global $_SCRIPTS;
  if (isset($_SCRIPTS[$sect])) {
    // if (count($_SCRIPTS[$sect]) > 0)
    krsort($_SCRIPTS[$sect]); // sort by priority
    foreach ($_SCRIPTS[$sect] as $p)
      foreach ($p as $l)
        print is_array($l) ? $l[0] : $l;
  }
}

/* Некоторые куски кода, вместе со скриптами, могут попадать в кеш. Точнее, не попадать, из-за того что они попадают в очередь $_SCRIPTS,
   без упоминания о необходимости задействовать их в закешированном куске кода. Это сигнал. Вывести скрипт НЕМЕДЛЕННО в виде комментария.
   Обработчик кешированного куска кода найдёт этот комментарий и поставит в текущую, динамическую очередь $_SCRIPTS.
 */
function add_cached_scripts($sect, $t) {
  return "<!--use_scripts($sect, '$t')-->";
}

function use_cached_scripts(&$t) {
  if ((strpos($t, '<!--use_scripts(') !== false) &&
      preg_match('/<!--use_scripts\((\d+),\s\'(.*)\'\)-->/', $t, $q) && isset($q[2])) {
    // Меняем коммент на вызов скрипта. Всё ок, 2 раза один скрипт использоваться не будет. Если был, коммент будет просто вырезан.
    $t = str_replace($q[0], add_scripts($q[1], $q[2]), $t);
  }
}

// dynamic load of scripts
function ajax_inline_load_callback($url, $onLoadJSFunc) {
  static $js_func_done;

  // setup JS-function
  if ($js_func_done)
    $out = '';
  else {
    $js_func_done = true;
    $out = <<<END
function loadJS(url, callback) {
  var script = document.createElement("script")
  script.type = "text/javascript";
  if (script.readyState) { // only required for IE <9
    script.onreadystatechange = function() {
      if (script.readyState === "loaded" || script.readyState === "complete") {
        script.onreadystatechange = null;
        callback();
      }
    };
  }else { // modern browsers
    script.onload = function() {
      callback();
    };
  }

  script.src = url;
  document.getElementsByTagName("head")[0].appendChild(script);
}

END;
  }

  $out.= <<<END
loadJS('$url', $onLoadJSFunc);

END;

  inline_script(HEAD_JS_INLINE, $out);
}
