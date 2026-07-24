<?php
/* pages/admin.php - 관리자 페이지 (탭형) */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
if (!$system_v['login']['valid'] || (int)$system_v['login']['group'] !== 1) {
    echo '<div class="msg err">' . e($L['admin_no_perm']) . '</div>';
    return;
}
global $sql;
// 관리자 인증 체크 - admin_verified 없거나 1시간 경과 시 인증 폼
$needAdminAuth = empty($_SESSION['admin_verified']) || (time() - (int)$_SESSION['admin_verified']) > 3600;
if ($needAdminAuth):
  $u = $sql->one("SELECT acc_totp_ok,acc_email FROM users WHERE acc_number=?", 'i', [$system_v['login']['number']]);
?>
<h1 class="h1">관리자 인증</h1>
<div class="card" style="max-width:420px;margin:30px auto">
  <div class="msg info">관리자 페이지 접속을 위한 TOTP 인증이 필요합니다.</div>
  <?php if ((int)$u['acc_totp_ok']===1): ?>
  <div id="auth_area">
    <label>TOTP 6자리</label><input id="auth_code" inputmode="numeric" pattern="[0-9]{6}" placeholder="000000"><button class="btn primary" id="auth_go" style="margin-top:8px">인증</button>
  </div>
  <?php else: ?>
  <div class="msg warn">관리자 계정에 TOTP가 설정되지 않았습니다. 마이페이지에서 TOTP를 먼저 설정하세요. <a href="/?mode=mypage" class="btn primary" style="margin-left:8px">마이페이지로</a></div>
  <?php endif; ?>
</div>
<script>
document.getElementById('auth_go')?.addEventListener('click', function(){
  var fd=new FormData(); fd.append('token',Jn.csrf()); fd.append('mode','admin_auth'); fd.append('code',document.getElementById('auth_code').value);
  Jn.req('/service_bridge.php',{form:fd}).then(function(j){ Jn.handleRes(j,function(){location.reload();}); });
});
</script>
<?php
return;
endif;

// 마스터 관리자 여부
$isMaster = (int)$sql->scalar("SELECT acc_group FROM users WHERE acc_number=?", 'i', [$system_v['login']['number']]) === 1;
$tab = $_GET['tab'] ?? 'users';
$tabs = ['users', 'reports', 'logs', 'firewall', 'system', 'products', 'chat_rooms'];
if (!in_array($tab, $tabs, true)) $tab = 'users';
$tabNames = [
    'users' => '사용자 관리', 'reports' => '신고 검토', 'logs' => '로그 검색',
    'firewall' => 'IP 차단', 'system' => '공지/점검',
];
$tabNames = [
    'users' => '사용자 관리',
    'reports' => '신고 검토',
    'logs' => '로그 검색',
    'firewall' => 'IP 및 클라이언트 차단',
    'system' => '시스템 공지 / 점검',
    'products' => '상품 관리',
    'chat_rooms' => '채팅방 관리',
];
?>
<h1 class="h1"><?= e($L['admin_title']) ?></h1>
<nav class="tabs" aria-label="관리자 탭">
<?php foreach ($tabs as $t): ?>
  <a href="/?mode=admin&tab=<?= e($t) ?>" class="tab <?= $tab===$t?'active':'' ?>"><?= e($tabNames[$t]) ?></a>
<?php endforeach; ?>
</nav>
<?php

if ($tab === 'users') {
    $users = $sql->run("SELECT acc_number,acc_id,acc_email,acc_status,acc_report_ban,acc_group,acc_balance,acc_totp_ok,registertime FROM users ORDER BY acc_number DESC LIMIT 200");
    $stNames = [0=>'활성',1=>'미인증',2=>'휴면',3=>'정지'];
    ?>
    <div class="table-swipe">
    <div class="swipe-hint"><span class="swipe-icon">↔</span> 좌우로 드래그</div>
    <table>
    <tr><th>#</th><th>ID</th><th>이메일</th><th>상태</th><th>TOTP</th><th>신고권한</th><th>권한</th><th>잔액</th><th>조치</th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= (int)$u['acc_number'] ?></td><td><?= e($u['acc_id']) ?></td><td><?= e($u['acc_email']) ?></td>
      <td><?= e($stNames[(int)$u['acc_status']] ?? '?') ?></td>
      <td><?= (int)$u['acc_totp_ok']===1?'<span class="badge ok">ON</span>':'-' ?></td>
      <td><?= (int)$u['acc_report_ban']===1?'<span class="badge dorm">금지</span>':'가능' ?></td>
      <td><?= (int)$u['acc_group']===1?'<span class="badge admin">관리자</span>':'일반' ?></td>
      <td><?= number_format((int)$u['acc_balance']) ?></td>
      <td style="white-space:nowrap">
        <button class="btn sm" onclick="adminToggleReport(<?= (int)$u['acc_number'] ?>)">신고권한</button>
        <button class="btn sm danger" onclick="adminDormant(<?= (int)$u['acc_number'] ?>)">휴면</button>
        <button class="btn sm danger" onclick="adminDeactivate(<?= (int)$u['acc_number'] ?>)">정지</button>
        <?php if ((int)$u['acc_group']!==1): ?>
          <button class="btn sm" onclick="adminSetAdmin(<?= (int)$u['acc_number'] ?>)">관리자</button>
          <?php if ($isMaster): ?><button class="btn sm danger" onclick="adminDeleteUser(<?= (int)$u['acc_number'] ?>)">삭제</button><?php endif; ?>
        <?php else: ?>
          <?php if ($isMaster && (int)$u['acc_number'] !== $system_v['login']['number']): ?>
            <button class="btn sm danger" onclick="adminRevoke(<?= (int)$u['acc_number'] ?>)">권한박탈</button>
            <!-- TOTP 삭제 기능 제거 (사용자 본인 마이페이지에서만 가능) -->
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </table>
    </div>
    <?php
} elseif ($tab === 'reports') {
    $reports = $sql->run("SELECT rpt_id,rpt_target,rpt_ref,rpt_reason,rpt_time, (SELECT acc_id FROM users WHERE acc_number=rpt_reporter) AS rid FROM reports WHERE rpt_status=0 ORDER BY rpt_id DESC LIMIT 100");
    if (!$reports) echo '<div class="empty">' . e($L['admin_no_reports']) . '</div>';
    ?>
    <div class="table-swipe">
    <div class="swipe-hint"><span class="swipe-icon">↔</span> 좌우로 드래그</div>
    <table>
    <tr><th>#</th><th>대상</th><th>참조</th><th>신고자</th><th>사유</th><th>시간</th><th>조치</th></tr>
    <?php foreach ($reports as $r): ?>
    <tr>
      <td><?= (int)$r['rpt_id'] ?></td><td><?= (int)$r['rpt_target']==0?'상품':'유저' ?></td><td><?= (int)$r['rpt_ref'] ?></td><td><?= e($r['rid'] ?: '[no data]') ?></td>
      <td  title="<?= e($r['rpt_reason']) ?>"><?= e(mb_strimwidth($r['rpt_reason'],0,80,'...')) ?></td><td class="nowrap small"><?= e($r['rpt_time']) ?></td>
      <td style="white-space:nowrap">
        <button class="btn sm" onclick="adminReport(<?= (int)$r['rpt_id'] ?>,1)">조치</button>
        <button class="btn sm" onclick="adminReport(<?= (int)$r['rpt_id'] ?>,2)">기각</button>
        <?php if ((int)$r['rpt_target']===0): ?>
          <button class="btn sm danger" onclick="adminBlind(<?= (int)$r['rpt_ref'] ?>)">차단</button>
          <button class="btn sm danger" onclick="adminDeletePrd(<?= (int)$r['rpt_ref'] ?>)">삭제</button>
        <?php endif; ?>
        <?php if ((int)$r['rpt_target']===1): ?><button class="btn sm danger" onclick="adminDormant(<?= (int)$r['rpt_ref'] ?>)">휴면</button><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </table>
    </div>
    <?php
} elseif ($tab === 'logs') {
    $lg = ['user'=>'','mode'=>'','ip'=>'','ua'=>'','msg'=>'','ok'=>'','op'=>'or','id_from'=>'','id_to'=>'','time_from'=>'','time_to'=>'','per_page'=>50];
    foreach (['user','mode','ip','ua','msg','ok','op','id_from','id_to','time_from','time_to','per_page'] as $k) { $lg[$k] = trim($_GET['lg_'.$k] ?? ''); }
    if (!in_array($lg['op'], ['and','or'], true)) $lg['op']='or';
    $per = max(10, min(200, (int)$lg['per_page']));
    $page = max(0, (int)($_GET['lg_page'] ?? 0));
    $where = ['1=1']; $params = []; $types = '';
    $conds = [];
    if ($lg['user'] !== '') { $conds[] = "(rl_user=? OR rl_user_name LIKE ?)"; $params[] = (int)$lg['user']; $params[] = '%'.$lg['user'].'%'; $types .= 'is'; }
    if ($lg['mode'] !== '') { $conds[] = "rl_mode LIKE ?"; $params[] = '%'.$lg['mode'].'%'; $types .= 's'; }
    if ($lg['ip'] !== '') { $conds[] = "rl_ip LIKE ?"; $params[] = '%'.$lg['ip'].'%'; $types .= 's'; }
    if ($lg['ua'] !== '') { $conds[] = "rl_ua LIKE ?"; $params[] = '%'.$lg['ua'].'%'; $types .= 's'; }
    if ($lg['msg'] !== '') { $conds[] = "rl_msg LIKE ?"; $params[] = '%'.$lg['msg'].'%'; $types .= 's'; }
    if ($lg['ok'] === '1') $where[] = 'rl_ok=1';
    elseif ($lg['ok'] === '0') $where[] = 'rl_ok=0';
    if ($lg['id_from'] !== '' && (int)$lg['id_from'] > 0) { $where[] = "rl_id >= ?"; $params[] = (int)$lg['id_from']; $types .= 'i'; }
    if ($lg['id_to'] !== '' && (int)$lg['id_to'] > 0) { $where[] = "rl_id <= ?"; $params[] = (int)$lg['id_to']; $types .= 'i'; }
    if ($lg['time_from'] !== '') { $where[] = "rl_time >= ?"; $params[] = $lg['time_from'].' 00:00:00'; $types .= 's'; }
    if ($lg['time_to'] !== '') { $where[] = "rl_time <= ?"; $params[] = $lg['time_to'].' 23:59:59'; $types .= 's'; }
    if ($conds) $where[] = '('.implode(' '.strtoupper($lg['op']).' ', $conds).')';
    $whereSql = implode(' AND ', $where);
    $count = (int)$sql->scalar("SELECT COUNT(*) FROM request_log WHERE $whereSql", $types, $params);
    $page_rows = $sql->run("SELECT rl_id,rl_user,rl_user_name,rl_mode,rl_msg,rl_ok,rl_ip,rl_ua,rl_memo,rl_time FROM request_log WHERE $whereSql ORDER BY rl_id DESC LIMIT $per OFFSET $page", $types, $params);
    $totalPages = max(1, (int)ceil($count / $per));
    $curPage = intval($page / $per) + 1;
    $GLOBALS['_log_per'] = $per;
    function lg_url($p) { $qs = $_GET; $qs['lg_page'] = (string)($p * $GLOBALS['_log_per']); return '/?mode=admin&tab=logs&'.http_build_query($qs); }
?>
<form method="get" class="card" action="/?mode=admin&tab=logs">
  <input type="hidden" name="mode" value="admin"><input type="hidden" name="tab" value="logs">
  <div class="row">
    <div><label>사용자(번호/ID)</label><input name="lg_user" value="<?= e($lg['user']) ?>" placeholder="번호 또는 ID"></div>
    <div><label>mode</label><input name="lg_mode" value="<?= e($lg['mode']) ?>" placeholder="wallet"></div>
    <div><label>IP</label><input name="lg_ip" value="<?= e($lg['ip']) ?>" placeholder="172"></div>
    <div><label>UA</label><input name="lg_ua" value="<?= e($lg['ua']) ?>" placeholder="curl"></div>
    <div><label>메시지</label><input name="lg_msg" value="<?= e($lg['msg']) ?>"></div>
    <div><label>결과</label><select name="lg_ok"><option value="">전체</option><option value="1" <?= $lg['ok']==='1'?'selected':'' ?>>성공만</option><option value="0" <?= $lg['ok']==='0'?'selected':'' ?>>실패만</option></select></div>
    <div><label>연산</label><select name="lg_op"><option value="or" <?= $lg['op']==='or'?'selected':'' ?>>OR</option><option value="and" <?= $lg['op']==='and'?'selected':'' ?>>AND</option></select></div>
  </div>
  <div class="row">
    <div><label>로그 번호 시작</label><input name="lg_id_from" value="<?= e($lg['id_from']) ?>" placeholder="100"></div>
    <div><label>로그 번호 끝</label><input name="lg_id_to" value="<?= e($lg['id_to']) ?>" placeholder="200"></div>
    <div><label>시작일 (YYYY-MM-DD)</label><input type="date" name="lg_time_from" value="<?= e($lg['time_from']) ?>"></div>
    <div><label>종료일 (YYYY-MM-DD)</label><input type="date" name="lg_time_to" value="<?= e($lg['time_to']) ?>"></div>
    <div><label>페이지당</label><select name="lg_per_page"><option value="20" <?= $per===20?'selected':'' ?>>20</option><option value="50" <?= $per===50?'selected':'' ?>>50</option><option value="100" <?= $per===100?'selected':'' ?>>100</option><option value="200" <?= $per===200?'selected':'' ?>>200</option></select></div>
  </div>
  <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <button class="btn primary" type="submit">검색</button>
    <a class="btn" href="/?mode=admin&tab=logs">전체 로그(초기화)</a>
    <span class="small">결과: <?= number_format($count) ?>건 · <?= $curPage ?>/<?= $totalPages ?>페이지</span>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:6px;padding:10px;background:#fff3cd;border-radius:8px">
    <span style="font-weight:700;font-size:13px">오래된 로그 정리:</span>
    <select id="log_days" style="max-width:100px;flex:0 0 auto">
      <option value="7">7일</option><option value="30" selected>30일</option><option value="90">90일</option><option value="180">180일</option><option value="365">365일</option>
    </select>
    <span class="small">이전 로그 삭제</span>
    <button class="btn sm danger" id="log_cleanup_btn">삭제 실행</button>
  </div>
</form>
<details open>
  <summary style="cursor:pointer;font-weight:700;font-size:15px;padding:10px;background:#f3f5f8;border-radius:8px;margin:8px 0">요청 로그 (<?= number_format($count) ?>건)</summary>
  <div class="table-swipe" style="margin-top:8px">
  <div class="swipe-hint"><span class="swipe-icon">↔</span> 좌우로 드래그</div>
  <table>
  <tr><th>#</th><th>사용자</th><th>mode</th><th>메모</th><th>결과</th><th>IP</th><th>UA</th><th>시간</th></tr>
  <?php if (!$page_rows) echo '<tr><td colspan=8 class="muted center">로그 없음</td></tr>'; ?>
  <?php foreach ($page_rows as $r): ?>
  <tr><td><?= (int)$r['rl_id'] ?></td><td><?= e($r['rl_user_name'] ?: '[no data]') ?></td><td><b><?= e($r['rl_mode']) ?></b></td><td class="small"><?= e($r['rl_memo'] ?: '[no data]') ?></td><td><?= (int)$r['rl_ok']===1?'<span class="badge ok">OK</span>':'<span class="badge report">FAIL</span>' ?></td><td class="small"><?= e($r['rl_ip']) ?></td><td class="small"><?= e($r['rl_ua']) ?></td><td class="nowrap small"><?= e($r['rl_time']) ?></td></tr>
  <?php endforeach; ?>
  </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <div class="pager">
    <?php if ($curPage > 1): ?><a class="btn sm" href="<?= lg_url(0) ?>">« 처음</a><a class="btn sm" href="<?= lg_url($curPage - 2) ?>">‹ 이전</a><?php endif; ?>
    <span class="small"><?= $curPage ?> / <?= $totalPages ?> 페이지</span>
    <?php if ($curPage < $totalPages): ?><a class="btn sm" href="<?= lg_url($curPage) ?>">다음 ›</a><a class="btn sm" href="<?= lg_url($totalPages - 1) ?>">마지막 »</a><?php endif; ?>
  </div>
  <?php endif; ?>
</details>
<details>
  <summary style="cursor:pointer;font-weight:700;font-size:15px;padding:10px;background:#f3f5f8;border-radius:8px;margin:8px 0">관리자 행위 로그 (최근 100건)</summary>
  <div class="table-swipe" style="margin-top:8px">
  <div class="swipe-hint"><span class="swipe-icon">↔</span> 좌우로 드래그</div>
  <table>
  <tr><th>#</th><th>관리자</th><th>행위</th><th>대상</th><th>상세 메모</th><th>시간</th></tr>
  <?php $adminLogs = $sql->run("SELECT a.am_id,a.am_admin,u.acc_id AS aname,a.am_action,a.am_target,t.acc_id AS tname,a.am_memo,a.am_time FROM admin_log a LEFT JOIN users u ON a.am_admin=u.acc_number LEFT JOIN users t ON a.am_target=t.acc_number ORDER BY a.am_id DESC LIMIT 100"); if (!$adminLogs) echo '<tr><td colspan=6 class="muted center">없음</td></tr>'; foreach ($adminLogs as $s): ?>
  <tr><td><?= (int)$s['am_id'] ?></td><td><?= e($s['aname'] ?: '[no data]') ?></td><td><b><?= e($s['am_action']) ?></b></td><td><?= e($s['tname'] ?: '[no data]') ?></td><td class="small"><?= e($s['am_memo'] ?: '[no data]') ?></td><td class="nowrap small"><?= e($s['am_time']) ?></td></tr>
  <?php endforeach; ?>
  </table>
  </div>
</details>
<details>
  <summary style="cursor:pointer;font-weight:700;font-size:15px;padding:10px;background:#f3f5f8;border-radius:8px;margin:8px 0">의심스러운 활동 (로그인 실패 최근 50건)</summary>
  <div class="table-swipe" style="margin-top:8px">
  <div class="swipe-hint"><span class="swipe-icon">↔</span> 좌우로 드래그</div>
  <table>
  <tr><th>#</th><th>사용자</th><th>IP</th><th>UA</th><th>시간</th></tr>
  <?php $suslog = $sql->run("SELECT lg_id,lg_user,lg_ip,lg_ok,lg_agent,lg_time FROM login_log WHERE lg_ok=0 ORDER BY lg_id DESC LIMIT 50"); if (!$suslog) echo '<tr><td colspan=5 class="muted center">없음</td></tr>'; foreach ($suslog as $s): ?>
  <tr><td><?= (int)$s['lg_id'] ?></td><td><?= (int)$s['lg_user']>0?(int)$s['lg_user']:'[no data]' ?></td><td><?= e($s['lg_ip']) ?></td><td class="small"><?= e($s['lg_agent']) ?></td><td class="nowrap small"><?= e($s['lg_time']) ?></td></tr>
  <?php endforeach; ?>
  </table>
  </div>
</details>
<?php
} elseif ($tab === 'firewall') {
    $fw = $sql->run("SELECT * FROM ip_firewall ORDER BY fw_type, fw_id DESC");
    ?>
    <h2 class="h2">새 규칙 추가</h2>
    <form id="fw_add" class="card" onsubmit="return false">
      <input type="hidden" name="token" value="<?= e($system_v['token']) ?>">
      <input type="hidden" name="mode" value="firewall_add">
      <div class="row">
        <div><label>종류</label><select name="fw_type"><option value="0">블랙리스트(차단)</option><option value="1">화이트리스트(허용)</option></select></div>
        <div><label>규칙유형</label><select name="fw_rule" id="fw_rule">
          <option value="ip">단일 IP (예: 1.2.3.4)</option>
          <option value="wildcard">와일드카드 (예: 192.168.*)</option>
          <option value="country">국가 (예: KR)</option>
        </select></div>
        <div><label>패턴</label><input name="fw_pattern" id="fw_pattern" placeholder="1.2.3.4"></div>
        <div><label>메모</label><input name="fw_memo" placeholder="(선택)"></div>
      </div>
      <div style="margin-top:10px"><button class="btn primary" id="fw_add_btn">추가</button></div>
    </form>
    <?php $torOn=((string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key='tor'")?:'0')==='1'; ?>
    <h2 class="h2">TOR 차단: <?= $torOn?'<span class="badge report">차단됨</span>':'<span class="badge ok">차단안됨</span>' ?> <button class="btn sm <?= $torOn?'':'danger' ?>" id="tor_toggle_btn"><?= $torOn?'비활성화':'활성화' ?></button></h2>
    <?php
    $uaOn=((string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key='ua_block'")?:'0')==='1';
    $uaMode=((string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key='ua_mode'")?:'or')==='and'?'AND':'OR';
    $uaList=(string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key='ua_list'");
    ?>
    <div class="card" style="margin:10px 0">
      <h2>클라이언트(UA) 차단: <?= $uaOn?'<span class="badge report">차단됨</span>':'<span class="badge ok">차단안됨</span>' ?></h2>
      <div class="row" style="align-items:center">
        <button class="btn sm <?= $uaOn?'':'danger' ?>" id="ua_toggle_btn"><?= $uaOn?'UA 차단 해제':'UA 차단 활성화' ?></button>
        <select id="ua_mode" style="max-width:100px;flex:0 0 auto">
          <option value="or" <?= $uaMode==='OR'?'selected':'' ?>>OR</option>
          <option value="and" <?= $uaMode==='AND'?'selected':'' ?>>AND</option>
        </select>
        <span class="small">매칭 방식 (OR: 하나라도 일치 / AND: 모두 일치)</span>
      </div>
      <label style="margin-top:8px">UA 패턴 (줄바꿈 구분)</label>
      <textarea id="ua_list" rows="4" placeholder="예:&#10;curl&#10;python-requests"><?= e($uaList) ?></textarea>
      <div style="margin-top:8px"><button class="btn primary" id="ua_save_btn">UA 설정 저장</button></div>
    </div>
    <h2 class="h2">현재 규칙 (<?= count($fw ?? []) ?>건)</h2>
    <div class="table-swipe">
    <div class="swipe-hint"><span class="swipe-icon">↔</span> 좌우로 드래그</div>
    <table>
    <tr><th>#</th><th>종류</th><th>유형</th><th>패턴</th><th>메모</th><th>시간</th><th>삭제</th></tr>
    <?php if (!$fw) echo '<tr><td colspan=8 class="muted center">규칙 없음</td></tr>'; ?>
    <?php foreach ($fw as $r): ?>
    <tr><td><?= (int)$r['fw_id'] ?></td><td><?= (int)$r['fw_type']===0?'<span class="badge report">블랙</span>':'<span class="badge ok">화이트</span>' ?></td><td><?= e($r['fw_rule']) ?></td><td class="mono"><?= e($r['fw_pattern']) ?></td><td class="small"><?= e($r['fw_memo']) ?></td><td class="nowrap small"><?= e($r['fw_time']) ?></td><td><button class="btn sm danger" onclick="fwDel(<?= (int)$r['fw_id'] ?>)">삭제</button></td></tr>
    <?php endforeach; ?>
    </table>
    </div>
    <div class="msg info">화이트리스트가 1건 이상 있으면, 화이트리스트에 일치하지 않는 모든 접속이 차단됩니다.</div>
    <?php
} elseif ($tab === 'system') {
    $notice = (string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key='notice'");
    $urgent = (string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key='notice_urgent'");
    $maint = (string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key='maintenance'");
    $termsCur = $sql->one("SELECT sd_id,sd_body,sd_time FROM site_docs WHERE sd_kind='terms' ORDER BY sd_id DESC LIMIT 1");
    $privacyCur = $sql->one("SELECT sd_id,sd_body,sd_time FROM site_docs WHERE sd_kind='privacy' ORDER BY sd_id DESC LIMIT 1");
    $termsHist = $sql->run("SELECT sd_id,sd_time FROM site_docs WHERE sd_kind='terms' ORDER BY sd_id DESC LIMIT 10");
    $privacyHist = $sql->run("SELECT sd_id,sd_time FROM site_docs WHERE sd_kind='privacy' ORDER BY sd_id DESC LIMIT 10");
    ?>
    <div class="card">
      <h2>공지사항 (메인 상단 바)</h2>
      <textarea id="notice_body" data-editor="rte" style="min-height:80px"><?= e($notice) ?></textarea>
      <div style="margin-top:8px"><button class="btn primary" id="notice_save">저장</button></div>
    </div>
    <div class="card">
      <h2>긴급 공지 (빨간 바)</h2>
      <textarea id="urgent_body" data-editor="rte" style="min-height:80px"><?= e($urgent) ?></textarea>
      <div style="margin-top:8px"><button class="btn primary" id="urgent_save">저장</button> (빈 내용은 표시 안 됨)</div>
    </div>
    <div class="card">
      <h2>서비스 임시 중단</h2>
      <div class="between">
        <div>현재 상태: <?= $maint==='1'?'<span class="badge report">점검 중</span>':'<span class="badge ok">정상</span>' ?>
        <div class="small">점검 중에는 일반 사용자는 모든 활동이 차단되고 점검 페이지로 전환됩니다. 관리자는 정상 이용 가능.</div></div>
        <button class="btn <?= $maint==='1'?'':'danger' ?>" id="maint_toggle"><?= $maint==='1'?'점검 해제':'점검 시작' ?></button>
      </div>
    </div>
    <div class="card">
      <h2>이용약관 갱신</h2>
      <?php if ($termsCur): ?><div class="small">현재 버전: #<?= (int)$termsCur['sd_id'] ?> (<?= e($termsCur['sd_time']) ?>)</div><?php endif; ?>
      <textarea id="terms_body" data-editor="rte" style="min-height:160px"><?= e($termsCur['sd_body'] ?? '') ?></textarea>
      <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <button class="btn primary" id="terms_save">갱신(새 버전)</button>
        <span class="small">이전 버전:</span>
        <?php foreach (array_slice($termsHist, 1) as $h): ?>
          <a class="btn sm ghost" href="/?mode=terms&v=<?= (int)$h['sd_id'] ?>">#<?= (int)$h['sd_id'] ?> (<?= e(mb_substr($h['sd_time'],0,10)) ?>)</a>
        <?php endforeach; ?>
        <?php if (count($termsHist) <= 1) echo '<span class="small">이전 버전 없음</span>'; ?>
      </div>
    </div>
    <div class="card">
      <h2>개인정보처리방침 갱신</h2>
      <?php if ($privacyCur): ?><div class="small">현재 버전: #<?= (int)$privacyCur['sd_id'] ?> (<?= e($privacyCur['sd_time']) ?>)</div><?php endif; ?>
      <textarea id="privacy_body" data-editor="rte" style="min-height:160px"><?= e($privacyCur['sd_body'] ?? '') ?></textarea>
      <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <button class="btn primary" id="privacy_save">갱신(새 버전)</button>
        <span class="small">이전 버전:</span>
        <?php foreach (array_slice($privacyHist, 1) as $h): ?>
          <a class="btn sm ghost" href="/?mode=privacy&v=<?= (int)$h['sd_id'] ?>">#<?= (int)$h['sd_id'] ?> (<?= e(mb_substr($h['sd_time'],0,10)) ?>)</a>
        <?php endforeach; ?>
        <?php if (count($privacyHist) <= 1) echo '<span class="small">이전 버전 없음</span>'; ?>
      </div>
    </div>
    <script>
    function saveDoc(key, val){ var fd=new FormData(); fd.append('token',Jn.csrf()); fd.append('mode','notice_set'); fd.append('key',key); fd.append('value',val); Jn.req('/service_bridge.php',{form:fd}).then(function(j){ Jn.handleRes(j); }); }
    function saveSiteDoc(kind){ var bid = kind==='terms'?'terms_body':'privacy_body'; var val = document.getElementById(bid).value; var mode = kind==='terms'?'admin_edit_terms':'admin_edit_privacy'; var fd=new FormData(); fd.append('token',Jn.csrf()); fd.append('mode',mode); fd.append('body',val); Jn.req('/service_bridge.php',{form:fd}).then(function(j){ Jn.handleRes(j, function(){ Jn.toast('갱신됨. 새 버전 저장됨.','ok'); }); }); }
    document.getElementById('notice_save').addEventListener('click', function(){ saveDoc('notice', document.getElementById('notice_body').value); });
    document.getElementById('urgent_save').addEventListener('click', function(){ saveDoc('notice_urgent', document.getElementById('urgent_body').value); });
    document.getElementById('terms_save').addEventListener('click', function(){ Jn.confirmBox('이용약관을 새 버전으로 갱신합니다. 이전 버전은 보존됩니다.', function(){ saveSiteDoc('terms'); }); });
    document.getElementById('privacy_save').addEventListener('click', function(){ Jn.confirmBox('개인정보처리방침을 새 버전으로 갱신합니다. 이전 버전은 보존됩니다.', function(){ saveSiteDoc('privacy'); }); });
    document.getElementById('maint_toggle').addEventListener('click', function(){ var on = '<?= $maint==='1'?'0':'1' ?>'; Jn.confirmBox(on==='1'?'서비스 점검을 시작합니다. 모든 사용자가 로그아웃됩니다.':'서비스 점검을 해제합니다.', function(){ var fd=new FormData(); fd.append('token',Jn.csrf()); fd.append('mode','service_maint'); fd.append('on',on); Jn.req('/service_bridge.php',{form:fd}).then(function(j){ Jn.handleRes(j,function(){location.reload();}); }); }); });
    </script>
    <?php
} elseif ($tab === 'products') {
    $ps = trim($_GET['ps'] ?? '');
    $pp = max(0, (int)($_GET['pp'] ?? 0));
    $per = 20;
    $where = '1=1'; $wparams = []; $wtypes = '';
    if ($ps !== '') { $where = "(p.prd_title LIKE ? OR COALESCE(NULLIF(u.acc_nick,''),u.acc_id) LIKE ?)"; $wparams = ['%'.$ps.'%', '%'.$ps.'%']; $wtypes = 'ss'; }
    $count = (int)$sql->scalar("SELECT COUNT(*) FROM products p JOIN users u ON p.prd_seller=u.acc_number WHERE $where", $wtypes, $wparams);
    $products = $sql->run("SELECT p.prd_number,p.prd_title,p.prd_price,p.prd_blind,p.prd_sold,p.prd_report_cnt,p.prd_time,COALESCE(NULLIF(u.acc_nick,''),u.acc_id) AS seller FROM products p JOIN users u ON p.prd_seller=u.acc_number WHERE $where ORDER BY p.prd_number DESC LIMIT $per OFFSET $pp", $wtypes, $wparams);
    $tp = max(1, (int)ceil($count / $per)); $cp = intval($pp / $per) + 1;
?>
<h2 class="h2">상품 관리 (<?= number_format($count) ?>건)</h2>
<form method="get" action="/?mode=admin&tab=products" style="display:flex;gap:8px;margin-bottom:10px">
  <input type="hidden" name="mode" value="admin"><input type="hidden" name="tab" value="products">
  <input name="ps" value="<?= e($ps) ?>" placeholder="제목 또는 판매자 검색" style="max-width:300px">
  <button class="btn sm primary" type="submit">검색</button>
  <a class="btn sm" href="/?mode=admin&tab=products">초기화</a>
</form>
<div class="table-swipe"><div class="swipe-hint"><span class="swipe-icon">↔</span> 좌우로 드래그</div><table>
<tr><th>#</th><th>제목</th><th>가격</th><th>판매자</th><th>상태</th><th>신고</th><th>시간</th><th>관리</th></tr>
<?php if(!$products) echo '<tr><td colspan=8 class="muted center">검색 결과 없음</td></tr>'; ?>
<?php foreach($products as $p): ?>
<tr>
  <td><?= (int)$p['prd_number'] ?></td>
  <td><a href="/?mode=view_post&view=<?= (int)$p['prd_number'] ?>"><?= e($p['prd_title']) ?></a></td>
  <td><?= number_format((int)$p['prd_price']) ?>원</td>
  <td><?= e($p['seller']) ?></td>
  <td><?php if((int)$p['prd_blind']===1): ?><span class="badge report">차단</span><?php elseif((int)$p['prd_sold']===1): ?><span class="badge ok">판매완료</span><?php else: ?><span class="badge ok">판매중</span><?php endif; ?></td>
  <td><?= (int)$p['prd_report_cnt'] ?></td>
  <td class="small"><?= e($p['prd_time']) ?></td>
  <td style="white-space:nowrap">
    <button class="btn sm" onclick="adminBlind(<?= (int)$p['prd_number'] ?>)"><?= (int)$p['prd_blind']===1?'차단해제':'차단' ?></button>
    <button class="btn sm danger" onclick="adminDeletePrd(<?= (int)$p['prd_number'] ?>)">삭제</button>
  </td>
</tr>
<?php endforeach; ?>
</table></div>
<?php if ($tp > 1): ?>
<div class="pager">
  <?php if ($cp > 1): ?><a class="btn sm" href="/?mode=admin&tab=products&ps=<?= urlencode($ps) ?>&pp=0">« 처음</a><a class="btn sm" href="/?mode=admin&tab=products&ps=<?= urlencode($ps) ?>&pp=<?= max(0,$pp-$per) ?>">‹ 이전</a><?php endif; ?>
  <span class="small"><?= $cp ?> / <?= $tp ?> 페이지</span>
  <?php if ($cp < $tp): ?><a class="btn sm" href="/?mode=admin&tab=products&ps=<?= urlencode($ps) ?>&pp=<?= $pp+$per ?>">다음 ›</a><a class="btn sm" href="/?mode=admin&tab=products&ps=<?= urlencode($ps) ?>&pp=<?= ($tp-1)*$per ?>">마지막 »</a><?php endif; ?>
</div>
<?php endif; ?>
<?php
} elseif ($tab === 'chat_rooms') {
    try { $sql->run("ALTER TABLE chat_rooms ADD COLUMN cr_blocked TINYINT UNSIGNED NOT NULL DEFAULT 0"); } catch(\Throwable $e) {}
    $cs = trim($_GET['cs'] ?? '');
    $cp2 = max(0, (int)($_GET['cp'] ?? 0));
    $per2 = 20;
    $where2 = "cr.room_type=1"; $wparams2 = []; $wtypes2 = '';
    if ($cs !== '') { $where2 .= " AND (cr.room_key LIKE ? OR EXISTS(SELECT 1 FROM chat_members cm2 JOIN users u2 ON u2.acc_number=cm2.cm_user WHERE cm2.cm_room=cr.room_id AND (u2.acc_id LIKE ? OR u2.acc_nick LIKE ?)))"; $wparams2 = ['%'.$cs.'%','%'.$cs.'%','%'.$cs.'%']; $wtypes2 = 'sss'; }
    $count2 = (int)$sql->scalar("SELECT COUNT(*) FROM chat_rooms cr WHERE $where2", $wtypes2, $wparams2);
    $crooms = $sql->run("SELECT cr.room_id,cr.room_key,cr.room_time,cr.cr_blocked,
        (SELECT COUNT(*) FROM chat_messages m WHERE m.msg_room=cr.room_id) AS msg_cnt,
        (SELECT MAX(m2.msg_time) FROM chat_messages m2 WHERE m2.msg_room=cr.room_id) AS last_time,
        (SELECT GROUP_CONCAT(DISTINCT COALESCE(NULLIF(u.acc_nick,''),u.acc_id) SEPARATOR ', ') FROM chat_members cm JOIN users u ON u.acc_number=cm.cm_user WHERE cm.cm_room=cr.room_id) AS members
     FROM chat_rooms cr WHERE $where2 ORDER BY cr.room_id DESC LIMIT $per2 OFFSET $cp2", $wtypes2, $wparams2);
    $tp2 = max(1, (int)ceil($count2 / $per2)); $cur2 = intval($cp2 / $per2) + 1;
    function parseRK(string $k): array { if(preg_match('/^P(\d+)_(\d+)_(\d+)/',$k,$m))return[(int)$m[1],(int)$m[2],(int)$m[3]]; return[0,0,0]; }
    function getDmLink(array $r): string { list($prd)=parseRK($r['room_key']);return '/?mode=dm&rid='.(int)$r['room_id'].'&prd='.$prd; }
?>
<h2 class="h2">채팅방 관리 (<?= number_format($count2) ?>건)</h2>
<form method="get" action="/?mode=admin&tab=chat_rooms" style="display:flex;gap:8px;margin-bottom:10px">
  <input type="hidden" name="mode" value="admin"><input type="hidden" name="tab" value="chat_rooms">
  <input name="cs" value="<?= e($cs) ?>" placeholder="상품ID 또는 참여자 검색" style="max-width:300px">
  <button class="btn sm primary" type="submit">검색</button>
  <a class="btn sm" href="/?mode=admin&tab=chat_rooms">초기화</a>
</form>
<div class="table-swipe"><div class="swipe-hint"><span class="swipe-icon">↔</span> 좌우로 드래그</div><table>
<tr><th>#</th><th>상품</th><th>참여자</th><th>메시지</th><th>마지막 활동</th><th>상태</th><th>생성일</th><th>관리</th></tr>
<?php if(!$crooms) echo '<tr><td colspan=8 class="muted center">채팅방 없음</td></tr>'; ?>
<?php foreach($crooms as $r):
    list($prdId)=parseRK($r['room_key']);$prdTitle='';if($prdId>0){$p=$sql->one("SELECT prd_title FROM products WHERE prd_number=?",'i',[$prdId]);$prdTitle=$p['prd_title']??'삭제됨';}
?>
<tr>
  <td><?= (int)$r['room_id'] ?></td>
  <td><?php if($prdId>0): ?><a href="/?mode=view_post&view=<?= $prdId ?>"><?= e($prdTitle) ?></a><?php else: ?>[no data]<?php endif; ?></td>
  <td class="small"><?= e($r['members']?:'[no data]') ?></td>
  <td><?= (int)$r['msg_cnt'] ?></td>
  <td class="small"><?= e($r['last_time']?:'없음') ?></td>
  <td><?= (int)$r['cr_blocked']===1?'<span class="badge report">차단</span>':'<span class="badge ok">활성</span>' ?></td>
  <td class="small"><?= e($r['room_time']) ?></td>
  <td style="white-space:nowrap">
    <a class="btn sm primary" href="<?= getDmLink($r) ?>">보기</a>
    <button class="btn sm" onclick="adminToggleRoomBlock(<?= (int)$r['room_id'] ?>)"><?= (int)$r['cr_blocked']===1?'차단해제':'차단' ?></button>
    <button class="btn sm danger" onclick="adminDeleteRoom(<?= (int)$r['room_id'] ?>)">삭제</button>
  </td>
</tr>
<?php endforeach; ?>
</table></div>
<?php if ($tp2 > 1): ?>
<div class="pager">
  <?php if ($cur2 > 1): ?><a class="btn sm" href="/?mode=admin&tab=chat_rooms&cs=<?= urlencode($cs) ?>&cp=0">« 처음</a><a class="btn sm" href="/?mode=admin&tab=chat_rooms&cs=<?= urlencode($cs) ?>&cp=<?= max(0,$cp2-$per2) ?>">‹ 이전</a><?php endif; ?>
  <span class="small"><?= $cur2 ?> / <?= $tp2 ?> 페이지</span>
  <?php if ($cur2 < $tp2): ?><a class="btn sm" href="/?mode=admin&tab=chat_rooms&cs=<?= urlencode($cs) ?>&cp=<?= $cp2+$per2 ?>">다음 ›</a><a class="btn sm" href="/?mode=admin&tab=chat_rooms&cs=<?= urlencode($cs) ?>&cp=<?= ($tp2-1)*$per2 ?>">마지막 »</a><?php endif; ?>
</div>
<?php endif; ?>
<?php
}
?>
<script>
function call(payload, cb){ payload.token = Jn.csrf(); Jn.req('/service_bridge.php',{form:toForm(payload)}).then(function(j){ Jn.handleRes(j, cb||function(){}); }); }
function toForm(o){ var f=new FormData(); Object.keys(o).forEach(function(k){ f.append(k, o[k]); }); return f; }
function adminReport(id, st){ call({mode:'admin_report_act', rpt_id:id, st:st+''}, function(){ location.reload(); }); }
function adminDeletePrd(pno){ Jn.modal('상품 삭제', '<div class="msg warn">이 상품을 삭제하시겠습니까? 복구 불가.</div>', '<button class="btn danger js-ok">삭제</button><button class="btn ghost js-cancel">닫기</button>', { onReady: function(m, cl){ m.querySelector('.js-ok').onclick=function(){ cl(); call({mode:'admin_delete_prd', prd:pno+''}, function(){ location.reload(); }); }; m.querySelector('.js-cancel').onclick=cl; } }); }
function adminDormant(uno){ call({mode:'admin_dormant_user', user:uno+''}, function(){ location.reload(); }); }
function adminDeactivate(uno){ Jn.confirmBox('계정을 정지/해제합니다.', function(){ call({mode:'admin_deactivate_user', user:uno+''}, function(){ location.reload(); }); }); }
function adminDeleteUser(uno){ Jn.confirmBox('이 계정을 영구 삭제합니다. 모든 데이터가 삭제되며 되돌릴 수 없습니다.', function(){ call({mode:'admin_delete_user', user:uno+''}, function(){ location.reload(); }); }); }
function adminToggleReport(uno){ call({mode:'admin_toggle_reportban', user:uno+''}, function(){ location.reload(); }); }
function adminSetAdmin(uno){ Jn.confirmBox('관리자 권한을 토글합니다.', function(){ call({mode:'admin_toggle_admin', user:uno+''}, function(){ location.reload(); }); }); }
function adminRevoke(uno){ Jn.confirmBox('관리자 권한을 박탈합니다.', function(){ call({mode:'admin_revoke_admin', user:uno+''}, function(){ location.reload(); }); }); }
function adminTotpDeleteUser(uno){ Jn.confirmBox('이 사용자의 TOTP를 삭제합니다.', function(){ call({mode:'totp_delete_user', user:uno+''}, function(){ location.reload(); }); }); }
document.getElementById('fw_add_btn')?.addEventListener('click', function(){ Jn.req('/service_bridge.php',{form:new FormData(document.getElementById('fw_add'))}).then(function(j){ Jn.handleRes(j,function(){location.reload();}); }); });
document.getElementById('tor_toggle_btn')?.addEventListener('click', function(){ call({mode:'tor_toggle'}, function(){ location.reload(); }); });
document.getElementById('ua_toggle_btn')?.addEventListener('click', function(){ call({mode:'ua_toggle'}, function(){ location.reload(); }); });
document.getElementById('ua_save_btn')?.addEventListener('click', function(){
  var fd=new FormData(); fd.append('token',Jn.csrf()); fd.append('mode','ua_save');
  fd.append('ua_list',document.getElementById('ua_list')?.value||'');
  fd.append('ua_mode',document.getElementById('ua_mode')?.value||'or');
  Jn.req('/service_bridge.php',{form:fd}).then(function(j){ Jn.handleRes(j,function(){location.reload();}); });
});
document.getElementById('log_cleanup_btn')?.addEventListener('click', function(){
  var days = document.getElementById('log_days')?.value || '30';
  Jn.confirmBox(days+'일 이전의 모든 로그(request/login/visitor)를 삭제합니다. 계속하시겠습니까?', function(){
    var fd=new FormData(); fd.append('token',Jn.csrf()); fd.append('mode','log_cleanup'); fd.append('days',days);
    Jn.req('/service_bridge.php',{form:fd}).then(function(j){ Jn.handleRes(j,function(){location.reload();}); });
  });
});
function fwDel(id){ Jn.confirmBox('이 규칙을 삭제합니다.', function(){ var fd=new FormData(); fd.append('token',Jn.csrf()); fd.append('mode','firewall_del'); fd.append('fw_id',id+''); Jn.req('/service_bridge.php',{form:fd}).then(function(j){ Jn.handleRes(j,function(){location.reload();}); }); }); }
</script>