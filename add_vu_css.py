from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
s += '''\n.vu-meter{position:absolute;left:18px;right:18px;bottom:18px;height:72px;display:flex;align-items:flex-end;justify-content:center;gap:4px;z-index:3;opacity:.8;filter:drop-shadow(0 2px 5px rgba(0,0,0,.7))}.vu-meter i{display:block;width:clamp(4px,1vw,9px);height:10%;min-height:5px;background:linear-gradient(180deg,#ffe09a 0%,#ffae18 45%,#e95738 100%);border-radius:2px 2px 0 0;transition:height .09s ease-out}.vu-meter.active i{animation:vuPulse .48s ease-in-out infinite alternate;animation-delay:var(--vu-delay,0s)}@keyframes vuPulse{from{height:var(--vu-height,18%)}to{height:calc(var(--vu-height,58%) + 18%)}}\n'''
p.write_text(s)
print('CSS do V.U. adicionado')
