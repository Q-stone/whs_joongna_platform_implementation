<?php
/* pages/report.php - 신고 폼 */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
if (!$system_v['login']['valid']) { echo '<div class="msg warn">' . e($L['login_required']) . ' <a href="/?mode=authorize">' . e($L['nav_login']) . '</a></div>'; return; }
if (!empty($system_v['login']['report_ban'])) { echo '<div class="msg err">' . e($L['report_ban']) . '</div>'; return; }
if ($system_v['view'] === null) { echo '<div class="msg err">' . e($L['report_target_bad']) . '</div>'; return; }
list($target, $ref) = explode('|', $system_v['view']);
$target = (int)$target; $ref = (int)$ref;
global $sql;
if ($target === 0) {
    $o = $sql->one("SELECT prd_title FROM products WHERE prd_number=?", 'i', [$ref]);
    $what = '상품 #' . $ref . ($o ? ' (' . $o['prd_title'] . ')' : '');
} else {
    $o = $sql->one("SELECT acc_id FROM users WHERE acc_number=?", 'i', [$ref]);
    $what = '사용자 #' . $ref . ($o ? ' (' . $o['acc_id'] . ')' : '');
}
?>
<h1 class="h1"><?= e($L['report_title']) ?></h1>
<div class="msg info"><?= e($L['report_target']) ?>: <?= e($what) ?></div>
<form id="rp" class="card" onsubmit="return false">
  <input type="hidden" name="token" value="<?= e($system_v['token']) ?>">
  <input type="hidden" name="mode" value="report">
  <input type="hidden" name="target" value="<?= $target ?>">
  <input type="hidden" name="ref" value="<?= $ref ?>">
  <label><?= e($L['report_reason']) ?></label>
  <textarea name="reason" maxlength="500" placeholder="<?= e($L['report_reason_placeholder']) ?>"></textarea>
  <div style="margin-top:12px"><button class="btn danger"><?= e($L['report_btn']) ?></button></div>
</form>
<script>
document.querySelector('#rp .btn.danger').addEventListener('click', function(){
  Jn.req('/service_bridge.php', { form: new FormData(document.getElementById('rp')) }).then(function(j){
    Jn.handleRes(j, function(){ Jn.toast(Jn.t('report_done'),'ok'); setTimeout(function(){location.href='/';}, 1000); });
  });
});
</script>