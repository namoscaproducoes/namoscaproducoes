from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
s += '\n.post-cover-placeholder{height:104px;background:linear-gradient(135deg,#ffae18,#d97808);display:flex;align-items:center;justify-content:center;text-align:center;color:#171717;font:700 14px Outfit;letter-spacing:1px}@media(max-width:600px){.post-cover-placeholder{height:90px}}\n'
p.write_text(s)
