from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
s += '''\n/* Menu fixo com faixa atual */
.topbar{position:sticky;top:0;z-index:50;backdrop-filter:blur(12px);box-shadow:0 3px 18px rgba(20,20,20,.06)}
.live-pill{display:flex;align-items:center;gap:8px;white-space:nowrap}
.live-pill b{display:inline-block;max-width:280px;overflow:hidden;text-overflow:ellipsis;color:var(--muted);font:10px 'DM Mono';letter-spacing:0;margin-left:4px}
@media(max-width:850px){.live-pill b{max-width:170px}}
@media(max-width:560px){.live-pill{font-size:9px}.live-pill b{max-width:120px}.topbar{height:76px}.brand img{width:145px}}
'''
p.write_text(s)
print('menu fixo atualizado')
