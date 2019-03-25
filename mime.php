<?
require_once('../defs.php');
require_once(ROOT.'/inc/utils/files.php');

function filename2mime($fn) {
  static $mime_types = array(
	'txt'	=> 'text/plain',
	'text'	=> 'text/plain',
	'ini'	=> 'text/plain',
	'conf'	=> 'text/plain',
	'htm'	=> 'text/html',
	'html'	=> 'text/html',
	'shtml'	=> 'text/html',
	'xhtml'	=> 'text/xhtml+xml',
	'hlp'	=> 'application/x-winhelp',
	'csv'	=> 'text/csv',
	'doc'	=> 'application/msword',
	'rtf'	=> 'application/rtf',
	'xls'	=> 'application/vnd.ms-excel',
	'ppt'	=> 'application/vnd.ms-powerpoint',
	'pdf'	=> 'application/pdf',
	'htc'	=> 'text/x-component',

	'xlsx'	=> 'application/vnd.openxmlformats-officedocument',
	'xltx'	=> 'application/vnd.openxmlformats-officedocument',
	'potx'	=> 'application/vnd.openxmlformats-officedocument',
	'ppsx'	=> 'application/vnd.openxmlformats-officedocument',
	'pptx'	=> 'application/vnd.openxmlformats-officedocument',
	'sldx'	=> 'application/vnd.openxmlformats-officedocument',
	'docx'	=> 'application/vnd.openxmlformats-officedocument',
	'xlam'	=> 'application/vnd.ms-excel.addin.macroEnabled.12',

	'xml'	=> 'text/xml',
	'xml.gz'=> 'text/xml',
	'css'	=> 'text/css',
	'css.gz'=> 'text/css',
	'js'	=> 'application/x-javascript',
	'js.gz'	=> 'application/x-javascript',
	'atom'	=> 'application/atom+xml',
	'rss'	=> 'application/rss+xml',
	'swf'	=> 'application/x-shockwave-flash',
	'crt'	=> 'application/x-x509-ca-cert',

	'asp'	=> 'text/asp',
	'php'	=> 'text/x-php',
	'py'	=> 'text/x-script.phyton',

	'jpg'	=> 'image/jpeg',
	'jpeg'	=> 'image/jpeg',
	'jpe'	=> 'image/jpeg',
	'jng'	=> 'image/x-jng',
	'png'	=> 'image/png',
	'gif'	=> 'image/gif',
	'tif'	=> 'image/tiff',
	'tiff'	=> 'image/tiff',
	'tga'	=> 'image/x-targa',
	'webp'	=> 'image/webp',
	'bmp'	=> 'image/x-ms-bmp',
	'bmp.gz'=> 'image/x-ms-bmp',
	'wbmp'	=> 'image/vnd.wap.wbmp',
	'ico'	=> 'image/x-icon',
	'ico.gz'=> 'image/x-icon',
	'wmf'	=> 'application/x-msmetafile',
	'wml'	=> 'text/vnd.wap.wml',
	'svg'	=> 'image/svg+xml',
	'svgz'	=> 'image/svg+xml',
	'svg.gz'=> 'image/svg+xml',
	'ps'	=> 'application/postscript',
	'ai'	=> 'application/postscript',
	'eps'	=> 'application/postscript',
	'psd'	=> 'image/x-photoshop',

	'ttf'	=> 'application/x-font-ttf',
	'otf'	=> 'font/opentype',

	'mid'	=> 'audio/midi',
	'midi'	=> 'audio/midi',
	'ogg'	=> 'audio/ogg',
	'ra'	=> 'audio/x-realaudio',
	'm4a'	=> 'audio/x-m4a',
	'mp3'	=> 'audio/mpeg',
	'mpg'	=> 'video/mpeg',
	'mpeg'	=> 'video/mpeg',
	'm4v'	=> 'video/x-m4v',
	'mov'	=> 'video/quicktime',
	'wmv'	=> 'video/x-ms-wmv',
	'avi'	=> 'video/x-msvideo',
	'asx'	=> 'video/x-ms-asf',
	'asf'	=> 'video/x-ms-asf',
	'flv'	=> 'video/x-flv',
	'webm'	=> 'video/webm',
	'3gp'	=> 'video/3gpp',
	'3gpp'	=> 'video/3gpp',
	'torrent'=>'application/x-bittorrent',

	'tar'	=> 'application/tar',
	'tgz'	=> 'application/tar+gzip',
	'gz'	=> 'application/gzip',
	'zip'	=> 'application/zip',
	'rar'	=> 'application/x-rar-compressed',
	'7z'	=> 'application/x-7z-compressed',
	'jar'	=> 'application/java-archive',
    );

  if (!$ext = file_ext($fn, 1))
    $ext = $fn; // maybe it's not the filename, just pure extension?

  if ($ext && isset($mime_types[$ext]))
    return $mime_types[$ext];

  return 'application/octet-stream';
}
