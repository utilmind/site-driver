<?
function roman_number($n){
  static $roman_num = array(
	'M'  => 1000,
	'CM' => 900,
	'D'  => 500,
	'CD' => 400,
	'C'  => 100,
	'XC' => 90,
	'L'  => 50,
	'XL' => 40,
	'X'  => 10,
	'IX' => 9,
	'V'  => 5,
	'IV' => 4,
	'I'  => 1);

  $num = intval($n);
  $o = '';

  foreach ($roman_num as $r => $n){
    // divide to get  matches
    $match = intval($num / $n);
    // assign the roman char * $matches
    $o.= str_repeat($r, $match);
    // substract from the number
    $num = $num % $n;
  }

  return $o;
}
