from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
start=s.find('\n/* Publicidade fixa:')
if start >= 0:
    s=s[:start]+'\n'
p.write_text(s)
print('estilos de anúncios removidos')
