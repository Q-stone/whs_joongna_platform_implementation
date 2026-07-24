<?php
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
$view = $system_v['view'] ?? 'view';
$tok = e($system_v['token']);
$totpPendingId = $_SESSION['totp_pending_id'] ?? '';
$totpPending = !empty($_SESSION['totp_pending']);
?>
<?php if ($totpPending): ?>
<h1 class="h1">2단계 인증</h1>
<form id="auth_form" class="card" onsubmit="return false">
  <input type="hidden" name="token" value="<?= $tok ?>">
  <input type="hidden" name="mode" value="board_login">
  <input type="hidden" name="form_id" value="<?= e($totpPendingId) ?>">
  <input type="hidden" name="form_pw" value="">
  <div class="msg info">TOTP가 설정된 계정입니다. Authenticator 앱의 6자리 코드를 입력하세요.</div>
  <label>2단계 인증(TOTP) 6자리</label>
  <input name="form_totp" id="form_totp" inputmode="numeric" pattern="[0-9]{6}" placeholder="000000" autofocus>
  <div style="margin-top:12px;display:flex;gap:8px">
    <button class="btn primary" id="auth_submit">인증</button>
    <button class="btn ghost" type="button" id="totp_back">이전으로</button>
  </div>
</form>
<script>document.getElementById('auth_submit').addEventListener('click',function(){Jn.req('/service_bridge.php',{form:new FormData(document.getElementById('auth_form'))}).then(function(j){if(j&&j.success){Jn.toast(j.description,'ok');setTimeout(function(){location.href=j.redirect||'/';},500)}else if(j&&j.error)Jn.toast(j.description,'err')})});document.getElementById('totp_back').addEventListener('click',function(){var fd=new FormData();fd.append('token','<?=$tok?>');fd.append('mode','totp_cancel');Jn.req('/service_bridge.php',{form:fd}).then(function(){location.href='/?mode=authorize'})})</script>
<?php return; endif; ?>

<?php if ($view === 'signup'):
  if ($system_v['login']['valid']) { echo '<div class="msg info">' . e($L['already_login']) . ' <a href="/">홈</a></div>'; return; }
?>
<h1 class="h1"><?= e($L['signup_title']) ?></h1>
<form id="auth_form" class="card" onsubmit="return false">
  <input type="hidden" name="token" value="<?= $tok ?>">
  <input type="hidden" name="mode" value="board_register">
  <div class="row" style="align-items:flex-end">
    <div style="flex:1"><label><?= e($L['login_id']) ?></label><input name="form_id" id="form_id" placeholder="<?= e($L['id_format']) ?>" autocomplete="username"></div>
    <button type="button" class="btn sm" id="btn_chk_id" style="flex:0 0 auto;margin-bottom:0">ID 중복</button>
  </div><div id="dup_id_msg" class="small" style="margin:4px 0"></div>
  <div style="flex:1"><label>닉네임</label><input name="form_nick" id="form_nick" placeholder="닉네임 (2~20자, 필수)" maxlength="20"></div>
  <div class="row">
    <div><label><?= e($L['login_pw']) ?></label><input type="password" name="form_pw" placeholder="<?= e($L['pw_format']) ?>" autocomplete="new-password"></div>
    <div><label><?= e($L['signup_pw_re']) ?></label><input type="password" name="form_pw_re" placeholder="<?= e($L['signup_pw_re']) ?>" autocomplete="new-password"></div>
  </div>
  <div class="row" style="align-items:flex-end">
    <div style="flex:1"><label><?= e($L['signup_email']) ?></label><input name="form_email" id="form_email" placeholder="<?= e($L['signup_email_hint']) ?>"></div>
    <button type="button" class="btn sm" id="btn_chk_email" style="flex:0 0 auto;margin-bottom:0">이메일 중복확인</button>
  </div><div id="dup_email_msg" class="small" style="margin:4px 0"></div>
  <label class="checkbox"><input type="checkbox" name="form_check_terms" id="form_check_terms"> <?= e($L['signup_terms_agree']) ?></label>
  <a class="small" href="javascript:void(0)" id="show_terms">이용약관 보기</a> · <a class="small" href="javascript:void(0)" id="show_privacy">개인정보처리방침 보기</a>
  <div style="margin-top:14px"><button class="btn primary lg" id="auth_submit"><?= e($L['signup_btn']) ?></button></div>
</form>
<div class="msg info" style="margin-top:12px">이미 계정이 있으신가요? <a href="/?mode=authorize">로그인</a></div>
<script>
function chkDup(kind){
  var input=document.getElementById(kind==='id'?'form_id':'form_email');
  var box=document.getElementById(kind==='id'?'dup_id_msg':'dup_email_msg');
  var val=input.value.trim();
  if(val===''){box.textContent='값을 입력하세요.';box.style.color='#dc2626';return;}
  var mode=kind==='id'?'check_dup_id':'check_dup_email';
  var keyField=kind==='id'?'form_id':'form_email';
  var fd=new FormData();fd.append('mode',mode);fd.append(keyField,val);
  box.textContent='확인 중...';box.style.color='#7b8794';
  Jn.req('/service_bridge.php',{form:fd}).then(function(j){
    if(j&&j.success){box.textContent='사용 가능';box.style.color='#16a34a'}
    else{box.textContent=j.description||'사용 불가';box.style.color='#dc2626'}
  });
}
document.getElementById('btn_chk_id').addEventListener('click',function(){chkDup('id')});
document.getElementById('btn_chk_email').addEventListener('click',function(){chkDup('email')});
document.getElementById('auth_submit').addEventListener('click',function(){
  Jn.req('/service_bridge.php',{form:new FormData(document.getElementById('auth_form'))}).then(function(j){
    if(j&&j.success){Jn.toast(j.description,'ok');if(j.redirect)setTimeout(function(){location.href=j.redirect},1400)}
    else Jn.handleRes(j);
  });
});
function showDocPopup(kind){
  Jn.fetchHtml('/?mode='+kind).then(function(html){
    var d=document.createElement('div');d.innerHTML=html;var c=d.querySelector('.card');var body=c?c.innerHTML:'내용을 불러올 수 없습니다.';
    Jn.modal(kind==='terms'?'이용약관':'개인정보처리방침',body,'<button class="btn ghost">닫기</button>',{onReady:function(m,cl){m.querySelector('.btn').onclick=cl}});
  });
}
document.getElementById('show_terms')?.addEventListener('click',function(){showDocPopup('terms')});
document.getElementById('show_privacy')?.addEventListener('click',function(){showDocPopup('privacy')});
</script>

<?php elseif ($view === 'logout'):
  if (!$system_v['login']['valid']) { echo '<div class="msg warn"><a href="/?mode=authorize">로그인</a>을 진행해주세요.</div>'; return; }
?>
<h1 class="h1"><?= e($L['logout_title']) ?></h1>
<form id="auth_form" class="card" onsubmit="return false">
  <input type="hidden" name="token" value="<?= $tok ?>">
  <input type="hidden" name="mode" value="board_logout">
  <button class="btn danger lg" id="auth_submit"><?= e($L['nav_logout']) ?></button>
</form>
<script>document.getElementById('auth_submit').addEventListener('click',function(){Jn.req('/service_bridge.php',{form:new FormData(document.getElementById('auth_form'))}).then(function(j){Jn.handleRes(j,function(){if(j.redirect)setTimeout(function(){location.href=j.redirect},600)})})})</script>

<?php else:
  if ($system_v['login']['valid']) { echo '<div class="msg info">' . e($L['already_login']) . ' <a href="/">홈</a> · <a href="/?mode=mypage">' . e($L['nav_mypage']) . '</a></div>'; return; }
?>
<h1 class="h1"><?= e($L['login_btn']) ?></h1>
<form id="auth_form" class="card" onsubmit="return false">
  <input type="hidden" name="token" value="<?= $tok ?>">
  <input type="hidden" name="mode" value="board_login">
  <label><?= e($L['login_id']) ?></label><input name="form_id" placeholder="<?= e($L['login_id_placeholder']) ?>" autocomplete="username">
  <label><?= e($L['login_pw']) ?></label><input type="password" name="form_pw" placeholder="<?= e($L['login_pw_placeholder']) ?>" autocomplete="current-password">
  <div style="margin-top:14px"><button class="btn primary lg" id="auth_submit"><?= e($L['login_btn']) ?></button></div>
</form>
<div class="msg info" style="margin-top:12px">계정이 없으신가요? <a href="/?mode=authorize&signup">회원가입</a></div>
<script>document.getElementById('auth_submit').addEventListener('click',function(){Jn.req('/service_bridge.php',{form:new FormData(document.getElementById('auth_form'))}).then(function(j){if(j&&j.totp_need){location.reload();return}if(j&&j.error)Jn.toast(j.description,'err');if(j&&j.success){Jn.toast(j.description,'ok');setTimeout(function(){location.href=j.redirect||'/'},500)}})})</script>
<?php endif; ?>