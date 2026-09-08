from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
s += '''\n.footer-heading img{width:190px;filter:invert(1);display:block;margin-bottom:28px}.footer-heading h3{margin:0;font-size:21px}.posts{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;align-items:stretch}.post{font-size:13px;border:1px solid #46443f;padding:0;max-width:none;background:#292825;display:flex;flex-direction:column;min-width:0}.post>img{width:100%;height:104px;object-fit:cover;display:block}.post-body{padding:13px;display:flex;flex-direction:column;gap:8px;flex:1}.post small{color:#999;display:block;margin:0;font:10px 'DM Mono'}.post b{font-size:13px;line-height:1.25;color:#fff;font-weight:500;display:block}.post a{color:var(--orange);text-decoration:none;font-size:11px;font-weight:700;margin-top:auto;padding-top:5px}.post a:hover{text-decoration:underline}@media(max-width:1000px){.posts{grid-template-columns:repeat(3,1fr)}}@media(max-width:600px){.posts{grid-template-columns:repeat(2,1fr)}.post>img{height:90px}}\n'''
p.write_text(s)
print('posts CSS appended')
