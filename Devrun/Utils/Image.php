<?php

namespace Devrun\Utils;

class Image extends \Nette\Utils\Image
{

    /**
     * @param string $font
     * @param int $fontSize
     * @param int $width
     * @param int $height
     * @param string $text
     * @return Image
     */
    public static function createImageText(string $font, int $fontSize, int $width, int $height, string $text)
    {
        list($lowLeftX, $lowLeftY, $lowRightX, $lowRightY, $highRightX, $highRightY, $highLeftX, $highLeftY) = imageftbbox($fontSize, 0, $font, $text);

        $textWidth  = $lowRightX - $lowLeftX;
        $textHeight = abs($lowLeftY) + abs($highRightY);

        $image = self::fromBlank($width, $height, self::rgb(200, 200, 200));
        $color = self::rgb(155, 155, 255);
        $image->filledRectangle(10, 10, $width - 10, $height - 10, $color);
        $image->ttfText($fontSize, 0, intval(($width - $textWidth) / 2), intval(($height - $textHeight) / 2 - $highLeftY), self::rgb(25, 15, 25), $font, $text);

        return $image;
    }


}