<?php

declare(strict_types=1);

function renderIcon(string $path, int $size, bool $maskable = false): void
{
    $scale = $size / 512;
    $image = imagecreatetruecolor($size, $size);
    imageantialias($image, true);
    $navy = imagecolorallocate($image, 17, 46, 53);
    $teal = imagecolorallocate($image, 8, 127, 117);
    $lime = imagecolorallocate($image, 210, 235, 137);
    $white = imagecolorallocate($image, 244, 247, 245);
    imagefill($image, 0, 0, $navy);

    $radius = ($maskable ? 160 : 178) * $scale;
    imagefilledellipse($image, (int) ($size / 2), (int) ($size / 2), (int) ($radius * 2), (int) ($radius * 2), $teal);

    $points = $maskable
        ? [[276, 114], [172, 280], [244, 280], [229, 414], [341, 231], [265, 231]]
        : [[279, 78], [155, 275], [241, 275], [223, 434], [357, 217], [266, 217]];
    $scaled = [];
    foreach ($points as [$x, $y]) {
        $scaled[] = (int) round($x * $scale);
        $scaled[] = (int) round($y * $scale);
    }
    imagefilledpolygon($image, $scaled, $white);

    $centerX = 257;
    $centerY = 256;
    $inner = [];
    foreach ($points as [$x, $y]) {
        $inner[] = (int) round(($centerX + ($x - $centerX) * .92) * $scale);
        $inner[] = (int) round(($centerY + ($y - $centerY) * .92) * $scale);
    }
    imagefilledpolygon($image, $inner, $lime);

    imagepng($image, $path, 9);
    imagedestroy($image);
}

$public = dirname(__DIR__).'/public/pwa';
renderIcon($public.'/icon-192.png', 192);
renderIcon($public.'/icon-512.png', 512);
renderIcon($public.'/icon-maskable-512.png', 512, true);
