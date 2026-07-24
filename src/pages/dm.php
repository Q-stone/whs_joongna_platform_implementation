<?php
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
if (!$system_v['login']['valid']) { echo '<div class="msg warn">' . e($L['login_required']) . ' <a href="/?mode=authorize">' . e($L['nav_login']) . '</a></div>'; return; }
if ($system_v['view'] === null) { echo '<div class="msg err">' . e($L['chat_no_room']) . '</div>'; return; }
$with = (int)$system_v['view'];
$me = (int)$system_v['login']['number'];
$prdRef = $_GET['prd'] ?? '';
$directRid = $_GET['rid'] ?? '';
global $sql;

if ($directRid !== '') {
  // 관리자 전용 (다른 사용자 rid 파라미터 접근 차단)
  if ((int)($system_v['login']['group']??0) !== 1) { echo '<div class="msg err">접근 권한이 없습니다.</div>'; return; }
  // 관리자 채팅방 보기: rid로 직접 접근
  $rid = (int)$directRid;
  $rm = $sql->one("SELECT room_key FROM chat_rooms WHERE room_id=?",'i',[$rid]);
  if (!$rm) { echo '<div class="msg err">존재하지 않는 채팅방입니다.</div>'; return; }
  if (preg_match('/^P(\d+)_(\d+)_(\d+)/', $rm['room_key'], $m)) {
    $prdRef = $m[1]; $u1 = (int)$m[2]; $u2 = (int)$m[3];
    // 현재 사용자가 아닌 다른 참여자를 with로 설정
    $with = ($u1 === $me) ? $u2 : $u1;
  } else { echo '<div class="msg err">채팅방 정보 오류</div>'; return; }
}
else if ($prdRef === '') {
$rk_alt = 'P%';
  $alt = $sql->one("SELECT cr.room_id,cr.room_key FROM chat_rooms cr JOIN chat_members cm ON cm.cm_room=cr.room_id WHERE cr.room_type=1 AND cm.cm_user=? AND cr.room_key LIKE ? ORDER BY cr.room_id DESC LIMIT 1",'is',[$me,$rk_alt]);
  if (!$alt) { echo '<div class="msg err">이 사용자와의 채팅 이력이 없습니다. 상품 상세에서 채팅을 열어주세요.</div>'; return; }
  if (preg_match('/^P(\d+)/', $alt['room_key'], $m)) {
      $prdRef = $m[1];
  } else { echo '<div class="msg err">채팅방 정보를 불러올 수 없습니다.</div>'; return; }
}
$prd = $sql->one("SELECT prd_number,prd_title,prd_price,prd_seller,COALESCE(NULLIF(u.acc_nick,''),u.acc_id) AS seller_name,(SELECT pm.pm_save_name FROM product_media pm WHERE pm.pm_prd=p.prd_number AND pm.pm_kind=0 ORDER BY pm.pm_is_main DESC, pm.pm_id ASC LIMIT 1) AS img FROM products p JOIN users u ON p.prd_seller=u.acc_number WHERE p.prd_number=?", 'i', [(int)$prdRef]);
if (!$prd) { echo '<div class="msg err">존재하지 않는 상품입니다.</div>'; return; }

$isAdmin = (int)($system_v['login']['group']??0) === 1;
// 판매자 검증: 구매자↔판매자 간 채팅만 허용 (관리자 rid 보기는 예외)
if ($directRid === '' && !$isAdmin) {
    $sellerId = (int)$prd['prd_seller'];
    if ($me !== $sellerId && $with !== $sellerId) {
        echo '<div class="msg err">해당 상품의 판매자와만 채팅할 수 있습니다.</div>'; return;
    }
}
if ($directRid !== '') {
  // rid로 직접 접근: 방 이미 존재함, 생성/멤버추가 안 함
} else {
  $rk = 'P' . (int)$prd['prd_number'] . '_' . min($me, $with) . '_' . max($me, $with);
  $room = $sql->one("SELECT room_id FROM chat_rooms WHERE room_key=?", 's', [$rk]);
  if (!$room) { $sql->run("INSERT INTO chat_rooms (room_type,room_key,room_time) VALUES (1,?,NOW())", 's', [$rk]); $rid = (int)$sql->insert_id(); }
  else { $rid = (int)$room['room_id']; }
  $sql->run("INSERT IGNORE INTO chat_members (cm_room,cm_user) VALUES (?,?),(?,?)", 'iiii', [$rid,$me,$rid,$with]);
}
$isMember = (bool)$sql->one("SELECT 1 FROM chat_members WHERE cm_room=? AND cm_user=?",'ii',[$rid,$me]);
$isBlocked = false;
try { $isBlocked = (int)$sql->scalar("SELECT cr_blocked FROM chat_rooms WHERE room_id=?",'i',[$rid])===1; } catch(\Throwable $e) {}
$canSend = $isMember && !$isBlocked;
$isObserver = $isAdmin && !$isMember;

// 비관리자 + 차단 방 = 접근 불가
if (!$isAdmin && $isBlocked) { echo '<div class="msg err">차단된 채팅방입니다.</div>'; return; }

$them = $sql->one("SELECT COALESCE(NULLIF(acc_nick,''),acc_id) AS name FROM users WHERE acc_number=?",'i',[$with]);
$themName = $them['name'] ?? $with;
?>
<h1 class="h1"><?= e($themName) ?> · 상품 #<?= e((string)$prd['prd_number']) ?>
  <?php if($isBlocked): ?><span class="badge report">차단</span><?php endif; ?>
  <?php if($isObserver): ?><span class="badge admin">참관</span><?php endif; ?>
</h1>
<?php if ($prd): ?>
<div class="card" style="background:#eff6ff;margin-bottom:10px;padding:10px;display:flex;gap:10px;align-items:center">
  <?php if ($prd['img']): ?><img src="<?= e('/uploads/'.$prd['img']) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:8px"><?php endif; ?>
  <div><b><?= e($prd['prd_title']) ?></b><br><span style="color:#2563eb;font-weight:700"><?= number_format((int)$prd['prd_price']) ?>원</span></div>
</div>
<?php endif; ?>
<div class="chat">
  <div class="head"><?= e($prd['prd_title']) ?> · 판매자: <?= e($themName) ?></div>
  <div class="body" id="dm-body"></div>
  <?php if ($canSend): ?>
  <div class="input">
    <input id="dm-text" placeholder="<?= e($L['chat_input_placeholder']) ?>">
    <button class="btn primary" id="dm-send"><?= e($L['send']) ?></button>
    <label class="btn filebtn"><?= e($L['chat_attach']) ?><input type="file" id="dm-file" accept="image/*,video/*"></label>
    <button class="btn sm" id="dm-send-money" type="button" title="바로 송금">송금</button>
    <button class="btn sm" id="dm-phone" type="button"><?= e($L['chat_phone']) ?></button>
    <button class="btn sm" id="dm-acc" type="button"><?= e($L['chat_account']) ?></button>
    <button class="btn sm" id="dm-addr" type="button"><?= e($L['chat_address']) ?></button>
  </div>
  <?php else: ?>
  <div class="input" style="justify-content:center;padding:12px;background:#fff3cd;color:#92400e;font-weight:600;font-size:13px">
    <?= $isBlocked ? '이 채팅방은 차단되어 읽기만 가능합니다.' : '관리자 참관 모드 (메시지 발신 불가)' ?>
  </div>
  <?php endif; ?>
</div>
<script>
Chat.setActiveRoom(<?= $rid ?>);
<?php if ($canSend): ?>
Chat.start(<?= $rid ?>, '#dm-body', '#dm-text', '#dm-send');
document.getElementById('dm-file').addEventListener('change', function(){ Chat.sendMedia(<?= $rid ?>, '#dm-file'); });
<?php else: ?>
Chat.startReadonly(<?= $rid ?>, '#dm-body');
<?php endif; ?>
<?php if ($canSend): ?>
function priv(kind){
  var who = '<?= ejs($themName) ?>';
  Jn.confirmBox(Jn.t('chat.priv_prompt_'+kind,{who:who}), function(){
    Jn.req('/service_bridge.php',{json:{mode:'chat_priv',room:<?= $rid ?>,kind:kind}}).then(function(j){
      Jn.handleRes(j, function(){ setTimeout(function(){ Chat.pollAll(); }, 400); });
    });
  });
}
document.getElementById('dm-phone').onclick = function(){ priv('phone'); };
document.getElementById('dm-acc').onclick = function(){ priv('account'); };
document.getElementById('dm-addr').onclick = function(){ priv('address'); };
document.getElementById('dm-send-money').onclick = function(){
  var prdId = <?= (int)$prd['prd_number'] ?? 0 ?>;
  var bal = <?= (int)($system_v['login']['balance'] ?? 0) ?>;
  var price = <?= (int)$prd['prd_price'] ?? 0 ?>;
  if (prdId === 0) { Jn.toast('상품 정보가 없습니다.','warn'); return; }
  Jn.modal('송금 결제',
    '<p>내 잔액: <b>'+Number(bal).toLocaleString()+'</b>원</p><p>상품 가격: <b>'+Number(price).toLocaleString()+'</b>원</p><p class="small">상대방에게 대금을 송금하고 구매 처리합니다.</p>',
    '<button class="btn primary js-ok" '+(bal >= price ? '' : 'disabled')+'>결제하기</button><button class="btn ghost js-cancel">취소</button>',
    { onReady: function(m,cl){
        m.querySelector('.js-ok').onclick = function(){
          cl();
          Jn.req('/service_bridge.php',{json:{mode:'wallet_buy',prd:parseInt(prdId)}}).then(function(r){ Jn.handleRes(r); });
        };
        m.querySelector('.js-cancel').onclick = cl;
      } });
};
<?php endif; ?>
</script>
