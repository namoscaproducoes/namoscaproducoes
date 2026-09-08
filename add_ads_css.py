from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
s += '''\n/* Publicidade fixa: não bloqueia o conteúdo principal e some em telas pequenas. */
.ad-lateral{position:fixed;right:12px;top:50%;transform:translateY(-50%);width:150px;min-height:120px;background:rgba(255,255,255,.82);z-index:30;padding:8px;box-shadow:0 4px 18px rgba(0,0,0,.12)}
.ad-rodape{position:fixed;left:50%;bottom:0;transform:translateX(-50%);width:min(728px,calc(100% - 32px));min-height:50px;background:rgba(255,255,255,.96);z-index:40;padding:4px 8px;box-shadow:0 -3px 18px rgba(0,0,0,.12)}
@media(max-width:1280px){.ad-lateral{display:none}}
@media(max-width:600px){.ad-rodape{width:calc(100% - 16px);min-height:45px}}
'''
p.write_text(s)
print('CSS de anúncios adicionado')
