<?php
/* pages/wallet.php - 내 지갑 */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
if (!$system_v['login']['valid']) { echo '<div class="msg warn">' . e($L['login_required']) . ' <a href="/?mode=authorize">' . e($L['nav_login']) . '</a></div>'; return; }
global $sql;
$me = (int)$system_v['login']['number'];
$bal = (int)$system_v['login']['balance'];
$ledger = $sql->run("SELECT l.led_id,l.led_kind,l.led_amount,l.led_balance_after,l.led_memo,l.led_time,
      (SELECT u.acc_id FROM users u WHERE u.acc_number=l.led_counterparty) AS cp
      FROM wallet_ledger l WHERE l.led_user=? ORDER BY l.led_id DESC LIMIT 50", 'i', [$me]);
$kinds = [$L['wallet_kind_0'], $L['wallet_kind_1'], $L['wallet_kind_2'], $L['wallet_kind_3'], $L['wallet_kind_4']];
?>
<h1 class="h1"><?= e($L['wallet_title']) ?></h1>
<div class="card">
  <div style="font-size:14px;color:var(--muted)"><?= e($L['wallet_balance']) ?></div>
  <div style="font-size:30px;font-weight:800;color:var(--brand)"><?= number_format($bal) ?> <?= e($L['product_price_unit']) ?></div>
  <hr class="hr">
  <div class="row" style="align-items:flex-end">
    <div>
      <label><?= e($L['wallet_charge_amt']) ?></label>
      <input id="charge_amt" inputmode="numeric" pattern="[0-9]{1,12}" placeholder="10000">
    </div>
    <button class="btn primary" id="charge_btn" style="flex:0 0 auto"><?= e($L['wallet_charge_btn']) ?></button>
  </div>
  <hr class="hr">
  <div class="row" style="align-items:flex-end">
    <div>
      <label><?= e($L['wallet_transfer_to']) ?></label>
      <div class="row" style="gap:6px">
        <input id="tr_to" inputmode="numeric" pattern="[0-9]{1,20}" placeholder="<?= e($L['wallet_transfer_to']) ?>" style="flex:1">
        <button class="btn sm" type="button" id="tr_confirm" style="flex:0 0 auto">확인</button>
      </div>
      <div id="tr_confirm_result" class="small" style="margin:4px 0"></div>
    </div>
    <div>
      <label><?= e($L['wallet_transfer_amt']) ?></label>
      <input id="tr_amt" inputmode="numeric" pattern="[0-9]{1,12}" placeholder="5000">
    </div>
    <button class="btn primary" id="tr_btn" style="flex:0 0 auto"><?= e($L['wallet_transfer_btn']) ?></button>
  </div>
</div>

<h2 class="h2"><?= e($L['wallet_history']) ?> (<?= count($ledger) ?>)</h2>
<?php if (empty($ledger)): ?>
<div class="empty"><?= e($L['no_result']) ?></div>
<?php else: ?>
<div class="table-wrap">
<table>
<tr><th><?= e($L['page']) ?>시간</th><th>종류</th><th>금액</th><th>잔액</th><th>상대</th><th>메모</th></tr>
<?php foreach ($ledger as $l): ?>
<tr>
  <td class="nowrap small"><?= e($l['led_time']) ?></td>
  <td><?= e($kinds[(int)$l['led_kind']] ?? '-') ?></td>
  <td><?= number_format((int)$l['led_amount']) ?></td>
  <td><?= number_format((int)$l['led_balance_after']) ?></td>
  <td><?= e($l['cp'] ?? '-') ?></td>
  <td class="truncate-1"><?= e($l['led_memo']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<script>
document.getElementById('charge_btn').addEventListener('click', function(){
  var amt=parseInt(document.getElementById('charge_amt').value,10);
  if(!amt||amt<=0){Jn.toast('<?= ejs($L['wallet_invalid_amt']) ?>','warn');return;}
  Jn.confirmBox(Jn.t('wallet_charge_confirm',{amt: amt.toLocaleString()}) || '<?= ejs($L['wallet_charge_confirm']) ?>'.replace('{amt}', amt.toLocaleString()), function(){
    Jn.req('/service_bridge.php',{json:{mode:'wallet_charge',amount:amt}}).then(function(j){ Jn.handleRes(j,function(){location.reload();}); });
  });
});
document.getElementById('tr_btn').addEventListener('click', function(){
  var to=parseInt(document.getElementById('tr_to').value,10);
  var amt=parseInt(document.getElementById('tr_amt').value,10);
  if(!to||!amt||amt<=0){Jn.toast('<?= ejs($L['wallet_invalid_amt']) ?>','warn');return;}
  Jn.confirmBox('<?= ejs($L['wallet_transfer_confirm']) ?>'.replace('{to}',to).replace('{amt}',amt.toLocaleString()), function(){
    Jn.req('/service_bridge.php',{json:{mode:'wallet_transfer',to:to,amount:amt}}).then(function(j){ Jn.handleRes(j,function(){location.reload();}); });
  });
});
document.getElementById('tr_confirm').addEventListener('click', function(){
  var to=parseInt(document.getElementById('tr_to').value,10);
  if(!to){Jn.toast('사용자 번호를 입력하세요','warn');return;}
  var fd=new FormData(); fd.append('token',Jn.csrf()); fd.append('mode','transfer_confirm'); fd.append('to',to);
  var box=document.getElementById('tr_confirm_result');
  box.textContent='확인 중...'; box.style.color='#7b8794';
  Jn.req('/service_bridge.php',{form:fd}).then(function(j){
    if(j&&j.success){ box.innerHTML='✓ ID: <b>'+Jn.esc(j.acc_id)+'</b> · 이름: <b>'+Jn.esc(j.name)+'</b> · 전화: '+Jn.esc(j.phone)+' · 은행: '+Jn.esc(j.bank)+' · 계좌: '+Jn.esc(j.account); box.style.color='#16a34a'; }
    else { box.textContent='✗ '+(j.description||'사용자 없음'); box.style.color='#dc2626'; }
  });
});
</script>