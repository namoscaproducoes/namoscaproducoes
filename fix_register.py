from pathlib import Path
for name in ['register.php', 'admin.php']:
    p = Path(name)
    s = p.read_text()
    s = s.replace('href="assets/style.css"', 'href="assets/style.css?v=3af6cad"')
    p.write_text(s)
p = Path('assets/register.js')
p.write_text("""const form=document.querySelector('#registerForm');
form.onsubmit=async e=>{e.preventDefault();const msg=document.querySelector('#registerMessage');msg.style.color='#766f63';msg.textContent='Enviando cadastro…';try{const r=await fetch('api/index.php?action=register',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.fromEntries(new FormData(form)))});const data=await r.json();if(!r.ok||data.error)throw new Error(data.error||'Não foi possível concluir o cadastro.');msg.style.color='#3d8750';msg.textContent='Cadastro realizado! Você já pode fazer login.';form.reset()}catch(err){msg.style.color='#d34d31';msg.textContent=err.message||'Erro de conexão. Tente novamente.'}};
""")
print('Cadastro atualizado')
