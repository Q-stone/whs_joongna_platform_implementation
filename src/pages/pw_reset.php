<?php
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
if ($system_v['login']['valid']) { echo '<div class="msg info">이미 로그인 되어 있습니다. <a href="/">홈</a></div>'; return; }
global $sql;
?>
<h1 class="h1">비밀번호 초기화</h1>
<div class="msg info">TOTP가 설정된 계정만 비밀번호 초기화가 가능합니다. 30분에 1회로 제한됩니다.</div>
<form id="pr_form" class="card" style="max-width:440px">
  <input type="hidden" name="token" value="<?= e($system_v['token']) ?>">
  <input type="hidden" name="mode" value="pw_reset">
  <label>ID</label><input name="form_id" placeholder="가입한 ID" required>
  <label>이메일</label><input name="form_email" placeholder="가입 시 등록한 이메일" required>
  <div id="pr_totp_area" style="display:none">
    <label>TOTP 6자리 코드</label>
    <input name="form_totp" id="pr_totp" inputmode="numeric" pattern="[0-9]{6}" placeholder="Authenticator 앱 코드" autocomplete="off">
  </div>
  <div style="margin-top:12px"><button class="btn primary lg" type="button" id="pr_submit">비밀번호 초기화</button></div>
</form>
<div id="pr_result" style="margin-top:12px"></div>
<div class="msg info" style="margin-top:12px"><a href="/?mode=authorize">로그인으로 돌아가기</a></div>
<script>
var prStep = 0;
document.getElementById('pr_submit').addEventListener('click', function(){
  var fd = new FormData(document.getElementById('pr_form'));
  if (prStep === 0) fd.delete('form_totp');
  Jn.req('/service_bridge.php', {form:fd}).then(function(j){
    if (j && j.success) {
      document.getElementById('pr_form').style.display = 'none';
      document.getElementById('pr_result').innerHTML = '<div class="msg ok"><b>비밀번호 초기화 완료</b><br>새 비밀번호: <code style="font-size:18px;color:#2563eb;font-weight:800">'+Jn.esc(j.new_pw)+'</code><br><a href="/?mode=authorize">로그인 하러 가기</a></div>';
    } else if (j && j.step === 1) {
      prStep = 1;
      document.getElementById('pr_totp_area').style.display = 'block';
      document.getElementById('pr_totp').focus();
      Jn.toast('TOTP 코드를 입력하세요','warn');
    } else {
      Jn.handleRes(j);
    }
  });
});
</script>
