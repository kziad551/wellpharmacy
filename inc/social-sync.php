<?php
/* ============================================================
   WELL PHARMACY — social auto-sync.

   INSTAGRAM: "Instagram API with Instagram Login" (graph.instagram.com).
     Needs a Business/Creator account and a long-lived user token, pasted into
     admin → Social Videos. The token lasts 60 days and we refresh it on every
     sync, so it stays alive indefinitely while syncing runs.

   TIKTOK: no key at all — the public oEmbed endpoint returns the cover image for
     any public video URL, so pasting a link is enough.

   ⚠ Both platforms serve covers from CDNs with EXPIRING urls, so every image is
     downloaded to uploads/ and referenced locally. Never hotlink them.
   ============================================================ */
require_once __DIR__ . '/functions.php';

const IG_API    = 'https://graph.instagram.com';
const SYNC_LIMIT = 24;         // how many recent posts to keep in view

/* ---------------------------------------------------------------- http ---- */
function sync_get(string $url, int $timeout = 20): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'WellPharmacy/1.0 (+https://wellpharmacy.top-wp.com)',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['ok' => $body !== false && $code === 200, 'code' => $code, 'body' => (string) $body, 'err' => $err];
    }
    $ctx  = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true,
             'header' => "User-Agent: WellPharmacy/1.0\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) { if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int) $m[1]; }
    return ['ok' => $body !== false && $code === 200, 'code' => $code, 'body' => (string) $body, 'err' => ''];
}

/** Download a remote cover into uploads/ and return the site-relative path (or ''). */
function sync_grab_image(string $url, string $key): string {
    if ($url === '') return '';
    $dir = defined('UPLOAD_DIR') ? UPLOAD_DIR : dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    foreach (['jpg', 'png', 'webp'] as $ext) {                 // already have it?
        if (is_file("$dir/social-$key.$ext") && filesize("$dir/social-$key.$ext") > 800) return "uploads/social-$key.$ext";
    }

    $r = sync_get($url, 25);
    if (!$r['ok'] || strlen($r['body']) < 800) return '';
    if (strlen($r['body']) > 8 * 1024 * 1024) return '';        // sanity cap

    $tmp = "$dir/.social-tmp-$key";
    if (@file_put_contents($tmp, $r['body']) === false) return '';
    $info = @getimagesize($tmp);
    if (!$info) { @unlink($tmp); return ''; }
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime']] ?? '';
    if ($ext === '') { @unlink($tmp); return ''; }

    $dest = "$dir/social-$key.$ext";
    if (!@rename($tmp, $dest)) { @unlink($tmp); return ''; }
    return "uploads/social-$key.$ext";
}

/* ------------------------------------------------------------- instagram --- */
/** Keep the 60-day token alive. Safe to call on every sync; Meta only extends
 *  tokens older than 24h and simply returns the same one otherwise. */
function ig_refresh_token(): bool {
    $tok = setting('ig_access_token', '');
    if ($tok === '') return false;
    $r = sync_get(IG_API . '/refresh_access_token?grant_type=ig_refresh_token&access_token=' . urlencode($tok));
    if (!$r['ok']) return false;
    $j = json_decode($r['body'], true);
    if (!empty($j['access_token'])) { set_setting('ig_access_token', $j['access_token'], 'social'); return true; }
    return false;
}

/**
 * Pull the latest Instagram media into social_posts.
 * Only ever touches rows with source='instagram' — hand-added posts are untouched.
 * @return array{ok:bool, added:int, updated:int, msg:string}
 */
function ig_sync(): array {
    $tok = setting('ig_access_token', '');
    if ($tok === '') return ['ok' => false, 'added' => 0, 'updated' => 0, 'msg' => 'No Instagram token saved yet.'];

    /* who am I (also gives us the handle + follower count for the heading) */
    $me = sync_get(IG_API . '/me?fields=id,username,followers_count,media_count&access_token=' . urlencode($tok));
    $meJ = json_decode($me['body'], true) ?: [];
    if (!$me['ok']) {
        $err = $meJ['error']['message'] ?? ('HTTP ' . $me['code']);
        return ['ok' => false, 'added' => 0, 'updated' => 0, 'msg' => 'Instagram rejected the token: ' . $err];
    }
    if (!empty($meJ['username'])) {
        set_setting('ig_username', $meJ['username'], 'social');
        if (setting('social_handle', '') === '') set_setting('social_handle', '@' . $meJ['username'], 'social');
    }
    if (!empty($meJ['followers_count'])) {
        $n = (int) $meJ['followers_count'];
        $pretty = $n >= 1000 ? rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k' : (string) $n;
        set_setting('social_followers', $pretty . ' followers', 'social');
    }

    $fields = 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp';
    $r = sync_get(IG_API . '/me/media?fields=' . $fields . '&limit=' . SYNC_LIMIT . '&access_token=' . urlencode($tok));
    if (!$r['ok']) {
        $j = json_decode($r['body'], true);
        return ['ok' => false, 'added' => 0, 'updated' => 0,
                'msg' => 'Could not read your media: ' . ($j['error']['message'] ?? ('HTTP ' . $r['code']))];
    }
    $items = json_decode($r['body'], true)['data'] ?? [];
    if (!$items) return ['ok' => true, 'added' => 0, 'updated' => 0, 'msg' => 'Connected, but that account has no posts yet.'];

    $added = $updated = 0; $sort = 0;
    foreach ($items as $m) {
        $id   = (string) ($m['id'] ?? '');
        if ($id === '') continue;
        /* videos/reels expose the still under thumbnail_url; photos use media_url */
        $cover = (string) ($m['thumbnail_url'] ?? $m['media_url'] ?? '');
        $local = sync_grab_image($cover, 'ig-' . $id);
        $cap   = trim((string) ($m['caption'] ?? ''));
        if (mb_strlen($cap) > 200) $cap = mb_substr($cap, 0, 197) . '…';

        $exists = row("SELECT id, thumb FROM social_posts WHERE platform='instagram' AND platform_id=?", [$id]);
        if ($exists) {
            q("UPDATE social_posts SET url=?, caption=?, sort=?, thumb=COALESCE(NULLIF(?,''), thumb) WHERE id=?",
              [(string) ($m['permalink'] ?? ''), $cap, $sort, $local, (int) $exists['id']]);
            $updated++;
        } else {
            q("INSERT INTO social_posts (platform, platform_id, source, url, thumb, caption, likes, sort, enabled)
               VALUES ('instagram', ?, 'instagram', ?, ?, ?, '', ?, 1)",
              [$id, (string) ($m['permalink'] ?? ''), $local, $cap, $sort]);
            $added++;
        }
        $sort++;
    }

    /* drop synced rows that are no longer in the feed (deleted on Instagram) */
    $keep = array_column($items, 'id');
    if ($keep) {
        $ph = implode(',', array_fill(0, count($keep), '?'));
        q("DELETE FROM social_posts WHERE source='instagram' AND platform_id NOT IN ($ph)", $keep);
    }

    ig_refresh_token();
    set_setting('ig_last_sync', date('Y-m-d H:i:s'), 'social');
    return ['ok' => true, 'added' => $added, 'updated' => $updated,
            'msg' => "Instagram synced — $added new, $updated updated."];
}

/* ---------------------------------------------------------------- tiktok --- */
/** Public oEmbed — no key. Returns ['thumb'=>siteRelativePath, 'title'=>..., 'id'=>...]
 *  $timeout is short when a human is waiting on a page save, long for cron. */
function tiktok_lookup(string $postUrl, int $timeout = 20): array {
    $r = sync_get('https://www.tiktok.com/oembed?url=' . urlencode($postUrl), $timeout);
    if (!$r['ok']) return [];
    $j = json_decode($r['body'], true);
    if (!$j || empty($j['thumbnail_url'])) return [];
    $id = '';
    if (preg_match('~/video/(\d+)~', (string) ($j['embed_product_id'] ?? $postUrl), $mm)) $id = $mm[1];
    if ($id === '' && !empty($j['embed_product_id'])) $id = (string) $j['embed_product_id'];
    if ($id === '' && preg_match('~/video/(\d+)~', $postUrl, $mm)) $id = $mm[1];
    return [
        'id'    => $id,
        'title' => (string) ($j['title'] ?? ''),
        'thumb' => sync_grab_image((string) $j['thumbnail_url'], 'tt-' . ($id ?: substr(md5($postUrl), 0, 12))),
    ];
}

/** One entry point for the admin button and the cron endpoint. */
function social_sync_all(): array {
    $out = ['instagram' => ['ok' => false, 'msg' => 'Instagram sync is switched off.']];
    if (setting('ig_sync_enabled', '0') === '1') $out['instagram'] = ig_sync();

    /* top up any TikTok rows that are still missing a cover */
    $fixed = 0;
    foreach (rows("SELECT id, url FROM social_posts WHERE platform='tiktok' AND (thumb='' OR thumb IS NULL)") as $t) {
        $info = tiktok_lookup($t['url']);
        if (!empty($info['thumb'])) { q("UPDATE social_posts SET thumb=? WHERE id=?", [$info['thumb'], (int) $t['id']]); $fixed++; }
    }
    $out['tiktok'] = ['ok' => true, 'msg' => $fixed ? "TikTok covers fetched for $fixed post(s)." : 'No TikTok covers needed fetching.'];

    set_setting('ig_last_result', $out['instagram']['msg'] . ' ' . $out['tiktok']['msg'], 'social');
    return $out;
}
