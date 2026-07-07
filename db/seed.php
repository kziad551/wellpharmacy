<?php
/* ============================================================
   WELL PHARMACY — seed data (faithful port of assets/data.js)
   Run AFTER schema.sql is loaded:  php db/seed.php
   ============================================================ */
/* Guard: this script TRUNCATES every table — never allow it to run over HTTP. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('This script can only be run from the command line.'); }

require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/functions.php';
$pdo = db();

/* image url helper — identical to data.js U() (default width 600 for packshots) */
function U(string $id, int $w = 600): string {
    return "https://images.unsplash.com/photo-$id?auto=format&fit=crop&w=$w&q=80";
}
$SHOTS = ['1556228720-195a672e8a03','1620916566398-39f1143ab7be','1601049541289-9b1b7bbbfe19',
          '1611930022073-b7a4ba5fcccd','1571781926291-c477ebfd024b','1598440947619-2c35fc9aa908'];
$SHOTS = array_merge($SHOTS, $SHOTS);                 // 12 entries, exactly like data.js
$shot = fn(int $i) => U($SHOTS[$i % 12], 600);

echo "Seeding WELL PHARMACY …\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
foreach (['order_items','orders','products','categories','brands','journal_posts','coupons','settings','admin_users','pages','subscribers','messages'] as $t) {
    $pdo->exec("TRUNCATE TABLE $t");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

/* ---------------- categories (drive the nav + PLP) ---------------- */
$cats = [
    ['Skincare',0,0],['Haircare',0,0],['Wellness',0,0],['Makeup',0,0],
    ['Personal Care',0,0],['Mom & Baby',0,0],['Sexual Wellness',0,0],
    ['Health Conditions',1,0],
];
$ci = $pdo->prepare("INSERT INTO categories (name,slug,image,in_nav,is_cross,is_sale,sort) VALUES (?,?,?,1,?,?,?)");
$catImg = ['Skincare'=>'1620916566398-39f1143ab7be','Haircare'=>'1522338242992-e1a54906a8da','Wellness'=>'1584017911766-d451b3d0e843','Makeup'=>'1596462502278-27bfdc403348','Personal Care'=>'1556228453-efd6c1ff04f6','Mom & Baby'=>'1515488042361-ee00e0ddd4e4','Sexual Wellness'=>'1571875257727-256c39da42af','Health Conditions'=>'1584017911766-d451b3d0e843'];
foreach ($cats as $i => [$name,$cross,$sale]) {
    $ci->execute([$name, slugify($name), U($catImg[$name] ?? '1584017911766-d451b3d0e843',400), $cross, $sale, $i]);
}

/* ---------------- brands (8 featured strip + directory) ---------------- */
$featBrands = [
    ['CeraVe','#0057B8'],['La Roche-Posay','#009CB7'],['The Ordinary','#111111'],['Bioderma','#008E83'],
    ['Avène','#0093C9'],['Vichy','#D81E26'],['Eucerin','#0046AD'],['Solgar','#A07C1F'],
];
$dirBrands = ['Aveeno','Bepanthen','Cetaphil','Centrum','Cetraben','Durex','Eau Thermale','Filorga','Garnier','Klorane','Lierac','Mustela','Nuxe','Neutrogena','Nivea','Pigeon','QV Skin','Rilastil','Sebamed','Sebderm','Uriage','Weleda'];
$bi = $pdo->prepare("INSERT INTO brands (name,slug,color,logo,featured,sort) VALUES (?,?,?,'',?,?)");
$s = 0;
foreach ($featBrands as [$n,$c]) { $bi->execute([$n, slugify($n), $c, 1, $s++]); }
foreach ($dirBrands as $n)        { $bi->execute([$n, slugify($n), '', 0, $s++]); }

/* ---------------- products — faithful port of data.js P[] ---------------- */
/* [id, brand, name, price, badge, rating, reviews, cat, shotIdx, was, sale, stock] */
$P = [
    ['lumiere-vitc','Lumière Skin','Vitamin C 15% Brightening Serum',28,'derm',4.8,1204,'Skincare',1,null,null,42],
    ['dermavera-ha','Dermavera','Pure Hyaluronic Acid 2% + B5',22,'best',4.9,3011,'Skincare',4,null,null,60],
    ['aurelle-niacin','Aurelle','Niacinamide 10% + Zinc Pore Serum',19,'',4.7,892,'Skincare',5,24,20,33],
    ['nocturna-retinol','NocturnaLab','Retinol 0.3% Renewal Night Serum',34,'trusted',4.6,540,'Skincare',7,null,null,4],
    ['solheure-spf','SolHeure','Invisible Fluid Sunscreen SPF 50+ PA++++',26,'new',4.8,2150,'Skincare',2,null,null,50],
    ['ceravita-cer','CeraVita','Ceramide Repair Moisturizer',24,'ff',4.9,1780,'Skincare',0,null,null,38],
    ['hydraluna-clean','Hydraluna','Gentle Hydrating Cleanser',16,'vegan',4.7,660,'Skincare',11,null,null,71],
    ['aurelle-azelaic','Aurelle','Azelaic Acid 10% Clarifying Gel',21,'derm',4.5,430,'Skincare',9,null,null,3],
    ['glowcollagen','GlowCollagen','Marine Collagen Peptides',32,'new',4.7,610,'Wellness',10,null,null,5],
    ['velours-tint','VeloursBeauty','Skin Tint Glow Foundation SPF 30',29,'vegan',4.6,520,'Makeup',8,null,null,27],
    ['lipcare-balm','LipCare+','Tinted Lip Treatment Balm SPF 15',12,'best',4.8,1330,'Makeup',8,null,null,84],
    ['phbalance-shampoo','PHbalance','Bond Repair Strengthening Shampoo',21,'new',4.6,470,'Haircare',3,null,null,40],
    ['vitawell-d3','VitaWell','Vitamin D3 2000 IU',14,'best',4.8,980,'Wellness',10,null,null,90],
    ['vitawell-cal','VitaWell','Calcium + Vitamin D3 Complex',18,'',4.6,410,'Wellness',10,21,15,46],
    ['puremarine-omega','PureMarine','Omega-3 1000mg Fish Oil',23,'trusted',4.7,720,'Wellness',10,null,null,52],
    ['ferrovita-iron','FerroVita','Gentle Iron + Vitamin C',15,'ff',4.6,340,'Wellness',10,null,null,29],
    ['zenwell-mag','ZenWell','Magnesium Glycinate Sleep + Calm',20,'best',4.8,1120,'Wellness',10,null,null,5],
    ['immunowell-zinc','ImmunoWell','Zinc + Vitamin C Daily Defense',13,'',4.5,560,'Wellness',10,14.4,10,48],
    ['rosegold-blush','RoseGoldCo','Soft Blush Cream Stick',17,'trend',4.7,380,'Makeup',8,null,null,31],
    ['phbalance-scalp','PHbalance','Caffeine + Biotin Scalp Serum',25,'derm',4.6,290,'Haircare',3,null,null,22],
    ['pureday-deo','PureDay','Aluminium-Free Deodorant',11,'ff',4.5,410,'Personal Care',11,null,null,66],
    ['tinycare-cream','TinyCare','Baby Soothing Diaper Cream',13,'derm',4.9,640,'Mom & Baby',0,null,null,58],
    ['intima-wash','IntimaCare','pH-Balanced Intimate Wash',15,'trusted',4.7,520,'Sexual Wellness',11,null,null,44],
    ['intima-gel','IntimaCare','Water-Based Intimate Gel',17,'vegan',4.6,300,'Sexual Wellness',11,null,null,36],
];
$KW = ['lumiere-vitc'=>'glow','dermavera-ha'=>'hydrate','aurelle-niacin'=>'clarify','nocturna-retinol'=>'renew','solheure-spf'=>'protect','ceravita-cer'=>'repair','hydraluna-clean'=>'cleanse','aurelle-azelaic'=>'clarify','glowcollagen'=>'glow','velours-tint'=>'tint','lipcare-balm'=>'tint','phbalance-shampoo'=>'repair','vitawell-d3'=>'vitamin d','vitawell-cal'=>'bone','puremarine-omega'=>'omega','ferrovita-iron'=>'iron','zenwell-mag'=>'calm','immunowell-zinc'=>'immunity','rosegold-blush'=>'blush','phbalance-scalp'=>'scalp','pureday-deo'=>'fresh','tinycare-cream'=>'soothe','intima-wash'=>'balance','intima-gel'=>'comfort'];
$DESC = ['lumiere-vitc'=>'Brightening serum','dermavera-ha'=>'Deep hydration','aurelle-niacin'=>'Pore-refining serum','nocturna-retinol'=>'Overnight renewal','solheure-spf'=>'Invisible SPF 50+','ceravita-cer'=>'Barrier moisturizer','hydraluna-clean'=>'Gentle daily cleanser','aurelle-azelaic'=>'Clarifying gel','glowcollagen'=>'Marine collagen','velours-tint'=>'Skin tint SPF 30','lipcare-balm'=>'Tinted lip treatment','phbalance-shampoo'=>'Bond repair','vitawell-d3'=>'Daily vitamin D3','vitawell-cal'=>'Calcium + D3','puremarine-omega'=>'Omega-3 fish oil','ferrovita-iron'=>'Gentle iron + C','zenwell-mag'=>'Sleep + calm','immunowell-zinc'=>'Daily defense','rosegold-blush'=>'Cream blush stick','phbalance-scalp'=>'Caffeine scalp serum','pureday-deo'=>'Aluminium-free','tinycare-cream'=>'Baby diaper cream','intima-wash'=>'pH-balanced wash','intima-gel'=>'Water-based gel'];

/* homepage rail image overrides (the "neat packshot" set from index.html) */
$HOME = [
    'solheure-spf'     => ['1556228720-195a672e8a03','1620916566398-39f1143ab7be','latest',1],
    'phbalance-shampoo'=> ['1601049541289-9b1b7bbbfe19','1611930022073-b7a4ba5fcccd','latest',2],
    'velours-tint'     => ['1571781926291-c477ebfd024b','1598440947619-2c35fc9aa908','latest',3],
    'aurelle-azelaic'  => ['1620916566398-39f1143ab7be','1556228720-195a672e8a03','latest',4],
    'vitawell-d3'      => ['1556228720-195a672e8a03','1601049541289-9b1b7bbbfe19','wellness',1],
    'zenwell-mag'      => ['1611930022073-b7a4ba5fcccd','1620916566398-39f1143ab7be','wellness',2],
    'puremarine-omega' => ['1601049541289-9b1b7bbbfe19','1556228720-195a672e8a03','wellness',3],
    'immunowell-zinc'  => ['1620916566398-39f1143ab7be','1611930022073-b7a4ba5fcccd','wellness',4],
];

$pi = $pdo->prepare("INSERT INTO products
  (id,name,brand,category,price,was,sale_pct,badge,rating,reviews,stock,low_stock,kw,descr,long_desc,image,hover_image,feat_latest,feat_wellness,home_sort,sort,status)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,5,?,?,?,?,?,?,?,?,?,'active')");
foreach ($P as $i => $p) {
    [$id,$brand,$name,$price,$badge,$rating,$reviews,$cat,$shotIdx,$was,$sale,$stock] = $p;
    $img   = $shot($shotIdx);
    $hover = $shot($i + 6);
    $featL = 0; $featW = 0; $hsort = 0;
    if (isset($HOME[$id])) {
        [$a,$b,$rail,$ord] = $HOME[$id];
        $img = U($a); $hover = U($b);
        if ($rail === 'latest')   $featL = 1;
        if ($rail === 'wellness') $featW = 1;
        $hsort = $ord;
    }
    $long = "Pharmacist-vetted and clinically-backed. {$DESC[$id]} from {$brand}. Genuine product, sourced from trusted brands and dispensed with care across Lebanon.";
    $pi->execute([$id,$name,$brand,$cat,$price,$was,$sale,$badge,$rating,$reviews,$stock,
                  $KW[$id]??'',$DESC[$id]??'',$long,$img,$hover,$featL,$featW,$hsort,$i]);
}

/* ---------------- journal (homepage blog cards) ---------------- */
$posts = [
    ['Vitamin C vs. Niacinamide: which brightener wins?','Skincare','1556228720-195a672e8a03','Dr. Lara Haddad, PharmD',6],
    ['The 5-step glass-skin routine your derm approves','Routines','1620916297397-a4a5402a3c6c','Clinical Team',7],
    ['Magnesium for sleep: the pharmacist’s guide','Wellness','1522335789203-aabd1fc54bc9','Dr. Rami N., PharmD',5],
];
$ji = $pdo->prepare("INSERT INTO journal_posts (title,slug,category,excerpt,body,image,author,read_min,status,published_at,sort) VALUES (?,?,?,?,?,?,?,?,'published',CURDATE(),?)");
foreach ($posts as $i => [$t,$c,$img,$auth,$rm]) {
    $ji->execute([$t, slugify($t), $c, 'A pharmacist-written guide from THE WELL journal.',
        '<p>Full article coming soon — edit this post in the admin panel.</p>', U($img,800), $auth, $rm, $i]);
}

/* ---------------- coupons ---------------- */
$co = $pdo->prepare("INSERT INTO coupons (code,type,value,min_spend,active) VALUES (?,?,?,?,1)");
$co->execute(['WELL10','percent',10,0]);
$co->execute(['GLOW20','percent',20,40]);
$co->execute(['FREESHIP','freeship',0,0]);

/* ---------------- settings: theme + store + content ---------------- */
$ins = $pdo->prepare("INSERT INTO settings (skey,sval,sgroup) VALUES (?,?,?)");
$set = function(string $k, string $v, string $g) use ($ins) { $ins->execute([$k,$v,$g]); };

/* theme tokens (client controls these in admin → Appearance) */
$set('theme_ink','#2C261F','theme');
$set('theme_ink_soft','#4B3F35','theme');
$set('theme_primary','#9C8158','theme');        // --rose (primary accent)
$set('theme_primary_deep','#7A6244','theme');    // --rose-deep
$set('theme_secondary','#9A6E3F','theme');       // --coral (CTA / secondary)
$set('theme_secondary_deep','#7E5730','theme');  // --coral-deep
$set('theme_cream','#EBE8DF','theme');           // --cream (background band)
$set('theme_cream2','#E2DDD0','theme');          // --cream-2
$set('theme_star','#B59A5E','theme');            // rating stars
$set('theme_font_display','Clash Display','theme');
$set('theme_font_body','General Sans','theme');

/* store */
$set('store_name','WELL SHOP','store');
$set('store_tagline','where Wellness meets You!','store');
$set('store_email','hello@wellpharmacy.com','store');
$set('store_phone','+961 3 627 766','store');
$set('whatsapp_number','9613627766','store');
$set('store_address','Airport Road, before Al Aytam station, Beirut','store');
$set('free_ship_threshold','49','store');
$set('currency_label','$ USD','store');
/* social media */
$set('social_instagram','https://www.instagram.com/wellhealthandbeautyy','social');
$set('social_tiktok','https://www.tiktok.com/@wellhealthandbeauty','social');
$set('social_facebook','','social');
$set('social_youtube','','social');
$set('social_pinterest','','social');
$set('announce_1','FREE SHIPPING on orders above $49','store');
$set('announce_2','Authentic Products • Expert Care • Secure Checkout','store');
$set('footer_about','The online home of Well Pharmacy, Beirut — fusing real pharmacist expertise with clean, trend-forward beauty. Real results. Real confidence. Powered by science. Loved by you. ♡','store');

/* opening hours (one "Label | Value" per line) */
$set('opening_hours', "Mon – Sat | 9am – 9pm\nSunday | 11am – 6pm", 'hours');
$set('hours_status', 'Open now', 'hours');

/* delivery + payment */
$set('ship_fee_beirut','3','delivery');
$set('ship_fee_outside','5','delivery');
$set('delivery_beirut_text','Beirut — same-day delivery','delivery');
$set('delivery_outside_text','Outside Beirut — 2-day delivery','delivery');
$set('cod_enabled','1','payment');
$set('areeba_enabled','0','payment');
$set('areeba_merchant_id','','payment');
$set('areeba_api_password','','payment');
$set('areeba_gateway_url','https://epayment.areeba.com','payment');

/* homepage content copy */
$set('hero_eyebrow','clinically trusted','content');
$set('hero_title','next-gen','content');
$set('hero_title_accent','wellness','content');
$set('hero_sub','Real results. Real confidence. Powered by science, dispensed with care — your everyday glow, distilled. ♡','content');
$set('promise_line1','glow,','content');
$set('promise_accent','responsibly.','content');
$set('promise_sub','Beirut-born, science-led skincare & wellness — dispensed with the care of your neighbourhood pharmacy, delivered to your door.','content');

/* ---------------- content pages (editable in admin) ---------------- */
$pg = $pdo->prepare("INSERT INTO pages (slug,title,intro,body,status,sort) VALUES (?,?,?,?, 'published', ?)");
$pg->execute(['shipping-delivery','Shipping & Delivery','Fast, reliable delivery across Lebanon — with Cash on Delivery available everywhere.',
'<h3>Where we deliver</h3><p>We deliver to <b>every governorate in Lebanon</b>. Orders are dispatched from our Beirut pharmacy and handled with care.</p>
<h3>Delivery times</h3><ul><li><b>Beirut:</b> same-day dispatch for orders placed before 2pm; delivery within 24 hours.</li><li><b>Outside Beirut:</b> 2 business days on average.</li></ul>
<h3>Shipping fees</h3><ul><li>Beirut: a small flat delivery fee applies at checkout.</li><li>Outside Beirut: flat fee by area, shown at checkout.</li><li><b>Free shipping</b> on orders above the threshold shown in your cart.</li></ul>
<h3>Cash on Delivery</h3><p>Pay in cash when your order arrives — available across Lebanon. A small COD handling fee may apply.</p>
<h3>Order tracking</h3><p>You will receive updates by phone/WhatsApp. You can also use the <a href="order-tracking">Track Order</a> page with your order number.</p>',0]);
$pg->execute(['returns-refunds','Returns & Refunds','Changed your mind? Our hassle-free returns make it easy.',
'<h3>Our promise</h3><p>If something isn\'t right, we\'ll make it right. You can return most items within <b>14 days</b> of delivery.</p>
<h3>What can be returned</h3><ul><li>Unopened items in their original, sealed packaging.</li><li>Items that arrived damaged, faulty, or incorrect (we cover return costs).</li></ul>
<h3>What can\'t be returned</h3><p>For health &amp; safety reasons, some products are non-returnable once opened — including certain skincare, supplements, intimate and baby care items. This does not affect your statutory rights.</p>
<h3>How to start a return</h3><ol><li>Contact us via <a href="contact">the contact page</a> or WhatsApp with your order number.</li><li>Our team confirms eligibility and arranges pickup or drop-off.</li><li>Once received and checked, your refund is issued.</li></ol>
<h3>Refunds</h3><p>Refunds are processed to your original payment method, or as store credit for Cash-on-Delivery orders, within 5–7 business days.</p>',1]);
$pg->execute(['faq','Frequently Asked Questions','Quick answers to the questions we hear most.',
'<h3>Do you deliver across Lebanon?</h3><p>Yes — to every governorate, with same-day dispatch in Beirut and Cash on Delivery available everywhere.</p>
<h3>Is Cash on Delivery available?</h3><p>Absolutely. Pay in cash when your order arrives. A small COD handling fee may apply.</p>
<h3>Are your products authentic?</h3><p>100%. Every product is sourced directly from trusted brands and quality-checked by our licensed pharmacists.</p>
<h3>Can I talk to a pharmacist before buying?</h3><p>Yes — reach us by WhatsApp or the contact form. Our licensed pharmacists answer product and wellness questions, privately.</p>
<h3>What payment methods do you accept?</h3><p>Cash on Delivery and secure card payment at checkout.</p>
<h3>How long does delivery take?</h3><p>Same-day in Beirut (order before 2pm); 2 business days on average elsewhere in Lebanon.</p>
<h3>What is your returns policy?</h3><p>Hassle-free returns within 14 days on most items. Some health products are non-returnable once opened, for safety. See <a href="returns-refunds">Returns &amp; Refunds</a>.</p>',2]);

/* ---------------- admin user ---------------- */
$au = $pdo->prepare("INSERT INTO admin_users (username,password_hash,name,role) VALUES (?,?,?, 'admin')");
$au->execute(['admin', password_hash('wellpharmacy', PASSWORD_DEFAULT), 'Store Admin']);

echo "Done.\n";
echo "  products: " . val("SELECT COUNT(*) FROM products") . "\n";
echo "  brands:   " . val("SELECT COUNT(*) FROM brands") . "\n";
echo "  admin login → username: admin   password: wellpharmacy   (CHANGE THIS)\n";
