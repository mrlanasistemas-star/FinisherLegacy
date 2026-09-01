<?php

namespace App\Enums;

/**
 * What an OrganizerDataSource feeds (brief §26/§28): a source scoped to a
 * single purpose only ever shows up as a candidate for that purpose's
 * selector; 'both' shows up for either.
 */
enum DataSourcePurpose: string
{
    case Participants = 'participants';
    case Results = 'results';
    case Both = 'both';
}
