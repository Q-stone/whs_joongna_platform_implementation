<?php
/* pages/terms.php - 이용약관. ?v=N -> 이전 버전, 생략 -> 최신 */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
global $sql;
$vid = only_digits($_GET['v'] ?? '', 20);
if ($vid === '') {
    $doc = $sql->one("SELECT sd_id,sd_body,sd_time FROM site_docs WHERE sd_kind='terms' ORDER BY sd_id DESC LIMIT 1");
    $isLatest = true;
} else {
    $doc = $sql->one("SELECT sd_id,sd_body,sd_time FROM site_docs WHERE sd_kind='terms' AND sd_id=?", 'i', [(int)$vid]);
    $isLatest = false;
}
$isAdmin = (int)$system_v['login']['group'] === 1;
$hist = $sql->run("SELECT sd_id,sd_time FROM site_docs WHERE sd_kind='terms' ORDER BY sd_id DESC LIMIT 10");
?>
<h1 class="h1"><?= e($L['terms_title']) ?> <?= $isLatest ? '' : '(이전 버전 #' . (int)$vid . ')' ?></h1>
<?php if ($doc): ?>
  <div class="card">
    <div><?= Security::render_text($doc['sd_body']) ?></div>
    <div class="small" style="margin-top:8px">버전 #<?= (int)$doc['sd_id'] ?> · <?= e($doc['sd_time']) ?><?php if(!$isLatest): ?> · <a href="/?mode=terms">최신 버전 보기</a><?php endif; ?></div>
  </div>
  <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span class="small">모든 버전:</span>
    <?php foreach ($hist as $h): ?>
      <a class="btn sm <?= (int)$h['sd_id']===(int)$doc['sd_id']?'primary':'ghost' ?>" href="/?mode=terms&v=<?= (int)$h['sd_id'] ?>">#<?= (int)$h['sd_id'] ?> (<?= e(mb_substr($h['sd_time'],0,10)) ?>)</a>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card">
    <p><?= e($L['terms_body_1']) ?></p>
    <p><?= e($L['terms_body_2']) ?></p>
    <p><?= e($L['terms_body_3']) ?></p>
    <p><?= e($L['terms_body_4']) ?></p>
  </div>
<?php endif; ?>
<?php if ($isAdmin): ?>
<p class="small">약관 갱신은 <a href="/?mode=admin&tab=system">관리자 · 시스템 공지/점검 탭</a>에서 할 수 있습니다.</p>
<?php endif; ?>