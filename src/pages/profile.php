<?php
/* pages/profile.php - 사용자 프로필 */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
if (!$system_v['login']['valid']) { echo '<div class="msg warn">' . e($L['login_required']) . ' <a href="/?mode=authorize">' . e($L['nav_login']) . '</a></div>'; return; }
if ($system_v['view'] === null) { echo '<div class="msg err">사용자 번호가 올바르지 않습니다.</div>'; return; }
global $sql;
$u = $sql->one("SELECT acc_number,acc_id,acc_nick,acc_intro,acc_trust,acc_trade_count,acc_sell_count,acc_buy_count,acc_group,acc_status,registertime FROM users WHERE acc_number=?", 'i', [(int)$system_v['view']]);
if (!$u) { echo '<div class="msg err">존재하지 않는 사용자</div>'; return; }
if ((int)$u['acc_status'] === 2) { echo '<div class="msg err">' . e($L['profile_dormant']) . '</div>'; return; }
$items = $sql->run("SELECT prd_number,prd_title,prd_price,prd_sold,
        (SELECT pm.pm_save_name FROM product_media pm WHERE pm.pm_prd=p.prd_number AND pm.pm_kind=0 ORDER BY pm.pm_is_main DESC, pm.pm_id ASC LIMIT 1) AS img
  FROM products p WHERE p.prd_seller=? AND p.prd_blind=0 ORDER BY p.prd_number DESC", 'i', [(int)$u['acc_number']]);
$reviews = $sql->run("SELECT rv_score,rv_comment,rv_time,(SELECT COALESCE(NULLIF(acc_nick,''),acc_id) FROM users WHERE acc_number=rv_writer) AS who
  FROM reviews WHERE rv_target=? ORDER BY rv_id DESC LIMIT 30", 'i', [(int)$u['acc_number']]);
$badge = (int)$u['acc_group']===1 ? '<span class="badge admin">'.e($L['nav_admin']).'</span>' : '';
?>
<h1 class="h1"><?= e($L['profile_user']) ?> <?= $badge ?></h1>
<div class="card">
  <div class="row">
    <div><div class="small"><?= e($L['profile_user']) ?></div><b style="font-size:18px"><?= e($u['acc_nick'] ?: $u['acc_id']) ?></b></div>
    <div><div class="small"><?= e($L['profile_joindate']) ?></div><?= e($u['registertime']) ?></div>
    <div><div class="small"><?= e($L['profile_trade']) ?></div><?= (int)$u['acc_trade_count'] ?> (<?= e($L['profile_sell']) ?> <?= (int)$u['acc_sell_count'] ?> · <?= e($L['profile_buy']) ?> <?= (int)$u['acc_buy_count'] ?>)</div>
    <div><div class="small"><?= e($L['profile_trust']) ?></div><b style="font-size:20px"><?= e($u['acc_trust']) ?></b> / 100</div>
  </div>
  <hr class="hr">
  <div class="msg info"><?= e($L['profile_introduce']) ?>: <?= e($u['acc_intro'] ?: $L['profile_no_intro']) ?></div>
  <?php if ((int)$system_v['login']['number'] != $u['acc_number']): ?>
    <div class="row" style="margin-top:8px">
      <button class="btn primary" onclick="location.href='/?mode=dm&with=<?= (int)$u['acc_number'] ?>'">채팅 확인</button>
      <?php if (empty($system_v['login']['report_ban'])): ?>
      <a class="btn danger" href="/?mode=report&target=1&ref=<?= (int)$u['acc_number'] ?>"><?= e($L['profile_report_user']) ?></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<h2 class="h2"><?= e($L['profile_selling']) ?></h2>
<?php if (empty($items)): ?>
  <div class="empty"><?= e($L['profile_no_products']) ?></div>
<?php else: ?>
<div class="grid">
<?php foreach ($items as $p):
  $img = $p['img']?('/uploads/'.e($p['img'])):'/cdn/img/no-image.svg'; ?>
  <a class="pcard" href="/?mode=view_post&view=<?= (int)$p['prd_number'] ?>">
    <div class="thumb"><img src="<?= $img ?>" alt="" loading="lazy"></div>
    <div class="meta"><div class="ptitle"><?= e($p['prd_title']) ?></div>
      <div class="pprice"><?= number_format((int)$p['prd_price']) ?> <?= e($L['product_price_unit']) ?></div>
      <div class="ptag"><?= (int)$p['prd_sold']===1?'<span class="badge ok">'.$L['product_sold'].'</span>':'' ?></div>
    </div>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<h2 class="h2"><?= e($L['profile_reviews']) ?></h2>
<?php if (empty($reviews)): ?>
  <div class="empty"><?= e($L['profile_no_reviews']) ?></div>
<?php else: ?>
<div class="card">
<?php foreach ($reviews as $r): ?>
  <div><b><?= e($r['who']) ?></b> · <?= (int)$r['rv_score'] ?>/100 · <?= e($r['rv_time']) ?><br><?= e($r['rv_comment']) ?></div>
  <hr class="hr">
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="msg info" style="margin-top:14px">
<b><?= e($L['trust_algo_title']) ?></b><br>
<?= e($L['trust_algo_desc']) ?>
</div>