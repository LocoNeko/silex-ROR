<?php

function mortality_chits( $qty ) {
    $qty = (int)$qty ;
    $result = Array() ;
    $chits = Array() ;
    for ($i=1 ; $i<=30 ; $i++) { $chits[$i] = $i ; }
    for ($i=31 ; $i<=34 ; $i++) { $chits[$i] = 0 ; }
    $chits [35] = -1 ; $chits [36] = -1 ;
    for ($i=$qty ; $i>0 ; $i--) {
        $pick = array_rand($chits) ;
        if ($chits[$pick]==-1) {
            $i+=2;
            array_push($result , "DRAW 2");
        } else {
            if (($key = array_search($chits[$pick], $chits)) !== false) {
                if ($chits[$pick]!=0) {
                    array_push($result , $chits[$pick]);
                } else {
                    array_push($result , "NONE");
                }
                unset($chits[$key]);
            }
        }
        if (count($chits)==2) {
            break;
        }
    }
    asort($result);
    return $result;
}
$result = mortality_chits(3) ;
var_dump($result) ;