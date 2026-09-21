<?php
/* Coming-soon / holding page for THE WELL SHOP.
   Returns 503 + Retry-After so search engines treat this as temporary and do NOT
   de-index the site. Everything is inlined so it renders with no other assets. */
http_response_code(503);
header('Retry-After: 86400');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>THE WELL SHOP — Coming soon</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600&display=swap">
<style>
  *{box-sizing:border-box}
  :root{
    --ink:#2e2a26; --soft:#6f665d; --line:#e8e1d8;
    --cream:#faf7f3; --blush:#f3e7e3; --sage:#e6ece4; --gold:#9c8158;
  }
  html,body{height:100%}
  body{margin:0;background:var(--cream);color:var(--ink);
    font:16px/1.65 Lora,Georgia,"Times New Roman",serif;
    display:flex;align-items:center;justify-content:center;
    padding:calc(32px + env(safe-area-inset-top,0px)) 20px calc(32px + env(safe-area-inset-bottom,0px));
    background-image:radial-gradient(60rem 40rem at 15% -10%,var(--blush),transparent 60%),
                     radial-gradient(50rem 36rem at 95% 110%,var(--sage),transparent 60%);}
  .wrap{max-width:560px;width:100%;text-align:center}
  .mark{width:66px;height:66px;margin:0 auto 26px;border-radius:50%;
    border:1px solid var(--gold);color:var(--gold);
    display:flex;align-items:center;justify-content:center;
    font-size:26px;letter-spacing:.06em;font-weight:600}
  h1{margin:0 0 6px;font-size:clamp(28px,7vw,40px);font-weight:600;letter-spacing:.02em;line-height:1.2}
  .tag{margin:0 0 28px;font-size:13px;letter-spacing:.22em;text-transform:uppercase;color:var(--gold)}
  .rule{width:54px;height:1px;background:var(--line);margin:0 auto 28px}
  p{margin:0 auto 18px;max-width:46ch;color:var(--soft);font-size:clamp(15px,4vw,17px)}
  .meta{margin-top:34px;padding-top:22px;border-top:1px solid var(--line);
    font-size:13.5px;color:var(--soft);font-family:system-ui,-apple-system,sans-serif}
  .meta a{color:var(--gold);text-decoration:none;border-bottom:1px solid transparent}
  .meta a:hover{border-bottom-color:var(--gold)}
  .meta span{display:inline-block;margin:0 9px}
  @media (prefers-color-scheme:dark){
    :root{--ink:#f2ede7;--soft:#b3a99e;--line:#3a332c;--cream:#171411;--blush:#2a1f1d;--sage:#1d2620}
  }
</style>
</head>
<body>
  <main class="wrap">
    <div class="mark">W</div>
    <p class="tag">Beirut &middot; Lebanon</p>
    <h1>Something good is on its way</h1>
    <div class="rule"></div>
    <p>THE WELL SHOP is getting its finishing touches. We&rsquo;re preparing our
       skincare, beauty and wellness collection for you.</p>
    <p>Back very soon.</p>
    <div class="meta">
      <span><a href="https://wa.me/96170067263">WhatsApp us</a></span>
      <span><a href="https://www.instagram.com/">Instagram</a></span>
    </div>
  </main>
</body>
</html>
