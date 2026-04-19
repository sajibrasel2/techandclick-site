<?php
/**
 * Latest Movies listing page — reads from auto_feed/movie_posts.json
 * Each movie links to its individual SEO page: /movie/slug.php
 */
$feedFile = __DIR__ . '/auto_feed/movie_posts.json';
$movies = [];
if (file_exists($feedFile)) {
    $raw = file_get_contents($feedFile);
    $movies = json_decode($raw, true) ?: [];
}
$total = count($movies);
?>
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Latest Movie Updates — New Releases & Search | techandclick.site</title>
  <meta name="description" content="Latest movie updates, new release alerts, movie search by name. Find Bollywood, Hollywood, Bangla, South Indian movie updates — all in one place." />
  <meta name="keywords" content="latest movie update, new movie release, movie search by name, bollywood movie update, hollywood movie update, bangla movie, south indian movie, movie info, cast plot rating, upcoming movie, movie release alert" />
  <meta name="robots" content="index,follow,max-image-preview:large" />
  <link rel="canonical" href="https://techandclick.site/latest-movies.php" />
  <meta name="theme-color" content="#0b1220" />

  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="Tech &amp; Click" />
  <meta property="og:title" content="Latest Movie Updates — New Releases &amp; Search" />
  <meta property="og:description" content="Latest movie updates, new release alerts, movie search by name." />
  <meta property="og:url" content="https://techandclick.site/latest-movies.php" />

  <meta name="twitter:card" content="summary" />
  <meta name="twitter:title" content="Latest Movie Updates — New Releases &amp; Search" />
  <meta name="twitter:description" content="Latest movie updates, new release alerts, movie search by name." />

  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "WebPage",
          "name": "Latest Movie Updates — New Releases & Search",
          "url": "https://techandclick.site/latest-movies.php",
          "inLanguage": "bn",
          "isPartOf": {"@type": "WebSite", "name": "Tech & Click", "url": "https://techandclick.site/"},
          "about": ["Movie Updates", "New Releases", "Movie Search"],
          "keywords": "latest movie update, new movie release, movie search by name, bollywood movie, hollywood movie, bangla movie, upcoming movie"
        },
        {
          "@type": "ItemList",
          "name": "Latest Movie Updates",
          "numberOfItems": <?php echo $total; ?>,
          "itemListElement": [
            <?php
            $pos = 1;
            $items = [];
            foreach (array_slice($movies, 0, 20) as $m):
                $items[] = '{"@type": "ListItem", "position": ' . $pos . ', "name": "' . htmlspecialchars($m['title'], ENT_QUOTES) . ($m['year'] ? ' (' . $m['year'] . ')' : '') . '", "url": "https://techandclick.site/movie/' . $m['slug'] . '.php"}';
                $pos++;
            endforeach;
            echo implode(",\n            ", $items);
            ?>
          ]
        }
      ]
    }
  </script>

  <style>
    :root{--bg:#0b1220;--bg2:#0f1b33;--border:rgba(255,255,255,.12);--text:#e5e7eb;--muted:#a7b0c0;--brand:#24A1DE;--shadow:0 18px 45px rgba(0,0,0,.35);--radius:16px;--container:1100px}
    *{box-sizing:border-box}
    body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,"Noto Sans";color:var(--text);background:linear-gradient(180deg,var(--bg),var(--bg2))}
    a{color:inherit;text-decoration:none}
    .container{max-width:var(--container);margin:0 auto;padding:22px 16px}
    .box{border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));padding:18px}
    .top{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 14px;border-radius:999px;border:1px solid var(--border);background:rgba(255,255,255,.05);font-weight:900;font-size:13px;cursor:pointer}
    .btn-primary{background:linear-gradient(135deg, rgba(36,161,222,.95), rgba(34,197,94,.90));border-color:transparent;color:#041018}
    h1{margin:14px 0 10px;font-size:28px;line-height:1.15}
    p{margin:0;color:var(--muted);font-weight:700;line-height:1.7}
    h2{margin:18px 0 10px;font-size:18px}
    .grid{display:grid;grid-template-columns:repeat(2, minmax(0,1fr));gap:12px}
    .card{border:1px solid var(--border);border-radius:var(--radius);background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));padding:14px;display:grid;gap:8px}
    .card h3{margin:0;font-size:15px}
    .card p{margin:0;color:var(--muted);font-weight:700;font-size:13px;line-height:1.5}
    .meta{display:flex;flex-wrap:wrap;gap:6px}
    .tag{padding:4px 10px;border-radius:999px;border:1px solid var(--border);background:rgba(255,255,255,.04);font-weight:800;font-size:11px}
    .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:2px}
    .empty{text-align:center;padding:40px 0;color:var(--muted)}
    .footer{margin-top:16px;border-top:1px solid rgba(255,255,255,.10);padding-top:14px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;color:rgba(229,231,235,.85)}
    @media(max-width:720px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="container">
    <div class="box">
      <div class="top">
        <a class="btn" href="./">← হোম</a>
        <a class="btn btn-primary" href="https://t.me/GetLatestMoviesBot" target="_blank" rel="noopener">Search Bot</a>
      </div>

      <h1>Latest Movie Updates — New Releases &amp; Search</h1>
      <p>
        সর্বশেষ মুভি আপডেট, নতুন রিলিজ অ্যালার্ট এবং মুভি সার্চ। Bollywood, Hollywood, Bangla, South Indian — সব ধরনের মুভির আপডেট এক জায়গায়। মুভির নাম দিয়ে সার্চ করতে <b>@GetLatestMoviesBot</b> বট ব্যবহার করুন।
      </p>

      <h2>সর্বশেষ মুভি (<?php echo $total; ?>)</h2>
      <?php if ($movies): ?>
      <div class="grid">
        <?php foreach (array_slice($movies, 0, 40) as $m):
            $slug  = $m['slug'];
            $title = htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8');
            $year  = htmlspecialchars($m['year'], ENT_QUOTES, 'UTF-8');
            $q     = htmlspecialchars($m['quality'], ENT_QUOTES, 'UTF-8');
            $l     = htmlspecialchars($m['language'], ENT_QUOTES, 'UTF-8');
            $g     = htmlspecialchars($m['genre'], ENT_QUOTES, 'UTF-8');
            $d     = htmlspecialchars($m['date'], ENT_QUOTES, 'UTF-8');
            $desc  = htmlspecialchars(mb_substr($m['desc'], 0, 120), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="card">
          <h3><?php echo $title; ?><?php echo $year ? " ({$year})" : ''; ?></h3>
          <div class="meta">
            <?php if ($q) echo "<span class=\"tag\">{$q}</span>"; ?>
            <?php if ($l) echo "<span class=\"tag\">{$l}</span>"; ?>
            <?php if ($g) echo "<span class=\"tag\">{$g}</span>"; ?>
            <span class="tag"><?php echo $d; ?></span>
          </div>
          <p><?php echo $desc; ?></p>
          <div class="actions">
            <a class="btn btn-primary" href="/movie/<?php echo $slug; ?>.php">বিস্তারিত</a>
            <a class="btn" href="https://t.me/GetLatestMoviesBot" target="_blank" rel="noopener">Search Bot</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="empty">
        <p>এখনো কোনো মুভি আপডেট নেই। চ্যানেলে নতুন পোস্ট এলে এখানে দেখাবে।</p>
        <a class="btn btn-primary" href="https://t.me/getlatestmoviebot" target="_blank" rel="noopener" style="margin-top:12px">Movie Channel ফলো করুন</a>
      </div>
      <?php endif; ?>

      <h2>Related searches</h2>
      <ul style="margin:8px 0 0;padding-left:18px;color:var(--muted);font-weight:700;line-height:1.7">
        <li>latest movie update</li>
        <li>new movie release 2026</li>
        <li>bollywood movie update</li>
        <li>hollywood movie update</li>
        <li>bangla movie update</li>
        <li>south indian movie update</li>
        <li>movie search by name</li>
        <li>upcoming movie alert</li>
        <li>movie info cast plot rating</li>
        <li>movie release date</li>
      </ul>

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
