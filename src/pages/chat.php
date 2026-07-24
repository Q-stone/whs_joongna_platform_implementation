<?php
/* pages/chat.php - 채팅 상대 목록 */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }
if (!$system_v['login']['valid']) { echo '<div class="msg warn">' . e($L['login_required']) . ' <a href="/?mode=authorize">' . e($L['nav_login']) . '</a></div>'; return; }
global $sql;
$me = (int)$system_v['login']['number'];

// 1:1 채팅방 목록 (각 방의 상대방 + 상품 정보)
$others = $sql->run(
    "SELECT u.acc_number, u.acc_id, COALESCE(NULLIF(u.acc_nick,''),u.acc_id) AS display_name,
        (SELECT MAX(m.msg_time) FROM chat_messages m WHERE m.msg_room=cm.cm_room) AS last_time,
        (SELECT m.msg_text FROM chat_messages m WHERE m.msg_room=cm.cm_room ORDER BY m.msg_id DESC LIMIT 1) AS last_msg,
        cr.room_key, cr.room_id
     FROM chat_members cm
     JOIN chat_rooms cr ON cr.room_id=cm.cm_room
     JOIN chat_members cm2 ON cm2.cm_room=cm.cm_room AND cm2.cm_user<>cm.cm_user
     JOIN users u ON u.acc_number=cm2.cm_user
     WHERE cm.cm_user=? AND cr.room_type=1
     ORDER BY last_time DESC",
    'i', [$me]
);

// 각 방의 상품 정보 추출 (room_key에서 P{prd}_{buyer} 파싱)
function parseRoomKey(string $key): array {
    if (preg_match('/^P(\d+)/', $key, $m)) {
        return [(int)$m[1], 0];
    }
    return [0, 0];
}
?>
<h1 class="h1">대화 상대 목록</h1>
<?php if (empty($others)): ?>
<div class="card"><div class="empty">아직 대화 상대가 없습니다. 상품 페이지에서 판매자에게 연락해보세요.</div></div>
<?php else: ?>
<div class="card">
<div class="table-swipe"><div class="swipe-hint"><span class="swipe-icon">↔</span> 좌우로 드래그</div>
<table style="width:100%">
<tr><th style="width:90px">상대방</th><th style="width:140px">상품</th><th>마지막 메시지</th><th style="width:130px;white-space:nowrap">시간</th><th style="width:60px">대화</th></tr>
<?php foreach ($others as $o):
    list($prdId, $buyerId) = parseRoomKey($o['room_key']);
    $prd = $prdId > 0 ? $sql->one("SELECT prd_title,prd_price,prd_seller FROM products WHERE prd_number=?",'i',[$prdId]) : null;
?>
<tr>
  <td><a href="/?mode=profile&view=<?= (int)$o['acc_number'] ?>"><?= e($o['display_name'] ?? $o['acc_id']) ?></a></td>
  <td><?php if ($prd): ?><a href="/?mode=view_post&view=<?= $prdId ?>"><?= e($prd['prd_title']) ?></a><br><span class="small" style="color:#2563eb"><?= number_format((int)$prd['prd_price']) ?>원</span><?php else: ?><span class="small">상품 정보 없음</span><?php endif; ?></td>
  <td style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:0" title="<?= e($o['last_msg'] ?? '') ?>"><?= e(mb_strimwidth($o['last_msg'] ?? '(메시지 없음)', 0, 80, '...')) ?></td>
  <td class="small"><?= e($o['last_time'] ?? '') ?></td>
  <td><a class="btn sm primary" href="/?mode=dm&with=<?= (int)$o['acc_number'] ?>&prd=<?= $prdId ?>">대화</a></td>
</tr>
<?php endforeach; ?>
</table>
</div>
</div>
<?php endif; ?>
<script>
// 채팅 목록 확인 시 모든 알림 초기화
if (window.Chat) Chat.clearNotifications();
<?php foreach ($others as $o): list($prdId) = parseRoomKey($o['room_key']);
  $rid = (int)$sql->scalar("SELECT cr.room_id FROM chat_rooms cr JOIN chat_members cm ON cm.cm_room=cr.room_id WHERE cr.room_key=? AND cm.cm_user=?",'si',[$o['room_key'],$me]);
  if ($rid) echo "Chat.registerRoom($rid);\n";
endforeach; ?>
</script>