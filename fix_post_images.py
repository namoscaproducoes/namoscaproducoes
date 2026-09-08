from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
s += '\n/* As capas dos posts devem manter suas cores originais; somente o logo usa inversão. */\n.post>img{filter:none!important;mix-blend-mode:normal}\n'
p.write_text(s)
print('filtro das capas removido')
