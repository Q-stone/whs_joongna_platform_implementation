/* common.js v4 - 클라이언트 공통 프레임워크
 *  CSRF 안정 토큰, fetch 래퍼(JSON/Form 자동 처리), 토스트, 모달, 광고차단 감지
 *  언어: lang_kor.json (PHP $L와 동일 키)
 */
window.Jn = (function () {
  var LANG = {};

  function loadLang(cb) {
    var cached = null;
    try { cached = localStorage.getItem('jn_lang'); } catch (e) {}
    if (cached) { try { LANG = JSON.parse(cached); } catch (e) { LANG = {}; } }
    fetch('/cdn/js/lang_kor.json', { cache: 'no-cache' })
      .then(function (r) { return r.json(); })
      .then(function (j) { LANG = j; try { localStorage.setItem('jn_lang', JSON.stringify(j)); } catch (e) {} if (cb) cb(); })
      .catch(function () { if (cb) cb(); });
  }
  function t(k, vars) {
    var parts = k.split('.'), v = LANG;
    for (var i = 0; i < parts.length; i++) {
      if (v && Object.prototype.hasOwnProperty.call(v, parts[i])) v = v[parts[i]];
      else return k;
    }
    if (typeof v !== 'string') return k;
    if (vars) Object.keys(vars).forEach(function (kk) { v = v.split('{' + kk + '}').join(String(vars[kk])); });
    return v;
  }

  function metaToken() { var m = document.querySelector('meta[name="csrf-token"]'); return m && m.content ? m.content : ''; }
  function syncCsrf() { var m = metaToken(); if (m) { try{ localStorage.setItem('jn_csrf', m);}catch(e){} return m; } try { return localStorage.getItem('jn_csrf') || ''; } catch (e) { return ''; } }
  function csrf() { return metaToken() || (function () { try { return localStorage.getItem('jn_csrf') || ''; } catch (e) { return ''; } })(); }

/* fetch 래퍼 - JSON/Form/GET 모두 지원. CSRF는 헤더로 자동 첨부 */
  function req(url, opts) {
    opts = opts || {};
    var headers = Object.assign({ 'X-CSRF-Token': csrf() }, opts.headers || {});
    var body = undefined;
    if (opts.json !== undefined) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(opts.json);
    } else if (opts.form) {
      body = opts.form;
    } else if (opts.body !== undefined) {
      headers['Content-Type'] = headers['Content-Type'] || 'application/x-www-form-urlencoded';
      body = opts.body;
    }
    // E2E encrypt if key present (not for GET/chat_poll)
var doEncrypt = function(cb) {
      var sp = getServerPub();
      if (!sp || sp.length!==64 || opts.method === 'GET' || !body) return cb(body, headers);
      // FormData에 파일이 포함된 경우 암호화 생략 (파일은 이진 전송 필요)
      var hasFiles = false;
      if (body instanceof FormData) { body.forEach(function(v,k){ if(v instanceof File)hasFiles=true; }); }
      if (hasFiles) return cb(body, headers);
      var mode = '';
      if (typeof body === 'string') { var m = body.match(/mode=([^&]+)/); if (m) mode = m[1]; }
      else if (body instanceof FormData) { mode = body.get('mode') || ''; }
      var py = typeof body === 'string' ? body : '[formdata]';
      if (body instanceof FormData) {
        var oo = {}; body.forEach(function(v,k){oo[k]=v}); py = JSON.stringify(oo);
      }
      initE2E().then(function() {
        if (!e2eAesKey) return cb(body, headers);
        e2eEncrypt(py).then(function(enc){
          if (!enc) return cb(body, headers);
          var fd = new FormData();
          fd.append('mode', mode);
          fd.append('_enc_data', enc.data);
          fd.append('_enc_iv', enc.iv);
          fd.append('_enc_tag', enc.tag);
          fd.append('token', csrf());
          if (e2eClientPub) fd.append('_dh_pub', e2eClientPub);
          return cb(fd, {});
        });
      });
    };
    return new Promise(function(resolve, reject){
      doEncrypt(function(b, h){
        resolve(fetch(url, { method: opts.method || 'POST', credentials: 'same-origin', headers: h, body: b, cache: opts.cache || 'no-cache' })
          .then(function (r) {
            var ct = r.headers.get('content-type') || '';
            if (ct.indexOf('application/json') !== -1) return r.json();
            return r.text().then(function (tt) { try { return JSON.parse(tt); } catch (e) { return { error: 1, description: tt }; } });
          })
          .then(function (j) {
            // E2E response decrypt
            if (j && j._enc_resp) {
              return e2eDecrypt(j._enc_resp, j._enc_iv, j._enc_tag).then(function(txt){
                var p = JSON.parse(txt);
                if (p && p.__new_csrf) { try { localStorage.setItem('jn_csrf', p.__new_csrf); } catch(e){} }
                return p;
              }).catch(function(){ return j; });
            }
            if (j && j.__new_csrf) { try { localStorage.setItem('jn_csrf', j.__new_csrf); } catch (e) {} }
            return j;
          }).then(function(j){ if(window.Chat)Chat.pollAll();return j; }));
      });
    });
  }
  function get(url) { return req(url, { method: 'GET' }); }

  /* 순수 HTML fetch (JSON 파싱 안 함) */
  function fetchHtml(url) {
    return fetch(url, { method: 'GET', credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf() } })
      .then(function (r) { return r.text(); });
  }

  /* 토스트 */
  function toast(msg, kind) {
    var box = document.getElementById('jn_toast') || (function () {
      var d = document.createElement('div'); d.id = 'jn_toast';
      d.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:99999;display:flex;flex-direction:column;gap:10px;max-width:92vw';
      document.body.appendChild(d); return d;
    })();
    var el = document.createElement('div');
    var bg = (kind === 'err') ? '#dc2626' : (kind === 'warn' ? '#d97706' : '#16a34a');
    el.style.cssText = 'background:' + bg + ';color:#fff;padding:11px 18px;border-radius:10px;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,.2);max-width:80vw;text-align:center';
    el.textContent = msg; box.appendChild(el);
    setTimeout(function () { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 420); }, 2800);
  }

  function handleRes(j, okCb) {
    if (!j) { toast(t('toast_err')); return; }
    if (j.csrf_expired) { toast(j.description || t('csrf_err'), 'err'); setTimeout(function () { location.reload(); }, 1300); return; }
    if (j.need_admin_auth) { toast(j.description || '관리자 인증 필요', 'warn'); setTimeout(function(){location.href='/?mode=admin'},800); return; }
    if (j.error) { toast(j.description || t('toast_err'), 'err'); if (j.redirect && j.notice) setTimeout(function () { location.href = j.redirect; }, 1300); return; }
    if (j.success) { if (okCb) okCb(j); else toast(j.description || t('toast_ok'), 'ok'); if (j.redirect) setTimeout(function () { location.href = j.redirect; }, 700); return; }
    if (j.redirect) { location.href = j.redirect; return; }
    toast(j.description || t('toast_err'), 'err');
  }

  /* 모달 */
  function modal(title, bodyHtml, footHtml, opts) {
    opts = opts || {};
    var bg = document.createElement('div'); bg.className = 'modal-bg show';
    var mm = document.createElement('div'); mm.className = 'modal';
    mm.innerHTML = '<div class="modal-head"><span>' + esc(title) + '</span><button class="x" aria-label="닫기">&times;</button></div>' +
      '<div class="modal-body">' + bodyHtml + '</div>' + (footHtml ? '<div class="modal-foot">' + footHtml + '</div>' : '');
    bg.appendChild(mm); document.body.appendChild(bg);
    function close() { bg.remove(); }
    bg.addEventListener('click', function (e) { if (e.target === bg && !opts.sticky) close(); });
    mm.querySelector('.x').addEventListener('click', close);
    if (opts.onReady) opts.onReady(mm, close);
    return { el: mm, bg: bg, close: close };
  }
  function confirmBox(msg, onYes) {
    modal(t('confirm'), '<p style="margin:0">' + esc(msg) + '</p>',
      '<button class="btn primary js-ok">' + esc(t('yes')) + '</button><button class="btn ghost js-cancel">' + esc(t('no')) + '</button>',
      { onReady: function (m, cl) { m.querySelector('.js-ok').onclick = function () { cl(); onYes(); }; m.querySelector('.js-cancel').onclick = cl; } });
  }

  function sendPrivate(action, kind, who) {
    var kmap = { phone: t('phone'), account: t('chat_account'), address: t('address') };
    var msg = t('chat_priv_prompt_' + kind, { who: who });
    return new Promise(function (resolve) {
      confirmBox(msg, function () {
        req(action, { json: { mode: 'chat_priv', kind: kind } }).then(function (j) { handleRes(j); resolve(!j.error); });
      });
    });
  }

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }

  /* 광고차단 감지 */
  function detectAdblock() {
    var bait = document.createElement('div');
    bait.className = 'ad-banner ad-zone ad-container textads adsbygoogle';
    bait.style.cssText = 'width:1px;height:1px;position:absolute;left:-9999px;top:-9999px';
    bait.innerHTML = '&nbsp;';
    document.body.appendChild(bait);
    setTimeout(function () {
      var blocked = bait.offsetParent === null || bait.offsetHeight === 0 || bait.clientHeight === 0 || window.getComputedStyle(bait).display === 'none';
      bait.remove();
      if (blocked) showAdblockModal();
    }, 300);
  }
  function showAdblockModal() {
    modal('광고 차단 프로그램 감지됨',
      '<div style="font-size:15px;line-height:1.6">' +
      '<p style="font-weight:700;font-size:16px;margin-top:0">광고 차단 프로그램을 해제해 주세요.</p>' +
      '<p>AdGuard 등 광고 차단 프로그램은 유저분들께 편의성을 제공하지만, ' +
      '일부 레이아웃이 깨지거나 문제가 발생하는 경우가 있습니다.</p>' +
      '<p class="small" style="color:#7b8794">광고 차단 프로그램을 비활성화한 새로고침 해주세요.</p>' +
      '</div>',
      '<button class="btn primary" onclick="location.reload()">' + esc(t('confirm')) + '</button>',
      { sticky: true });
  }

  /* 언어 즉시 로드 (DOMContentLoaded보다 먼저 동기 cached 사용) */
  loadLang();

  /* ECDH via Web Crypto X25519 */
  var e2eAesKey = null, e2eClientPub = null, _e2eReady = false;
  function getServerPub(){ var m=document.querySelector('meta[name="e2e-pub"]'); return m?m.content:''; }
  function _h2b(h){ return new Uint8Array(h.match(/.{1,2}/g).map(function(b){return parseInt(b,16)})); }
  async function initE2E(){
    if (e2eAesKey) return e2eAesKey;
    var sp = getServerPub(); if (!sp || sp.length!==64) { console.log('[E2E] no server pub key'); return null; }
    try {
      var spKey = await crypto.subtle.importKey('raw', _h2b(sp).buffer, 'X25519', false, []);
      var ckp = await crypto.subtle.generateKey('X25519', true, ['deriveKey']);
      var cpubRaw = new Uint8Array(await crypto.subtle.exportKey('raw', ckp.publicKey));
      e2eClientPub = Array.from(cpubRaw).map(function(b){return b.toString(16).padStart(2,'0')}).join('');
      console.log('[E2E] client pub:', e2eClientPub.substring(0,16)+'...');
      // ECDH shared → HMAC → raw export → AES-GCM import (가장 호환성 높음)
      var hmacKey = await crypto.subtle.deriveKey(
        {name:'X25519', public: spKey}, ckp.privateKey,
        {name:'HMAC', hash:'SHA-256', length:256}, true, ['sign']);
      var shRaw = new Uint8Array(await crypto.subtle.exportKey('raw', hmacKey));
      console.log('[E2E] shared secret (first 8B):', Array.from(shRaw.slice(0,8)).map(function(b){return b.toString(16).padStart(2,'0')}).join(''));
      e2eAesKey = await crypto.subtle.importKey('raw', shRaw.buffer, {name:'AES-GCM', length:256}, true, ['encrypt','decrypt']);
      _e2eReady = true;
      console.log('[E2E] key exchange complete');
      return e2eAesKey;
    } catch(e){ console.log('[E2E] init failed:', e.message || e); return null; }
  }
  async function e2eEncrypt(text){
    var key = await initE2E(); if (!key) { console.log('[E2E] encrypt: no key'); return null; }
    var iv = crypto.getRandomValues(new Uint8Array(12));
    var enc = await crypto.subtle.encrypt({name:'AES-GCM', iv:iv}, key, new TextEncoder().encode(text));
    var tag = new Uint8Array(enc.slice(-16)), data = new Uint8Array(enc.slice(0,-16));
    console.log('[E2E] encrypt ok, payload len='+text.length);
    return { data: Array.from(data).map(function(b){return b.toString(16).padStart(2,'0')}).join(''),
             iv:   Array.from(iv).map(function(b){return b.toString(16).padStart(2,'0')}).join(''),
             tag:  Array.from(tag).map(function(b){return b.toString(16).padStart(2,'0')}).join('') };
  }
  async function e2eDecrypt(encResp, ivHex, tagHex){
    var key = await initE2E(); if (!key) { console.log('[E2E] decrypt: no key'); return null; }
    try {
      var d=_h2b(encResp); var i=_h2b(ivHex); var t=_h2b(tagHex);
      var combo=new Uint8Array(d.length+16); combo.set(d); combo.set(t,d.length);
      var dec=await crypto.subtle.decrypt({name:'AES-GCM',iv:i},key,combo);
      var txt = new TextDecoder().decode(dec);
      console.log('[E2E] decrypt ok, len='+txt.length);
      return txt;
    } catch(e) { console.log('[E2E] decrypt FAIL:', e.message || e); return null; }
  }

  return { t: t, req: req, get: get, fetchHtml: fetchHtml, toast: toast, handleRes: handleRes, syncCsrf: syncCsrf, csrf: csrf,
    loadLang: loadLang, sendPrivate: sendPrivate, esc: esc, modal: modal, confirmBox: confirmBox,
    e2eEncrypt: e2eEncrypt, e2eDecrypt: e2eDecrypt,
      detectAdblock: detectAdblock, LANG: function () { return LANG; } };
})();

/* Jn.router - SPA 페이지 내비게이션 (E2E 암호화) */
Jn.router = {
  _initDone: false,
  init: function () {
    if (this._initDone) return; this._initDone = true;
    var self = this;
    // 초기 state 등록
    var qs = location.search.replace('?', '');
    var initMode = new URLSearchParams(location.search).get('mode') || 'board';
    history.replaceState({ mode: initMode, params: qs }, '', location.href);
    // <a> 클릭 인터셉트
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a');
      if (!a || !a.href) return;
      var u;
      try { u = new URL(a.href, location.origin); } catch (ex) { return; }
      if (u.origin !== location.origin) return;
      if (u.pathname !== '/' && u.pathname !== '/index.php' && u.pathname !== '/service_bridge.php') return;
      if (a.getAttribute('download') || a.getAttribute('target')) return;
      if (a.hasAttribute('data-no-route')) return;
      if (e.ctrlKey || e.metaKey || e.shiftKey) return;
      e.preventDefault();
      var params = u.search.replace('?', '');
      var mode = u.searchParams.get('mode') || 'board';
      self.navigate(u.pathname + u.search, mode, params);
    });
    // GET 폼 인터셉트
    document.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      var f = e.target;
      if (!f || !f.method) return;
      if (f.method.toLowerCase() !== 'get') return;
      var action = f.action || location.href;
      var au; try { au = new URL(action, location.origin); } catch (ex) { return; }
      if (au.origin !== location.origin) return;
      var pp = au.pathname;
      if (pp !== '/' && pp !== '/index.php' && pp !== '/service_bridge.php') return;
      e.preventDefault();
      var fd = new FormData(f);
      var sp = new URLSearchParams(fd).toString();
      var mode = fd.get('mode') || 'board';
      self.navigate('/?' + sp, mode, sp);
    });
    // popstate (back/forward)
    window.addEventListener('popstate', function (e) {
      if (e.state && e.state.mode) {
        self.loadPage(e.state.mode, e.state.params || '');
      } else {
        location.reload();
      }
    });
  },
  navigate: function (url, mode, params) {
    history.pushState({ mode: mode, params: params }, '', url);
    this.loadPage(mode, params);
  },
  loadPage: function (mode, params) {
    var self = this;
    // DM 페이지가 아니면 활성 채팅방 초기화 (다른 방 메시지 수신 가능하도록)
    if (mode !== 'dm' && window.Chat) Chat.setActiveRoom(null);
    var content = document.getElementById('app-content');
    if (content) { content.style.opacity = '0.4'; content.style.transition = 'opacity .15s'; }
    var fd = new FormData();
    fd.append('mode', 'page_load');
    fd.append('route', mode);
    fd.append('params', params || '');
    Jn.req('/service_bridge.php', { form: fd }).then(function (j) {
      if (content) { content.style.opacity = '1'; }
      if (!j) return;
      if (j.redirect) { location.href = j.redirect; return; }
      if (j.error) { Jn.handleRes(j); return; }
      if (j.page_title) document.title = j.page_title + ' - 나무장터';
      var md = document.querySelector('meta[name="description"]');
      if (md && j.meta_desc) md.content = j.meta_desc;
      var bc = document.getElementById('app-breadcrumb');
      if (bc && j.breadcrumb) bc.innerHTML = j.breadcrumb;
      if (content && j.content_html) {
        content.innerHTML = j.content_html;
        // 인라인 script 실행 (innerHTML은 script를 실행하지 않음)
        content.querySelectorAll('script').forEach(function (s) {
          var ns = document.createElement('script');
          Array.from(s.attributes).forEach(function (a) { ns.setAttribute(a.name, a.value); });
          ns.textContent = s.textContent;
          s.parentNode.replaceChild(ns, s);
        });
        if (typeof bindEditors === 'function') bindEditors();
        document.querySelectorAll('.table-swipe').forEach(function (el) {
          el.addEventListener('scroll', function () { el.classList.add('swiped-once'); }, { once: true });
          el.addEventListener('touchmove', function () { el.classList.add('swiped-once'); }, { once: true });
        });
        window.scrollTo(0, 0);
      }
      var tu = document.getElementById('app-topbar-user');
      if (tu && j.topbar_user !== undefined) { tu.textContent = j.topbar_user || ''; tu.title = j.topbar_user || ''; }
      var tl = document.getElementById('app-topbar-link');
      if (tl && j.topbar_link_text) { tl.textContent = j.topbar_link_text; tl.href = j.topbar_link_href || '/?mode=authorize'; }
      var nb = document.getElementById('app-notice-bar');
      if (nb && j.notice_bar !== undefined) { nb.innerHTML = j.notice_bar; }
    });
  }
};

function bindEditors() {
  document.querySelectorAll('textarea[data-editor]').forEach(function (ta) {
    if (ta.dataset.editorBound) return;
    ta.dataset.editorBound = '1';
    var bar = document.createElement('div');
    bar.className = 'editor-bar';
    bar.style.cssText = 'display:flex;gap:4px;margin-bottom:4px;flex-wrap:wrap';
    var tags = [
      ['B', 'b', '볼드'],
      ['I', 'i', '이탈릭'],
      ['S', 'strike', '취소선'],
      ['―', 'hr', '구분선'],
    ];
    tags.forEach(function (t) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn sm';
      b.title = t[2];
      if (t[0] === 'B') b.innerHTML = '<b>B</b>';
      else if (t[0] === 'I') b.innerHTML = '<i>I</i>';
      else if (t[0] === 'S') b.innerHTML = '<strike>S</strike>';
      else b.textContent = t[0];
      b.onclick = function (e) { e.preventDefault(); wrapTa(ta, t[1]); };
      bar.appendChild(b);
    });
    function wrapTa(t, tag) {
      var s = t.selectionStart, e = t.selectionEnd, v = t.value;
      if (s === e) { t.focus(); return; }
      var sel = v.substring(s, e);
      t.value = v.substring(0, s) + '<' + tag + '>' + sel + '</' + tag + '>' + v.substring(e);
      var nl = tag.length * 2 + 5;
      t.focus();
      t.setSelectionRange(e + nl, e + nl);
    }
    ta.parentNode.insertBefore(bar, ta);
  });
}
document.addEventListener('DOMContentLoaded', function () {
  Jn.syncCsrf();
  Jn.detectAdblock();
  Jn.router.init();
  bindEditors();

  // table drag scroll
  var dragTbl=null, dragSx=0, dragSl=0;
  document.addEventListener('mousedown',function(e){
    var t=e.target.closest('.table-swipe');
    if(t){dragTbl=t;dragSx=e.pageX;dragSl=t.scrollLeft;}
  });
  document.addEventListener('mousemove',function(e){
    if(!dragTbl)return;e.preventDefault();dragTbl.scrollLeft=dragSl-(e.pageX-dragSx);
  });
  document.addEventListener('mouseup',function(){dragTbl=null;});
});
