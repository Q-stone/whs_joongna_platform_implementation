/* chat.js v8 - 지속 알림 + last_id 저장으로 초기화 후 재충전 방지 */
window.Chat = (function () {
  var rooms = {};
  var activeRoom = null;
  var unreadByRoom = {};
  var knownRooms = {};
  var lastById = {}; // room_id -> last_seen_msg_id

  function getToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    if (m && m.content) return m.content;
    try { return localStorage.getItem('jn_csrf') || ''; } catch (e) { return ''; }
  }

  function saveState() {
    try { localStorage.setItem('chat_unread', JSON.stringify(unreadByRoom)); } catch(e){}
    try { localStorage.setItem('chat_rooms', JSON.stringify(knownRooms)); } catch(e){}
    try { localStorage.setItem('chat_last', JSON.stringify(lastById)); } catch(e){}
  }
  function loadState() {
    try { unreadByRoom = JSON.parse(localStorage.getItem('chat_unread') || '{}'); } catch(e){ unreadByRoom={}; }
    try { knownRooms = JSON.parse(localStorage.getItem('chat_rooms') || '{}'); } catch(e){ knownRooms={}; }
    try { lastById = JSON.parse(localStorage.getItem('chat_last') || '{}'); } catch(e){ lastById={}; }
  }

  function updateDot() {
    var dot = document.getElementById('chat-dot');
    if (!dot) return;
    var total = 0; Object.keys(unreadByRoom).forEach(function(k){ total += unreadByRoom[k]; });
    dot.style.display = total > 0 ? 'inline-block' : 'none';
    dot.title = total > 0 ? (total + '개 읽지 않은 메시지') : '';
  }

  function startWatcher() {
    Object.keys(knownRooms).forEach(function(rid) {
      if (!rooms[rid]) rooms[rid] = { last: lastById[rid] || 0, box: null, input: null, sendBtn: null };
    });
    if (Object.keys(rooms).length > 0) pollAll(); // 1회만
  }

  function start(roomId, boxSel, inputSel, sendBtnSel) {
    var box = document.querySelector(boxSel);
    if (!box) return;
    // DM 표시용: 항상 last=0으로 전체 메시지 로드 (lastById는 알림 감시용)
    rooms[roomId] = { last: 0, box: box, input: document.querySelector(inputSel), sendBtn: document.querySelector(sendBtnSel) };
    knownRooms[roomId] = true;
    saveState();
    if (rooms[roomId].sendBtn) rooms[roomId].sendBtn.addEventListener('click', function () { send(roomId); });
    if (rooms[roomId].input) rooms[roomId].input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(roomId); } });
    pollAll();
  }

  function startReadonly(roomId, boxSel) {
    var box = document.querySelector(boxSel);
    if (!box) return;
    rooms[roomId] = { last: 0, box: box, input: null, sendBtn: null };
    knownRooms[roomId] = true;
    saveState();
    pollAll();
  }

  function send(roomId) {
    var r = rooms[roomId];
    if (!r || !r.input) return;
    var txt = r.input.value.trim();
    if (!txt) return;
    r.input.value = '';
    var fd = new FormData();
    fd.append('token', getToken());
    fd.append('mode', 'chat_send');
    fd.append('room', String(roomId));
    fd.append('text', txt);
    fetch('/service_bridge.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': getToken() }, body: fd })
      .then(function (res) { return res.json(); })
      .then(function (j) { if (j && j.error && window.Jn) Jn.toast(j.description, 'err'); pollAll(); })
      .catch(function () {});
  }

  function sendMedia(roomId, fileInputSel) {
    var f = document.querySelector(fileInputSel);
    if (!f || !f.files || !f.files[0]) return;
    var fd = new FormData();
    fd.append('token', getToken());
    fd.append('mode', 'chat_media');
    fd.append('room', String(roomId));
    fd.append('file', f.files[0]);
    fetch('/service_bridge.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': getToken() }, body: fd })
      .then(function (res) { return res.json(); })
      .then(function (j) { if (j && j.error && window.Jn) Jn.toast(j.description, 'err'); f.value = ''; pollAll(); })
      .catch(function () {});
  }

  function pollAll() { Object.keys(rooms).forEach(function (rid) { fetchNew(parseInt(rid, 10)); }); }

  function fetchNew(roomId) {
    var r = rooms[roomId]; if (!r) return;
    var tok = getToken();
    fetch('/service_bridge.php?act=chat_poll&room=' + roomId + '&last=' + r.last + '&token=' + encodeURIComponent(tok), { method: 'GET', credentials: 'same-origin', headers: { 'X-CSRF-Token': tok } })
      .then(function (res) { return res.json(); })
      .then(function (j) {
        if (!j || j.error) return;
        if (j.messages && j.messages.length) {
          var newOtherCount = 0;
          j.messages.forEach(function (m) {
            if (r.box) render(roomId, m);
            r.last = Math.max(r.last, m.msg_id);
            if (!m.is_me) newOtherCount++;
          });
          if (r.box) r.box.scrollTop = r.box.scrollHeight;
          // last_id 저장
          lastById[roomId] = r.last;
          saveState();
          if (newOtherCount > 0 && activeRoom !== roomId) {
            if (!unreadByRoom[roomId]) unreadByRoom[roomId] = 0;
            unreadByRoom[roomId] += newOtherCount;
            saveState();
            updateDot();
            var other = j.messages.find(function(m){return !m.is_me;});
            if (window.Jn && other) Jn.toast(other.by + '님에게서 채팅이 왔습니다', 'warn');
          }
        }
        if (j.__new_csrf) { try { localStorage.setItem('jn_csrf', j.__new_csrf); } catch (e) {} }
      }).catch(function () {});
  }

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }

  function render(roomId, m) {
    var r = rooms[roomId]; if (!r || !r.box) return;
    var div = document.createElement('div'); div.className = 'bubble ' + (m.is_me ? 'me' : '');
    var html = '<div class="by">' + esc(m.by) + '</div>';
    if (m.kind === 0) html += '<div>' + esc(m.text) + '</div>';
    else if (m.kind === 1) html += m.is_video ? '<video src="' + esc(m.ref) + '" controls style="max-width:100%"></video>' : '<img src="' + esc(m.ref) + '" style="max-width:100%"/>';
    else if (m.kind >= 2) { var lb = { 2: '전화번호 공유', 3: '계좌정보 공유', 4: '주소 공유' }; html += '<div class="badge ok">' + (lb[m.kind] || '') + '</div><br><span class="small">' + esc(m.text) + '</span>'; }
    div.innerHTML = html; r.box.appendChild(div);
  }

  function setActiveRoom(roomId) { activeRoom = roomId; if (roomId != null && unreadByRoom[roomId]) { delete unreadByRoom[roomId]; saveState(); updateDot(); } }
  function clearNotifications() { unreadByRoom = {}; try { localStorage.removeItem('chat_unread'); } catch(e){} updateDot(); }
  function registerRoom(roomId) {
    knownRooms[roomId] = true;
    if (!rooms[roomId]) rooms[roomId] = { last: lastById[roomId] || 0, box: null, input: null, sendBtn: null };
    saveState();
    startWatcher();
  }

  function stop() { }

  loadState();
  updateDot();

  return { start: start, startReadonly: startReadonly, send: send, sendMedia: sendMedia, stop: stop, pollAll: pollAll, setActiveRoom: setActiveRoom, registerRoom: registerRoom, updateDot: updateDot, clearNotifications: clearNotifications };
})();