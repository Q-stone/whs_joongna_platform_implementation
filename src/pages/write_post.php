<?php
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
if (!$system_v['login']['valid']) { echo '<div class="msg warn">' . e($L['login_required']) . ' <a href="/?mode=authorize">' . e($L['nav_login']) . '</a></div>'; return; }
if (!empty($system_v['login']['dormant'])) { echo '<div class="msg err">' . e($L['dormant_block']) . '</div>'; return; }
require __DIR__ . '/_tags.php';
global $sql;
$edit = null; $media = [];
if ($system_v['view'] !== null) {
    $edit = $sql->one("SELECT prd_number,prd_seller,prd_tag,prd_title,prd_desc,prd_price,prd_blind FROM products WHERE prd_number=?", 'i', [(int)$system_v['view']]);
    if (!$edit) { echo '<div class="msg err">' . e($L['product_not_found']) . '</div>'; return; }
    if ((int)$edit['prd_seller'] !== (int)$system_v['login']['number'] && (int)$system_v['login']['group'] === 0) { echo '<div class="msg err">' . e($L['product_no_perm_edit']) . '</div>'; return; }
    if ((int)$edit['prd_blind'] === 1 && (int)($system_v['login']['group'] ?? 0) !== 1) { echo '<div class="msg warn">' . e($L['product_hidden']) . '</div>'; return; }
    $media = $sql->run("SELECT pm_id,pm_save_name,pm_kind FROM product_media WHERE pm_prd=? ORDER BY pm_is_main DESC, pm_id ASC", 'i', [(int)$edit['prd_number']]);
}
?>
<h1 class="h1"><?= $edit ? e($L['product_edit']) : e($L['product_new']) ?></h1>
<form id="post_form" class="card" onsubmit="return false" enctype="multipart/form-data">
  <input type="hidden" name="token" value="<?= e($system_v['token']) ?>">
  <input type="hidden" name="mode" value="<?= $edit ? 'revise_post' : 'leave_post' ?>">
  <?php if ($edit): ?><input type="hidden" name="revise_post_number" value="<?= (int)$edit['prd_number'] ?>"><?php endif; ?>
  <label><?= e($L['product_title']) ?></label>
  <input name="form_title" placeholder="<?= e($L['product_title_placeholder']) ?>" value="<?= e($edit['prd_title'] ?? '') ?>">
  <label><?= e($L['product_category']) ?></label>
  <select name="form_tag">
    <?php foreach ($TAGS as $id => $name): ?>
      <option value="<?= (int)$id ?>" <?= ($edit && (int)$edit['prd_tag']===$id) ? 'selected' : '' ?>><?= e($name) ?></option>
    <?php endforeach; ?>
  </select>
  <label><?= e($L['product_price']) ?> (<?= e($L['product_price_unit']) ?>)</label>
  <input name="form_price" id="form_price" inputmode="numeric" value="<?= e((string)($edit['prd_price'] ?? 0)) ?>" placeholder="0">
  <label><?= e($L['product_desc']) ?> <span class="small">(HTML: &lt;b&gt; &lt;i&gt; &lt;strike&gt; &lt;hr&gt; 사용 가능. 줄바꿈은 자동으로 &lt;br&gt;)</span></label>
  <div class="editor-bar" style="display:flex;gap:4px;margin-bottom:4px;flex-wrap:wrap">
    <button type="button" class="btn sm" data-wrap="b" title="Bold"><b>B</b></button>
    <button type="button" class="btn sm" data-wrap="i" title="Italic"><i>I</i></button>
    <button type="button" class="btn sm" data-wrap="strike" title="Strikethrough"><strike>S</strike></button>
    <button type="button" class="btn sm" data-tag="hr" title="Horizontal line">―</button>
  </div>
  <textarea name="form_desc" id="form_desc" placeholder="<?= e($L['product_desc_placeholder']) ?>" rows="10"><?= $edit['prd_desc'] ? Security::for_textarea($edit['prd_desc']) : '' ?></textarea>
  <label><?= e($L['product_photos']) ?></label>
  <input type="file" name="form_media[]" multiple accept="image/*,video/*">
  <div class="small"><?= e($L['product_photos_hint']) ?></div>
  <?php if (!empty($media)): ?>
    <div style="margin-top:10px"><b>기존 미디어</b> (대표가 먼저 표시)</div>
    <div class="row" style="gap:8px;margin-top:6px">
    <?php foreach ($media as $m): $mid=(int)$m['pm_id']; $src='/uploads/'.e($m['pm_save_name']); $isv=(int)$m['pm_kind']===1; ?>
      <div style="flex:0 0 110px;position:relative;border:1px solid var(--line);border-radius:8px;padding:4px;text-align:center" id="media_<?= $mid ?>">
        <?php if ($isv): ?><video src="<?= $src ?>" style="width:90px;height:90px;object-fit:cover"></video><?php else: ?><img src="<?= $src ?>" style="width:90px;height:90px;object-fit:cover;border-radius:6px"><?php endif; ?>
        <div style="display:flex;gap:3px;justify-content:center;margin-top:3px">
          <button type="button" class="btn sm" data-set-main="<?= $mid ?>" style="font-size:11px;padding:2px 6px">대표</button>
          <button type="button" class="btn sm danger" data-del-media="<?= $mid ?>" style="font-size:11px;padding:2px 6px">삭제</button>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <label class="checkbox"><input type="checkbox" name="form_check_notice"> <?= e($L['product_notice']) ?></label>
  <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn primary lg" id="post_submit"><?= $edit ? e($L['edit']) : e($L['save']) ?></button>
    <?php if ($edit): ?>
      <button class="btn danger" type="button" id="post_delete"><?= e($L['delete']) ?></button>
      <input type="hidden" name="form_post_delete" value="">
    <?php endif; ?>
  </div>
</form>
<script>
(function(){
  var ta = document.getElementById('form_desc');
  if (!ta) return;
  function wrapInline(tag){
    var s = ta.selectionStart, e = ta.selectionEnd, t = ta.value;
    if (s === e) { Jn.toast('텍스트를 먼저 선택하세요','warn'); return; }
    var sel = t.substring(s, e);
    ta.value = t.substring(0, s) + '<' + tag + '>' + sel + '</' + tag + '>' + t.substring(e);
    var nl = tag.length * 2 + 5;
    ta.focus();
    ta.setSelectionRange(e + nl, e + nl);
  }
  function insertInline(tag){
    var s = ta.selectionStart, t = ta.value;
    var ins = '<' + tag + '>';
    ta.value = t.substring(0, s) + ins + t.substring(s);
    ta.focus();
    ta.setSelectionRange(s + ins.length, s + ins.length);
  }
  document.querySelectorAll('button[data-wrap]').forEach(function(b){
    b.addEventListener('click', function(){ wrapInline(b.getAttribute('data-wrap')); });
  });
  document.querySelectorAll('button[data-tag]').forEach(function(b){
    b.addEventListener('click', function(){ insertInline(b.getAttribute('data-tag')); });
  });
  document.getElementById('post_submit').addEventListener('click', function(){
    Jn.req('/service_bridge.php', { form: new FormData(document.getElementById('post_form')) }).then(function(j){
      if (j && j.success) { Jn.toast(j.description, 'ok'); setTimeout(function(){ if(j.redirect) location.href=j.redirect; }, 700); }
      else if (j && j.error) Jn.toast(j.description, 'err');
    });
  });
  var del = document.getElementById('post_delete');
  if (del) del.addEventListener('click', function(){
    Jn.confirmBox('<?= ejs($L['product_delete_confirm']) ?>', function(){
      document.querySelector('[name=form_post_delete]').value = 'on';
      Jn.req('/service_bridge.php', { form: new FormData(document.getElementById('post_form')) }).then(function(j){
        if (j && j.success) { Jn.toast(j.description, 'ok'); setTimeout(function(){ if(j.redirect) location.href=j.redirect; }, 700); }
      });
    });
  });
  document.querySelectorAll('button[data-del-media]').forEach(function(b){
    b.addEventListener('click', function(){
      var id = b.getAttribute('data-del-media');
      Jn.confirmBox('이 미디어를 삭제합니다?', function(){
        var fd = new FormData(); fd.append('token', Jn.csrf()); fd.append('mode', 'media_delete'); fd.append('pm_id', id);
        Jn.req('/service_bridge.php', { form: fd }).then(function(j){
          Jn.handleRes(j, function(){
            var el = document.getElementById('media_' + id);
            if (el) el.remove();
          });
        });
      });
    });
  });
  document.querySelectorAll('button[data-set-main]').forEach(function(b){
    b.addEventListener('click', function(){
      var id = b.getAttribute('data-set-main');
      var fd = new FormData(); fd.append('token', Jn.csrf()); fd.append('mode', 'media_set_main'); fd.append('pm_id', id); fd.append('prd', '<?= (int)($edit['prd_number'] ?? 0) ?>');
      Jn.req('/service_bridge.php', { form: fd }).then(function(j){ Jn.handleRes(j, function(){ Jn.toast('대표 이미지로 설정','ok'); }); });
    });
  });
})();
</script>