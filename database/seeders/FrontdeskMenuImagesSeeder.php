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

            $url = "https://picsum.photos/seed/{$slug}/400/400";

            try {
                $response = Http::timeout(20)
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
}
