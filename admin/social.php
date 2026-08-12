<?php
/* Admin → Social Videos: the Instagram / TikTok clips shown in the homepage
   "as seen on social" section. One row = one post. */
require __DIR__ . '/inc/layout.php';

if (is_post()) {
    csrf_check();
    $act = (string) input('action');

    if ($act === 'delete') {
        q("DELETE FROM social_posts WHERE id = ?", [(int) input('id')]);
        flash('Video removed.');
        redirect('social');
    }
    if ($act === 'toggle') {
        q("UPDATE social_posts SET enabled = 1 - enabled WHERE id = ?", [(int) input('id')]);
        flash('Visibility updated.');
        redirect('social');
    }
    if ($act === 'section') {
        foreach (['social_sec_enabled' => input('sec_enabled') ? '1' : '0',
                  'social_sec_eyebrow' => trim((string) input('sec_eyebrow')),
                  'social_sec_title'   => trim((string) input('sec_title')),
                  'social_sec_sub'     => trim((string) input('sec_sub'))] as $k => $v) {
            q("INSERT INTO settings (skey, sval, sgroup) VALUES (?,?,'social')
               ON DUPLICATE KEY UPDATE sval = VALUES(sval)", [$k, $v]);
        }
        flash('Section settings saved.');
        redirect('social');
    }

    /* add / update one post */
    $id       = (int) input('id');
    $platform = input('platform') === 'tiktok' ? 'tiktok' : 'instagram';
    $url      = trim((string) input('url'));
    $caption  = trim((string) input('caption'));
    $sort     = (int) input('sort');
    $thumb    = trim((string) input('thumb'));

    $upErr = null;
    if ($u = save_upload('thumb_file', $upErr)) $thumb = $u;

    if ($url === '') { flash('Paste the link to the Instagram or TikTok post.', 'err'); redirect('social'); }

    /* warn (don't block) when we can't build an inline player — the card still
       opens the post in a new tab, which is a fine fallback. */
    $embed = social_embed_url($platform, $url);
    $warn  = $embed === ''
        ? ' Note: that link can\'t be played inside the site (short vm.tiktok.com links and profile links can\'t be embedded) — the card will open it in a new tab instead. Use the full post URL for an inline player.'
        : '';

    if ($id) {
        q("UPDATE social_posts SET platform=?, url=?, thumb=?, caption=?, sort=? WHERE id=?",
          [$platform, $url, $thumb, $caption, $sort, $id]);
        flash('Video updated.' . $warn, $warn ? 'err' : 'ok');
    } else {
        q("INSERT INTO social_posts (platform, url, thumb, caption, sort, enabled) VALUES (?,?,?,?,?,1)",
          [$platform, $url, $thumb, $caption, $sort]);
        flash('Video added.' . $warn, $warn ? 'err' : 'ok');
    }
    redirect('social');
}

$edit = null;
if ($eid = (int) input('edit')) $edit = row("SELECT * FROM social_posts WHERE id = ?", [$eid]);
$list = rows("SELECT * FROM social_posts ORDER BY sort, id DESC");
$on   = setting('social_sec_enabled', '1') === '1';

admin_head('Social Videos', 'social', count($list) . ' video' . (count($list) === 1 ? '' : 's'));
?>
<div class="a-grid-2" style="display:grid;grid-template-columns:1.25fr .95fr;gap:20px;align-items:start">

  <div>
    <div class="a-card"><div class="hd"><h2>Homepage videos</h2></div><div class="bd" style="padding:0">
      <?php if (!$list): ?>
        <div class="empty">No videos yet — add the first one on the right.</div>
      <?php else: ?>
      <table class="a-table">
        <thead><tr><th></th><th>Post</th><th>Where</th><th>Order</th><th>Shown</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $p):
            $embed = social_embed_url($p['platform'], $p['url']); ?>
          <tr>
            <td>
              <?php if ($p['thumb']): ?>
                <img class="thumb" src="<?= e(asrc($p['thumb'])) ?>" alt="" onerror="this.style.visibility='hidden'">
              <?php else: ?>
                <span class="thumb" style="display:inline-flex;align-items:center;justify-content:center;background:#EBE8DF;color:#8A7D6E">▶</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="nm" href="social?edit=<?= (int)$p['id'] ?>"><?= e($p['caption'] ?: 'Untitled clip') ?></a>
              <div class="br"><span class="faint" style="word-break:break-all"><?= e($p['url']) ?></span></div>
              <?php if (!$embed): ?><div class="br"><span class="pill pill-muted">opens in a new tab (not embeddable)</span></div><?php endif; ?>
            </td>
            <td><?= $p['platform'] === 'tiktok' ? 'TikTok' : 'Instagram' ?></td>
            <td><?= (int) $p['sort'] ?></td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="pill <?= $p['enabled'] ? 'pill-good' : 'pill-muted' ?>" style="border:0;cursor:pointer">
                  <?= $p['enabled'] ? 'Visible' : 'Hidden' ?>
                </button>
              </form>
            </td>
            <td style="text-align:right">
              <a class="btn btn-ghost btn-sm" href="social?edit=<?= (int)$p['id'] ?>">Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Remove this video?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-bad btn-sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div></div>

    <div class="a-card" style="margin-top:20px"><div class="hd"><h2>Section heading</h2></div><div class="bd">
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="section">
        <label class="switch" style="margin-bottom:14px">
          <input type="checkbox" name="sec_enabled" value="1" <?= $on ? 'checked' : '' ?>> Show the section on the homepage
        </label>
        <div class="field"><label>Eyebrow</label><input class="input" name="sec_eyebrow" value="<?= e(setting('social_sec_eyebrow','follow the glow')) ?>"></div>
        <div class="field"><label>Title</label><input class="input" name="sec_title" value="<?= e(setting('social_sec_title','as seen on social')) ?>"><div class="hint">The last word is styled in the script accent, like the other homepage titles.</div></div>
        <div class="field"><label>Sub-line</label><input class="input" name="sec_sub" value="<?= e(setting('social_sec_sub','')) ?>"></div>
        <button class="btn btn-primary">Save section</button>
      </form>
    </div></div>
  </div>

  <div class="a-card"><div class="hd"><h2><?= $edit ? 'Edit video' : 'Add a video' ?></h2></div><div class="bd">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

      <div class="field"><label>Platform</label>
        <select class="input" name="platform">
          <option value="instagram" <?= ($edit && $edit['platform']==='instagram') ? 'selected':'' ?>>Instagram</option>
          <option value="tiktok"    <?= ($edit && $edit['platform']==='tiktok')    ? 'selected':'' ?>>TikTok</option>
        </select>
      </div>

      <div class="field"><label>Link to the post</label>
        <input class="input" name="url" value="<?= e($edit['url'] ?? '') ?>" placeholder="https://www.instagram.com/reel/ABC123/">
        <div class="hint">
          Open the post, copy the address bar. Works: <code>instagram.com/p/…</code>, <code>instagram.com/reel/…</code>,
          <code>tiktok.com/@name/video/123…</code>.<br>
          Short <code>vm.tiktok.com</code> links and profile links still work as cards, but can't play inside the site.
        </div>
      </div>

      <div class="field"><label>Cover image</label>
        <input class="input" name="thumb" value="<?= e($edit['thumb'] ?? '') ?>" placeholder="https://… or upload below">
        <input class="input" type="file" name="thumb_file" accept="image/*" style="margin-top:8px">
        <div class="hint">Take a screenshot of the video and upload it. Instagram and TikTok don't let us pull the cover automatically. Portrait (9:16) looks best.</div>
      </div>

      <div class="field"><label>Caption <span class="faint">(optional)</span></label>
        <input class="input" name="caption" value="<?= e($edit['caption'] ?? '') ?>" maxlength="200" placeholder="Our 3-step winter routine">
      </div>

      <div class="field"><label>Order</label>
        <input class="input" type="number" name="sort" value="<?= e((string)($edit['sort'] ?? 0)) ?>">
        <div class="hint">Lower shows first. The section displays up to 12.</div>
      </div>

      <button class="btn btn-primary"><?= $edit ? 'Save video' : 'Add video' ?></button>
      <?php if ($edit): ?><a class="btn" href="social">Cancel</a><?php endif; ?>
    </form>
  </div></div>

</div>
<?php admin_foot(); ?>
