<?php require_once __DIR__.'/bootstrap.php';
$artist='Rádio Social Plus Brasil';$title='A sua rádio, a qualquer hora';$art='assets/logo.png';
// Muitos streams Icecast/Shoutcast oferecem metadados em status-json.xsl; ajuste se a Brascast fornecer outro endpoint.
$urls=['https://s01.brascast.com:7034/status-json.xsl','https://s01.brascast.com:7034/stats?sid=1&json=1'];foreach($urls as $url){$c=@file_get_contents($url);if(!$c)continue;$j=json_decode($c,true);$src=$j['icestats']['source']??$j['source']??null;if(is_array($src)&&isset($src[0]))$src=$src[0];$song=$src['title']??$src['streamtitle']??'';if($song){$parts=preg_split('/\s+-\s+/',$song,2);if(count($parts)>1){$artist=trim($parts[0]);$title=trim($parts[1]);}else$title=trim($song);break;}}
if(LASTFM_API_KEY && $artist!== 'Rádio Social Plus Brasil'){$u='https://ws.audioscrobbler.com/2.0/?method=track.getinfo&api_key='.urlencode(LASTFM_API_KEY).'&artist='.urlencode($artist).'&track='.urlencode($title).'&format=json';$j=json_decode(@file_get_contents($u),true);$images=$j['track']['album']['image']??[];if($images)$art=end($images)['#text']?:$art;}
$st=$db->prepare("SELECT COUNT(*) FROM votes WHERE artist=? AND title=? AND vote='like'");$st->execute([$artist,$title]);json_out(['artist'=>$artist,'title'=>$title,'art'=>$art,'votes'=>(int)$st->fetchColumn()]);
?>
