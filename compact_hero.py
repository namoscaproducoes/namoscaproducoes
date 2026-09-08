from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
s += '''\n/* Hero mais compacto: texto no topo e votação mais próxima */
.hero{padding:38px 0 48px;align-items:start}
.hero-copy{padding-top:8px}
.hero-sub{margin-top:0;margin-bottom:0}
.hero-meta{margin-top:28px}
.content-grid{padding-bottom:64px}
.top-section{margin-top:52px}
@media(max-width:850px){.hero{padding:32px 0 52px;align-items:start}.hero-copy{padding-top:0}.content-grid{padding-bottom:54px}}
@media(max-width:560px){.hero{padding:28px 0 42px}.hero-meta{margin-top:24px}.top-section{margin-top:42px}}
'''
p.write_text(s)
print('hero compactado')
