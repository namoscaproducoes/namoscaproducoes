from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
s += '''\n/* Correção final da grade de conteúdos recentes */
.footer-top{grid-template-columns:220px minmax(0,1fr);gap:34px}
.footer-heading{min-width:0}
.posts{width:100%;min-width:0;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
.post{width:100%;min-width:0;overflow:hidden}
.post>img,.post-cover-placeholder{width:100%;height:118px;object-fit:cover}
.post-body{min-width:0;padding:14px;gap:9px}
.post-body b{font-size:13px;line-height:1.3;overflow-wrap:anywhere;word-break:normal}
.post-body a{white-space:nowrap}
@media(max-width:1000px){.footer-top{grid-template-columns:190px minmax(0,1fr)}.posts{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:650px){.footer-top{grid-template-columns:1fr}.posts{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:390px){.posts{grid-template-columns:1fr}}
'''
p.write_text(s)
print('layout dos posts corrigido')
