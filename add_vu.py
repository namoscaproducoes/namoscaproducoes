from pathlib import Path
p=Path('assets/app.js')
s=p.read_text()
s += r'''
// V.U. visual: usa o áudio real quando o streaming permite CORS; caso contrário mantém ritmo visual.
let vuContext=null,vuAnalyser=null,vuSource=null,vuFrame=null;
function animateFallback(){const meter=document.querySelector('#vuMeter');if(!meter)return;meter.classList.add('active');const bars=meter.querySelectorAll('i');bars.forEach((bar,i)=>{bar.style.setProperty('--vu-delay',`${(i%5)*.06}s`);bar.style.setProperty('--vu-height',`${22+Math.random()*68}%`)})}
function animateReal(){if(!vuAnalyser)return;const data=new Uint8Array(vuAnalyser.frequencyBinCount),bars=document.querySelectorAll('#vuMeter i');vuAnalyser.getByteFrequencyData(data);bars.forEach((bar,i)=>{const index=Math.floor(i*data.length/bars.length);bar.style.height=`${Math.max(10,Math.min(100,data[index]/2.55))}%`});vuFrame=requestAnimationFrame(animateReal)}
function startVU(){const meter=document.querySelector('#vuMeter');if(!meter)return;meter.classList.add('active');animateFallback();try{if(!vuContext){vuContext=new (window.AudioContext||window.webkitAudioContext)();vuAnalyser=vuContext.createAnalyser();vuAnalyser.fftSize=64;vuSource=vuContext.createMediaElementSource(radio);vuSource.connect(vuAnalyser);vuAnalyser.connect(vuContext.destination)}if(vuContext.state==='suspended')vuContext.resume();cancelAnimationFrame(vuFrame);animateReal()}catch(e){/* streaming sem CORS: fallback visual permanece ativo */}}
radio.addEventListener('play',startVU);radio.addEventListener('pause',()=>{document.querySelector('#vuMeter')?.classList.remove('active');cancelAnimationFrame(vuFrame)});
'''
p.write_text(s)
print('V.U. adicionado')
