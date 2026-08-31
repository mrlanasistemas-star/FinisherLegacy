<?php

namespace App\Enums;

/**
 * ResolveIncident requires one of these — "resolved" with no recorded
 * action doesn't help anyone (brief §136-§138).
 */
enum IncidentResolutionType: string
{
    case Fixed = 'fixed';
    case Reprinted = 'reprinted';
    case Refunded = 'refunded';
    case NoActionNeeded = 'no_action_needed';
    case Escalated = 'escalated';
    case Other = 'other';
}
