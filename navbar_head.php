<?php require_once(__DIR__ . '/init.php'); ?>
<?php
// navbar_head.php - Head assets only
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
<script>(function(){var t='light';try{t=localStorage.getItem('farm-theme')||'light';}catch(e){}if(t!=='dark'&&t!=='light')t='light';document.documentElement.setAttribute('data-theme',t);document.documentElement.setAttribute('data-bs-theme',t);})();</script>
<?php
$tenantPrimaryColor = currentFarm()['primary_color'] ?? '#198754';
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $tenantPrimaryColor)) $tenantPrimaryColor = '#198754';
?>
<!-- Bootstrap CSS (local fallback for offline environments) -->
<link href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/css/bootstrap.min.css'); ?>" rel="stylesheet">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Custom CSS -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/style.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/dashboard.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/theme.css'); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/print.css'); ?>">
<script defer src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/print-manager.js'); ?>"></script>

<style>
:root { --farm-primary: <?php echo htmlspecialchars($tenantPrimaryColor, ENT_QUOTES, 'UTF-8'); ?>; --bs-primary: var(--farm-primary); --bs-link-color: var(--farm-primary); --bs-link-hover-color: var(--farm-primary); }
.btn-primary { --bs-btn-bg: var(--farm-primary); --bs-btn-border-color: var(--farm-primary); --bs-btn-hover-bg: var(--farm-primary); --bs-btn-hover-border-color: var(--farm-primary); --bs-btn-active-bg: var(--farm-primary); --bs-btn-active-border-color: var(--farm-primary); }
.text-primary { color: var(--farm-primary) !important; }
</style>



<!-- Platform-wide notification system -->
<style>
.app-notifications {
    position: fixed;
    top: 72px;
    left: 50%;
    transform: translateX(-50%);
    width: min(1120px, calc(100% - 32px));
    z-index: 20000;
    display: flex;
    flex-direction: column;
    gap: 12px;
    pointer-events: none;
}
.app-notification {
    --notify-accent: #dc3545;
    --notify-bg: #fff1f2;
    --notify-border: #ffb4bb;
    --notify-title: #b4232f;
    display: grid;
    grid-template-columns: 64px minmax(0, 1fr) minmax(230px, 0.42fr) 36px;
    align-items: center;
    gap: 18px;
    padding: 14px 18px;
    border: 1px solid var(--notify-border);
    border-left: 6px solid var(--notify-accent);
    border-radius: 16px;
    background: linear-gradient(105deg, var(--notify-bg), #fff 72%);
    box-shadow: 0 12px 34px rgba(15, 23, 42, .14);
    color: #172033;
    pointer-events: auto;
    animation: appNotifyIn .22s ease-out;
}
.app-notification-success { --notify-accent:#198754; --notify-bg:#effaf3; --notify-border:#a9dfbd; --notify-title:#137443; }
.app-notification-warning { --notify-accent:#f59e0b; --notify-bg:#fff8e8; --notify-border:#ffd58a; --notify-title:#a86600; }
.app-notification-info { --notify-accent:#0d6efd; --notify-bg:#eef6ff; --notify-border:#9fc5ff; --notify-title:#0759bb; }
.app-notification-error { --notify-accent:#dc3545; --notify-bg:#fff1f2; --notify-border:#ffb4bb; --notify-title:#b4232f; }
.app-notification-icon {
    width: 56px; height: 56px; border-radius: 50%; display:flex; align-items:center; justify-content:center;
    background: color-mix(in srgb, var(--notify-accent) 13%, white);
    color: var(--notify-accent); font-size: 25px;
}
.app-notification-title { color: var(--notify-title); font-weight: 800; font-size: 1rem; margin-bottom: 3px; }
.app-notification-message { font-size: .96rem; line-height: 1.45; color:#263247; overflow-wrap:anywhere; }
.app-notification-tip { border-left: 1px solid rgba(0,0,0,.10); padding-left: 18px; font-size: .84rem; line-height:1.35; color:#4b5565; }
.app-notification-tip-label { color: var(--notify-title); font-weight:800; font-size:.9rem; margin-bottom:3px; }
.app-notification-close { border:0; background:transparent; color:var(--notify-title); font-size:1.05rem; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
.app-notification-close:hover { background: rgba(0,0,0,.06); }
.app-notification.is-closing { animation: appNotifyOut .18s ease-in forwards; }
@keyframes appNotifyIn { from { opacity:0; transform:translateY(-10px) scale(.985); } to { opacity:1; transform:none; } }
@keyframes appNotifyOut { to { opacity:0; transform:translateY(-8px) scale(.985); } }
@media (max-width: 760px) {
    .app-notifications { width:calc(100% - 20px); top:10px; }
    .app-notification { grid-template-columns:44px minmax(0,1fr) 34px; gap:11px; padding:12px; }
    .app-notification-icon { width:42px; height:42px; font-size:19px; }
    .app-notification-tip { display:none; }
    .app-notification-title { font-size:.92rem; }
    .app-notification-message { font-size:.86rem; }
}
@media (prefers-reduced-motion: reduce) {
    .app-notification { animation:none; }
}
</style>
<script>
window.AppNotify = window.AppNotify || (function () {
    const meta = {
        success: { cls:'app-notification-success', icon:'bi-check-circle-fill', title:'Success', tip:'Your changes have been saved successfully.' },
        warning: { cls:'app-notification-warning', icon:'bi-exclamation-triangle-fill', title:'Warning', tip:'Please review this before continuing.' },
        info: { cls:'app-notification-info', icon:'bi-info-circle-fill', title:'Information', tip:'Review the information above before continuing.' },
        danger: { cls:'app-notification-error', icon:'bi-exclamation-triangle-fill', title:'Action could not be completed', tip:'Please check the information entered and try again.' },
        error: { cls:'app-notification-error', icon:'bi-exclamation-triangle-fill', title:'Action could not be completed', tip:'Please check the information entered and try again.' }
    };
    const close = (el) => { if (!el) return; el.classList.add('is-closing'); setTimeout(() => el.remove(), 190); };
    const add = (type, message, title, tip) => {
        const m = meta[type] || meta.info;
        const wrap = document.getElementById('appNotifications');
        if (!wrap) return;
        const el = document.createElement('div');
        el.className = `app-notification ${m.cls}${type !== 'danger' && type !== 'error' ? ' app-notification-auto' : ''}`;
        el.setAttribute('role','alert');
        el.innerHTML = `<div class="app-notification-icon" aria-hidden="true"><i class="bi ${m.icon}"></i></div>
            <div class="app-notification-content"><div class="app-notification-title"></div><div class="app-notification-message"></div></div>
            <div class="app-notification-tip"><div class="app-notification-tip-label"></div><div class="app-notification-tip-text"></div></div>
            <button type="button" class="app-notification-close" data-notification-close aria-label="Dismiss notification"><i class="bi bi-x-lg"></i></button>`;
        if (!title && message) {
            const parts = message.match(/^(.+?[.!?])(?:\s+|$)(.*)$/s);
            if (parts && parts[1].length <= 96) {
                title = parts[1];
                message = parts[2] || (type === 'success' ? 'The requested action was completed successfully.' : (type === 'error' || type === 'danger' ? 'Please check the information entered and try again.' : 'Please review the information above.'));
            }
        }
        el.querySelector('.app-notification-title').textContent = title || m.title;
        el.querySelector('.app-notification-message').textContent = message || '';
        el.querySelector('.app-notification-tip-label').textContent = type === 'success' ? 'Done' : (type === 'warning' || type === 'info' ? 'Action' : 'Tip');
        el.querySelector('.app-notification-tip-text').textContent = tip || m.tip;
        wrap.appendChild(el);
        const timeout = type === 'success' ? 2500 : (type === 'info' ? 4500 : (type === 'warning' ? 5000 : 0));
        if (timeout > 0) setTimeout(() => close(el), timeout);
        return el;
    };
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-notification-close]');
        if (btn) close(btn.closest('.app-notification'));
    });
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.app-notification-auto').forEach(el => {
            const timeout = el.classList.contains('app-notification-success') ? 2500 : (el.classList.contains('app-notification-info') ? 4500 : 5000);
            setTimeout(() => close(el), timeout);
        });
    });
    return { show:add, success:(m,t,tip)=>add('success',m,t,tip), error:(m,t,tip)=>add('error',m,t,tip), warning:(m,t,tip)=>add('warning',m,t,tip), info:(m,t,tip)=>add('info',m,t,tip) };
})();
</script>

<!-- Platform-wide confirmation dialog -->
<style>
.app-confirm-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.48);backdrop-filter:blur(3px);z-index:21000;display:flex;align-items:center;justify-content:center;padding:20px;animation:appConfirmFade .16s ease-out}
.app-confirm-card{width:min(520px,100%);background:#fff;border-radius:20px;box-shadow:0 24px 70px rgba(15,23,42,.28);overflow:hidden;border:1px solid rgba(15,23,42,.08);animation:appConfirmIn .18s ease-out}
.app-confirm-head{display:flex;align-items:center;gap:14px;padding:22px 24px 10px}
.app-confirm-icon{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff4e5;color:#d97706;font-size:22px;flex:0 0 auto}
.app-confirm-title{font-size:1.1rem;font-weight:800;color:#172033}
.app-confirm-body{padding:8px 24px 22px;color:#4b5565;line-height:1.5;font-size:.96rem}
.app-confirm-actions{display:flex;justify-content:flex-end;gap:10px;padding:16px 24px;background:#f8fafc;border-top:1px solid #e9eef5}
.app-confirm-btn{border-radius:10px;padding:9px 18px;font-weight:700;border:1px solid transparent;cursor:pointer}
.app-confirm-cancel{background:#fff;border-color:#cbd5e1;color:#334155}
.app-confirm-danger{background:#dc3545;color:#fff}
.app-confirm-primary{background:var(--farm-primary,#198754);color:#fff}
.app-confirm-btn:focus{outline:3px solid rgba(13,110,253,.22);outline-offset:2px}
@keyframes appConfirmFade{from{opacity:0}to{opacity:1}}
@keyframes appConfirmIn{from{opacity:0;transform:translateY(8px) scale(.985)}to{opacity:1;transform:none}}
@media(max-width:520px){.app-confirm-card{border-radius:16px}.app-confirm-head{padding:18px 18px 8px}.app-confirm-body{padding:8px 18px 18px}.app-confirm-actions{padding:14px 18px}.app-confirm-btn{flex:1}}
</style>
<script>
window.AppConfirm = window.AppConfirm || (function(){
    let active = null;
    function esc(s){ return String(s ?? '').replace(/[&<>\"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[c])); }
    function ask(message, opts={}){
        if(active){ active.resolve(false); active.el.remove(); active=null; }
        const title=opts.title || 'Please confirm';
        const confirmText=opts.confirmText || 'Continue';
        const danger=opts.danger !== false;
        const el=document.createElement('div');
        el.className='app-confirm-backdrop';
        el.innerHTML=`<div class="app-confirm-card" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle">
          <div class="app-confirm-head"><div class="app-confirm-icon"><i class="bi bi-exclamation-triangle-fill"></i></div><div class="app-confirm-title" id="appConfirmTitle">${esc(title)}</div></div>
          <div class="app-confirm-body">${esc(message)}</div>
          <div class="app-confirm-actions"><button type="button" class="app-confirm-btn app-confirm-cancel" data-confirm-cancel>Cancel</button><button type="button" class="app-confirm-btn ${danger?'app-confirm-danger':'app-confirm-primary'}" data-confirm-ok>${esc(confirmText)}</button></div>
        </div>`;
        document.body.appendChild(el);
        const ok=el.querySelector('[data-confirm-ok]'), cancel=el.querySelector('[data-confirm-cancel]');
        const finish=value=>{ if(!active)return; const r=active.resolve; active=null; el.remove(); r(value); };
        active={el,resolve:()=>{}};
        const promise=new Promise(resolve=>{active.resolve=resolve;});
        ok.addEventListener('click',()=>finish(true)); cancel.addEventListener('click',()=>finish(false));
        el.addEventListener('click',e=>{if(e.target===el)finish(false)});
        const key=e=>{if(e.key==='Escape'){finish(false);document.removeEventListener('keydown',key)}}; document.addEventListener('keydown',key);
        setTimeout(()=>ok.focus(),20);
        return promise;
    }
    async function submit(form, message, opts={}){
        const ok=await ask(message, opts);
        if(ok){
            form.dataset.confirmed='1';
            // HTMLFormElement.submit() does not include the clicked submit button
            // in the request. Many platform POST handlers intentionally use
            // button names such as delete_transaction/delete_record to select
            // the action. Preserve that submitter before performing the native
            // submission so confirmation cannot silently turn a delete into a
            // no-op.
            const submitter = opts.submitter;
            let hidden = null;
            if (submitter && submitter.name) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = submitter.name;
                hidden.value = submitter.value || '';
                form.appendChild(hidden);
            }
            form.submit();
        }
        return false;
    }
    document.addEventListener('submit', function(e){
        const form=e.target.closest('form[data-confirm]');
        if(!form || form.dataset.confirmed==='1') return;
        e.preventDefault();
        submit(form, form.getAttribute('data-confirm'), {
            title:form.getAttribute('data-confirm-title')||'Please confirm',
            confirmText:form.getAttribute('data-confirm-button')||'Confirm',
            danger:form.getAttribute('data-confirm-danger')!=='false',
            submitter:e.submitter || null
        });
    }, true);
    return {ask,submit};
})();
</script>

<!-- Lightweight JS debug helper (shows runtime errors when ?debug=1 or localStorage app-debug=1) -->
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/debug.js'); ?>" defer></script>

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">

