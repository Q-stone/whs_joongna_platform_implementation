<?php
/* pages/view_post.php - 상품 상세 */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
if ($system_v['view'] === null) { echo '<div class="msg err">' . e($L['product_not_found']) . '</div>'; return; }
global $sql;
$row = $sql->one(
    "SELECT p.prd_number,p.prd_seller,p.prd_tag,p.prd_title,p.prd_desc,p.prd_price,p.prd_blind,p.prd_sold,p.prd_time,p.prd_revised,
            COALESCE(NULLIF(u.acc_nick,''),u.acc_id) AS seller,u.acc_number AS snum,u.acc_trust,t.tag_name
     FROM products p JOIN users u ON p.prd_seller=u.acc_number LEFT JOIN tags t ON p.prd_tag=t.tag_id
     WHERE p.prd_number=?", 'i', [(int)$system_v['view']]
);
if (!$row) { echo '<div class="msg err">' . e($L['product_not_found']) . '</div>'; return; }
if ((int)$row['prd_blind'] === 1 && (int)($system_v['login']['group'] ?? 0) !== 1) { echo '<div class="msg warn">' . e($L['product_hidden']) . '</div>'; return; }
if ($system_v['login']['dormant']) { echo '<div class="msg err">' . e($L['dormant_block']) . '</div>'; return; }
$media = $sql->run("SELECT pm_save_name,pm_kind,pm_orig_name,pm_size FROM product_media WHERE pm_prd=? ORDER BY pm_is_main DESC, pm_id ASC", 'i', [(int)$row['prd_number']]);
$main = $media[0]['pm_save_name'] ?? '';
$snum = (int)$row['snum'];
$mine = $system_v['login']['valid'] && (int)$system_v['login']['number'] === $snum;
?>
<div class="detail">
  <div class="gallery">
    <?php if ($main): ?>
      <img class="main-img" src="<?= e('/uploads/'.$main) ?>" alt="<?= e($row['prd_title']) ?>">
    <?php else: ?>
      <img class="main-img" src="/cdn/img/no-image.svg" alt="no image">
    <?php endif; ?>
    <div class="thumbs">
    <?php foreach (array_slice($media, 1) as $m): $src='/uploads/'.$m['pm_save_name']; ?>
      <?php if ((int)$m['pm_kind']===1): ?><video src="<?= e($src) ?>"></video><?php else: ?><img src="<?= e($src) ?>" alt=""><?php endif; ?>
    <?php endforeach; ?>
    </div>
  </div>
  <div class="summary">
    <h1 class="h1"><?= e($row['prd_title']) ?> <?php if ((int)$row['prd_sold']===1): ?><span class="badge ok"><?= e($L['product_sold']) ?></span><?php endif; ?></h1>
    <div class="price"><?= number_format((int)$row['prd_price']) ?> <?= e($L['product_price_unit']) ?></div>
    <div class="small" style="margin:6px 0">
      <span class="tag"><?= e($row['tag_name'] ?? '기타') ?></span>
      <span class="small"><?= e($row['prd_time']) ?></span>
      <?php if ($row['prd_time'] !== $row['prd_revised']): ?><span class="small">· <?= e($row['prd_revised']) ?></span><?php endif; ?>
    </div>
    <hr class="hr">
    <p><?= Security::render_text($row['prd_desc']) ?></p>
    <hr class="hr">
    <div class="seller">
      <a class="btn" href="/?mode=profile&view=<?= $snum ?>">판매자: <?= e($row['seller']) ?> (<?= e($L['profile_trust']) ?> <?= e($row['acc_trust']) ?>)</a>
    </div>
    <div class="actions">
      <?php if ($mine): ?>
        <a class="btn primary" href="/?mode=write_post&view=<?= (int)$row['prd_number'] ?>"><?= e($L['edit']) ?></a>
        <a class="btn" href="/?mode=mypage"><?= e($L['my_products']) ?></a>
      <?php elseif ($system_v['login']['valid']): ?>
        <button class="btn primary" onclick="startDm(<?= $snum ?>, '<?= ejs($row['seller']) ?>', <?= (int)$row['prd_number'] ?>)"><?= e($L['profile_chat']) ?></button>
        <button class="btn primary" onclick="buyNow(<?= (int)$row['prd_number'] ?>)"><?= e($L['wallet_buy_btn']) ?></button>
        <?php if (empty($system_v['login']['report_ban'])): ?>
        <a class="btn danger" href="/?mode=report&target=0&ref=<?= (int)$row['prd_number'] ?>"><?= e($L['report_title']) ?></a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
function startDm(uid, uname, pid){
  if (uid == <?= (int)$system_v['login']['number'] ?>){ Jn.toast('본인입니다.','warn'); return; }
  location.href = '/?mode=dm&with=' + uid + '&prd=' + pid;
}
function buyNow(pid){
  Jn.confirmBox('판매자에게 상품 금액을 송금하고 구매 처리합니다. 진행?', function(){
    Jn.req('/service_bridge.php',{json:{mode:'wallet_buy',prd:pid}}).then(function(j){
      Jn.handleRes(j, function(){ if(j.redirect) location.href=j.redirect; });
    });
  });
}
// 사진 클릭 확대
document.querySelectorAll('.gallery img').forEach(function(img){
  img.style.cursor='pointer';
  img.addEventListener('click', function(){
    Jn.modal('', '<img src="'+img.src+'" style=\"max-width:90vw;max-height:85vh;object-fit:contain;border-radius:8px\">', '',
      {onReady:function(m,cl){m.querySelector('img').style.cursor='default';}});
  });
});
</script>