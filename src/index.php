<?php
/**
 * index.php - 프론트 컨트롤러 (라우팅 + SEO meta + breadcrumb + 안정 CSRF)
 * 모든 페이지 접근은 이 파일을 통함. phpresources 직접 접근은 상수로 차단.
 */
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_secure','0'); // 개발환경 HTTP
ini_set('session.cookie_samesite','Strict');
session_start();
const main_index_files = 1024;
$system_v = [];
$sql = '';

include_once __DIR__ . '/phpresources/resource_check.php';
include_once __DIR__ . '/phpresources/is_valid_user.php';
require __DIR__ . '/phpresources/language/language_kor.php';

$system_v['view'] = null;
$system_v['mode'] = 'board';
$system_v['header_name'] = $L['nav_products'] ?? '전체 상품';
$system_v['page'] = 0;
$system_v['token'] = stable_csrf();          // 세션 안정 토큰
$system_v['desc_page'] = '';                // SEO description
firewall_check();

// page_acl (느슨한 컨텍스트 검증용). 토큰 안정화로 ACL 고집은 완화.
$_SESSION['page_acl'] = 'none';

// 라우팅 메타
$ROUTE = [
    'board'       => ['page' => 'board',       'name' => $L['nav_products'] ?? '전체 상품',  'acl' => 'comment', 'desc' => '나무장터의 모든 중고 상품을 한눈에. 카테고리별로 쉽게 찾아보세요.'],
    'authorize'   => ['page' => 'authorize',   'name' => $L['nav_login'] ?? '로그인',        'acl' => 'authorize', 'desc' => '나무장터 로그인 및 회원가입.'],
    'write_post'  => ['page' => 'write_post',  'name' => $L['nav_sell'] ?? '상품 등록',       'acl' => 'post', 'desc' => '중고 상품을 등록하여 판매하세요.'],
    'view_post'   => ['page' => 'view_post',   'name' => $L['product_detail'] ?? '상품 상세',  'acl' => 'comment', 'desc' => '상품 상세 정보와 판매자 정보.'],
    'mypage'      => ['page' => 'mypage',      'name' => $L['nav_mypage'] ?? '마이페이지',    'acl' => 'account', 'desc' => '내 계정 정보, 보안 설정, 내 상품 관리.'],
    'profile'     => ['page' => 'profile',     'name' => $L['profile_title'] ?? '사용자 프로필','acl' => 'comment', 'desc' => '판매자 프로필과 판매 상품, 평가.'],
    'report'      => ['page' => 'report',      'name' => $L['report_title'] ?? '신고',         'acl' => 'report', 'desc' => '부적절한 상품 또는 사용자 신고.'],
    'dm'          => ['page' => 'dm',          'name' => $L['nav_chat'] ?? '1:1 채팅',         'acl' => 'comment', 'desc' => '1:1 채팅으로 거래를 진행하세요.'],
    'review'      => ['page' => 'review',      'name' => '거래 평가',    'acl' => 'comment', 'desc' => '거래 상대방 평가.'],
    'chat'        => ['page' => 'chat',        'name' => '채팅 목록',                         'acl' => 'comment', 'desc' => '대화 상대 목록.'],
    'wallet'      => ['page' => 'wallet',      'name' => $L['nav_wallet'] ?? '내 지갑',        'acl' => 'account', 'desc' => '가상 지갑 잔액과 거래 이력.'],
    'admin'       => ['page' => 'admin',       'name' => $L['nav_admin'] ?? '관리자',         'acl' => 'none', 'desc' => '플랫폼 관리.'],
    'search'      => ['page' => 'search',      'name' => $L['search_title'] ?? '검색',         'acl' => 'none', 'desc' => '상품명, 카테고리, AND/OR로 상품 상세 검색. (빈 검색은 전체 상품 목록)'],
    'terms'       => ['page' => 'terms',       'name' => $L['terms_title'] ?? '이용약관',      'acl' => 'none', 'desc' => '나무장터 이용약관.'],
    'privacy'     => ['page' => 'privacy',     'name' => $L['privacy_title'] ?? '개인정보처리방침','acl' => 'none', 'desc' => '개인정보처리방침.'],
    'pw_reset'    => ['page' => 'pw_reset',    'name' => '비밀번호 초기화',   'acl' => 'authorize', 'desc' => 'TOTP로 비밀번호 초기화.'],
];

$mode = $_GET['mode'] ?? 'board';
if (!isset($ROUTE[$mode])) { $mode = 'board'; }
$_SESSION['page_acl'] = $ROUTE[$mode]['acl'];
$system_v['mode'] = $ROUTE[$mode]['page'];
$system_v['header_name'] = $ROUTE[$mode]['name'];
$system_v['desc_page'] = $ROUTE[$mode]['desc'];

// 세부 view 파라미터 처리
switch ($system_v['mode']) {
    case 'authorize':
        if (isset($_GET['signup'])) { $system_v['view'] = 'signup'; $system_v['header_name'] = $L['nav_signup'] ?? '회원가입'; }
        elseif (isset($_GET['logout'])) { $system_v['view'] = 'logout'; $system_v['header_name'] = $L['nav_logout'] ?? '로그아웃'; }
        else { $system_v['view'] = 'view'; $system_v['header_name'] = $L['nav_login'] ?? '로그인'; }
        break;
    case 'write_post':
        if (isset($_GET['view']) && preg_match('/^([0-9]){1,20}$/', $_GET['view'])) {
            $system_v['view'] = $_GET['view'];
            $system_v['header_name'] = $L['product_edit'] ?? '상품 수정';
        }
        break;
    case 'view_post':
        if (isset($_GET['view']) && preg_match('/^([0-9]){1,20}$/', $_GET['view'])) $system_v['view'] = $_GET['view'];
        break;
    case 'profile':
        if (isset($_GET['view']) && preg_match('/^([0-9]){1,20}$/', $_GET['view'])) $system_v['view'] = $_GET['view'];
        break;
    case 'report':
        if (isset($_GET['target']) && in_array($_GET['target'], ['0', '1'], true)
            && isset($_GET['ref']) && preg_match('/^([0-9]){1,20}$/', $_GET['ref'])) {
            $system_v['view'] = $_GET['target'] . '|' . $_GET['ref'];
        }
        break;
    case 'dm':
        if (isset($_GET['with']) && preg_match('/^([0-9]){1,20}$/', $_GET['with'])) $system_v['view'] = $_GET['with'];
        elseif (isset($_GET['rid']) && preg_match('/^([0-9]){1,20}$/', $_GET['rid'])) $system_v['view'] = '0'; // 관리자 rid 보기
        break;
}
if (isset($_GET['page']) && preg_match('/^([0-9]){1,20}$/', $_GET['page'])) {
    $system_v['page'] = (int)$_GET['page'];
}
$system_v['end_page'] = $system_v['page'] + 20;

// breadcrumb
$system_v['crumbs'] = [
    ['name' => $L['home'] ?? '홈', 'href' => '/'],
];
if ($system_v['mode'] !== 'board') {
    $system_v['crumbs'][] = ['name' => $system_v['header_name'], 'href' => '/?mode=' . $system_v['mode']];
}

$siteName = $L['site_name'] ?? '나무장터';
$pageTitle = e($system_v['header_name']) . ' - ' . e($siteName);
$seoDesc = e($system_v['desc_page']);

// 시스템 공지/점검 상태 로드
$sysNotice = (string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key=?","s",["notice"]);
$sysUrgent = (string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key=?","s",["notice_urgent"]);
$sysMaint  = (string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key=?","s",["maintenance"]);
$sysMaintOn = ($sysMaint === '1');
$sysPopupOn = ((string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key=?","s",["notice_popup"])) === '1';
$isAdminUser = (int)($system_v["login"]["group"] ?? 0) === 1;

// 방문자 로그
$vlip = client_ip();
$sql->run("INSERT INTO visitor_log (vl_ip,vl_cc,vl_referer,vl_page,vl_ua,vl_time) VALUES (?,?,?,?,?,NOW())",
    "sssss", [$vlip, ip_to_cc($vlip), mb_substr($_SERVER["HTTP_REFERER"] ?? "",0,500), mb_substr($_SERVER["REQUEST_URI"] ?? "/",0,200), mb_substr($_SERVER["HTTP_USER_AGENT"] ?? "",0,250)]);

// 팝업 공지 (가장 최신 고정+팝업=True인 공지)
$popupNotice = $sysPopupOn ? $sql->one("SELECT nb_id,nb_title,nb_content FROM notice_board WHERE nb_pinned=1 AND nb_popup=1 ORDER BY nb_id DESC LIMIT 1") : null;
?>
<!doctype html>
<html lang="ko-KR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?= e($system_v['token']) ?>">
  <meta name="e2e-pub" content="<?= e(E2EEncrypt::init()) ?>">
  <title><?= $pageTitle ?></title>
  <meta name="description" content="<?= $seoDesc ?>">
  <meta name="robots" content="index, follow">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= e($siteName) ?>">
  <meta property="og:title" content="<?= $pageTitle ?>">
  <meta property="og:description" content="<?= $seoDesc ?>">
  <link rel="canonical" href="<?= e(('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'))) ?>">
  <link rel="stylesheet" href="/cdn/css/style.css?<?= filemtime(__DIR__ . '/cdn/css/style.css') ?>">
  <script src="/cdn/js/qrcode.min.js"></script>
  <script src="/cdn/js/common.js?36"></script>
  <script src="/cdn/js/chat.js?12"></script>
</head>
<body>
<?php if ($sysMaintOn && !$isAdminUser && $system_v['mode'] !== 'authorize'): ?>
<div class="maint-screen">
  <div class="maint-card">
    <h1>서비스 점검 중</h1>
    <p>현재 서비스 점검 진행 중입니다.</p>
    <p class="small">잠시 후 다시 이용해 주세요. 감사합니다.</p><p style="margin-top:14px"><a class="btn primary" href="/?mode=authorize" style="text-decoration:none">관리자 로그인</a></p>
  </div>
</div>
<style>
.maint-screen{position:fixed;inset:0;background:#f4f5f7;display:flex;align-items:center;justify-content:center;z-index:9999}
.maint-card{background:#fff;padding:50px 40px;border-radius:16px;text-align:center;max-width:380px;box-shadow:0 10px 40px rgba(0,0,0,.1)}
.maint-card h1{color:#2563eb;font-size:22px;margin:0 0 10px}
</style>
</body></html>
<?php if ($sysMaintOn && !$isAdminUser && $system_v['mode'] !== 'authorize') exit; endif; ?>

<header class="topbar">
  <a class="brand" href="/" aria-label="<?= e($siteName) ?>">나무<br class="br-mobile">장터</a>
  <nav class="nav" aria-label="주 메뉴">
    <a href="/?mode=board"><?= e($L['nav_products'] ?? '상품') ?></a>
    <?php if ($system_v['login']['valid']): ?>
      <a href="/?mode=write_post"><?= e($L['nav_sell'] ?? '상품등록') ?></a>
      <a href="/?mode=chat"><?= e($L['nav_chat'] ?? '채팅') ?><span id="chat-dot" class="chat-dot" style="display:none"></span></a>
      <a href="/?mode=wallet"><?= e($L['nav_wallet'] ?? '지갑') ?></a>
      <a href="/?mode=mypage"><?= e($L['nav_mypage'] ?? '마이페이지') ?></a>
    <?php endif; ?>
  </nav>
  <div class="nav-right">
    <span id="app-topbar-user" class="who" title="<?= e($system_v['login']['id']) ?>"><?= e($system_v['login']['valid'] ? ($system_v['login']['nick'] ?? $system_v['login']['id']) : '') ?></span>
    <form class="header-search" action="/?mode=search" method="get" role="search" style="flex:none;max-width:160px">
      <input type="hidden" name="mode" value="search"><input type="hidden" name="op" value="or">
      <input type="search" name="value" placeholder="검색" aria-label="검색" style="padding:5px 8px;font-size:13px;width:80px">
      <button class="btn sm primary" type="submit">검색</button>
    </form>
    <a id="app-topbar-link" class="btn <?= $system_v['login']['valid'] ? 'ghost' : 'primary' ?>" href="<?= e($system_v['login']['authorize_link']) ?>">
      <?= e($system_v['login']['authorize_phrase']) ?>
    </a>
  </div>
</header>

<div id="app-notice-bar">
<?php if ($sysUrgent !== ''): ?>
<div class="urgent-bar" role="alert"><?= e($sysUrgent) ?></div>
<?php elseif ($sysNotice !== ''): ?>
<div class="notice-bar" role="status"><?= e($sysNotice) ?></div>
<?php endif; ?>
</div>

<?php if ($popupNotice): ?>
<div id="notice_popup" class="modal-bg show" style="z-index:99">
  <div class="modal" style="max-width:420px">
    <div class="modal-head"><span><?= e($popupNotice['nb_title']) ?></span><button class="x" id="popup_close" aria-label="닫기">&times;</button></div>
    <div class="modal-body"><?= Security::render_text($popupNotice['nb_content']) ?></div>
    <div class="modal-foot">
      <label class="checkbox"><input type="checkbox" id="popup_today" onclick="closePopup()"> 오늘 하루 보지 않기</label>
      <button class="btn primary" id="popup_ok" onclick="closePopup()">닫기</button>
    </div>
  </div>
</div>
<script>
(function(){
  var d = new Date().toDateString();
  if (localStorage.getItem('popup_<?= (int)$popupNotice['nb_id'] ?>') === d) { var el = document.getElementById('notice_popup'); if (el) el.remove(); }
 else { function closePopup(){ var el=document.getElementById('notice_popup'); if(el)el.remove(); if(document.getElementById('popup_today').checked) localStorage.setItem('popup_<?= (int)$popupNotice['nb_id'] ?>', d); }
   document.getElementById('popup_close').onclick = closePopup;
   document.getElementById('popup_ok').onclick = closePopup; }
})();
</script>
<?php endif; ?>

<nav id="app-breadcrumb" class="breadcrumb" aria-label="경로">
  <?php foreach ($system_v['crumbs'] as $i => $c): ?>
    <?php if ($i > 0): ?><span class="sep">/</span><?php endif; ?>
    <a href="<?= e($c['href']) ?>"><?= e($c['name']) ?></a>
  <?php endforeach; ?>
</nav>

<main class="wrap" id="app-content">
<?php
// 마스터 관리자가 없고 localhost 접속이면 생성 폼 노출
$adminCount = (int)$sql->scalar("SELECT COUNT(*) FROM users WHERE acc_group=1");
$myIp = client_ip();
$isLocal = (strpos($myIp,'127.')===0 || strpos($myIp,'192.168.')===0 || strpos($myIp,'10.')===0 || $myIp==='::1' ||
  (strpos($myIp,'172.')===0 && (int)explode('.',$myIp)[1]>=16 && (int)explode('.',$myIp)[1]<=31));
if ($adminCount === 0 && $isLocal && $system_v['mode'] === 'board'):
?>
<div class="card" style="border-color:#fde68a;background:#fffbeb;max-width:520px;margin:40px auto">
  <h1 class="h1" style="margin-top:0">서비스 활성화를 위한 마스터 관리자 계정 생성</h1>
  <div class="msg warn">아직 관리자가 없습니다. localhost/사설망 접속자만 마스터 관리자를 생성할 수 있습니다.</div>
  <form id="master_form" onsubmit="return false">
    <input type="hidden" name="token" value="<?= e($system_v['token']) ?>">
    <input type="hidden" name="mode" value="master_admin_create">
    <label>관리자 ID</label><input name="form_id" placeholder="영문/숫자 4~20자리">
    <div class="row">
      <div><label>비밀번호</label><input type="password" name="form_pw" placeholder="8~32자리"></div>
      <div><label>이메일</label><input name="form_email" placeholder="gmail.com 등"></div>
    </div>
    <div class="msg info">TOTP 필수: 아래 시크릿을 Authenticator 앱에 등록 후, 6자리 코드를 입력하세요.</div>
    <div id="master_totp_area">
      <button class="btn" type="button" id="master_totp_gen">TOTP 시크릿 생성</button>
    </div>
    <label>TOTP 6자리 코드</label>
    <input name="form_totp" id="form_totp_master" inputmode="numeric" pattern="[0-9]{6}" placeholder="000000">
    <div style="margin-top:12px"><button class="btn primary lg" id="master_submit">마스터 관리자 생성</button></div>
  </form>
  <script>
  (function(){
    var secret = '';
    document.getElementById('master_totp_gen').addEventListener('click', function(){
      var fd=new FormData(); fd.append('token','<?= e($system_v['token']) ?>'); fd.append('mode','totp_init');
      // totp_init은 로그인 필요하므로 master_admin_create에서 별도 처리
      // 대신: master_admin_create에서 form_totp가 비어있으면 secret을 발급해서 응답
      Jn.req('/service_bridge.php',{json:{mode:'master_admin_create',form_id:'_check_',form_pw:'_',form_email:'_@_._',form_totp:''}}).then(function(j){
        if (j && j.totp_secret) {
          secret = j.totp_secret;
          var area = document.getElementById('master_totp_area');
          area.innerHTML = '<div class="qr-box" id="qr_box_master"></div><code class="secret-code">'+Jn.esc(secret)+'</code>';
          function draw(){ if(typeof QRCode==='undefined'){setTimeout(draw,200);return;} new QRCode(document.getElementById('qr_box_master'),{text:'otpauth://totp/joongna?secret='+secret,width:180,height:180}); }
          draw();
        } else { Jn.handleRes(j); }
      });
    });
    document.getElementById('master_submit').addEventListener('click', function(){
      var fd=new FormData(document.getElementById('master_form'));
      // secret을 hidden으로 추가
      if (secret) fd.append('totp_secret', secret);
      Jn.req('/service_bridge.php',{form:fd}).then(function(j){
        if (j && j.totp_secret && !j.success) {
          // 시크릿 발급만 한 경우
          secret = j.totp_secret;
          var area = document.getElementById('master_totp_area');
          area.innerHTML = '<div class="qr-box" id="qr_box_master"></div><code class="secret-code">'+Jn.esc(secret)+'</code>';
          function draw(){ if(typeof QRCode==='undefined'){setTimeout(draw,200);return;} new QRCode(document.getElementById('qr_box_master'),{text:'otpauth://totp/joongna?secret='+secret,width:180,height:180}); }
          draw();
          Jn.toast('QR 코드를 스캔하고 6자리 코드를 입력하세요','warn');
        } else { Jn.handleRes(j); }
      });
    });
  })();
  </script>
</div>
<?php
endif;

$pg = __DIR__ . '/pages/' . $system_v['mode'] . '.php';
if (is_file($pg)) {
    include $pg;
} else {
    echo '<div class="msg err">' . e($L['page_not_found'] ?? '존재하지 않는 페이지입니다.') . '</div>';
}
?>
</main>

<footer class="footer">
  <div class="foot-links">
    <a href="/?mode=terms"><?= e($L['terms_title'] ?? '이용약관') ?></a>
    <a href="/?mode=privacy"><?= e($L['privacy_title'] ?? '개인정보처리방침') ?></a>
  </div>
  <div class="copyright">© <?= date('Y') ?> <?= e($siteName) ?></div>
</footer>

</body>
</html>