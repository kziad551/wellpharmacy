# Well Pharmacy — How to make changes & get them online

## The two sites
| | URL |
|---|---|
| **Local (dev)** | http://localhost/wellpharmacy |
| **Live (online)** | https://wellpharmacy.wztechno.com  ·  admin at `/admin/` |

There are **two kinds of change**, and they travel to the live site differently. This is the whole thing 👇

---

## 1) CODE changes (look, layout, features — the `.php` / `.css` / `.js` files)

1. Edit the files locally.
2. Test at **http://localhost/wellpharmacy** (after CSS/JS edits, hard-refresh with `Ctrl+F5`).
3. Commit & push:
   ```bash
   git add -A
   git commit -m "what changed"
   git push
   ```
4. The server's **auto-deploy webhook pulls automatically** → it's **live within seconds** at wellpharmacy.wztechno.com.

That's the entire deploy. No file uploads, no Ziad.

> After CSS/JS changes, bump `ASSET_VER` in `inc/config.php` (`dev1` → `dev2` → …) so browsers fetch the new files instead of the cached ones.

---

## 2) CONTENT / DATA changes (products, home sections, brands, prices, journal…)

This lives in the **database**, which is **not** in git. So pushing does **not** move data. Two ways:

### ✅ Option A — edit directly on the LIVE admin (recommended)
- Go to **https://wellpharmacy.wztechno.com/admin/**
- Add / edit products, home sections, brands, etc. **there**.
- It writes **straight to the live database → instantly online.**
- No local step, no syncing. **Use this for everyday content.**

### Option B — edit locally, then push the DB online (bulk / initial loads only)
Use this when you've built a lot of data locally and want to copy it up.
1. Make the changes in your **local** admin (`localhost/wellpharmacy/admin/`) — writes to your **local** DB; you see it on localhost.
2. Export the local DB:
   ```bash
   "C:\xampp\mysql\bin\mysqldump.exe" -u root --default-character-set=utf8mb4 wellpharmacy_dev > wellpharmacy_db_export.sql
   ```
3. Load it into the **online** DB with one of:
   - **phpMyAdmin** → *Import* tab → choose the `.sql` → *Go*  (needs the phpMyAdmin URL from Ziad — one-time), **or**
   - **The one-time import script** — ask Claude to regenerate `refresh.php`; `git push`; open its URL once; then Claude deletes it.

   ⚠️ **This REPLACES the entire online database with your local one** (and resets the admin password to whatever your local one is). Great for a full sync; don't use it if the live DB has real orders you want to keep.

---

## Golden rule
- **Code** → `git push` (auto-deploys). ✅
- **Content** → do it on the **live admin** (no sync needed). ✅
- **Sync the whole DB local→online** → only for bulk/initial loads (Option B).

---

## Answering "I added something locally, how do I see it online?"
- If it was a **code** change → push it. Done.
- If it was **content** (a product, a section…) → the fastest is to just **make that same edit in the live admin**. Otherwise use Option B to copy the whole local DB up.
