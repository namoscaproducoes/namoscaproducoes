from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
s += '''\n.content-ad{width:min(1180px,calc(100% - 48px));min-height:90px;margin:0 auto 56px;padding:10px 0;text-align:center;overflow:hidden}.content-ad .adsbygoogle{min-height:90px}@media(max-width:560px){.content-ad{width:calc(100% - 32px);min-height:70px;margin-bottom:36px}.content-ad .adsbygoogle{min-height:70px}}\n'''
p.write_text(s)
print('estilo do anúncio de conteúdo adicionado')
