<?php

namespace App\Enums;

/**
 * The whitelist of Product content section shapes (product UX
 * consolidation brief §111-§113) — `content` json shape per type:
 * - text: {body: string}
 * - features: {items: string[]}
 * - steps: {items: {title: string, body: string}[]}
 * - video: {product_media_id: int}
 * - faq: {items: {question: string, answer: string}[]}
 */
enum ProductContentSectionType: string
{
    case Text = 'text';
    case Features = 'features';
    case Steps = 'steps';
    case Video = 'video';
    case Faq = 'faq';
}
