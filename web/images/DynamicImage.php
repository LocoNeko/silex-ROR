<?php
    header("Content-type: image/png");
    $image = imagecreatefrompng('Senator.png') ;
    if(!$image) {
        /* Create a blank image */
        $image  = imagecreatetruecolor(150, 30);
        $bgc = imagecolorallocate($image, 255, 255, 255);
        $tc  = imagecolorallocate($image, 0, 0, 0);

        imagefilledrectangle($image, 0, 0, 400, 275, $bgc);

        /* Output an error message */
        imagestring($image, 1, 5, 5, 'Error loading Senator.png', $tc);
    }

    // Colours
    $white = imagecolorallocate($image, 255, 255, 255);
    $grey = imagecolorallocate($image, 128, 128, 128);
    $black = imagecolorallocate($image, 0, 0, 0);
    $red = imagecolorallocate($image, 255, 0, 0);

    putenv('GDFONTPATH=' . realpath('.'));
    $font = "DejaVuSerif";

    // Dynamic text
    $senatorName = $_GET['name'] ;
    $MIL= $_GET['MIL'] ;
    $ORA= $_GET['ORA'] ;
    $LOY= $_GET['LOY'] ;
    $POP= $_GET['POP'] ;
    $INF= $_GET['INF'] ;
    $knights= $_GET['knights'] ;
    $treasury = $_GET['treasury'] ;
    $office = $_GET['office'] ;
    $priorConsul = $_GET['priorConsul'] ;
    
    // Get coordinates for the name 
    $image_width = imagesx($image);  
    $image_height = imagesy($image);
    $text_box = imagettfbbox(15,0,$font,$senatorName);
    $text_width = $text_box[2]-$text_box[0];
    $x_name = ($image_width/2) - ($text_width/2);
    
    // Writes texts
    text_with_shadow($image, 15, 0, $x_name , 25, $black, $font, $senatorName);
    text_with_shadow($image, 20, 0, 360 , 68, $black, $font, $MIL);
    text_with_shadow($image, 20, 0, 360 , 104, $black, $font, $ORA);
    text_with_shadow($image, 20, 0, 360 , 138, $black, $font, $LOY);
    text_with_shadow($image, 40, 0, 32 , 244, ($POP<0 ? $red : $black), $font, abs($POP));
    if ($knights>0) {
        imagefilledrectangle($image, 330, 200, 376, 250, $black);
        text_with_shadow($image, 38, 0, 336 , 243, $white, $font, $knights);
    }
    text_with_shadow($image, 40, 0, 216 , 244, $black, $font, $INF%10);
    if ($INF>10) {
        text_with_shadow($image, 40, 0, 150 , 244, $black, $font, (int)($INF/10));
    }
    if ($treasury>0) {
        text_with_shadow($image, 30, 0, 48 , 120, $black, $font, $treasury);
    }
    if ($office!=NULL) {
        $officeImage = imagecreatefrompng('Office_'.$office.'.png') ;
        if ($officeImage) {
            imagecopy($image , imagescale($officeImage , 96 , -1 , IMG_BICUBIC_FIXED) , 100 , 50 , 0 , 0 , 96 , 96) ;
        }
    }
    if ($priorConsul) {
        $priorConsulImage = imagecreatefrompng('priorConsul.png') ;
        if ($priorConsulImage) {
            imagecopy($image , imagescale($priorConsulImage , 64 , -1 , IMG_BICUBIC_FIXED) , 250 , 50 , 0 , 0 , 64 , 64) ;
        }
    }
    
    // Output image
    imagepng($image);
    imagedestroy($image);
    
    function text_with_shadow($image , $font_size , $angle , $x , $y , $colour , $font , $text) {
        global $grey ;
        imagettftext($image, $font_size, $angle, $x+2 , $y+2, $grey, $font, $text);
        imagettftext($image, $font_size, $angle, $x , $y, $colour, $font, $text);
    }