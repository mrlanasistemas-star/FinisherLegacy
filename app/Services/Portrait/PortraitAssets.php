<?php

namespace App\Services\Portrait;

use GdImage;
use RuntimeException;

/**
 * Files the portrait is drawn with (config/portrait.php): the TTF of each
 * font role — first existing file wins — and the FL monogram images.
 */
final class PortraitAssets
{
    /** @var array<string, GdImage> */
    private array $marks = [];

    /** @var array<string, string> */
    private array $fonts = [];

    public function font(string $role): string
    {
        if (isset($this->fonts[$role])) {
            return $this->fonts[$role];
        }

        foreach ((array) config("portrait.fonts.{$role}", []) as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $this->fonts[$role] = $path;
            }
        }

        throw new RuntimeException("No TTF font available for portrait role [{$role}].");
    }

    public function mark(string $variant): GdImage
    {
        if (isset($this->marks[$variant])) {
            return $this->marks[$variant];
        }

        $path = (string) config("portrait.marks.{$variant}", '');
        $image = is_file($path) ? @imagecreatefrompng($path) : false;

        if ($image === false) {
            throw new RuntimeException("FL monogram asset [{$variant}] not found.");
        }

        imagealphablending($image, true);

        return $this->marks[$variant] = $image;
    }
}
