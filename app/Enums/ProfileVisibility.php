<?php

namespace App\Enums;

/**
 * public    → anyone (not blocked) sees the profile and its public posts.
 * followers → signed-in people may see the header and follow; content is
 *             for followers only; never listed in search/explore.
 * private   → owner only.
 */
enum ProfileVisibility: string
{
    case Public = 'public';
    case Followers = 'followers';
    case Private = 'private';
}
