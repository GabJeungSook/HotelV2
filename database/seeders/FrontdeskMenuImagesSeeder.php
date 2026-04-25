<?php

namespace Database\Seeders;

use App\Models\FrontdeskMenu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds menu images for frontdesk_menus.
 *
 * Strategy:
 *   1. If fixture exists in database/seeders/menu-images/<slug>.jpg → copy.
 *   2. Else, fetch HiClipart search results for an item-specific query and
 *      pick the first product image URL. Download, save to fixture + storage.
 *      HiClipart returns clean transparent-style product photos with no humans.
 *
 * Run:
 *     php artisan db:seed --class=FrontdeskMenuImagesSeeder
 *     ARTISAN_FORCE_IMG=1 php artisan db:seed --class=FrontdeskMenuImagesSeeder
 */
class FrontdeskMenuImagesSeeder extends Seeder
{
    public function run(): void
    {
        $force = (bool) env('ARTISAN_FORCE_IMG', false);
        $publicDir  = 'menu-images';
        $fixtureDir = database_path('seeders/menu-images');

        Storage::disk('public')->makeDirectory($publicDir);
        if (!is_dir($fixtureDir)) {
            mkdir($fixtureDir, 0755, true);
        }

        $menus = FrontdeskMenu::all();
        $this->command?->info("Seeding images for {$menus->count()} frontdesk menus...");

        $copied = 0;
        $downloaded = 0;
        $failed = 0;

        foreach ($menus as $menu) {
            $slug = Str::slug($menu->name) ?: 'item-' . $menu->id;
            $publicPath  = "{$publicDir}/{$slug}.jpg";
            $fixturePath = "{$fixtureDir}/{$slug}.jpg";

            $haveFixture = is_file($fixturePath) && filesize($fixturePath) > 1500;

            if ($haveFixture && !$force) {
                Storage::disk('public')->put($publicPath, file_get_contents($fixturePath));
                $menu->update(['image' => $publicPath]);
                $copied++;
                $this->command?->info("  [copy] {$menu->name}  <- fixture");
                continue;
            }

            $queries = $this->queriesFor($menu->name);
            $imageUrl = null;
            $usedQuery = null;
            foreach ($queries as $q) {
                $imageUrl = $this->findHiClipartImage($q);
                if ($imageUrl) {
                    $usedQuery = $q;
                    break;
                }
                usleep(300000);
            }

            if (!$imageUrl) {
                $this->command?->warn("  [skip] {$menu->name}: no HiClipart result (tried: " . implode(' | ', $queries) . ')');
                $failed++;
                continue;
            }

            try {
                $bytes = Http::timeout(25)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; menu-image-seeder)'])
                    ->withOptions(['allow_redirects' => true])
                    ->get($imageUrl)
                    ->body();

                if (strlen($bytes) < 2000) {
                    $this->command?->warn("  [skip] {$menu->name}: image too small ({$query})");
                    $failed++;
                    continue;
                }

                file_put_contents($fixturePath, $bytes);
                Storage::disk('public')->put($publicPath, $bytes);
                $menu->update(['image' => $publicPath]);

                $kb = round(strlen($bytes) / 1024);
                $this->command?->info("  [dl]   {$menu->name}  <- hiclipart('{$usedQuery}') {$kb}KB");
                $downloaded++;

                // be polite — short delay between requests
                usleep(500000);
            } catch (\Throwable $e) {
                $this->command?->warn("  [fail] {$menu->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->command?->info("Done. Copied: {$copied}, downloaded: {$downloaded}, failed: {$failed}");
        $this->command?->info("Fixtures: database/seeders/menu-images/  — commit these for offline reuse.");
    }

    /**
     * Ordered list of search queries to try for an item, from most specific
     * to most generic. The first one that returns a HiClipart hit wins.
     */
    private function queriesFor(string $name): array
    {
        $n = strtolower($name);

        $direct = [
            'coke'         => ['coca cola can', 'coca cola', 'soda can'],
            'pepsi'        => ['pepsi can', 'pepsi', 'soda can'],
            'sprite'       => ['sprite soda can', 'sprite drink', 'soda can'],
            '7-up'         => ['7up soda can', '7up', 'lemon soda can'],
            '7up'          => ['7up soda can', '7up', 'lemon soda can'],
            'mountain dew' => ['mountain dew can', 'mountain dew', 'soda can'],
            'royal'        => ['royal tru orange soda', 'royal soda', 'orange soda can', 'soda can'],
            'pineorange'   => ['pineapple orange juice', 'orange juice', 'fruit juice'],
            'pineapple'    => ['pineapple juice bottle', 'pineapple juice', 'fruit juice'],
            'mango nectar' => ['mango nectar drink', 'mango juice bottle', 'fruit juice'],
            'mango'        => ['mango juice', 'mango drink', 'fruit juice'],
            'four season'  => ['four seasons juice', 'fruit punch drink', 'fruit juice bottle', 'juice drink'],
            'fit n right'  => ['fit and right juice', 'fruit juice bottle', 'juice drink', 'orange juice'],
            'minute maid'  => ['minute maid orange juice', 'minute maid', 'orange juice'],
            'cobra'        => ['cobra energy drink', 'energy drink can', 'energy drink'],
            'cali'         => ['calamansi juice', 'calamansi drink', 'lemonade bottle', 'fruit juice'],
            'c2'           => ['c2 iced tea', 'iced tea bottle', 'tea drink'],
            'mineral'      => ['mineral water bottle', 'water bottle'],
            'water'        => ['mineral water bottle', 'water bottle'],
            'junkfood'     => ['potato chips bag', 'snack chips', 'crisps bag'],
            'chips'        => ['potato chips bag', 'snack chips'],
            'soap'         => ['soap bar', 'bath soap'],
            'shampoo'      => ['shampoo bottle', 'shampoo'],
            'conditioner'  => ['conditioner bottle', 'hair conditioner', 'shampoo bottle'],
            'toothbrush'   => ['toothbrush'],
            'toothpaste'   => ['toothpaste tube', 'toothpaste'],
            'shaver'       => ['shaver razor', 'razor'],
            'modess'       => ['sanitary pad', 'pad hygiene'],
        ];

        foreach ($direct as $needle => $queries) {
            if (str_contains($n, $needle)) {
                return $queries;
            }
        }

        return [strtolower($name), 'product'];
    }

    /**
     * Scrape HiClipart search results and return the first full-resolution
     * image URL (skips thumbnails). Returns null on failure.
     */
    private function findHiClipartImage(string $query): ?string
    {
        try {
            $html = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
                ->get('https://www.hiclipart.com/search', ['clipart' => $query])
                ->body();
        } catch (\Throwable $e) {
            return null;
        }

        // Extract every preview URL, drop thumbnails, keep first full-size.
        if (!preg_match_all('#https://p[0-9]+\.hiclipart\.com/preview/[^\s"]+\.(?:png|jpg)#', $html, $m)) {
            return null;
        }

        foreach ($m[0] as $url) {
            if (str_ends_with($url, '-thumbnail.jpg') || str_ends_with($url, '-thumbnail.png')) {
                continue;
            }
            return $url;
        }

        return null;
    }
}
