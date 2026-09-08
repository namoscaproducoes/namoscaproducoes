from pathlib import Path
p=Path('assets/style.css')
s=p.read_text()
s += '''\n/* Cores semânticas para os botões de votação */
.vote-btn.like{background:#dff3e5;border-color:#79bd8d;color:#174d2a}
.vote-btn.like>span{color:#238044}
.vote-btn.like:hover,.vote-btn.like.active{background:#bfe8c9;border-color:#238044}
.vote-btn.dislike{background:#fde1df;border-color:#e2a09a;color:#6f211c}
.vote-btn.dislike>span{color:#bd3d32}
.vote-btn.dislike:hover,.vote-btn.dislike.active{background:#f7c5c0;border-color:#bd3d32}
.vote-btn.like small,.vote-btn.dislike small{color:currentColor;opacity:.72}
'''
p.write_text(s)
print('cores dos botões de votação adicionadas')
