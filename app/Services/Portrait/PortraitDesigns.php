<?php

namespace App\Services\Portrait;

use App\Actions\Commerce\ResolveProductPrice;
use App\Enums\MediaKind;
use App\Enums\StoryMediaRole;
use App\Models\CartItem;
use App\Models\LegacyStory;
use App\Models\MediaFile;
use App\Models\OrderItem;
use App\Models\PortraitDesign;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saved Portarretrato personalizations (docs/renovacion/portarretrato.md
 * §Personalización) — one place for the rules, the defaults taken from the
 * memory, the render and the shapes the web builder, the app and the admin
 * order detail read, so all three show exactly the same thing.
 *
 * The DIGITAL side of a memory (album order, cover = first photo, captions)
 * stays in the story itself; a design only describes the PHYSICAL print:
 * main photo + crop + template + words.
 */
final class PortraitDesigns
{
    /** Words printed on the frame and their max length (same as the builder's download). */
    public const array FIELDS = [
        'name' => 80,
        'event' => 160,
        'details' => 160,
        'time' => 20,
        'distance' => 40,
        'position' => 40,
        'pace' => 20,
        'bib' => 12,
        'category' => 40,
        'dedication' => 600,
    ];

    public function __construct(
        private readonly PortraitTemplates $templates,
        private readonly PortraitComposer $composer,
        private readonly ResolveProductPrice $prices,
    ) {}

    /**
     * Validation rules shared by the web builder and the API. `$photoKey`
     * is `photo_uuid` (API, MediaFile uuid) or `media_file_id` (web).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(bool $partial = false, string $photoKey = 'photo_uuid'): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $rules = [
            'title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'template' => [$req, Rule::in($this->templates->keys())],
            $photoKey => [$req, $photoKey === 'photo_uuid' ? 'uuid' : 'integer'],
            'crop' => ['sometimes', 'nullable', 'array'],
            'crop.x' => ['required_with:crop', 'numeric', 'between:0,1'],
            'crop.y' => ['required_with:crop', 'numeric', 'between:0,1'],
            'crop.w' => ['required_with:crop', 'numeric', 'gt:0', 'max:1'],
            'crop.h' => ['required_with:crop', 'numeric', 'gt:0', 'max:1'],
            'fields' => ['sometimes', 'nullable', 'array'],
        ];

        foreach (self::FIELDS as $key => $max) {
            $rules["fields.{$key}"] = ['nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'template.in' => __('Elige una plantilla.'),
            'template.required' => __('Elige una plantilla.'),
            'photo_uuid.required' => __('Elige la foto principal.'),
            'media_file_id.required' => __('Elige la foto principal.'),
        ];
    }

    /**
     * A ready image of THIS story, by MediaFile uuid or id. Anything else
     * (another story's file, a video, a file still processing) is a 422.
     */
    public function storyPhoto(LegacyStory $story, string|int $key): MediaFile
    {
        /** @var MediaFile|null $file */
        $file = $story->media()
            ->wherePivot('role', StoryMediaRole::Photo->value)
            ->where(is_int($key) ? 'media_files.id' : 'media_files.uuid', $key)
            ->first();

        if ($file === null || $file->kind !== MediaKind::Image || ! $file->isServable()) {
            throw ValidationException::withMessages([is_int($key) ? 'media_file_id' : 'photo_uuid' => __('Esa foto todavía se está procesando o ya no existe.')]);
        }

        return $file;
    }

    /**
     * Persists a new design or updates one (only the keys present).
     *
     * @param  array<string, mixed>  $data  validated input
     */
    public function save(LegacyStory $story, int $userId, array $data, ?PortraitDesign $design = null, string $photoKey = 'photo_uuid'): PortraitDesign
    {
        $design ??= new PortraitDesign(['uuid' => (string) Str::uuid(), 'user_id' => $userId, 'legacy_story_id' => $story->id]);

        if (array_key_exists($photoKey, $data)) {
            $key = $photoKey === 'photo_uuid' ? (string) $data[$photoKey] : (int) $data[$photoKey];
            $design->media_file_id = $this->storyPhoto($story, $key)->id;
        }

        if (array_key_exists('template', $data)) {
            $design->template = (string) $data['template'];
        }

        if (array_key_exists('title', $data)) {
            $design->title = self::text($data['title']);
        }

        if (array_key_exists('crop', $data)) {
            $design->crop = self::crop($data['crop']);
        }

        if (array_key_exists('fields', $data)) {
            $design->fields = self::fields(is_array($data['fields']) ? $data['fields'] : []);
        }

        $design->save();

        return $design;
    }

    /**
     * Server render of a design-like set of values. `$preview` uses the
     * display variant and skips the resolution check (screen size).
     *
     * @param  array{x: float, y: float, w: float, h: float}|null  $crop
     * @param  array<string, mixed>  $fields
     */
    public function render(string $template, MediaFile $file, ?array $crop, array $fields, ?PortraitSpec $spec, PortraitOptions $options, bool $preview = false): PortraitResult
    {
        return $this->composer->compose($template, $this->bytes($file, $preview), $crop, self::data($fields), $spec, $options);
    }

    /**
     * The same render for a preview request that failed for a reason the
     * user can act on: always a 422 on `photo`, never a 500.
     *
     * @param  array{x: float, y: float, w: float, h: float}|null  $crop
     * @param  array<string, mixed>  $fields
     */
    public function previewJpeg(string $template, MediaFile $file, ?array $crop, array $fields, int $width): string
    {
        try {
            return $this->render($template, $file, $crop, $fields, PortraitSpec::fromConfig()->scaledToWidth($width), PortraitOptions::preview(), preview: true)->jpeg;
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['photo' => __('No pudimos preparar esa foto. Prueba con otra.')]);
        }
    }

    /**
     * Words prefilled from the memory (official race data first).
     *
     * @param  array<string, mixed>  $view  StoryPresenter::story()
     * @return array<string, string|null>
     */
    public function defaults(LegacyStory $story, array $view): array
    {
        /** @var array<string, mixed> $race */
        $race = $view['race'];
        $date = $race['date'] ?? null;

        return [
            'name' => self::text($race['athlete_name'] ?? null) ?? $this->athleteName($story),
            'event' => self::text($race['event_name'] ?? null) ?? self::text($view['title'] ?? null),
            'details' => PortraitData::join([$race['location'] ?? null, is_string($date) ? Carbon::parse($date)->settings(['locale' => 'es'])->isoFormat('D [de] MMMM YYYY') : null]),
            'time' => self::text($race['time'] ?? null),
            'distance' => self::text($race['distance'] ?? $race['race'] ?? null),
            'position' => self::text($race['position'] ?? null),
            'pace' => self::text($race['pace'] ?? null),
            'bib' => self::text($race['bib_number'] ?? null),
            'category' => self::text($race['category'] ?? null),
            'dedication' => $story->dedication,
        ];
    }

    /**
     * The frame product as the "order this portrait" button needs it, or
     * null when it is not in the catalog. Price comes from the catalog
     * (ResolveProductPrice) — never from here.
     *
     * @return array{name: string, slug: string, variant_id: int, variant_uuid: string, price_minor: int|null, currency: string|null, available: bool}|null
     */
    public function product(): ?array
    {
        $product = Product::query()->whereIn('slug', PortraitProduct::slugs())->with(['variants' => fn ($q) => $q->where('active', true)->orderBy('id')])->first();
        /** @var ProductVariant|null $variant */
        $variant = $product?->variants->first();

        if ($product === null || $variant === null) {
            return null;
        }

        try {
            $price = $this->prices->handle($product, $variant);
            [$amount, $currency] = [$price->amountMinor, $price->currency];
        } catch (Throwable) {
            [$amount, $currency] = [null, null];
        }

        return [
            'name' => $product->name,
            'slug' => $product->slug,
            'variant_id' => $variant->id,
            'variant_uuid' => $variant->uuid,
            'price_minor' => $amount,
            'currency' => $currency,
            'available' => $product->isPurchasable() && $amount !== null,
        ];
    }

    /**
     * Whether a variant belongs to a frame product (only those carry a design).
     */
    public static function isFrameVariant(ProductVariant $variant): bool
    {
        return PortraitProduct::isFrameProduct($variant->loadMissing('product')->product);
    }

    /**
     * Shape read by the web builder and the app.
     *
     * @return array<string, mixed>
     */
    public function toArray(PortraitDesign $design): array
    {
        $file = $design->mediaFile;

        return [
            'uuid' => $design->uuid,
            'title' => $design->title,
            'template' => $design->template,
            'template_label' => $this->templateLabel($design->template),
            'media_file_id' => $design->media_file_id,
            'photo_uuid' => $file?->uuid,
            'photo_thumb_url' => $file !== null && $file->isServable() ? ($file->url('card') ?? $file->url()) : null,
            'crop' => $design->crop,
            'fields' => [...array_fill_keys(array_keys(self::FIELDS), null), ...($design->fields ?? [])],
            'last_ordered_at' => $design->last_ordered_at?->toIso8601String(),
            'created_at' => $design->created_at?->toIso8601String(),
            'updated_at' => $design->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Frozen copy kept on the OrderItem (metadata.portrait_design): what
     * production prints even if the customer edits or deletes the design.
     *
     * @return array<string, mixed>
     */
    public function snapshot(PortraitDesign $design): array
    {
        $design->loadMissing('story');

        return [
            'id' => $design->id,
            'uuid' => $design->uuid,
            'title' => $design->title,
            'template' => $design->template,
            'template_label' => $this->templateLabel($design->template),
            'media_file_id' => $design->media_file_id,
            'crop' => $design->crop,
            'fields' => $design->fields ?? [],
            'story_public_id' => $design->story?->public_id,
            'story_title' => $design->story?->title ?: $design->story?->event_name,
            'snapshot_at' => now()->toIso8601String(),
        ];
    }

    /**
     * CheckoutCart hook: the frozen design of a cart line (null when the
     * line carries none or the design was deleted meanwhile). Also stamps
     * `last_ordered_at` — inside the checkout transaction, so a failed
     * checkout rolls it back too.
     *
     * @return array<string, mixed>|null
     */
    public static function snapshotForCartItem(CartItem $item): ?array
    {
        $id = $item->metadata['portrait_design_id'] ?? null;
        $design = $id !== null ? PortraitDesign::query()->find((int) $id) : null;

        if ($design === null) {
            return null;
        }

        $design->forceFill(['last_ordered_at' => now()])->save();

        return app(self::class)->snapshot($design);
    }

    /**
     * Cart metadata for "order this portrait": the design must be the
     * buyer's and the variant a frame. Returns [] when no design was sent.
     *
     * @return array{portrait_design_id?: int}
     */
    public static function cartMetadata(?User $user, ?PortraitDesign $design, ProductVariant $variant): array
    {
        if ($design === null) {
            return [];
        }

        $design->loadMissing('story');

        if ($user === null || $design->story === null || ! $design->story->isOwnedBy($user)) {
            throw ValidationException::withMessages(['portrait_design_uuid' => __('No encontramos ese portarretrato.')]);
        }

        if (! self::isFrameVariant($variant)) {
            throw ValidationException::withMessages(['portrait_design_uuid' => __('Ese diseño solo se puede pedir con el portarretrato.')]);
        }

        return ['portrait_design_id' => $design->id];
    }

    /**
     * Compact shape of a design on a cart line / order line (thumb + names).
     *
     * @param  array<string, mixed>|null  $metadata  CartItem or OrderItem metadata
     * @return array{uuid: string|null, title: string|null, template_label: string, thumb_url: string|null}|null
     */
    public function summary(?array $metadata): ?array
    {
        $snapshot = $metadata['portrait_design'] ?? null;
        $id = is_array($snapshot) ? ($snapshot['id'] ?? null) : ($metadata['portrait_design_id'] ?? null);

        if ($id === null) {
            return null;
        }

        $design = PortraitDesign::query()->with('mediaFile')->find((int) $id);
        $mediaId = is_array($snapshot) ? ($snapshot['media_file_id'] ?? null) : $design?->media_file_id;
        $file = $mediaId !== null ? ($design?->mediaFile?->id === (int) $mediaId ? $design->mediaFile : MediaFile::query()->find((int) $mediaId)) : null;
        $template = is_array($snapshot) ? (string) ($snapshot['template'] ?? '') : (string) $design?->template;

        if ($design === null && ! is_array($snapshot)) {
            return null;
        }

        return [
            'uuid' => is_array($snapshot) ? ($snapshot['uuid'] ?? null) : $design->uuid,
            'title' => is_array($snapshot) ? ($snapshot['title'] ?? null) : $design->title,
            'template_label' => $this->templateLabel($template),
            'thumb_url' => $file !== null && $file->isServable() ? ($file->url('card') ?? $file->url()) : null,
        ];
    }

    /**
     * Admin order detail: what production needs to print an ordered frame
     * (the frozen snapshot, the photo thumb and the file downloads).
     *
     * @return array<string, mixed>|null
     */
    public function forAdmin(OrderItem $item): ?array
    {
        $snapshot = $item->metadata['portrait_design'] ?? null;

        if (! is_array($snapshot)) {
            return null;
        }

        $summary = $this->summary($item->metadata);
        $fields = array_filter((array) ($snapshot['fields'] ?? []), fn ($v) => is_string($v) && $v !== '');
        $route = fn (array $q) => route('admin.orders.items.portrait', ['item' => $item->id, ...$q]);

        return [
            'title' => $snapshot['title'] ?? null,
            'template_label' => $this->templateLabel((string) ($snapshot['template'] ?? '')),
            'thumb_url' => $summary['thumb_url'] ?? null,
            'photo_available' => ($summary['thumb_url'] ?? null) !== null,
            'fields' => $fields,
            'story_title' => $snapshot['story_title'] ?? null,
            'story_url' => isset($snapshot['story_public_id']) ? route('legacy.story.show', ['story' => $snapshot['story_public_id']]) : null,
            'snapshot_at' => $snapshot['snapshot_at'] ?? null,
            'downloads' => [
                'print_shop_pdf' => $route(['salida' => 'print_shop', 'formato' => 'pdf', 'marcas' => 1]),
                'frame_jpg' => $route(['salida' => 'frame', 'formato' => 'jpg']),
            ],
        ];
    }

    public function templateLabel(string $key): string
    {
        $all = $this->templates->all();

        return isset($all[$key]) ? (string) ($all[$key]['label'] ?? $key) : $key;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function data(array $fields): PortraitData
    {
        $f = fn (string $k) => self::text($fields[$k] ?? null);

        return new PortraitData(
            name: $f('name'),
            eventLine: PortraitData::join([$f('event'), $f('details')]),
            time: $f('time'),
            resultLine: null,
            dedication: $f('dedication'),
            distance: $f('distance'),
            position: $f('position'),
            pace: $f('pace'),
            bib: $f('bib'),
            category: $f('category'),
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, string|null>
     */
    public static function fields(array $fields): array
    {
        $out = [];

        foreach (array_keys(self::FIELDS) as $key) {
            $out[$key] = self::text($fields[$key] ?? null);
        }

        return $out;
    }

    /**
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    public static function crop(mixed $crop): ?array
    {
        if (! is_array($crop) || ! isset($crop['x'], $crop['y'], $crop['w'], $crop['h'])) {
            return null;
        }

        return ['x' => (float) $crop['x'], 'y' => (float) $crop['y'], 'w' => (float) $crop['w'], 'h' => (float) $crop['h']];
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /**
     * The print is cut from the original; a preview only needs the display size.
     */
    private function bytes(MediaFile $file, bool $preview): string
    {
        $display = $file->resolveVariant('display')['path'] ?? null;
        $path = $preview ? ($display ?? $file->original_path ?? $file->path) : ($file->original_path ?? $display ?? $file->path);
        $bytes = $path !== null ? Storage::disk($file->disk)->get($path) : null;

        if (! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages(['photo' => __('No pudimos leer esa foto. Prueba con otra.')]);
        }

        return $bytes;
    }

    private function athleteName(LegacyStory $story): ?string
    {
        $athlete = $story->athlete;

        if ($athlete === null) {
            return null;
        }

        $name = filled($athlete->full_name) ? $athlete->full_name : trim($athlete->first_name.' '.$athlete->last_name);

        return $name !== '' ? $name : null;
    }
}
