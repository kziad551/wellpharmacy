<?php
require __DIR__ . '/inc/layout.php';

$id = (int) input('id');
$editing = $id > 0 && ($p = row("SELECT * FROM pages WHERE id = ?", [$id]));
if ($id > 0 && !$editing) { flash('Page not found.', 'err'); redirect('pages'); }

if (is_post()) {
    csrf_check();
    $title = trim((string) input('title'));
    $slug  = slugify((string) (input('slug') ?: $title));
    $data = [
        'title'  => $title,
        'slug'   => $slug,
        'intro'  => trim((string) input('intro')),
        'body'   => (string) input('body'),
        'status' => input('status') === 'draft' ? 'draft' : 'published',
        'sort'   => (int) input('sort'),
    ];
    if ($title === '') { flash('Title is required.', 'err'); redirect($editing ? "page-edit?id=$id" : 'page-edit'); }

    if ($editing) {
        $data['id'] = $id;
        q("UPDATE pages SET title=:title, slug=:slug, intro=:intro, body=:body, status=:status, sort=:sort WHERE id=:id", $data);
        flash('Page updated.');
    } else {
        if (row("SELECT id FROM pages WHERE slug = ?", [$slug])) { flash('A page with that URL already exists.', 'err'); redirect('page-edit'); }
        q("INSERT INTO pages (title,slug,intro,body,status,sort) VALUES (:title,:slug,:intro,:body,:status,:sort)", $data);
        flash('Page created.');
    }
    redirect('pages');
}

$v = $editing ? $p : ['id'=>0,'title'=>'','slug'=>'','intro'=>'','body'=>'','status'=>'published','sort'=>0];
admin_head($editing ? 'Edit page' : 'Add page', 'pages', $editing ? $v['title'] : 'New content page');
?>
<form method="post" action="<?= $editing ? "page-edit?id=$id" : "page-edit" ?>">
  <?= csrf_field() ?>
  <div class="page-actions"><a class="btn btn-ghost" href="pages">← Back</a><div class="spacer"></div><button class="btn btn-primary">Save page</button></div>
  <div class="a-card"><div class="bd">
    <div class="f-row">
      <div class="field"><label>Title</label><input class="input" name="title" value="<?= e($v['title']) ?>" required></div>
      <div class="field"><label>URL slug</label><input class="input" name="slug" value="<?= e($v['slug']) ?>" placeholder="auto from title"><div class="hint">Page address, e.g. <b>shipping-delivery</b> → /shipping-delivery</div></div>
    </div>
    <div class="field"><label>Intro line</label><input class="input" name="intro" value="<?= e($v['intro']) ?>" placeholder="Short summary shown under the title"></div>
    <div class="field"><label>Body</label><textarea class="input" name="body" data-rich rows="18"><?= e($v['body']) ?></textarea>
      <div class="hint">Use the toolbar to format — bold, headings, lists and links. No HTML needed.</div>
    </div>
    <div class="f-row">
      <div class="field"><label>Status</label><select class="input" name="status"><option value="published" <?= $v['status']==='published'?'selected':'' ?>>Published</option><option value="draft" <?= $v['status']==='draft'?'selected':'' ?>>Draft</option></select></div>
      <div class="field"><label>Sort order</label><input class="input" type="number" name="sort" value="<?= e($v['sort']) ?>"></div>
    </div>
  </div></div>
  <div class="page-actions"><div class="spacer"></div><button class="btn btn-primary">Save page</button></div>
</form>
<?php admin_foot();
