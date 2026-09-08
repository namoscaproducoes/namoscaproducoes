from pathlib import Path
p=Path('api/index.php')
s=p.read_text()
old="if($action==='top'){$rows=$db->query(\"SELECT artist,title,SUM(CASE WHEN vote='like' THEN 1 ELSE -1 END) score,COUNT(*) votes FROM votes GROUP BY artist,title ORDER BY score DESC,votes DESC LIMIT 10\")->fetchAll(PDO::FETCH_ASSOC);if(!$rows)$rows=[['artist'=>'Rádio Social Plus Brasil','title'=>'Aguardando seus votos','score'=>0,'votes'=>0]];json_out(['items'=>$rows]);}"
new="""if($action==='top'){$rows=$db->query(\"SELECT artist,title,SUM(CASE WHEN vote='like' THEN 1 ELSE -1 END) score,COUNT(*) votes FROM votes GROUP BY artist,title ORDER BY score DESC,votes DESC LIMIT 10\")->fetchAll(PDO::FETCH_ASSOC);if(!$rows)$rows=[['artist'=>'Rádio Social Plus Brasil','title'=>'Aguardando seus votos','score'=>0,'votes'=>0]];foreach($rows as &$row){$row['art']='assets/logo.png';if(LASTFM_API_KEY){$u='https://ws.audioscrobbler.com/2.0/?method=track.getinfo&api_key='.urlencode(LASTFM_API_KEY).'&artist='.urlencode($row['artist']).'&track='.urlencode($row['title']).'&format=json';$j=json_decode(@file_get_contents($u),true);$images=$j['track']['album']['image']??[];if($images){$last=end($images);if(!empty($last['#text']))$row['art']=$last['#text'];}}else{$u='https://itunes.apple.com/search?term='.urlencode($row['artist'].' '.$row['title']).'&entity=song&limit=1';$j=json_decode(@file_get_contents($u),true);$row['art']=$j['results'][0]['artworkUrl100']??$row['art'];}}unset($row);json_out(['items'=>$rows]);}"""
if old not in s: raise SystemExit('top endpoint not found')
p.write_text(s.replace(old,new,1))

p=Path('assets/app.js')
s=p.read_text()
old="function renderTop(items){$('#topList').innerHTML=items.map((x,i)=>`<div class=\"top-item\"><span class=\"rank\">0${i+1}</span><div class=\"song\"><b>${esc(x.title)}</b><span>${esc(x.artist)}</span></div><div class=\"score\"><strong>${x.score}</strong><small>pontos</small></div></div>`).join('')}"
new="function renderTop(items){$('#topList').innerHTML=items.map((x,i)=>`<div class=\"top-item\"><span class=\"rank\">${String(i+1).padStart(2,'0')}</span><img class=\"top-art\" src=\"${esc(x.art||'assets/logo.png')}\" alt=\"Capa de ${esc(x.title)}\" loading=\"lazy\"><div class=\"song\"><b>${esc(x.title)}</b><span>${esc(x.artist)}</span></div><div class=\"score\"><strong>${x.score}</strong><small>pontos</small></div></div>`).join('')}"
if old not in s: raise SystemExit('renderTop not found')
p.write_text(s.replace(old,new,1))

p=Path('assets/style.css')
s=p.read_text()+'''\n.top-item .top-art{width:52px;height:52px;flex:0 0 52px;object-fit:cover;background:#2f2e2b;border-radius:2px;display:block}.top-item .song{min-width:0}.top-item .song b,.top-item .song span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}@media(max-width:560px){.top-item{gap:10px}.top-item .top-art{width:44px;height:44px;flex-basis:44px}.top-item .score{min-width:38px}.top-item .song b,.top-item .song span{white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}}\n'''
p.write_text(s)
print('capas do top 10 adicionadas')
