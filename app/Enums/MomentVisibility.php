<?php

namespace App\Enums;

/**
 * Who can see a Legacy Moment — always on top of the author's profile
 * visibility (a private profile hides everything but the owner's view).
 */
enum MomentVisibility: string
{
    case Public = 'public';
    case Followers = 'followers';
    case Private = 'private';
}
