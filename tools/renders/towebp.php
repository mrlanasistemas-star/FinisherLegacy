<?php

/**
 * php towebp.php <in.png> <out.webp> <width> [quality]
 * Resizes (keeping aspect + alpha) and encodes WebP with GD.
 */
[$self, $in, $out, $width] = $argv;
$quality = (int) ($argv[4] ?? 82);

$src = (str_ends_with(strtolower($in), '.png') ? imagecreatefrompng($in) : imagecreatefromjpeg($in)) ?: throw new RuntimeException("Cannot read {$in}");
$w = imagesx($src);
$h = imagesy($src);
$width = min((int) $width, $w);
$height = (int) round($h * $width / $w);

$dst = imagecreatetruecolor($width, $height);
imagealphablending($dst, false);
imagesavealpha($dst, true);
imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $w, $h);

imagewebp($dst, $out, $quality) || throw new RuntimeException("Cannot write {$out}");
printf("  %s %dx%d %.0f KB\n", basename($out), $width, $height, filesize($out) / 1024);
