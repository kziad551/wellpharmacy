<?php
/* ============================================================
   Server-rendered top chrome (utility bar + header + nav).

   This is a straight port of utilBar(), logo(), shopIcons(), header() and the
   <li> loop of buildNav() from assets/chrome.js. It exists so the menu is in
   the HTML at first paint instead of waiting for ~950KB of script.

   chrome.js still owns everything interactive. It skips re-rendering when this
   markup is already present (see the guards in mountChrome and buildNav), so if
   a deploy ever reverts inc/head.php the JS quietly renders the header the old
   way rather than leaving the site headerless.

   Keep the markup byte-compatible with chrome.js: the CSS in well.css and the
   event wiring in chrome.js both key off these exact classes and attributes.
   ============================================================ */

/* The 11 icons the header needs, copied verbatim from `const I` in chrome.js. */
function well_icon(string $n): string {
    static $I = [
        'search'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
        'heart'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.5-1.5 3-3.2 3-5.5A4.5 4.5 0 0 0 12 5.5 4.5 4.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/></svg>',
        'bag'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 4 6v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6l-2-4z"/><path d="M4 6h16"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
        'user'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>',
        'cross'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
        'close'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>',
        'menu'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>',
        'whatsapp'=> '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.002-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>',
        'ig'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>',
        'tiktok'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 3c.3 2.3 1.7 3.9 4 4.1v2.8c-1.4.1-2.7-.3-4-1v6.1c0 3.4-2.5 5.9-5.8 5.9A5.7 5.7 0 0 1 4.5 15c0-3.3 2.9-5.9 6.5-5.2v3a2.8 2.8 0 0 0-3.5 2.6c0 1.5 1.2 2.6 2.7 2.6 1.6 0 2.8-1.2 2.8-3V3z"/></svg>',
    ];
    return $I[$n] ?? '';
}

function well_util_bar(): string {
    $a1 = setting('announce_1', 'FREE SHIPPING on orders above $49');
    $a2 = setting('announce_2', 'Authentic Products • Expert Care • Secure Checkout');
    $cur = setting('currency_label', '$ USD');
    return '<div class="utilbar"><div class="wrap">
      <div class="marq">
        <span>' . e($a1) . '</span><span class="dot">·</span>
        <span>' . e($a2) . '</span>
      </div>
      <div class="right">
        <a href="contact">Help</a><span class="dot">·</span>
        <span>EN | ' . e($cur) . '</span>
      </div>
    </div></div>';
}

function well_logo(): string {
    $name = setting('store_name', 'WELL SHOP');
    $tag  = setting('store_tagline', 'where Wellness meets You!');
    $src  = brand_image('store_logo');
    $mode = setting('logo_mode', 'auto');

    $showPic  = $src !== '' && ($mode === 'auto' || $mode === 'logo' || $mode === 'both');
    $showName = !$showPic || $mode === 'name' || $mode === 'both';

    /* NOT asset(): chrome.js emits this src unversioned, and brand_image() already
       returns the stored path. Running it through asset() would change the URL. */
    $pic = $showPic
        ? '<img class="logo-img" src="' . e($src) . '" alt="' . e($name) . '" width="160" height="48">'
        : '<span class="badge-circle">' . well_icon('cross') . '</span>';
    $words = $showName
        ? '<span><span class="name">' . e($name) . '</span><br><span class="tag">' . e($tag) . '</span></span>'
        : '';

    return '<a class="logo' . ($showPic ? ' has-img' : '') . '" href="index" aria-label="' . e($name) . ' — home">
      ' . $pic . $words . '
    </a>';
}

/* favourites / account / bag. Emitted TWICE (mobile row + desktop nav row), exactly
   as chrome.js does; well.css shows one and hides the other per breakpoint.
   Both badges carry style="display:none" so no page paints a "0" bubble before
   syncBadges() runs. syncBadges sets style.display = n ? '' : 'none', which clears
   the inline value when the count is non-zero, so this is correct in both directions. */
function well_shop_icons(?array $me): string {
    $acctHref  = $me ? 'account' : 'login';
    $acctLabel = $me ? 'My account' : 'Sign in or register';
    $acctText  = $me
        ? '<b>hi, ' . e($me['first_name']) . '</b><span>my account</span>'
        : '<b>sign in</b><span>or register</span>';
    return '<a class="icon-btn" href="wishlist" aria-label="My favourites">' . well_icon('heart')
         . '<span class="count wishc" data-wish-count hidden style="display:none">0</span></a>
      <a class="hdr-acct" href="' . $acctHref . '" aria-label="' . $acctLabel . '">
        ' . well_icon('user') . '
        <span class="t">' . $acctText . '</span></a>
      <button class="icon-btn" data-open-cart aria-label="Cart">' . well_icon('bag')
         . '<span class="count" data-cart-count style="display:none">0</span></button>';
}

/* The <li> list only. chrome.js keeps ownership of the More dropdown behaviour and
   the mega panel; it wires handlers onto whatever markup it finds. */
function well_nav_items(string $active): string {
    $navCats = array_column(rows("SELECT name FROM categories WHERE in_nav=1 ORDER BY sort"), 'name');
    $nav     = array_merge(['Shop All', 'Brands', 'Offers'], $navCats);
    $more    = array_slice($navCats, 7);   // NAV_INLINE_CATS in assets/data.php

    $hrefFor = static function (string $n): string {
        if ($n === 'Shop All') return 'skincare';
        if ($n === 'Brands')   return 'brands';
        if ($n === 'Offers')   return 'offers';
        return 'skincare?cat=' . rawurlencode($n);   // matches encodeURIComponent
    };
    $item = static function (string $n) use ($active, $hrefFor): string {
        $cross = $n === 'Health Conditions' ? '<span class="x">' . well_icon('cross') . '</span>' : '';
        $cls   = $n === 'Offers' ? 'offers' : '';
        return '<li class="' . ($active === $n ? 'active' : '') . '" data-menu="' . e($n) . '">'
             . '<a class="' . $cls . '" href="' . e($hrefFor($n)) . '">' . $cross . e($n) . '</a></li>';
    };

    $inline = '';
    foreach ($nav as $n) { if (!in_array($n, $more, true)) $inline .= $item($n); }

    $moreHTML = '';
    if ($more) {
        $anyActive = in_array($active, $more, true) ? ' active' : '';
        $sub = '';
        foreach ($more as $n) {
            $sub .= '<li' . ($active === $n ? ' class="active"' : '') . '><a href="' . e($hrefFor($n)) . '">' . e($n) . '</a></li>';
        }
        $moreHTML = '<li class="nav-more' . $anyActive . '">
        <button type="button" class="nav-more-btn" aria-expanded="false" aria-haspopup="true">More<span class="chev">' . well_icon('chevron') . '</span></button>
        <ul class="nav-more-list">' . $sub . '</ul>
      </li>';
    }
    return $inline . $moreHTML;
}

function well_chrome_top(string $active = 'Shop All'): string {
    $me  = function_exists('current_customer') ? current_customer() : null;
    $wa  = setting('whatsapp_number', '9613627766');
    /* data.php defaults these to '' but chrome.js falls back to real URLs, so the
       fallback has to live here too or both icons ship href="". */
    $ig  = setting('social_instagram', '') ?: 'https://www.instagram.com/wellhealthandbeautyy';
    $tt  = setting('social_tiktok', '')    ?: 'https://www.tiktok.com/@wellhealthandbeauty';
    $icons = well_shop_icons($me);

    return well_util_bar() . '<header class="site-header" id="siteHeader">
      <div class="wrap hdr-main">
        <button class="nav-toggle" data-nav-toggle aria-label="Menu" aria-expanded="false"><span class="nt-open">' . well_icon('menu') . '</span><span class="nt-close">' . well_icon('close') . '</span></button>
        ' . well_logo() . '
        <div class="hdr-search">
          <form class="search" role="search" action="search" method="get">
            ' . well_icon('search') . '
            <input name="q" placeholder="Search for products, brands or concerns…" aria-label="Search">
          </form>
        </div>
        <div class="hdr-right">
          <a class="hdr-expert wa-expert" href="https://wa.me/' . e($wa) . '" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
            <span class="wa-av">' . well_icon('whatsapp') . '</span>
            <span class="t"><b>Chat with us</b><span>on WhatsApp</span></span>
          </a>
          <a class="icon-btn ig" href="' . e($ig) . '" target="_blank" rel="noopener" aria-label="Instagram">' . well_icon('ig') . '</a>
          <a class="icon-btn tt" href="' . e($tt) . '" target="_blank" rel="noopener" aria-label="TikTok">' . well_icon('tiktok') . '</a>
          <div class="shop-icons mobile-icons">' . $icons . '</div>
        </div>
      </div>
      <div class="nav-row"><div class="wrap nav-wrap">
          <ul class="nav-list" id="navList">' . well_nav_items($active) . '</ul>
          <div class="shop-icons desk-icons">' . $icons . '</div>
        </div>
        <div class="mega" id="megaPanel"></div>
      </div>
    </header>';
}
