<?php
/* shared styling for login / register / verify / account / wishlist — matches the storefront */
$AUTH_CSS = <<<CSS
<style>
  .authwrap{max-width:460px;margin-inline:auto;padding:44px 20px 72px}
  .authwrap.wide{max-width:1060px}
  .authcard{background:#fff;border:1px solid var(--border-2);border-radius:var(--r-card);padding:32px 30px;box-shadow:var(--sh-sm)}
  .authcard h1{font-family:var(--fp);font-size:clamp(26px,3vw,34px);font-weight:600;text-transform:lowercase;margin:0 0 6px}
  .authcard .sub{font-size:14px;color:var(--text-muted);margin:0 0 22px;line-height:1.5}
  .authcard .field label{font-size:13px;font-weight:600;color:var(--ink-soft)}
  .two{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .authcard .btn{width:100%;height:50px;margin-top:6px}
  .swap{margin:18px 0 0;text-align:center;font-size:13.5px;color:var(--text-muted)}
  .swap a{color:var(--rose-deep);font-weight:600;text-decoration:underline}
  .flash{border-radius:12px;padding:12px 14px;font-size:13.5px;margin:0 0 16px;line-height:1.5}
  .flash.ok{background:var(--mint-tint);color:#4A4832}
  .flash.err{background:#F7E9E2;color:var(--coral-deep)}
  .otpbox{letter-spacing:12px;text-align:center;font-size:26px;font-weight:700;font-family:var(--fp)}
  .optnote{background:var(--blush-tint);border-radius:12px;padding:12px 14px;font-size:12.5px;color:var(--rose-deep);margin:0 0 18px}
  /* account layout */
  .acct-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:22px}
  .acct-head h1{font-family:var(--fp);font-size:clamp(28px,3.4vw,40px);font-weight:600;text-transform:lowercase;margin:0}
  .acct-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px}
  .acct-tabs a{height:38px;padding:0 16px;border-radius:var(--r-pill);border:1px solid var(--border-2);background:#fff;
    font-size:13px;font-weight:600;color:var(--ink-soft);display:inline-flex;align-items:center}
  .acct-tabs a.on{background:var(--ink);color:#fff;border-color:var(--ink)}
  .panel{background:#fff;border:1px solid var(--border-2);border-radius:var(--r-card);padding:26px}
  .panel h2{font-family:var(--fp);font-size:22px;font-weight:600;text-transform:lowercase;margin:0 0 18px}
  .ordrow{border:1px solid var(--border-2);border-radius:14px;padding:16px;margin-bottom:12px}
  .ordrow .top{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center}
  .ordrow .no{font-family:var(--fp);font-weight:600;font-size:16px}
  .ordrow .when{font-size:12.5px;color:var(--text-muted)}
  .ordrow .li{font-size:13px;color:var(--text-muted);margin-top:8px;line-height:1.6}
  .pill{height:24px;padding:0 10px;border-radius:var(--r-pill);font-size:11px;font-weight:700;text-transform:uppercase;
    display:inline-flex;align-items:center;background:var(--cream-2);color:var(--ink-soft)}
  .pill.new{background:var(--clinic-blue-tint);color:var(--clinic-blue)}
  .pill.delivered{background:var(--mint-tint);color:#5C5942}
  .pill.cancelled{background:#F7E9E2;color:var(--coral-deep)}
  .empty{text-align:center;padding:44px 20px;color:var(--text-muted)}
  .empty b{display:block;font-family:var(--fp);font-size:20px;color:var(--ink);margin-bottom:6px}
  .phone-row{display:grid;grid-template-columns:minmax(0,190px) minmax(0,1fr);gap:8px}
  .phone-row .phone-dial{padding-inline:10px}
  @media(max-width:640px){.two{grid-template-columns:1fr}.phone-row{grid-template-columns:minmax(0,130px) minmax(0,1fr)}}
</style>
CSS;
