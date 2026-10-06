<?php

// php crop.php in.png out-prefix sliceHeight [scale]
[$self, $in, $prefix, $h] = $argv;
$scale = (float) ($argv[4] ?? 1);
$src = imagecreatefrompng($in);
$w = imagesx($src);
$H = imagesy($src);
for ($y = 0, $i = 0; $y < $H; $y += $h, $i++) {
    $hh = min($h, $H - $y);
    $dst = imagecreatetruecolor((int) ($w * $scale), (int) ($hh * $scale));
    imagecopyresampled($dst, $src, 0, 0, 0, $y, (int) ($w * $scale), (int) ($hh * $scale), $w, $hh);
    imagepng($dst, "{$prefix}-{$i}.png");
}
echo $i, " slices\n";
