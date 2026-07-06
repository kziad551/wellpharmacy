<?php
require __DIR__ . '/inc/layout.php';

$id = (int) input('id');
$editing = $id > 0 && ($p = row("SELECT * FROM journal_posts WHERE id = ?", [$id]));
if ($id > 0 && !$editing) { flash('Post not found.', 'err'); redirect('journal'); }

if (is_post()) {
    csrf_check();
    $title = trim((string) input('title'));
    $slug  = slugify((string) (input('slug') ?: $title));
    $image = trim((string) input('image'));
    $upErr = null;
    if ($u = save_upload('image_file', $upErr)) $image = $u;
    $pub = trim((string) input('published_at'));

    if ($title === '') { flash('Title is required.', 'err'); redirect($editing ? "journal-edit?id=$id" : 'journal-edit'); }

    $data = [
        'title'        => $title,
        'slug'         => $slug,
        'category'     => trim((string) input('category')),
        'excerpt'      => trim((string) input('excerpt')),
        'body'         => (string) input('body'),
        'image'        => $image,
        'author'       => trim((string) input('author')),
        'read_min'     => max(1, (int) input('read_min')),
        'status'       => input('status') === 'draft' ? 'draft' : 'published',
        'published_at' => $pub !== '' ? $pub : null,
        'sort'         => (int) input('sort'),
    ];

    if ($editing) {
        $data['id'] = $id;
        q("UPDATE journal_posts SET title=:title, slug=:slug, category=:category, excerpt=:excerpt, body=:body, image=:image, author=:author, read_min=:read_min, status=:status, published_at=:published_at, sort=:sort WHERE id=:id", $data);
        flash($upErr ? 'Post updated — but the image was not changed: ' . $upErr : 'Post updated.', $upErr ? 'err' : 'ok');
    } else {
        if (row("SELECT id FROM journal_posts WHERE slug = ?", [$slug])) { flash('A post with that URL slug already exists.', 'err'); redirect('journal-edit'); }
        q("INSERT INTO journal_posts (title,slug,category,excerpt,body,image,author,read_min,status,published_at,sort)
           VALUES (:title,:slug,:category,:excerpt,:body,:image,:author,:read_min,:status,:published_at,:sort)", $data);
        flash($upErr ? 'Post created — but no image was added: ' . $upErr : 'Post created.', $upErr ? 'err' : 'ok');
    }
    redirect('journal');
}

$v = $editing ? $p : ['id'=>0,'title'=>'','slug'=>'','category'=>'','excerpt'=>'','body'=>'','image'=>'','author'=>'','read_min'=>5,'status'=>'published','published_at'=>date('Y-m-d'),'sort'=>0];
admin_head($editing ? 'Edit post' : 'Add post', 'journal', $editing ? $v['title'] : 'New journal post');
?>
<form method="post" action="<?= $editing ? "journal-edit?id=".e($id) : "journal-edit" ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="page-actions"><a class="btn btn-ghost" href="journal">← Back</a><div class="spacer"></div><button class="btn btn-primary">Save post</button></div>

  <div class="a-grid" style="grid-template-columns:1.5fr 1fr">
    <div style="display:flex;flex-direction:column;gap:18px">
      <div class="a-card"><div class="hd"><h2>Article</h2></div><div class="bd">
        <div class="field"><label>Title</label><input class="input" name="title" value="<?= e($v['title']) ?>" required></div>
        <div class="f-row">
          <div class="field"><label>URL slug</label><input class="input" name="slug" value="<?= e($v['slug']) ?>" placeholder="auto from title"><div class="hint">/journal-post?slug=…</div></div>
          <div class="field"><label>Category</label><input class="input" name="category" value="<?= e($v['category']) ?>" placeholder="Skincare"></div>
        </div>
        <div class="field"><label>Excerpt</label><textarea class="input" name="excerpt" rows="2"><?= e($v['excerpt']) ?></textarea><div class="hint">Short summary shown on the journal cards.</div></div>
        <div class="field"><label>Body</label><textarea class="input" name="body" data-rich rows="16"><?= e($v['body']) ?></textarea>
          <div class="hint">Use the toolbar to format — bold, headings, lists and links. No HTML needed.</div>
        </div>
      </div></div>
    </div>

    <div style="display:flex;flex-direction:column;gap:18px">
      <div class="a-card"><div class="hd"><h2>Cover image</h2></div><div class="bd">
        <?php if ($v['image']): ?><img src="<?= e(asrc($v['image'])) ?>" style="width:100%;max-height:150px;object-fit:cover;border-radius:10px;margin-bottom:8px;border:1px solid var(--a-border2)"><?php endif; ?>
        <input class="input" name="image" value="<?= e($v['image']) ?>" placeholder="https://… or upload below">
        <input type="file" name="image_file" accept="image/*" data-maxmb="10" style="margin-top:8px;font-size:12.5px"><div class="hint">JPG/PNG/WebP, max 10 MB — big photos are auto-optimized.</div>
      </div></div>

      <div class="a-card"><div class="hd"><h2>Publishing</h2></div><div class="bd">
        <div class="field"><label>Status</label><select class="input" name="status">
          <option value="published" <?= $v['status']==='published'?'selected':'' ?>>Published (visible)</option>
          <option value="draft" <?= $v['status']==='draft'?'selected':'' ?>>Draft (hidden)</option>
        </select></div>
        <div class="f-row">
          <div class="field"><label>Author</label><input class="input" name="author" value="<?= e($v['author']) ?>" placeholder="Dr. Lara Haddad, PharmD"></div>
          <div class="field"><label>Read time (min)</label><input class="input" type="number" min="1" name="read_min" value="<?= e($v['read_min']) ?>"></div>
        </div>
        <div class="f-row">
          <div class="field"><label>Publish date</label><input class="input" type="date" name="published_at" value="<?= e($v['published_at']) ?>"></div>
          <div class="field"><label>Sort order</label><input class="input" type="number" name="sort" value="<?= e($v['sort']) ?>"></div>
        </div>
      </div></div>
    </div>
  </div>
  <div class="page-actions" style="margin-top:18px"><div class="spacer"></div><button class="btn btn-primary">Save post</button></div>
</form>
<?php admin_foot();
