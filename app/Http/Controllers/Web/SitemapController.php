<?php

namespace App\Http\Controllers\Web;

use App\Enums\PetStatus;
use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * `GET sitemap.xml` for crawlers: the public pages and every listing a visitor
 * can open — available, not soft-deleted, owner active — newest first.
 *
 * shortcut: one file capped at 5000 URLs, cached for an hour; move to a
 * sitemap index when listings pass 5000.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap', 3600, function (): string {
            $pets = Pet::query()
                ->where('status', PetStatus::Available)
                ->whereHas('user', fn (Builder $users) => User::activeOnly($users))
                ->select(['slug', 'updated_at'])
                ->latest('updated_at')
                ->limit(5000)
                ->get();

            return view('sitemap', [
                'pages' => [route('home'), route('help'), route('guidelines'), route('support')],
                'pets'  => $pets,
            ])->render();
        });

        return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
