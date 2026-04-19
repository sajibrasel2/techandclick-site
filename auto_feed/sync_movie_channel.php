<?php
/**
 * Fetches recent posts from Telegram movie channel (@getlatestmoviebot)
 * via Bot API, parses movie info, and generates:
 *   1. Individual SEO page per movie  (movie/movie-slug.php)
 *   2. JSON feed for listing page     (auto_feed/movie_posts.json)
 *   3. Updates sitemap with new movie URLs
 *
 * Run via cron every 30 min:
 *   php /home/techandc/public_html/auto_feed/sync_movie_channel.php
 */

// ── Config ──────────────────────────────────────────────────────────
$BOT_TOKEN   = getenv('TC_TG_BOT_TOKEN') ?: '';
$CHANNEL     = '@getlatestmoviebot';
$BASE_URL    = 'https://techandclick.site';
$SITE_ROOT   = dirname(__DIR__);          // .../public_html
$MOVIE_DIR   = $SITE_ROOT . '/movie';     // .../public_html/movie
$FEED_FILE   = __DIR__ . '/movie_posts.json';
$LIMIT       = 20;                         // fetch last 20 posts

if (!$BOT_TOKEN) {
    echo "Missing env TC_TG_BOT_TOKEN\n";
    exit(1);
}

// ── Ensure directories ─────────────────────────────────────────────
if (!is_dir($MOVIE_DIR)) mkdir($MOVIE_DIR, 0755, true);

// ── Load existing feed (avoid duplicates) ──────────────────────────
$existing = [];
if (file_exists($FEED_FILE)) {
    $raw = file_get_contents($FEED_FILE);
    $existing = json_decode($raw, true) ?: [];
}
$existingSlugs = array_column($existing, 'slug');

// ── Fetch channel posts via Bot API ────────────────────────────────
$url = "https://api.telegram.org/bot{$BOT_TOKEN}/getUpdates?limit=100&allowed_updates=[\"channel_post\"]";
$resp = file_get_contents($url);
$data = json_decode($resp, true);

if (!$data || !isset($data['result'])) {
    echo "No data from Telegram API\n";
    exit(0);
}

$newMovies = [];

foreach ($data['result'] as $update) {
    if (!isset($update['channel_post'])) continue;
    $post = $update['channel_post'];

    // Only our channel
    $chatId = $post['chat']['id'] ?? 0;
    $chatUser = $post['chat']['username'] ?? '';
    if (ltrim($chatUser, '@') !== ltrim($CHANNEL, '@') && $chatId !== $CHANNEL) continue;

    $text = $post['text'] ?? ($post['caption'] ?? '');
    if (!$text) continue;

    $msgId = $post['message_id'];
    $date  = date('Y-m-d', $post['date']);

    // ── Parse movie info from post text ────────────────────────────
    $movie = parseMoviePost($text, $date, $msgId);
    if (!$movie) continue;

    $slug = $movie['slug'];

    // Skip if already processed
    if (in_array($slug, $existingSlugs)) continue;

    $newMovies[] = $movie;
}

// ── Generate SEO pages for new movies ──────────────────────────────
foreach ($newMovies as $movie) {
    generateMoviePage($movie, $MOVIE_DIR, $BASE_URL);
}

// ── Merge and save feed ────────────────────────────────────────────
$allMovies = array_merge($newMovies, $existing);
// Keep max 100
$allMovies = array_slice($allMovies, 0, 100);

file_put_contents($FEED_FILE, json_encode($allMovies, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// ── Update sitemap ────────────────────────────────────────────────
updateSitemap($allMovies, $SITE_ROOT, $BASE_URL);

echo "Synced " . count($newMovies) . " new movies\n";

// ══════════════════════════════════════════════════════════════════
// Helper functions
// ══════════════════════════════════════════════════════════════════

function parseMoviePost(string $text, string $date, int $msgId): ?array {
    // Try to extract movie name from common patterns:
    // 🎬 Movie Name (Year)  |  🎥 Movie Name  |  **Movie Name**  |  plain text first line

    $lines = explode("\n", $text);
    $firstLine = trim($lines[0] ?? '');
    if (!$firstLine) return null;

    // Remove emoji prefixes
    $clean = preg_replace('/^[\p{Emoji}\s]+/u', '', $firstLine);
    $clean = trim($clean);

    // Try to extract year
    $year = '';
    if (preg_match('/\b(19\d{2}|20\d{2})\b/', $clean, $m)) {
        $year = $m[1];
    }

    // Remove year from title for clean name
    $title = preg_replace('/\s*[\(\[]?\b(19\d{2}|20\d{2})\b[\)\]]?\s*/', '', $clean);
    $title = trim($title, " \t\n\r\0\x0B-—:");

    if (!$title || strlen($title) < 2) return null;

    // Generate slug
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($year) $slug .= '-' . $year;

    // Try to detect genre/quality from full text
    $quality = '';
    if (preg_match('/\b(720p|1080p|4K|HDRip|WEB-DL|WEBRip|BluRay|CAM|HDR)\b/i', $text, $m)) {
        $quality = $m[1];
    }

    $language = '';
    if (preg_match('/\b(Hindi|Bangla|Bengali|Tamil|Telugu|English|Dual|Dubbed|Subbed)\b/i', $text, $m)) {
        $language = $m[1];
    }

    $genre = '';
    if (preg_match('/\b(Action|Comedy|Drama|Thriller|Horror|Romance|Sci-Fi|Fantasy|Animation|Adventure|Crime|Mystery)\b/i', $text, $m)) {
        $genre = $m[1];
    }

    // Clean description (first 200 chars)
    $desc = trim(strip_tags($text));
    $desc = mb_substr($desc, 0, 200);

    return [
        'title'     => $title,
        'year'      => $year,
        'slug'      => $slug,
        'quality'   => $quality,
        'language'  => $language,
        'genre'     => $genre,
        'date'      => $date,
        'desc'      => $desc,
        'msg_id'    => $msgId,
    ];
}

function generateMoviePage(array $m, string $dir, string $baseUrl): void {
    $slug   = $m['slug'];
    $title  = $m['title'];
    $year   = $m['year'];
    $quality= $m['quality'];
    $lang   = $m['language'];
    $genre  = $m['genre'];
    $date   = $m['date'];
    $desc   = htmlspecialchars($m['desc'], ENT_QUOTES, 'UTF-8');

    // Pre-compute all display values (no ternary in heredoc)
    $pageTitle = $title . ($year ? " ({$year})" : '') . " — Movie Info & Update";
    $metaDesc  = $title . ($year ? " {$year}" : '') . " movie update. "
               . ($quality ? "{$quality} " : '')
               . ($lang ? "{$lang} " : '')
               . ($genre ? "{$genre} movie. " : '')
               . "Search and find {$title} on Telegram bot @GetLatestMoviesBot.";

    $keywords = strtolower($title . " movie, "
               . ($year ? $title . " {$year}, " : '')
               . $title . " update, "
               . ($quality ? $title . " {$quality}, " : '')
               . ($lang ? $title . " {$lang}, " : '')
               . "latest movie update, new movie release, "
               . ($genre ? "{$genre} movie, " : '')
               . ($year ? "{$year} movie, " : '')
               . "movie search, movie info, cast plot rating");

    $pageUrl  = "{$baseUrl}/movie/{$slug}.php";
    $channelLink = "https://t.me/getlatestmoviebot";

    // Pre-compute conditional HTML blocks
    $h1Text       = $title . ($year ? " ({$year})" : '');
    $qualityTag   = $quality ? '<span class="tag">' . htmlspecialchars($quality) . '</span>' : '';
    $langTag      = $lang ? '<span class="tag">' . htmlspecialchars($lang) . '</span>' : '';
    $genreTag     = $genre ? '<span class="tag">' . htmlspecialchars($genre) . '</span>' : '';
    $yearLi       = $year ? '<li>' . htmlspecialchars($title) . ' ' . htmlspecialchars($year) . '</li>' : '';
    $qualityLi    = $quality ? '<li>' . htmlspecialchars($title) . ' ' . htmlspecialchars($quality) . '</li>' : '';
    $langLi       = $lang ? '<li>' . htmlspecialchars($title) . ' ' . htmlspecialchars($lang) . '</li>' : '';
    $genreLi      = $genre ? '<li>' . htmlspecialchars($genre) . ' movie ' . htmlspecialchars($year) . '</li>' : '';
    $aboutExtra   = '';
    if ($year)  $aboutExtra .= ', "' . htmlspecialchars($year) . '"';
    if ($genre) $aboutExtra .= ', "' . htmlspecialchars($genre) . '"';

    $html = <<<HTML
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{$pageTitle}</title>
  <meta name="description" content="{$metaDesc}" />
  <meta name="keywords" content="{$keywords}" />
  <meta name="robots" content="index,follow,max-image-preview:large" />
  <link rel="canonical" href="{$pageUrl}" />
  <meta name="theme-color" content="#0b1220" />

  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="Tech &amp; Click" />
  <meta property="og:title" content="{$pageTitle}" />
  <meta property="og:description" content="{$metaDesc}" />
  <meta property="og:url" content="{$pageUrl}" />

  <meta name="twitter:card" content="summary" />
  <meta name="twitter:title" content="{$pageTitle}" />
  <meta name="twitter:description" content="{$metaDesc}" />

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "WebPage",
          "name": "{$pageTitle}",
          "url": "{$pageUrl}",
          "inLanguage": "bn",
          "isPartOf": {"@type": "WebSite", "name": "Tech & Click", "url": "{$baseUrl}/"},
          "about": ["{$title}", "Movie Update"{$aboutExtra}],
          "keywords": "{$keywords}"
        },
        {
          "@type": "FAQPage",
          "name": "{$title} FAQ",
          "inLanguage": "bn",
          "mainEntity": [
            {
              "@type": "Question",
              "name": "{$title} মুভি কোথায় পাওয়া যাবে?",
              "acceptedAnswer": {"@type": "Answer", "text": "@GetLatestMoviesBot বটে '{$title}' লিখে সার্চ করুন—রেজাল্ট পেয়ে যাবেন।"}
            },
            {
              "@type": "Question",
              "name": "{$title} এর quality কী?",
              "acceptedAnswer": {"@type": "Answer", "text": "বর্তমানে পাওয়া যাচ্ছে {$quality} quality এ।"}
            }
          ]
        }
      ]
    }
  </script>

  <style>
    :root{--bg:#0b1220;--bg2:#0f1b33;--border:rgba(255,255,255,.12);--text:#e5e7eb;--muted:#a7b0c0;--shadow:0 18px 45px rgba(0,0,0,.35);--radius:16px;--container:980px}
    *{box-sizing:border-box}
    body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,"Noto Sans";color:var(--text);background:linear-gradient(180deg,var(--bg),var(--bg2))}
    a{color:inherit;text-decoration:none}
    .container{max-width:var(--container);margin:0 auto;padding:22px 16px}
    .box{border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));padding:18px}
    .top{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 14px;border-radius:999px;border:1px solid var(--border);background:rgba(255,255,255,.05);font-weight:900;font-size:13px}
    .btn-primary{background:linear-gradient(135deg, rgba(36,161,222,.95), rgba(34,197,94,.90));border-color:transparent;color:#041018}
    h1{margin:14px 0 10px;font-size:28px;line-height:1.15}
    p{margin:0;color:var(--muted);font-weight:700;line-height:1.7}
    h2{margin:18px 0 10px;font-size:18px}
    .meta{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0}
    .tag{padding:6px 12px;border-radius:999px;border:1px solid var(--border);background:rgba(255,255,255,.04);font-weight:800;font-size:12px}
    .list{margin:8px 0 0;padding-left:18px;color:var(--muted);font-weight:700;line-height:1.7}
    .list li{margin:6px 0}
    .links{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}
    .footer{margin-top:16px;border-top:1px solid rgba(255,255,255,.10);padding-top:14px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;color:rgba(229,231,235,.85)}
  </style>
</head>
<body>
  <div class="container">
    <div class="box">
      <div class="top">
        <a class="btn" href="{$baseUrl}/">← হোম</a>
        <a class="btn" href="{$baseUrl}/latest-movies.php">সব মুভি</a>
        <a class="btn btn-primary" href="https://t.me/GetLatestMoviesBot" target="_blank" rel="noopener">Search Bot</a>
      </div>

      <h1>{$h1Text}</h1>
      <div class="meta">
        {$qualityTag}
        {$langTag}
        {$genreTag}
        <span class="tag">{$date}</span>
      </div>
      <p>{$desc}</p>

      <h2>Quick links</h2>
      <div class="links">
        <a class="btn btn-primary" href="https://t.me/GetLatestMoviesBot" target="_blank" rel="noopener">Search on Bot</a>
        <a class="btn" href="{$channelLink}" target="_blank" rel="noopener">Movie Channel</a>
      </div>

      <h2>Related searches</h2>
      <ul class="list">
        <li>{$title} movie</li>
        {$yearLi}
        {$qualityLi}
        {$langLi}
        <li>latest movie update</li>
        <li>new movie release {$year}</li>
        {$genreLi}
        <li>movie search by name</li>
        <li>movie info cast plot rating</li>
      </ul>

      <h2>Other pages</h2>
      <div class="links">
        <a class="btn" href="{$baseUrl}/education-board-result-bot.php">Result Bot</a>
        <a class="btn" href="{$baseUrl}/daraz-offer.php">Daraz Offer</a>
      </div>

      <div class="footer">
        <div>© <span id="year"></span> Tech &amp; Click — techandclick.site</div>
        <div><a href="https://t.me/GetLatestMoviesBot" target="_blank" rel="noopener">t.me/GetLatestMoviesBot</a></div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      var yearEl = document.getElementById('year');
      if (yearEl) yearEl.textContent = new Date().getFullYear();
    })();
  </script>
</body>
</html>
HTML;

    file_put_contents($dir . "/{$slug}.php", $html);
}

function updateSitemap(array $movies, string $siteRoot, string $baseUrl): void {
    $sitemapPath = $siteRoot . '/sitemap.xml';

    // Read existing sitemap and remove old movie/ entries
    $existing = '';
    if (file_exists($sitemapPath)) {
        $existing = file_get_contents($sitemapPath);
    }

    // Build movie URL blocks
    $movieUrls = '';
    foreach (array_slice($movies, 0, 50) as $m) {
        $url = "{$baseUrl}/movie/{$m['slug']}.php";
        $movieUrls .= "  <url>\n    <loc>{$url}</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.7</priority>\n  </url>\n";
    }

    // Remove any existing movie/ block from sitemap
    $existing = preg_replace('#\s*<url>\s*<loc>' . preg_quote($baseUrl, '#') . '/movie/.*?</url>\s*#s', '', $existing);

    // Insert movie URLs before </urlset>
    $existing = str_replace('</urlset>', $movieUrls . '</urlset>', $existing);

    file_put_contents($sitemapPath, $existing);
}
