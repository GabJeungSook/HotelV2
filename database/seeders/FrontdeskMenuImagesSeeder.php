<?php

namespace Database\Seeders;

use App\Models\FrontdeskMenu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds menu images for frontdesk_menus by downloading from Picsum
 * (deterministic per item slug — same image every time you run it).
 *
 * Usage:
 *     php artisan db:seed --class=FrontdeskMenuImagesSeeder
 *
 * Re-run safe: skips items that already have an image unless --force.
 * To force re-download for everything:
 *     ARTISAN_FORCE_IMG=1 php artisan db:seed --class=FrontdeskMenuImagesSeeder
 */
class FrontdeskMenuImagesSeeder extends Seeder
{
    public function run(): void
    {
        $force = (bool) env('ARTISAN_FORCE_IMG', false);
        $dir = 'menu-images';
        Storage::disk('public')->makeDirectory($dir);

        $menus = FrontdeskMenu::all();
        $this->command?->info("Seeding images for {$menus->count()} frontdesk menus...");

        $ok = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($menus as $menu) {
            if (!$force && $menu->image && Storage::disk('public')->exists($menu->image)) {
                $skipped++;
                continue;
            }

            $slug = Str::slug($menu->name) ?: 'item-' . $menu->id;
            $relativePath = "{$dir}/{$slug}.jpg";

            $keyword = $this->keywordFor($menu->name);
            // LoremFlickr — Flickr-tagged photo by keyword. Free, no API key.
            $url = "https://loremflickr.com/400/400/" . urlencode($keyword);

            try {
                $response = Http::timeout(25)
                    ->withOptions(['allow_redirects' => true])
                    ->get($url);

                if (!$response->ok() || $response->body() === '') {
                    $this->command?->warn("  [skip] {$menu->name}: HTTP {$response->status()}");
                    $failed++;
                    continue;
                }

                Storage::disk('public')->put($relativePath, $response->body());
                $menu->update(['image' => $relativePath]);
                $this->command?->info("  [ok]   {$menu->name}  -> {$relativePath}");
                $ok++;
            } catch (\Throwable $e) {
                $this->command?->warn("  [fail] {$menu->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->command?->info("Done. Saved: {$ok}, skipped (already had image): {$skipped}, failed: {$failed}");
    }

    /**
     * Map menu item name to a search keyword that matches the product type.
     * Falls back to a generic "drink" keyword if nothing matches.
     */
    private function keywordFor(string $name): string
    {
        $n = strtolower($name);

        // direct brand matches first
        $directHits = [
            'coke'         => 'coca-cola',
            'pepsi'        => 'pepsi',
            'sprite'       => 'sprite-soda',
            '7-up'         => '7up-soda',
            '7up'          => '7up-soda',
            'mountain dew' => 'mountain-dew',
            'royal'        => 'orange-soda',
            'pineapple'    => 'pineapple-juice',
            'pineorange'   => 'pineapple-juice',
            'mango'        => 'mango-juice',
            'four season'  => 'fruit-juice',
            'fit n right'  => 'fruit-juice',
            'minute maid'  => 'orange-juice',
            'cobra'        => 'energy-drink',
            'cali'         => 'calamansi-juice',
            'c2'           => 'iced-tea',
            'mineral'      => 'bottled-water',
            'water'        => 'bottled-water',
            'junkfood'     => 'snack-chips',
            'chips'        => 'snack-chips',
            'soap'         => 'soap-bar',
            'shampoo'      => 'shampoo-bottle',
            'conditioner'  => 'shampoo-bottle',
            'toothbrush'   => 'toothbrush',
            'toothpaste'   => 'toothpaste',
            'shaver'       => 'razor',
            'modess'       => 'sanitary-napkin',
        ];

        foreach ($directHits as $needle => $keyword) {
            if (str_contains($n, $needle)) {
                return $keyword;
            }
        }

        // category fallback
        if (preg_match('/(juice|nectar|orange|apple|mango|pineapple|fruit)/', $n)) {
            return 'fruit-juice';
        }
        if (preg_match('/(soda|cola|drink|cobra|royal|sprite)/', $n)) {
            return 'soft-drink';
        }
        if (preg_match('/(soap|shampoo|condition|tooth|shaver|razor|napkin|toilet)/', $n)) {
            return 'toiletries';
        }

        return 'beverage';
    }
}
