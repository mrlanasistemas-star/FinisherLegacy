<?php

// php sheet.php out.png cols thumbW file1 file2 ...
[$self, $out, $cols, $tw] = array_slice($argv, 0, 4);
$files = array_slice($argv, 4);
$imgs = array_map(fn ($f) => imagecreatefromwebp($f), $files);
$th = (int) round(imagesy($imgs[0]) * $tw / imagesx($imgs[0]));
$rows = (int) ceil(count($imgs) / $cols);
$sheet = imagecreatetruecolor($cols * $tw, $rows * $th);
imagefill($sheet, 0, 0, imagecolorallocate($sheet, 255, 255, 255));
foreach ($imgs as $i => $im) {
    imagecopyresampled($sheet, $im, ($i % $cols) * $tw, intdiv($i, $cols) * $th, 0, 0, $tw, $th, imagesx($im), imagesy($im));
}
imagepng($sheet, $out);
