<?php

namespace App\Services\Portrait;

/**
 * A minimal ICC v4 sRGB profile, built in code (no third-party profile
 * file to license) and embedded in the exported JPEG as an APP2 segment.
 * GD writes untagged RGB; tagging it tells the print lab the file is
 * sRGB instead of leaving it to their default.
 */
final class SrgbProfile
{
    private static ?string $bytes = null;

    /** Inserts the profile right after the JFIF header of a baseline JPEG. */
    public static function embed(string $jpeg): string
    {
        if (strncmp($jpeg, "\xFF\xD8", 2) !== 0) {
            return $jpeg;
        }

        $offset = 2;

        // Keep APP0 (JFIF, where GD stores the dpi) first.
        if (substr($jpeg, 2, 2) === "\xFF\xE0") {
            /** @var array{1: int} $length */
            $length = unpack('n', substr($jpeg, 4, 2));
            $offset = 4 + $length[1];
        }

        $payload = "ICC_PROFILE\0\x01\x01".self::bytes();
        $segment = "\xFF\xE2".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, $offset).$segment.substr($jpeg, $offset);
    }

    public static function bytes(): string
    {
        return self::$bytes ??= self::build();
    }

    private static function build(): string
    {
        $xyz = fn (float $x, float $y, float $z): string => "XYZ \0\0\0\0".self::fixed($x).self::fixed($y).self::fixed($z);
        // IEC 61966-2-1 transfer curve as a parametric curve (type 3).
        $curve = "para\0\0\0\0".pack('nn', 3, 0)
            .self::fixed(2.4).self::fixed(1 / 1.055).self::fixed(0.055 / 1.055).self::fixed(1 / 12.92).self::fixed(0.04045);

        // D65 primaries, Bradford-adapted to the D50 connection space.
        $tags = [
            'desc' => self::text('sRGB'),
            'cprt' => self::text('No copyright, use freely'),
            'wtpt' => $xyz(0.9642, 1.0, 0.8249),
            'rXYZ' => $xyz(0.43607, 0.22249, 0.01392),
            'gXYZ' => $xyz(0.38515, 0.71687, 0.09708),
            'bXYZ' => $xyz(0.14307, 0.06061, 0.71410),
            'rTRC' => $curve,
            'gTRC' => $curve,
            'bTRC' => $curve,
            'chad' => "sf32\0\0\0\0".implode('', array_map(self::fixed(...), [
                1.04788, 0.02292, -0.05022,
                0.02959, 0.99048, -0.01707,
                -0.00925, 0.01508, 0.75168,
            ])),
        ];

        $table = '';
        $data = '';
        $offset = 128 + 4 + 12 * count($tags);
        /** @var array<string, array{0: int, 1: int}> $written identical payloads are stored once */
        $written = [];

        foreach ($tags as $signature => $payload) {
            if (! isset($written[$payload])) {
                $written[$payload] = [$offset + strlen($data), strlen($payload)];
                $data .= $payload.str_repeat("\0", (4 - strlen($payload) % 4) % 4);
            }

            $table .= $signature.pack('NN', $written[$payload][0], $written[$payload][1]);
        }

        $body = pack('N', count($tags)).$table.$data;

        $header = pack('N', 128 + strlen($body))
            ."\0\0\0\0"                              // preferred CMM
            ."\x04\x20\x00\x00"                      // version 4.2
            .'mntr'.'RGB '.'XYZ '
            .pack('n6', 2026, 1, 1, 0, 0, 0)         // creation date
            .'acsp'
            ."\0\0\0\0"                              // platform
            .pack('N', 0)                            // flags
            ."\0\0\0\0"."\0\0\0\0"                   // manufacturer, model
            .str_repeat("\0", 8)                     // attributes
            .pack('N', 0)                            // perceptual intent
            .self::fixed(0.9642).self::fixed(1.0).self::fixed(0.8249)
            ."\0\0\0\0"                              // creator
            .str_repeat("\0", 16)                    // profile id
            .str_repeat("\0", 28);

        return $header.$body;
    }

    /** s15Fixed16Number. */
    private static function fixed(float $value): string
    {
        return pack('N', ((int) round($value * 65536)) & 0xFFFFFFFF);
    }

    /** multiLocalizedUnicodeType with one en-US record. */
    private static function text(string $value): string
    {
        $utf16 = mb_convert_encoding($value, 'UTF-16BE', 'UTF-8');

        return "mluc\0\0\0\0".pack('NN', 1, 12).'enUS'.pack('NN', strlen($utf16), 28).$utf16;
    }
}
