<?php
/* pages/search.php - 상품 검색 (AND/OR) */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
require __DIR__ . '/_tags.php';
global $sql;
$kw = isset($_GET['value']) ? trim($_GET['value']) : '';
if (!preg_match('/^([^<>\n\t\r\f\|]){1,80}$/', $kw)) $kw = '';
$op = $_GET['op'] ?? 'or';
if (!in_array($op, ['and', 'or'], true)) $op = 'or';
$tag = isset($_GET['tag']) ? only_digits($_GET['tag'], 4) : '';
$page = preg_match('/^[0-9]{1,20}$/', (string)$system_v['page']) ? (int)$system_v['page'] : 0;

$params = []; $types = ''; $exprs = [];
$terms = [];
if ($kw !== '') {
    foreach (preg_split('/\s+/', $kw) as $p) if ($p !== '') $terms[] = $p;
}
foreach ($terms as $t) { $exprs[] = "(p.prd_title LIKE ? OR p.prd_desc LIKE ?)"; array_push($params, "%$t%","%$t%"); $types .= 'ss'; }
$where = ["p.prd_blind=0", "u.acc_status<>3"];
// 빈 검색어여도 필터(카테고리/가격/상태/판매자)만으로 검색 동작. 전혀 필터 없으면 전체 목록.
if ($exprs) $where[] = $op==='and' ? implode(' AND ', $exprs) : implode(' OR ', $exprs);
if ($tag !== '') { $where[] = "p.prd_tag=?"; $params[] = (int)$tag; $types .= 'i'; }
// 세부 필터: 가격 범위, 판매상태, 판매자
$min = only_digits($_GET['min'] ?? '', 12);
$max = only_digits($_GET['max'] ?? '', 12);
if ($min !== '') { $where[] = "p.prd_price>=?"; $params[] = (int)$min; $types .= 'i'; }
if ($max !== '') { $where[] = "p.prd_price<=?"; $params[] = (int)$max; $types .= 'i'; }
$soldFilter = $_GET['sold'] ?? '';
if ($soldFilter === '0') $where[] = "p.prd_sold=0";
elseif ($soldFilter === '1') $where[] = "p.prd_sold=1";
$sellerId = only_digits($_GET['seller'] ?? '', 20);
if ($sellerId !== '') { $where[] = "p.prd_seller=?"; $params[] = (int)$sellerId; $types .= 'i'; }
$whereSql = implode(' AND ', $where);
$count = (int)$sql->scalar("SELECT COUNT(*) FROM products p JOIN users u ON p.prd_seller=u.acc_number WHERE $whereSql", $types, $params);
$rows = $sql->run(
    "SELECT p.prd_number,p.prd_title,p.prd_price,p.prd_sold,p.prd_time,t.tag_name,
            (SELECT pm.pm_save_name FROM product_media pm WHERE pm.pm_prd=p.prd_number AND pm.pm_kind=0 ORDER BY pm.pm_is_main DESC, pm.pm_id ASC LIMIT 1) AS img
     FROM products p JOIN users u ON p.prd_seller=u.acc_number
     LEFT JOIN tags t ON p.prd_tag=t.tag_id
     WHERE $whereSql ORDER BY p.prd_number DESC LIMIT $page, 20",
    $types, $params
);
?>
<h1 class="h1"><?= e($L['search_title']) ?></h1>
<form method="get" action="/?mode=search" class="card">
  <input type="hidden" name="mode" value="search">
  <div class="row">
    <input name="value" placeholder="<?= e($L['search_placeholder']) ?>" value="<?= e($kw) ?>">
    <select name="op" style="max-width:160px;flex:0 0 160px">
      <option value="or" <?= $op==='or'?'selected':'' ?>><?= e($L['search_op_or']) ?></option>
      <option value="and" <?= $op==='and'?'selected':'' ?>><?= e($L['search_op_and']) ?></option>
    </select>
    <select name="tag" style="max-width:200px;flex:0 0 200px">
      <option value=""><?= e($L['product_category_all']) ?></option>
      <?php foreach ($TAGS as $id=>$name): ?><option value="<?= (int)$id ?>" <?= (string)$id===$tag?'selected':'' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
    <button class="btn primary" style="flex:0 0 auto"><?= e($L['search_title']) ?></button>
  </div>
  <details style="margin-top:8px">
    <summary style="cursor:pointer;font-weight:600;color:var(--muted)"><?= e($L['search_detail']) ?></summary>
    <div class="row" style="margin-top:8px">
      <div><label>최소 가격</label><input name="min" inputmode="numeric" value="<?= e($min) ?>" placeholder="0"></div>
      <div><label>최대 가격</label><input name="max" inputmode="numeric" value="<?= e($max) ?>" placeholder="제한없음"></div>
      <div><label>판매상태</label><select name="sold"><option value="">전체</option><option value="0" <?= ($_GET['sold']??'')==='0'?'selected':'' ?>><?= e($L['product_selling']) ?></option><option value="1" <?= ($_GET['sold']??'')==='1'?'selected':'' ?>><?= e($L['product_sold']) ?></option></select></div>
      <div><label>판매자 번호</label><input name="seller" inputmode="numeric" value="<?= e($sellerId) ?>" placeholder="선택"></div>
    </div>
  </details>
</form>
<div class="small" style="margin:6px 0"><?= e($L['search_result']) ?>: <?= str_replace('{n}', (string)$count, $L['search_count']) ?><?= $kw !== '' ? ' · ' . ($op==='and'?e($L['search_op_and']):e($L['search_op_or'])) : ' · 전체' ?></div>
<?php if (empty($rows)): ?>
  <div class="empty"><?= $kw==='' && $tag==='' ? e($L['product_none']) : e($L['no_result']) ?></div>
<?php else: ?>
<div class="grid">
<?php foreach ($rows as $p):
  $img = $p['img']?('/uploads/'.e($p['img'])):'/cdn/img/no-image.svg'; ?>
  <a class="pcard" href="/?mode=view_post&view=<?= (int)$p['prd_number'] ?>">
    <div class="thumb"><img src="<?= $img ?>" alt="" loading="lazy"></div>
    <div class="meta"><div class="ptitle"><?= e($p['prd_title']) ?></div>
      <div class="pprice"><?= number_format((int)$p['prd_price']) ?> <?= e($L['product_price_unit']) ?></div>
      <div class="ptag"><?= e($p['tag_name']??'기타') ?><?= (int)$p['prd_sold']===1?' · <span class="badge ok">'.$L['product_sold'].'</span>':'' ?></div>
    </div>
  </a>
<?php endforeach; ?>
</div>
<div class="pager">
  <?php if ($page>=20): ?><a class="btn sm" href="?mode=search&value=<?= urlencode($kw) ?>&op=<?= $op ?>&tag=<?= $tag ?>&page=<?= max(0,$page-20) ?>"><?= e($L['prev']) ?></a><?php endif; ?>
  <span class="small"><?= e($L['page']) ?> <?= intdiv($page,20)+1 ?></span>
  <?php if (count($rows)==20): ?><a class="btn sm" href="?mode=search&value=<?= urlencode($kw) ?>&op=<?= $op ?>&tag=<?= $tag ?>&page=<?= $page+20 ?>"><?= e($L['next']) ?></a><?php endif; ?>
</div>
<?php endif; ?>