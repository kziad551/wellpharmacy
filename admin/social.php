<?php
/* Admin → Social Videos: the Instagram / TikTok clips shown in the homepage
   "as seen on social" section. One row = one post. */
require __DIR__ . '/inc/layout.php';
require_once dirname(__DIR__) . '/inc/social-sync.php';

if (is_post()) {
    csrf_check();
    $act = (string) input('action');

    if ($act === 'ig_save') {
        $tok = trim((string) input('ig_token'));
        if ($tok !== '') set_setting('ig_access_token', $tok, 'social');
        set_setting('ig_sync_enabled', input('ig_enabled') ? '1' : '0', 'social');
        flash('Instagram settings saved.');
        redirect('social');
    }
    if ($act === 'sync') {
        $res = social_sync_all();
        $ig  = $res['instagram'];
        flash($ig['msg'] . ' ' . $res['tiktok']['msg'], $ig['ok'] ? 'ok' : 'err');
        redirect('social');
    }

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
                  'social_sec_sub'     => trim((string) input('sec_sub')),
                  'social_handle'      => trim((string) input('sec_handle')),
                  'social_followers'   => trim((string) input('sec_followers'))] as $k => $v) {
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
    $likes    = trim((string) input('likes'));

    $upErr = null;
    if ($u = save_upload('thumb_file', $upErr)) $thumb = $u;

    if ($url === '') { flash('Paste the link to the Instagram or TikTok post.', 'err'); redirect('social'); }

    /* SAVE FIRST. Fetching a TikTok cover talks to the internet, and if that call
       stalls (e.g. the host firewall is blocking tiktok.com) the request can die
       before the write lands — silently losing the edit. So the row is written
       here, and the cover is a best-effort top-up afterwards. */
    if ($id) {
        q("UPDATE social_posts SET platform=?, url=?, thumb=?, caption=?, likes=?, sort=? WHERE id=?",
          [$platform, $url, $thumb, $caption, $likes, $sort, $id]);
        $saved = 'Video updated.';
    } else {
        q("INSERT INTO social_posts (platform, url, thumb, caption, likes, sort, enabled) VALUES (?,?,?,?,?,?,1)",
          [$platform, $url, $thumb, $caption, $likes, $sort]);
        $id    = (int) db()->lastInsertId();
        $saved = 'Video added.';
    }

    /* Best-effort cover for TikTok — short timeout so nobody waits on a page save. */
    $auto = '';
    if ($platform === 'tiktok' && $thumb === '') {
        $tt = tiktok_lookup($url, 6);
        if (!empty($tt['thumb'])) {
            $extraCap = ($caption === '' && !empty($tt['title'])) ? mb_substr($tt['title'], 0, 200) : $caption;
            q("UPDATE social_posts SET thumb=?, caption=? WHERE id=?", [$tt['thumb'], $extraCap, $id]);
            $auto = ' Cover pulled from TikTok.';
        } else {
            $auto = ' No cover yet — upload one.';
        }
    }

    /* NB: whether a post plays inline or opens in a new tab is shown as a small tag
       on its row in the list. It used to be repeated in this message on every single
       save, which is just nagging — the admin already knows. */
    flash($saved . $auto, 'ok');
    redirect('social');
}

$edit = null;
if ($eid = (int) input('edit')) $edit = row("SELECT * FROM social_posts WHERE id = ?", [$eid]);
$list = rows("SELECT * FROM social_posts ORDER BY sort, id DESC");
$on   = setting('social_sec_enabled', '1') === '1';

admin_head('Social Videos', 'social', count($list) . ' video' . (count($list) === 1 ? '' : 's'));
?>
<?php
$igOn    = setting('ig_sync_enabled', '0') === '1';
$igTok   = setting('ig_access_token', '');
$igUser  = setting('ig_username', '');
$igWhen  = setting('ig_last_sync', '');
$igLast  = setting('ig_last_result', '');
$synced  = (int) val("SELECT COUNT(*) FROM social_posts WHERE source='instagram'");
$cronUrl = rtrim(setting('site_url', ''), '/') . '/actions/social-sync?key=' . setting('social_sync_key', '');
?>
<div class="a-card" style="margin-bottom:20px"><div class="hd">
  <h2>Instagram auto-sync</h2>
  <span class="pill <?= $igOn && $igTok ? 'pill-good' : 'pill-muted' ?>">
    <?= $igOn && $igTok ? ($igUser ? 'Connected as @' . e($igUser) : 'Connected') : 'Not connected' ?>
  </span>
</div><div class="bd">

  <?php if (!$igTok): ?>
    <p style="margin:0 0 14px;font-size:13.5px;line-height:1.6">
      Paste a long-lived Instagram token below and your latest posts will pull in by themselves —
      covers, captions, handle and follower count included. Setup steps are in
      <b>INSTAGRAM-SETUP.md</b> (about 15 minutes, one time).
    </p>
  <?php endif; ?>

  <form method="post" style="margin-bottom:16px">
    <?= csrf_field() ?><input type="hidden" name="action" value="ig_save">
    <label class="switch" style="margin-bottom:12px">
      <input type="checkbox" name="ig_enabled" value="1" <?= $igOn ? 'checked' : '' ?>> Pull my Instagram posts automatically
    </label>
    <div class="field">
      <label>Instagram access token</label>
      <input class="input" name="ig_token" value="" placeholder="<?= $igTok ? 'saved — leave blank to keep it' : 'IGAA…' ?>" autocomplete="off">
      <div class="hint">
        <?php if ($igTok): ?>A token is saved (<?= e(substr($igTok, 0, 8)) ?>…<?= e(substr($igTok, -4)) ?>). Leave blank to keep it, or paste a new one to replace it.
        <?php else: ?>A long-lived token from your Meta app. We refresh it on every sync, so it won't expire.<?php endif; ?>
      </div>
    </div>
    <button class="btn btn-primary">Save Instagram settings</button>
  </form>

  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding-top:14px;border-top:1px solid var(--a-border,#E4DFD3)">
    <form method="post" style="display:inline">
      <?= csrf_field() ?><input type="hidden" name="action" value="sync">
      <button class="btn btn-primary" <?= $igTok ? '' : 'disabled' ?>>Sync now</button>
    </form>
    <span class="faint" style="font-size:13px">
      <?= $synced ?> post<?= $synced === 1 ? '' : 's' ?> pulled from Instagram<?php
        if ($igWhen) echo ' · last sync ' . e(date('j M Y, H:i', strtotime($igWhen))); ?>
    </span>
  </div>
  <?php if ($igLast): ?><p class="faint" style="margin:10px 0 0;font-size:12.5px"><?= e($igLast) ?></p><?php endif; ?>

  <details style="margin-top:14px">
    <summary style="cursor:pointer;font-size:13px;font-weight:600">Keep it updating by itself (cron)</summary>
    <p style="font-size:13px;line-height:1.6;margin:10px 0 0">
      In cPanel → <b>Cron Jobs</b>, add one job running every 6 hours:<br>
      <code style="display:block;padding:9px 11px;background:#F4F1E9;border-radius:8px;margin:8px 0;word-break:break-all">curl -s "<?= e($cronUrl) ?>" &gt;/dev/null 2&gt;&amp;1</code>
      That URL is secret — anyone with it can trigger a sync (nothing else). Without the cron, the grid
      still updates whenever you press <b>Sync now</b>.
    </p>
  </details>
</div></div>

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
        <div class="field"><label>Instagram handle</label><input class="input" name="sec_handle" value="<?= e(setting('social_handle','')) ?>" placeholder="@wellhealthandbeautyy"><div class="hint">Leave blank and we'll read it from the Instagram link in Settings → Social.</div></div>
        <div class="field"><label>Follower count</label><input class="input" name="sec_followers" value="<?= e(setting('social_followers','')) ?>" placeholder="68k followers"><div class="hint">Free text — Instagram doesn't let us read this automatically, so update it yourself now and then. Leave blank to hide.</div></div>
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

      <div class="field"><label>Likes <span class="faint">(optional)</span></label>
        <input class="input" name="likes" value="<?= e($edit['likes'] ?? '') ?>" maxlength="16" placeholder="82">
        <div class="hint">Shown on the little heart when someone hovers the tile. Type it as you want it to read (e.g. <code>82</code> or <code>1.2k</code>). Leave blank for just a heart.</div>
      </div>

      <div class="field"><label>Order</label>
        <input class="input" type="number" name="sort" value="<?= e((string)($edit['sort'] ?? 0)) ?>">
        <div class="hint">Lower shows first. The grid shows up to 10 (5 across, 2 rows).</div>
      </div>

      <button class="btn btn-primary"><?= $edit ? 'Save video' : 'Add video' ?></button>
      <?php if ($edit): ?><a class="btn" href="social">Cancel</a><?php endif; ?>
    </form>
  </div></div>

</div>
<?php admin_foot(); ?>
