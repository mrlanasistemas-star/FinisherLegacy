<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Event;
use App\Models\Product;
use App\Queries\Store\PublicCollections;
use Illuminate\Http\Response;

/**
 * GET /sitemap.xml — indexable public pages only: marketing pages,
 * published collections, active products and published events. Athlete
 * profiles, Legacy stories, tag URLs (/t/…) and recipient links are
 * private-by-default and never listed. Built from APP_URL via route().
 */
class SitemapController extends Controller
{
    public function __invoke(PublicCollections $collections): Response
    {
        $urls = [
            ['loc' => route('home'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => route('collections.index'), 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => route('how-it-works'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => route('store.products.index'), 'changefreq' => 'weekly', 'priority' => '0.7'],
            ['loc' => route('events.index'), 'changefreq' => 'daily', 'priority' => '0.6'],
            ['loc' => route('about'), 'changefreq' => 'monthly', 'priority' => '0.4'],
            ['loc' => route('contact'), 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        foreach ($collections->forSitemap() as $collection) {
            $urls[] = ['loc' => route('collections.show', $collection), 'lastmod' => $collection->updated_at?->toAtomString(), 'changefreq' => 'weekly', 'priority' => '0.8'];
        }

        Product::query()
            ->where('active', true)
            ->where('status', ProductStatus::Active->value)
            ->where('type', '!=', ProductType::DigitalPhoto->value)
            ->orderBy('sort_order')
            ->limit(1000)
            ->get(['id', 'slug', 'updated_at'])
            ->each(function (Product $product) use (&$urls) {
                $urls[] = ['loc' => route('store.products.show', $product), 'lastmod' => $product->updated_at?->toAtomString(), 'changefreq' => 'weekly', 'priority' => '0.7'];
            });

        Event::query()
            ->where('status', EventStatus::Published)
            ->orderByDesc('id')
            ->limit(1000)
            ->get(['id', 'slug', 'updated_at'])
            ->each(function (Event $event) use (&$urls) {
                $urls[] = ['loc' => route('events.show', $event), 'lastmod' => $event->updated_at?->toAtomString(), 'changefreq' => 'weekly', 'priority' => '0.5'];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc>'
                .(isset($url['lastmod']) ? '<lastmod>'.e($url['lastmod']).'</lastmod>' : '')
                .'<changefreq>'.$url['changefreq'].'</changefreq><priority>'.$url['priority'].'</priority></url>'."\n";
        }

        return response($xml."</urlset>\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
