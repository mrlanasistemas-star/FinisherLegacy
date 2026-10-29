<?php

namespace App\Http\Controllers;

use App\Http\Resources\Api\V1\AthleteResource;
use App\Queries\Social\MutualConnections;
use App\Queries\Social\PeopleQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Personas" (/comunidad/personas) — the people hub of Comunidad:
 * Sugeridos · Amigos de amigos · Amigos · Seguidores · Siguiendo ·
 * Bloqueados, with a name/@username filter. Lists come from
 * App\Queries\Social\PeopleQuery (privacy via SocialVisibility); each page
 * is annotated in bulk with mutuals/follow state (MutualConnections).
 */
class CommunityPeopleController extends Controller
{
    private const int PER_PAGE = 24;

    public function index(Request $request, PeopleQuery $people, MutualConnections $mutuals): Response
    {
        $viewer = $request->user();
        $tab = in_array($request->query('tab'), PeopleQuery::TABS, true) ? (string) $request->query('tab') : PeopleQuery::SUGGESTED;
        $search = trim(mb_substr($request->string('q')->toString(), 0, 60));

        return Inertia::render('community/People', [
            'tab' => $tab,
            'q' => $search,
            'people' => Inertia::scroll(function () use ($people, $mutuals, $viewer, $tab, $search) {
                $page = $people->builder($viewer, $tab, $search)
                    ->paginate(self::PER_PAGE)
                    ->withQueryString();

                if ($tab !== PeopleQuery::BLOCKED) {
                    $mutuals->annotate($viewer, $page->getCollection());
                }

                return AthleteResource::collection($page);
            }),
            'counts' => fn () => $people->counts($viewer),
        ]);
    }

    /**
     * The old "Mis conexiones" URL (/comunidad/conexiones) lives on as the
     * matching tab of the hub.
     */
    public function legacy(Request $request): RedirectResponse
    {
        $tab = in_array($request->query('tab'), [PeopleQuery::FOLLOWERS, PeopleQuery::BLOCKED], true)
            ? (string) $request->query('tab')
            : PeopleQuery::FOLLOWING;

        return to_route('community.people', ['tab' => $tab], 301);
    }
}
