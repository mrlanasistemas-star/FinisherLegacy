<?php

namespace App\Http\Controllers;

use App\Models\CompanyGalleryItem;
use App\Models\CompanyMilestone;
use App\Models\CompanySetting;
use App\Models\Sport;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Nosotros" — who is behind Finisher Legacy. Every block is editable
 * from admin (CompanySetting / CompanyMilestone / CompanyGalleryItem);
 * nothing about the company's history, location or contact channels is
 * hardcoded or invented. Empty sections tell the page to render its
 * clearly-labelled placeholders instead.
 */
class AboutController extends Controller
{
    public function __invoke(): Response
    {
        $settings = CompanySetting::values();

        return Inertia::render('About', [
            'company' => [
                'intro' => $settings['about_intro'],
                'problem' => $settings['about_problem'],
                'origin' => $settings['about_origin'],
                'experience' => $settings['about_experience'],
                'vision' => $settings['about_vision'],
                'country' => $settings['country'],
                'city' => $settings['city'],
                'address' => $settings['address'],
            ],
            'channels' => CompanySetting::contactChannels(),
            'milestones' => CompanyMilestone::query()->where('is_visible', true)->ordered()->get()
                ->map(fn (CompanyMilestone $m) => [
                    'id' => $m->id,
                    'period' => $m->period,
                    'title' => $m->title,
                    'description' => $m->description,
                    'location' => $m->location,
                    'image_url' => $m->imageUrl(),
                ]),
            'gallery' => CompanyGalleryItem::query()->where('is_visible', true)->ordered()->get()
                ->map(fn (CompanyGalleryItem $item) => [
                    'id' => $item->id,
                    'image_url' => $item->imageUrl(),
                    'width' => $item->width,
                    'height' => $item->height,
                    'title' => $item->title,
                    'description' => $item->description,
                ]),
            'sports' => Sport::query()->where('active', true)->orderBy('sort_order')->pluck('name'),
        ]);
    }
}
