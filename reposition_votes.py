from pathlib import Path
p=Path('index.php')
s=p.read_text()
old='<div class="hero-meta"><span>◉  AO VIVO 24H</span><span>✦  BRASIL</span></div></div>'
new='''<div class="hero-meta"><span>◉  AO VIVO 24H</span><span>✦  BRASIL</span></div><section class="vote-section vote-hero"><div class="section-head"><div><p class="eyebrow">A comunidade decide</p><h2>O que você achou<br>dessa música?</h2></div><div class="vote-count"><strong id="voteCount">0</strong><span>votos na faixa atual</span></div></div><div class="vote-actions"><button class="vote-btn like" data-vote="like"><span>♡</span><div><b>Gostei!</b><small>Quero ouvir de novo</small></div></button><button class="vote-btn dislike" data-vote="dislike"><span>♧</span><div><b>Não curti</b><small>Ajude a gente a escolher</small></div></button></div></section></div>'''
if old not in s: raise SystemExit('hero anchor not found')
s=s.replace(old,new,1)
start='<section class="content-grid container"><div class="main-col"><section class="vote-section"><div class="section-head"><div><p class="eyebrow">A comunidade decide</p><h2>O que você achou<br>dessa música?</h2></div><div class="vote-count"><strong id="voteCount">0</strong><span>votos na faixa atual</span></div></div><div class="vote-actions"><button class="vote-btn like" data-vote="like"><span>♡</span><div><b>Gostei!</b><small>Quero ouvir de novo</small></div></button><button class="vote-btn dislike" data-vote="dislike"><span>♧</span><div><b>Não curti</b><small>Ajude a gente a escolher</small></div></div></section><section class="top-section">'
# Actual source has closing tags in one long line; remove only the first vote section through its opening top section.
if start not in s:
    a=s.find('<section class="content-grid container"><div class="main-col"><section class="vote-section">')
    b=s.find('<section class="top-section">',a)
    if a<0 or b<0: raise SystemExit('vote block not found')
    s=s[:a]+'<section class="content-grid container"><div class="main-col">'+s[b:]
else:
    s=s.replace(start,'<section class="content-grid container"><div class="main-col"><section class="top-section">',1)
s=s.replace('AS TOP 5 <em>MAIS VOTADAS</em>','AS TOP 10 <em>MAIS VOTADAS</em>')
s=s.replace('assets/style.css?v=compact1','assets/style.css?v=top10')
p.write_text(s)

p=Path('api/index.php')
s=p.read_text().replace('ORDER BY score DESC,votes DESC LIMIT 5','ORDER BY score DESC,votes DESC LIMIT 10')
p.write_text(s)

p=Path('assets/style.css')
s=p.read_text()+'''\n/* Votação compacta ao lado do player e ranking top 10 */
.vote-hero{margin-top:38px;max-width:560px}
.vote-hero .section-head{align-items:flex-end}
.vote-hero .section-head h2{font-size:27px;letter-spacing:-.8px;margin-bottom:14px}
.vote-hero .vote-count strong{font-size:27px}
.vote-hero .vote-actions{gap:8px}
.vote-hero .vote-btn{padding:11px 12px;gap:9px}
.vote-hero .vote-btn>span{font-size:24px}
.vote-hero .vote-btn small{font-size:11px}
.top-section{margin-top:32px}
@media(max-width:850px){.vote-hero{max-width:none;margin-top:32px}.vote-hero .section-head h2{font-size:30px}}
@media(max-width:560px){.vote-hero{margin-top:28px}.vote-hero .vote-actions{grid-template-columns:1fr}.vote-hero .section-head h2{font-size:28px}}
'''
p.write_text(s)
print('votação reposicionada e top 10 configurado')
