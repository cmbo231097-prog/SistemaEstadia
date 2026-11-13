.input-wrap.has-toggle input, .input-ico.has-toggle input { padding-right:44px; }
.toggle-pass {
    position:absolute; right:6px; top:50%; transform:translateY(-50%);
    background:none; border:0; border-radius:6px; padding:8px; cursor:pointer;
    color:#64748b; display:grid; place-items:center;
}
.toggle-pass:hover { color:#1e293b; background:rgba(100, 116, 139, .12); }
.toggle-pass:focus-visible { outline:2px solid #2563eb; outline-offset:1px; }
.toggle-pass svg { width:18px; height:18px; }
.toggle-pass .ico-hide { display:none; }
.toggle-pass[aria-pressed="true"] .ico-show { display:none; }
.toggle-pass[aria-pressed="true"] .ico-hide { display:block; }
