<?php
require __DIR__ . '/inc/functions.php';

$slug = (string) input('slug');
$post = $slug !== '' ? row("SELECT * FROM journal_posts WHERE slug = ? AND status='published'", [$slug]) : null;

if (!$post) {
    http_response_code(404);
    $PAGE_TITLE = 'Article not found';
    include __DIR__ . '/inc/head.php';
    echo '<div class="wrap section" style="text-align:center"><h1 class="h2">Article not found</h1><p class="muted">That post doesn\'t exist or was unpublished.</p><a class="btn btn-primary" href="journal">Back to journal</a></div>';
    include __DIR__ . '/inc/foot.php';
    exit;
}

$more = rows("SELECT title, slug, category, image, author, read_min FROM journal_posts
              WHERE status='published' AND id <> ? ORDER BY sort, published_at DESC, id DESC LIMIT 3", [$post['id']]);

$PAGE_TITLE = $post['title'] . ' — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = 'Shop All';
$HEAD_CSS = <<<CSS
<style>
  .post-hero{background:var(--hero-grad);border-bottom:1px solid var(--border)}
  .post-hero .wrap{padding-block:40px 34px;max-width:820px}
  .post-hero .cat-l{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--rose-deep)}
  .post-hero h1{font-family:var(--fp);font-size:clamp(30px,4.4vw,50px);font-weight:600;line-height:1.05;letter-spacing:-.02em;margin:10px 0 12px}
  .post-hero .meta{font-size:13.5px;color:var(--text-muted)}
  .post-cover{max-width:960px;margin:26px auto 0;aspect-ratio:16/8;border-radius:var(--r-lg);overflow:hidden;border:1px solid var(--border);background:var(--cream-2)}
  .post-cover img{width:100%;height:100%;object-fit:cover}
  .post-body{max-width:760px;margin-inline:auto;padding-block:40px 20px}
  .post-body h3{font-family:var(--fp);font-size:24px;font-weight:600;margin:32px 0 12px;letter-spacing:-.01em}
  .post-body h3:first-child{margin-top:0}
  .post-body p,.post-body li{font-size:16.5px;line-height:1.8;color:var(--ink-soft)}
  .post-body ul,.post-body ol{margin:10px 0 10px 22px;display:flex;flex-direction:column;gap:8px}
  .post-body a{color:var(--rose-deep);text-decoration:underline;font-weight:600}
  .post-body b{color:var(--ink)}
  .more-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;padding-bottom:20px}
  .blogcard{border:1px solid var(--border);border-radius:var(--r-card);overflow:hidden;background:#fff;transition:transform .25s,box-shadow .25s;display:flex;flex-direction:column}
  .blogcard:hover{transform:translateY(-6px);box-shadow:var(--sh-lg)}
  .blogcard .img{aspect-ratio:16/10;overflow:hidden;background:var(--cream-2)}
  .blogcard .img img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
  .blogcard:hover .img img{transform:scale(1.05)}
  .blogcard .b{padding:18px 20px 22px}
  .blogcard .cat-l{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--rose-deep)}
  .blogcard h3{font-size:18px;margin:8px 0 8px;line-height:1.15}
  .blogcard .meta{font-size:12px;color:var(--text-muted)}
  @media(max-width:820px){.more-grid{grid-template-columns:1fr}}
</style>
CSS;

include __DIR__ . '/inc/head.php';
?>
<section class="post-hero">
  <div class="wrap">
    <nav class="crumb"><a href="index">Home</a><span class="sep">›</span><a href="journal">Journal</a><span class="sep">›</span><b><?= e($post['category'] ?: 'Article') ?></b></nav>
    <?php if ($post['category']): ?><span class="cat-l"><?= e($post['category']) ?></span><?php endif; ?>
    <h1><?= e($post['title']) ?></h1>
    <div class="meta">By <?= e($post['author'] ?: 'The Well Team') ?> · <?= (int)$post['read_min'] ?> min read<?= $post['published_at'] ? ' · ' . e(date('M j, Y', strtotime($post['published_at']))) : '' ?></div>
  </div>
</section>

<?php if ($post['image']): ?>
<div class="wrap"><div class="post-cover graded" data-imgwrap><img class="gimg" data-grade src="<?= e($post['image']) ?>" alt=""></div></div>
<?php endif; ?>

<div class="wrap"><div class="post-body"><?= $post['body'] ?: '<p class="muted">This article is coming soon.</p>' ?></div></div>

<?php if ($more): ?>
<section class="wrap section-tight">
  <div class="sec-head"><div><span class="eyebrow">keep reading</span><h2 class="h2">more from the <span class="script">journal</span></h2></div><a class="btn btn-ghost" href="journal">all posts</a></div>
  <div class="more-grid">
    <?php foreach ($more as $m): ?>
      <a class="blogcard" href="journal-post?slug=<?= e(urlencode($m['slug'])) ?>">
        <div class="img graded" data-imgwrap><img class="gimg" data-grade src="<?= e($m['image']) ?>" alt="" loading="lazy"></div>
        <div class="b"><span class="cat-l"><?= e($m['category']) ?></span><h3><?= e($m['title']) ?></h3><span class="meta">By <?= e($m['author']) ?> · <?= (int)$m['read_min'] ?> min read</span></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<div id="usp"></div>
<?php
$PAGE_JS = "<script>document.getElementById('usp').innerHTML = WELL.uspHTML(); WELL.guardImages(document);</script>";
include __DIR__ . '/inc/foot.php';
