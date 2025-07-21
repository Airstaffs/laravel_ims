<?php
define('QR_IMAGE', true);

class QRimage {

    //----------------------------------------------------------------------
    public static function png($frame, $filename = false, $pixelPerPoint = 4, $outerFrame = 4, $back_color = 0xFFFFFF, $fore_color = 0x000000, $border_color = 0x000000, $text = '', $text_color = 0x000000, $font_file = '', $font_size = 5, $saveandprint = false)
    {
        $image = self::image($frame, $pixelPerPoint, $outerFrame, $back_color, $fore_color, $border_color, $text, $text_color, $font_file, $font_size);

        if ($filename === false) {
            header("Content-type: image/png");
            imagepng($image);
        } else {
            imagepng($image, $filename);
            if ($saveandprint) {
                header("Content-type: image/png");
                imagepng($image);
            }
        }

        imagedestroy($image);
    }

    //----------------------------------------------------------------------
    public static function jpg($frame, $filename = false, $pixelPerPoint = 8, $outerFrame = 4, $q = 85, $border_color = 0x000000, $text = '', $text_color = 0x000000, $font_file = '', $font_size = 5)
    {
        $image = self::image($frame, $pixelPerPoint, $outerFrame, $border_color, $text, $text_color, $font_file, $font_size);

        if ($filename === false) {
            header("Content-type: image/jpeg");
            imagejpeg($image, null, $q);
        } else {
            imagejpeg($image, $filename, $q);
        }

        imagedestroy($image);
    }

    //----------------------------------------------------------------------
    private static function image($frame, $pixelPerPoint = 4, $outerFrame = 4, $back_color = 0xFFFFFF, $fore_color = 0x000000, $border_color = 0x000000, $text = '', $text_color = 0x000000, $font_file = '', $font_size = 5)
    {
        $h = count($frame);
        $w = strlen($frame[0]);

        $imgW = $w + 2 * $outerFrame;
        $imgH = $h + 2 * $outerFrame;

        $base_image = imagecreatetruecolor($imgW, $imgH);

        // Convert hexadecimal color code to RGB
        $r1 = ($fore_color >> 16) & 0xFF;
        $g1 = ($fore_color >> 8) & 0xFF;
        $b1 = $fore_color & 0xFF;

        $r2 = ($back_color >> 16) & 0xFF;
        $g2 = ($back_color >> 8) & 0xFF;
        $b2 = $back_color & 0xFF;

        $r_border = ($border_color >> 16) & 0xFF;
        $g_border = ($border_color >> 8) & 0xFF;
        $b_border = $border_color & 0xFF;

        $r_text = ($text_color >> 16) & 0xFF;
        $g_text = ($text_color >> 8) & 0xFF;
        $b_text = $text_color & 0xFF;

        $col[0] = imagecolorallocate($base_image, $r2, $g2, $b2);
        $col[1] = imagecolorallocate($base_image, $r1, $g1, $b1);
        $col_border = imagecolorallocate($base_image, $r_border, $g_border, $b_border);
        $col_text = imagecolorallocate($base_image, $r_text, $g_text, $b_text);

        imagefill($base_image, 0, 0, $col[0]);

        // Draw QR code
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ($frame[$y][$x] == '1') {
                    imagesetpixel($base_image, $x + $outerFrame, $y + $outerFrame, $col[1]);
                }
            }
        }

        // Draw border
        imagerectangle($base_image, 0, 0, $imgW - 1, $imgH - 1, $col_border);

        // Add text
        if (!empty($text) && !empty($font_file)) {
            $textBox = imagettfbbox($font_size, 0, $font_file, $text);
            $textWidth = $textBox[2] - $textBox[0];
            $textX = 10; // Padding from left
            $textY = $imgH - 10; // Padding from bottom

            imagettftext($base_image, $font_size, 0, $textX, $textY, $col_text, $font_file, $text);
        }

        $target_image = imagecreatetruecolor($imgW * $pixelPerPoint, $imgH * $pixelPerPoint);
        imagecopyresized($target_image, $base_image, 0, 0, 0, 0, $imgW * $pixelPerPoint, $imgH * $pixelPerPoint, $imgW, $imgH);
        imagedestroy($base_image);

        return $target_image;
    }
}
?>
