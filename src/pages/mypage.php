<?php
/* pages/mypage.php - 마이페이지 */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
if (!$system_v['login']['valid']) { echo '<div class="msg warn">' . e($L['login_required']) . ' <a href="/?mode=authorize">' . e($L['nav_login']) . '</a></div>'; return; }
global $sql;
$u = $sql->one("SELECT acc_intro,acc_phone,acc_address,acc_email,acc_totp_ok,acc_totp_secret,acc_id,acc_nick,acc_balance FROM users WHERE acc_number=?", 'i', [$system_v['login']['number']]);
$ipsec = $sql->one("SELECT is_check_ip,is_check_cc,is_allow_cc FROM ip_security WHERE is_user=?", 'i', [$system_v['login']['number']]);
$my_products = $sql->run(
    "SELECT p.prd_number,p.prd_title,p.prd_price,p.prd_blind,p.prd_sold,p.prd_report_cnt,
            (SELECT pm.pm_save_name FROM product_media pm WHERE pm.pm_prd=p.prd_number AND pm.pm_kind=0 ORDER BY pm.pm_is_main DESC, pm.pm_id ASC LIMIT 1) AS img
     FROM products p WHERE p.prd_seller=? ORDER BY p.prd_number DESC", 'i', [$system_v['login']['number']]
);
$totp_has = !empty($u['acc_totp_secret']) && strlen((string)$u['acc_totp_secret']) >= 16;
$totp_on = (int)$u['acc_totp_ok'] === 1;

// 결제정보
$pay = $sql->one("SELECT upi_real_name,upi_bank,upi_account,upi_phone,upi_address FROM user_payment_info WHERE upi_user=?", 'i', [$system_v['login']['number']]);

// 관리자 패널: localhost 접속 + 관리자 없을 때 자동승격 버튼, 관리자일 경우 관리자 패널 링크
$myIp = client_ip();
$isLocal = (strpos($myIp,'127.')===0 || strpos($myIp,'192.168.')===0 || strpos($myIp,'10.')===0 || (strpos($myIp,'172.')===0 && (int)explode('.',$myIp)[1]>=16 && (int)explode('.',$myIp)[1]<=31) || $myIp==='::1');
$adminCount = (int)$sql->scalar("SELECT COUNT(*) FROM users WHERE acc_group=1 AND acc_status<>3");
$canSelfPromote = $isLocal && $adminCount === 0 && !$system_v['login']['dormant'];
?>
<h1 class="h1"><?= e($L['mypage_title']) ?> <a class="btn sm primary" href="/?mode=profile&view=<?= (int)$system_v['login']['number'] ?>" style="margin-left:auto">내 프로필 보기</a></h1>

<div class="card">
<h2><?= e($L['account_info']) ?></h2>
<form id="mp" onsubmit="return false">
  <input type="hidden" name="token" value="<?= e($system_v['token']) ?>">
  <input type="hidden" name="mode" value="account_settings">
  <label><?= e($L['intro']) ?></label>
  <textarea name="acc_intro" maxlength="300" placeholder="<?= e($L['intro_placeholder']) ?>"><?= e($u['acc_intro']) ?></textarea>
  <label>로그인 ID</label>
  <input value="<?= e($u['acc_id'] ?? '') ?>" disabled style="background:#f3f5f8;color:#7b8794;max-width:240px">
  <label>닉네임 (표시 이름)</label>
  <input name="acc_nick" value="<?= e($u['acc_nick'] ?? '') ?>" placeholder="2~20자, 선택" maxlength="20">
  <div class="row">
    <div><label><?= e($L['phone']) ?></label><input name="acc_phone" value="<?= e($u['acc_phone']) ?>"></div>
    <div><label><?= e($L['address']) ?></label><input name="acc_address" value="<?= e($u['acc_address']) ?>"></div>
  </div>
  <label><?= e($L['signup_email']) ?></label><input name="acc_email" value="<?= e($u['acc_email']) ?>">
  <hr class="hr">
  <label><?= e($L['current_pw']) ?></label><input type="password" name="form_pw" required autocomplete="current-password">
  <div class="row">
    <div><label><?= e($L['new_pw']) ?></label><input type="password" name="form_change_pw" autocomplete="new-password"></div>
    <div><label><?= e($L['new_pw_re']) ?></label><input type="password" name="form_change_pw_re" autocomplete="new-password"></div>
  </div>
  <label class="checkbox"><input type="checkbox" name="form_check_change_pw"> <?= e($L['change_pw_check']) ?></label>
  <hr class="hr">
  <h2><?= e($L['ip_security']) ?></h2>
  <label class="checkbox"><input type="checkbox" name="is_check_ip" <?= $ipsec && (int)$ipsec['is_check_ip']===1?'checked':'' ?>> <?= e($L['ip_check_ip']) ?></label>
  <label class="checkbox"><input type="checkbox" name="is_check_cc" <?= $ipsec && (int)$ipsec['is_check_cc']===1?'checked':'' ?>> <?= e($L['ip_check_cc']) ?></label>
  <div class="small"><?= e($L['ip_hint']) ?></div>
  <hr class="hr">
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn primary" id="mp_save"><?= e($L['save']) ?></button>
    <button class="btn danger" type="button" id="mp_all_logout"><?= e($L['all_logout_btn']) ?></button>
  </div>
  <hr class="hr">
  <details>
    <summary style="cursor:pointer;color:var(--err);font-weight:700">계정 삭제 (되돌릴 수 없음)</summary>
    <div class="card" style="margin-top:8px;border-color:#fecaca">
      <div class="msg warn">계정 삭제 시 모든 데이터(상품/채팅/지갑이력/평가)가 영구 삭제되며 되돌릴 수 없습니다.</div>
      <label>본인 확인용 비밀번호</label>
      <div class="row">
        <input type="password" id="del_pw" placeholder="현재 비밀번호" style="max-width:240px">
        <button class="btn danger" type="button" id="mp_delete_self" style="flex:0 0 auto">계정 영구 삭제</button>
      </div>
    </div>
  </details>
</form>
</div>

<div class="card">
<h2>결제/송금 정보 (거래에 필수)</h2>
<?php if (!$pay || $pay['upi_real_name']==='' || $pay['upi_account']===''): ?>
<div class="msg warn">거래(송금/구매)를 하려면 이름과 계좌정보를 반드시 먼저 등록해야 합니다.</div>
<?php endif; ?>
<form id="pay_form" onsubmit="return false">
  <input type="hidden" name="token" value="<?= e($system_v['token']) ?>">
  <input type="hidden" name="mode" value="payment_info">
  <div class="row">
    <div><label>실명</label><input name="upi_real_name" value="<?= e($pay['upi_real_name'] ?? '') ?>" placeholder="홍길동"></div>
    <div><label>은행</label><input name="upi_bank" value="<?= e($pay['upi_bank'] ?? '') ?>" placeholder="국민은행"></div>
  </div>
  <label>계좌번호</label><input name="upi_account" value="<?= e($pay['upi_account'] ?? '') ?>" placeholder="123-45-67890">
  <div class="row">
    <div><label><?= e($L['phone']) ?></label><input name="upi_phone" value="<?= e($pay['upi_phone'] ?? '') ?>"></div>
    <div><label><?= e($L['address']) ?></label><input name="upi_address" value="<?= e($pay['upi_address'] ?? '') ?>"></div>
  </div>
  <div style="margin-top:10px"><button class="btn primary" id="pay_save"><?= e($L['save']) ?></button></div>
</form>
</div>

<div class="card">
  <h2><?= e($L['totp_title']) ?> — <?= $totp_on ? '<span class="badge ok">ON</span>' : '<span class="badge">OFF</span>' ?></h2>
  <div id="totp_setup">
    <?php if (!$totp_has): ?>
      <button class="btn" type="button" id="totp_init_btn"><?= e($L['totp_init']) ?></button>
    <?php elseif ($totp_on): ?>
      <div class="msg ok">TOTP가 활성화되어 있습니다. 재설정하려면 먼저 삭제하세요.</div>
      <div class="row" style="margin-top:8px;align-items:center;flex-wrap:wrap">
        <button class="btn danger" type="button" id="totp_del_btn">TOTP 삭제 (현재 6자리 코드 필요)</button>
      </div>
    <?php else: ?>
      <div class="msg warn">TOTP 시크릿이 발급되었으나 인증이 완료되지 않았습니다. 새 시크릿으로 시작하려면 먼저 기존 시크릿을 삭제하세요.</div>
      <div class="row" style="margin-top:8px;align-items:center;flex-wrap:wrap">
        <button class="btn danger" type="button" id="totp_del_btn">TOTP 시크릿 삭제 (현재 6자리 코드 필요)</button>
      </div>
    <?php endif; ?>    </div></div>
</div>

<div class="card">
  <div class="between">
    <h2><?= e($L['my_products']) ?> (<?= count($my_products) ?>)</h2>
    <a class="btn sm" href="/?mode=write_post"><?= e($L['go_sell']) ?></a>
  </div>
  <?php if (empty($my_products)): ?>
    <div class="empty"><?= e($L['product_none']) ?></div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <tr><th></th><th>#</th><th><?= e($L['product_title']) ?></th><th><?= e($L['product_price']) ?></th><th>상태</th><th><?= e($L['product_report_count']) ?></th><th><?= e($L['edit']) ?></th></tr>
    <?php foreach ($my_products as $p):
      $img = $p['img'] ? ('/uploads/'.$p['img']) : '/cdn/img/no-image.svg'; ?>
    <tr>
      <td><img src="<?= e($img) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px"></td>
      <td><?= (int)$p['prd_number'] ?></td>
      <td><a href="/?mode=view_post&view=<?= (int)$p['prd_number'] ?>"><?= e($p['prd_title']) ?></a></td>
      <td><?= number_format((int)$p['prd_price']) ?></td>
      <td><?php if((int)$p['prd_blind']===1): echo '<span class="badge dorm">'.$L['product_blocked'].'</span>'; elseif((int)$p['prd_sold']===1): echo '<span class="badge ok">'.$L['product_sold'].'</span>'; else: echo '<span class="badge">'.$L['product_selling'].'</span>'; endif; ?></td>
      <td><?= (int)$p['prd_report_cnt'] ?></td>
      <td><a class="btn sm" href="/?mode=write_post&view=<?= (int)$p['prd_number'] ?>"><?= e($L['edit']) ?></a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>

<script src="/cdn/js/qrcode.min.js"></script>
<script>
document.getElementById('mp_save').addEventListener('click', function(){
  Jn.req('/service_bridge.php', { form: new FormData(document.getElementById('mp')) }).then(function(j){ Jn.handleRes(j); });
});
document.getElementById('mp_all_logout').addEventListener('click', function(){
  Jn.confirmBox('<?= ejs($L['all_logout_confirm']) ?>', function(){
    var fd=new FormData(); fd.append('token', '<?= e($system_v['token']) ?>'); fd.append('mode','all_logout');
    Jn.req('/service_bridge.php',{form:fd}).then(function(j){ Jn.handleRes(j, function(){ if(j.redirect) setTimeout(function(){location.href=j.redirect;},800); }); });
  });
});
document.getElementById('totp_init_btn')?.addEventListener('click', function(){
  var fd=new FormData(); fd.append('token', '<?= e($system_v['token']) ?>'); fd.append('mode','totp_init');
  Jn.req('/service_bridge.php',{form:fd}).then(function(j){
    if (j && j.success && j.show_totp_setup) { showTotpSetup(j.otpauth, j.secret); }
    else Jn.handleRes(j);
  });
});
document.getElementById('totp_verify_btn')?.addEventListener('click', function(){
  var code=document.getElementById('totp_code').value;
  var fd=new FormData(); fd.append('token', '<?= e($system_v['token']) ?>'); fd.append('mode','totp_verify'); fd.append('code',code);
  Jn.req('/service_bridge.php',{form:fd}).then(function(j){ Jn.handleRes(j, function(){ location.reload(); }); });
});
document.getElementById('totp_del_btn')?.addEventListener('click', function(){
  Jn.modal('TOTP 삭제', '<div class="msg warn">TOTP를 삭제하려면 현재 Authenticator 앱의 6자리 코드를 입력하세요.</div><input id="totp_del_code" inputmode="numeric" pattern="[0-9]{6}" placeholder="6자리 코드" style="margin-top:10px">',
    '<button class="btn danger js-ok">삭제</button><button class="btn ghost js-cancel">취소</button>',
    { onReady: function(m, cl){
        m.querySelector('.js-ok').onclick = function(){
          var code = m.querySelector('#totp_del_code').value;
          var fd = new FormData(); fd.append('token', Jn.csrf()); fd.append('mode','totp_delete'); fd.append('code', code);
          Jn.req('/service_bridge.php', { form: fd }).then(function(j){ Jn.handleRes(j, function(){ cl(); setTimeout(function(){location.reload();},700); }); });
        };
        m.querySelector('.js-cancel').onclick = cl;
      } });
});
document.getElementById('pay_save')?.addEventListener('click', function(){
  Jn.req('/service_bridge.php', { form: new FormData(document.getElementById('pay_form')) }).then(function(j){ Jn.handleRes(j); });
});
document.getElementById('mp_delete_self')?.addEventListener('click', function(){
  Jn.confirmBox('정말 계정을 영구 삭제하시겠습니까? 모든 데이터가 삭제되며 되돌릴 수 없습니다.', function(){
    var pw = document.getElementById('del_pw').value;
    var fd = new FormData(); fd.append('token', '<?= e($system_v['token']) ?>'); fd.append('mode','account_delete_self'); fd.append('form_pw', pw);
    Jn.req('/service_bridge.php',{form:fd}).then(function(j){ Jn.handleRes(j, function(){ if(j.redirect) setTimeout(function(){location.href=j.redirect;},800); }); });
  });
});

function showTotpSetup(otpauth, secret){
  Jn.modal('2단계 인증(TOTP) 설정',
    '<div style="text-align:center">' +
    '<p style="font-weight:600">Google Authenticator, Authy 등 앱에서 아래 QR코드를 스캔하거나 시크릿을 직접 입력하세요.</p>' +
    '<div class="qr-box" id="qr_box2" style="margin:12px auto;display:inline-block"></div>' +
    '<div style="margin:8px 0"><span class="small">시크릿:</span><code class="secret-code" id="ts2">'+Jn.esc(secret)+'</code></div>' +
    '<p class="small" style="color:#7b8794">등록 후 앱에 표시되는 6자리 코드를 아래 입력하고 "인증 활성화"를 누르세요.</p>' +
    '<div class="row" style="align-items:center;margin-top:10px">' +
      '<input id="totp_code2" inputmode="numeric" pattern="[0-9]{6}" placeholder="6자리 코드" style="max-width:180px;flex:0 0 180px">' +
      '<button class="btn primary" id="totp_verify2" type="button">인증 활성화</button>' +
    '</div></div>',
    '<button class="btn ghost js-cancel">닫기</button>',
    { onReady: function(m, cl){
        var tryDraw = function(){
          if (typeof QRCode === 'undefined') { setTimeout(tryDraw, 200); return; }
          var box = m.querySelector('#qr_box2'); if (box) { box.innerHTML = ''; new QRCode(box, { text: otpauth, width: 200, height: 200 }); }
        };
        tryDraw();
        var ts = m.querySelector('#ts2');
        if (ts) { ts.style.cursor = 'pointer'; ts.onclick = function(){ navigator.clipboard.writeText(secret); Jn.toast('복사됨','ok'); }; }
        m.querySelector('#totp_verify2').onclick = function(){
          var code = m.querySelector('#totp_code2').value;
          if (!/^[0-9]{6}$/.test(code)) { Jn.toast('6자리 코드 입력','warn'); return; }
          var fd = new FormData(); fd.append('token', Jn.csrf()); fd.append('mode','totp_verify'); fd.append('code', code);
          Jn.req('/service_bridge.php', { form: fd }).then(function(j){
            if (j && j.success) { cl(); Jn.toast(j.description,'ok'); setTimeout(function(){ location.reload(); }, 700); }
            else Jn.toast(j.description || '오류','err');
          });
        };
        m.querySelector('.js-cancel').onclick = cl;
      } });
}
</script>

<?php
// 구매 내역 및 평가 (모든 사용자)
if ($system_v['login']['valid']):
$mu = (int)$system_v['login']['number'];
$phist = $sql->run("SELECT p.prd_number,p.prd_title,p.prd_price,p.prd_seller,COALESCE(NULLIF(u.acc_nick,''),u.acc_id) AS seller FROM products p JOIN users u ON p.prd_seller=u.acc_number WHERE p.prd_sold=1 AND EXISTS (SELECT 1 FROM wallet_ledger w WHERE w.led_user=? AND w.led_kind=1 AND w.led_memo LIKE CONCAT('%#',p.prd_number)) LIMIT 20",'i',[$mu]);
?>
<div class="card"><h2>구매 내역 및 평가</h2>
<?php if(empty($phist)): ?><div class="empty">구매한 상품이 없습니다.</div>
<?php else: ?><div class="table-swipe"><div class="swipe-hint"><span class="swipe-icon">↔</span> 좌우로 드래그</div><table>
<tr><th>상품</th><th>가격</th><th>판매자</th><th>평가</th></tr>
<?php foreach($phist as $p): ?>
<tr><td><a href="/?mode=view_post&view=<?= (int)$p['prd_number'] ?>"><?= e($p['prd_title']) ?></a></td>
<td><?= number_format((int)$p['prd_price']) ?>원</td><td><?= e($p['seller']) ?></td>
<td><a class="btn sm primary" href="/?mode=review&prd=<?= (int)$p['prd_number'] ?>&to=<?= (int)$p['prd_seller'] ?>">평가</a></td></tr>
<?php endforeach; ?></table></div><?php endif; ?>
</div>
<?php endif; ?>

<?php
// 관리자 패널
if ((int)($system_v['login']['group'] ?? 0) === 1): ?>
<div class="card" style="border-color:#fde2e2;background:#fef9f9">
  <div class="between">
    <h2 style="margin:0">시스템 관리자 페이지</h2>
    <a class="btn danger" href="/?mode=admin">접속</a>
  </div>
</div>
<?php elseif ($isLocal && $adminCount === 0): ?>
<div class="card" style="border-color:#fde68a;background:#fffbeb">
  <div class="between">
    <div><h2 style="margin:0">최초 관리자 설정</h2>
    <div class="small">localhost에서 접속, 관리자 없음. 승격 가능.</div></div>
    <button class="btn primary" id="self_promote">관리자로 승격</button>
  </div>
</div>
<script>document.getElementById('self_promote')?.addEventListener('click',function(){Jn.confirmBox('관리자로 승격합니다.',function(){var fd=new FormData();fd.append('token','<?= e($system_v['token']) ?>');fd.append('mode','admin_autopromote');Jn.req('/service_bridge.php',{form:fd}).then(function(j){Jn.handleRes(j,function(){location.reload()});});});});</script>
<?php endif; ?>

