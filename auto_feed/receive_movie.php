<?php
/**
 * Receives movie data via HTTP POST from the Telegram bot (bot.py)
 * and generates an individual SEO page + updates the JSON feed + sitemap.
 *
 * Expected POST fields:
 *   title, year, quality, language, genre, date, desc, slug, thumbnail, source_url
 *
 * Security: requires a secret key via POST field 'secret' or header X-Webhook-Secret
 */

// ── Config ──────────────────────────────────────────────────────────
$SECRET      = 'tc_movie_hook_2026';   // must match bot.py config
$BASE_URL    = 'https://techandclick.site';
$SITE_ROOT   = dirname(__DIR__);       // .../public_html
$MOVIE_DIR   = $SITE_ROOT . '/movie';
$FEED_FILE   = __DIR__ . '/movie_posts.json';
$LOG_FILE    = __DIR__ . '/hook_movie.log';

// ── Auth check ──────────────────────────────────────────────────────
$provided = $_POST['secret'] ?? ($_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '');
if ($provided !== $SECRET) {
    http_response_code(403);
    echo "Forbidden";
    _log("AUTH FAILED from " . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    exit(1);
}

// ── Read input ──────────────────────────────────────────────────────
$title     = trim($_POST['title'] ?? '');
$year      = trim($_POST['year'] ?? '');
$quality   = trim($_POST['quality'] ?? '');
$language  = trim($_POST['language'] ?? '');
$genre     = trim($_POST['genre'] ?? '');
$date      = trim($_POST['date'] ?? date('Y-m-d'));
$desc      = trim($_POST['desc'] ?? '');
$slug      = trim($_POST['slug'] ?? '');
$thumbnail = trim($_POST['thumbnail'] ?? '');
$sourceUrl = trim($_POST['source_url'] ?? '');

if (!$title || !$slug) {
    http_response_code(400);
    echo "Missing title or slug";
    exit(1);
}

// ── Ensure directories ─────────────────────────────────────────────
if (!is_dir($MOVIE_DIR)) mkdir($MOVIE_DIR, 0755, true);

// ── Load existing feed ─────────────────────────────────────────────
$existing = [];
if (file_exists($FEED_FILE)) {
    $raw = file_get_contents($FEED_FILE);
    $existing = json_decode($raw, true) ?: [];
}
$existingSlugs = array_column($existing, 'slug');

// Skip if already exists
if (in_array($slug, $existingSlugs)) {
    echo "Already exists: {$slug}";
    _log("SKIP already exists: {$slug}");
    exit(0);
}

// ── Build movie record ─────────────────────────────────────────────
$movie = [
    'title'      => $title,
    'year'       => $year,
    'slug'       => $slug,
    'quality'    => $quality,
    'language'   => $language,
    'genre'      => $genre,
    'date'       => $date,
    'desc'       => mb_substr($desc, 0, 200),
    'thumbnail'  => $thumbnail,
    'source_url' => $sourceUrl,
];

// ── Generate SEO page ──────────────────────────────────────────────
generateMoviePage($movie, $MOVIE_DIR, $BASE_URL);

// ── Update feed ────────────────────────────────────────────────────
array_unshift($existing, $movie);
$existing = array_slice($existing, 0, 100);
file_put_contents($FEED_FILE, json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// ── Update sitemap ─────────────────────────────────────────────────
updateSitemap($existing, $SITE_ROOT, $BASE_URL);

echo "OK: {$slug}";
_log("CREATED: {$slug}");

// ══════════════════════════════════════════════════════════════════
// Helper functions
// ══════════════════════════════════════════════════════════════════

function _log(string $msg): void {
    global $LOG_FILE;
    $line = date('Y-m-d H:i:s') . " | " . $msg . "\n";
    @file_put_contents($LOG_FILE, $line, FILE_APPEND);
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
    $thumb  = htmlspecialchars($m['thumbnail'], ENT_QUOTES, 'UTF-8');

    // Pre-compute display values
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

    $pageUrl     = "{$baseUrl}/movie/{$slug}.php";
    $channelLink = "https://t.me/getlatestmoviebot";

    // Pre-compute conditional HTML
    $h1Text     = $title . ($year ? " ({$year})" : '');
    $qualityTag = $quality ? '<span class="tag">' . htmlspecialchars($quality) . '</span>' : '';
    $langTag    = $lang ? '<span class="tag">' . htmlspecialchars($lang) . '</span>' : '';
    $genreTag   = $genre ? '<span class="tag">' . htmlspecialchars($genre) . '</span>' : '';
    $yearLi     = $year ? '<li>' . htmlspecialchars($title) . ' ' . htmlspecialchars($year) . '</li>' : '';
    $qualityLi  = $quality ? '<li>' . htmlspecialchars($title) . ' ' . htmlspecialchars($quality) . '</li>' : '';
    $langLi     = $lang ? '<li>' . htmlspecialchars($title) . ' ' . htmlspecialchars($lang) . '</li>' : '';
    $genreLi    = $genre ? '<li>' . htmlspecialchars($genre) . ' movie ' . htmlspecialchars($year) . '</li>' : '';
    $aboutExtra = '';
    if ($year)  $aboutExtra .= ', "' . htmlspecialchars($year) . '"';
    if ($genre) $aboutExtra .= ', "' . htmlspecialchars($genre) . '"';

    $ogImage = $thumb ? '<meta property="og:image" content="' . $thumb . '" />' : '';

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
  {$ogImage}

  <meta name="twitter:card" content="summary_large_image" />
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

    $existing = '';
    if (file_exists($sitemapPath)) {
        $existing = file_get_contents($sitemapPath);
    }

    // Remove old movie/ entries
    $existing = preg_replace('#\s*<url>\s*<loc>' . preg_quote($baseUrl, '#') . '/movie/.*?</url>\s*#s', '', $existing);

    // Build movie URL blocks
    $movieUrls = '';
    foreach (array_slice($movies, 0, 50) as $m) {
        $url = "{$baseUrl}/movie/{$m['slug']}.php";
        $movieUrls .= "  <url>\n    <loc>{$url}</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.7</priority>\n  </url>\n";
    }

    $existing = str_replace('</urlset>', $movieUrls . '</urlset>', $existing);
    file_put_contents($sitemapPath, $existing);
}
