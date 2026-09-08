from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
start=s.index('.grain{')
end=s.index('}', start)+1
s=s[:start]+'.grain{position:fixed;inset:0;pointer-events:none;opacity:.045;background:#fff;z-index:4}'+s[end:]
p.write_text(s)
print('CSS atualizado:',len(s),'bytes')
