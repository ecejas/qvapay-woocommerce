# WordPress.org plugin directory assets

This folder is reserved for the visual assets shown on the wordpress.org
plugin directory listing. These files do **not** ship inside the plugin
zip — they live in the SVN repo's separate `/assets/` path once the plugin
is approved.

Expected files (PNG, sRGB, no transparency):

| File                  | Dimensions  | Purpose                          |
|-----------------------|-------------|----------------------------------|
| `banner-1544x500.png` | 1544 × 500  | Header banner (retina).          |
| `banner-772x250.png`  |  772 × 250  | Header banner (standard).        |
| `icon-256x256.png`    |  256 × 256  | Listing icon (retina).           |
| `icon-128x128.png`    |  128 × 128  | Listing icon (standard).         |
| `screenshot-*.png`    | recommended | Numbered screenshots referenced  |
|                       |  1280 × 800 | from `readme.txt`'s Screenshots. |

See: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
