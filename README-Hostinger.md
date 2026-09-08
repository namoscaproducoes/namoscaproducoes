# Rádio Social Plus Brasil — pacote Hostinger para socialplusbraisl.com.br

1. Envie todo o conteúdo desta pasta para a pasta apontada pelo domínio `https://socialplusbraisl.com.br/`.
2. Garanta que o PHP tenha `pdo_sqlite` habilitado e que a pasta `data/` tenha permissão de escrita (755 ou 775).
3. Edite `config.php`: informe sua chave da Last.fm em `LASTFM_API_KEY` (opcional) e troque `ADMIN_PASSWORD` antes de publicar.
4. O painel fica em `/admin.php`; ele exibe votos e oferece download dos usuários em CSV, que abre no Excel.
5. A página tenta ler metadados Icecast/Shoutcast do stream. Se a Brascast usar outro endpoint, ajuste as URLs em `api/metadata.php`.
6. O rodapé consulta o feed público do WordPress em `/wp-json/wp/v2/posts` para exibir os 5 posts mais recentes.

O cadastro, login, votos e atividade são persistidos em SQLite. Para sincronizar usuários com WordPress ou proteger o painel com usuários WordPress, será necessário adaptar os endpoints ao ambiente do site.
