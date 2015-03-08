<?php
    header("Content-type: image/png");
    $image = imagecreatefrompng('../web/images/Senator.png') ;
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

    // Dynamic content
    $get_vars = filter_input_array (INPUT_GET);
    if (isset($get_vars['vars'])) {
        $vars = unserialize(urldecode($get_vars['vars'])) ;
        if ($vars['type']=='Family' || $vars['type']=='Statesman') {
            // Get coordinates for the name 
            $image_width = imagesx($image);  
            $image_height = imagesy($image);
            $text_box = imagettfbbox(15 , 0 , $font , $vars['name']);
            $text_width = $text_box[2]-$text_box[0];
            $x_name = ($image_width/2) - ($text_width/2);

            // Writes texts
            text_with_shadow($image, 15, 0, $x_name , 25, ($get_vars['leader'] ? $red : $black), $font, $vars['name']);
            imagettftext($image, 12, 0, 200 , 45, $black, $font, ($vars['hasStatesman'] ? '['.$vars['senatorID'].']' : $vars['senatorID']));
            text_with_shadow($image, 20, 0, 360 , 68, $black, $font, $vars['MIL']);
            text_with_shadow($image, 20, 0, 360 , 104, $black, $font, $vars['ORA']);
            text_with_shadow($image, 20, 0, 360 , 138, $black, $font, $vars['LOY']);
            text_with_shadow($image, 40, 0, 32 , 244, ($vars['POP']<0 ? $red : $black), $font, abs($vars['POP']));
            if ($vars['knights']>0) {
                imagefilledrectangle($image, 330, 200, 376, 250, $black);
                text_with_shadow($image, 38, 0, 336 , 243, $white, $font, $vars['knights']);
            }
            text_with_shadow($image, 40, 0, 216 , 244, $black, $font, $vars['INF']%10);
            if ($vars['INF']>10) {
                text_with_shadow($image, 40, 0, 150 , 244, $black, $font, (int)($vars['INF']/10));
            }
            if ($vars['treasury']>0) {
                text_with_shadow($image, 30, 0, 48 , 120, $black, $font, $vars['treasury']);
            }
            if ($vars['office']!=NULL) {
                $officeImage = imagecreatefrompng('../web/images/Office_'.$vars['office'].'.png') ;
                if ($officeImage) {
                    imagecopy($image , imagescale($officeImage , 96 , -1 , IMG_BICUBIC_FIXED) , 100 , 50 , 0 , 0 , 96 , 96) ;
                    imagedestroy($officeImage) ;
                }
            }
            if ($vars['priorConsul']) {
                $priorConsulImage = imagecreatefrompng('../web/images/priorConsul.png') ;
                if ($priorConsulImage) {
                    imagecopy($image , imagescale($priorConsulImage , 64 , -1 , IMG_BICUBIC_FIXED) , 250 , 50 , 0 , 0 , 64 , 64) ;
                    imagedestroy($priorConsulImage) ;
                }
            }
        }
    }
    
    // Output image
    imagepng($image);
    imagedestroy($image);
    
    function text_with_shadow($image , $font_size , $angle , $x , $y , $colour , $font , $text) {
        global $grey ;
        imagettftext($image, $font_size, $angle, $x+1 , $y+1, $grey, $font, $text);
        imagettftext($image, $font_size, $angle, $x , $y, $colour, $font, $text);
    }