<?php

namespace App\Services\Portrait;

use InvalidArgumentException;

/**
 * The data-driven templates of config/portrait.php §templates, plus what
 * the builder needs to mirror them in its live preview.
 */
final class PortraitTemplates
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        /** @var array<string, array<string, mixed>> $templates */
        $templates = (array) config('portrait.templates', []);

        return $templates;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        $template = $this->all()[$key] ?? null;

        if (! is_array($template)) {
            throw new InvalidArgumentException("Unknown portrait template [{$key}].");
        }

        return $template;
    }

    public function defaultKey(): string
    {
        $default = (string) config('portrait.default_template', 'carbono');

        return array_key_exists($default, $this->all()) ? $default : (string) ($this->keys()[0] ?? 'carbono');
    }

    /**
     * Photo box in pixels for a canvas.
     *
     * @return array{x: int, y: int, w: int, h: int}
     */
    public function photoBox(string $key, int $canvasW, int $canvasH): array
    {
        /** @var array{x: float, y: float, w: float, h: float} $photo */
        $photo = $this->get($key)['photo'];

        return [
            'x' => (int) round($photo['x'] * $canvasW),
            'y' => (int) round($photo['y'] * $canvasH),
            'w' => (int) round($photo['w'] * $canvasW),
            'h' => (int) round($photo['h'] * $canvasH),
        ];
    }

    /**
     * Templates as the builder page consumes them (photo box included so
     * the cropper uses the exact print aspect; the preview itself is a
     * server render, see StoryPrintController::previewPortrait).
     *
     * @return list<array<string, mixed>>
     */
    public function forClient(PortraitSpec $spec): array
    {
        $out = [];

        foreach ($this->all() as $key => $template) {
            $box = $this->photoBox($key, $spec->widthPx(), $spec->heightPx());

            $out[] = [
                'value' => $key,
                'label' => (string) ($template['label'] ?? $key),
                'description' => (string) ($template['description'] ?? ''),
                'background' => (string) ($template['background'] ?? '#FFFFFF'),
                'photo' => $template['photo'],
                'photo_px' => $box,
                'aspect' => $box['h'] > 0 ? $box['w'] / $box['h'] : 1,
            ];
        }

        return $out;
    }
}
