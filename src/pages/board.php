<?php
/* pages/board.php - 전체 상품 조회 (전체 채팅 제거됨) */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
require __DIR__ . '/_tags.php';
$safe_page = preg_match('/^[0-9]{1,20}$/', (string)$system_v['page']) ? (int)$system_v['page'] : 0;

$products = $sql->run(
    "SELECT p.prd_number,p.prd_title,p.prd_price,p.prd_sold,p.prd_time,
            COALESCE(NULLIF(u.acc_nick,''),u.acc_id) AS seller,t.tag_name,
            (SELECT pm.pm_save_name FROM product_media pm WHERE pm.pm_prd=p.prd_number AND pm.pm_kind=0
             ORDER BY pm.pm_is_main DESC, pm.pm_id ASC LIMIT 1) AS img
     FROM products p JOIN users u ON p.prd_seller=u.acc_number
     LEFT JOIN tags t ON p.prd_tag=t.tag_id
     WHERE p.prd_blind=0 AND u.acc_status<>3
     ORDER BY p.prd_number DESC LIMIT $safe_page, 20"
);
?>
<form class="card" method="get" action="/?mode=search">
  <input type="hidden" name="mode" value="search">
  <input type="hidden" name="op" value="or">
  <div class="row">
    <input name="value" placeholder="<?= e($L['search_placeholder']) ?>" style="flex:2">
    <select name="tag" style="max-width:180px;flex:0 0 180px;max-height:40px">
      <option value=""><?= e($L['product_category_all']) ?></option>
      <?php foreach ($TAGS as $id => $name): ?>
        <option value="<?= (int)$id ?>"><?= e($name) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn primary" style="flex:0 0 auto"><?= e($L['search_title']) ?></button>
  </div>
  <details style="margin-top:8px">
    <summary style="cursor:pointer;font-weight:600;color:var(--muted)">고급 검색</summary>
    <div class="row" style="margin-top:8px">
      <div><label>최소 가격</label><input name="min" inputmode="numeric" placeholder="0"></div>
      <div><label>최대 가격</label><input name="max" inputmode="numeric" placeholder="제한없음"></div>
      <div><label>판매상태</label><select name="sold"><option value="">전체</option><option value="0">판매중</option><option value="1">판매완료</option></select></div>
    </div>
  </details>
</form>

<h1 class="h1"><?= e($L['product_list_title']) ?></h1>
<?php if (empty($products)): ?>
  <div class="empty"><?= e($L['product_none']) ?></div>
<?php else: ?>
<div class="grid">
<?php foreach ($products as $p):
    $img = $p['img'] ? ('/uploads/' . e($p['img'])) : '/cdn/img/no-image.svg';
?>
  <a class="pcard" href="/?mode=view_post&view=<?= (int)$p['prd_number'] ?>">
    <div class="thumb"><img src="<?= $img ?>" alt="" loading="lazy"></div>
    <div class="meta">
      <div class="ptitle"><?= e($p['prd_title']) ?></div>
      <div class="pprice"><?= number_format((int)$p['prd_price']) ?> <?= e($L['product_price_unit']) ?></div>
      <div class="ptag"><?= e($p['seller']) ?> · <?= e($p['tag_name'] ?? '기타') ?>
        <?php if ((int)$p['prd_sold']===1): ?> <span class="badge ok"><?= e($L['product_sold']) ?></span><?php endif; ?>
      </div>
    </div>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="pager">
  <?php if ($safe_page >= 20): ?><a class="btn sm" href="/?mode=board&page=<?= max(0, $safe_page - 20) ?>"><?= e($L['prev']) ?></a><?php endif; ?>
  <span class="small"><?= e($L['page']) ?> <?= intdiv((int)$safe_page, 20) + 1 ?></span>
  <?php if (count($products) == 20): ?><a class="btn sm" href="/?mode=board&page=<?= $safe_page + 20 ?>"><?= e($L['next']) ?></a><?php endif; ?>
</div>