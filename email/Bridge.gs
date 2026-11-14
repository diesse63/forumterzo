/*
 * Ponte Gestione email — progetto Apps Script SEPARATO.
 * Proprietà script: BRIDGE_SECRET (64 caratteri hex).
 * Pubblicare come App web, Esegui come Me, accesso Chiunque.
 * Nessuna rubrica o tabella del gestionale viene scritta su Google Sheets.
 */
function bridgeHex_(bytes) { return bytes.map(function(b){return ('0'+((b+256)%256).toString(16)).slice(-2);}).join(''); }
function bridgeSign_(data,key) { return bridgeHex_(Utilities.computeHmacSha256Signature(data,key,Utilities.Charset.UTF_8)); }
function bridgeEqual_(a,b) { if(typeof a!=='string'||typeof b!=='string'||a.length!==b.length)return false;var v=0;for(var i=0;i<a.length;i++)v|=a.charCodeAt(i)^b.charCodeAt(i);return v===0; }
function bridgeOutput_(result,secret) {
 var data=Utilities.base64Encode(JSON.stringify(result),Utilities.Charset.UTF_8);
 return ContentService.createTextOutput(JSON.stringify({data:data,signature:bridgeSign_(data,secret)})).setMimeType(ContentService.MimeType.JSON);
}
function bridgeFingerprint_(secret) { return bridgeHex_(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256,secret,Utilities.Charset.UTF_8)).slice(0,16); }
function autorizzaPonte() {
 var secret=PropertiesService.getScriptProperties().getProperty('BRIDGE_SECRET')||'';
 if(!/^[a-f0-9]{64}$/.test(secret))throw new Error('BRIDGE_SECRET mancante o non valida: occorrono 64 caratteri esadecimali minuscoli.');
 console.log('Impronta credenziale (non è il segreto): '+bridgeFingerprint_(secret));
 var owner=Session.getEffectiveUser().getEmail();
 if(!owner)throw new Error('Account Google non identificabile.');
 console.log('Account: '+owner+'; quota destinatari: '+MailApp.getRemainingDailyQuota());
}
function doGet() { return ContentService.createTextOutput('Ponte Gestione email: richieste POST firmate richieste.'); }
function doPost(e) {
 var props=PropertiesService.getScriptProperties(),secret=props.getProperty('BRIDGE_SECRET')||'',nonce='',sender='';
 if(!/^[a-f0-9]{64}$/.test(secret))return ContentService.createTextOutput('Ponte non configurato.');
 try {
  if(!e||!e.postData||e.postData.contents.length>250000)throw new Error('Richiesta non valida.');
  var env=JSON.parse(e.postData.contents);
  if(typeof env.data!=='string'||!bridgeEqual_(bridgeSign_(env.data,secret),env.signature))throw new Error('Firma non valida.');
  var p=JSON.parse(Utilities.newBlob(Utilities.base64Decode(env.data)).getDataAsString('UTF-8'));
  nonce=String(p.nonce||'');
  if(!/^[a-f0-9]{32}$/.test(nonce)||Math.abs(Date.now()/1000-Number(p.timestamp))>300)throw new Error('Richiesta scaduta.');
  sender=Session.getEffectiveUser().getEmail().toLowerCase();
  if(!sender||String(p.sender||'').toLowerCase()!==sender)throw new Error('Mittente diverso dall’account proprietario.');
  var lock=LockService.getScriptLock();lock.waitLock(25000);
  try {
   var cache=CacheService.getScriptCache();
   if(cache.get('nonce_'+nonce))throw new Error('Richiesta già ricevuta.');
   cache.put('nonce_'+nonce,'1',600);
   var result={ok:true,nonce:nonce,sender:sender,quota:MailApp.getRemainingDailyQuota()};
   if(p.op==='ping')return bridgeOutput_(result,secret);
   if(!/^[a-f0-9]{32}$/.test(String(p.job||'')))throw new Error('Identificativo invio non valido.');
   var key='job_'+p.job,saved=props.getProperty(key),job=saved?JSON.parse(saved):null;
   if(p.op==='status'){result.status=job?job.status:'unknown';return bridgeOutput_(result,secret);}
   if(p.op!=='send')throw new Error('Operazione non consentita.');
   if(!/^[^\s@,;<>]+@[^\s@,;<>]+\.[^\s@,;<>]+$/.test(String(p.to||''))||String(p.subject||'').length>300||/[\r\n]/.test(String(p.subject||''))||!String(p.text||'').trim()||String(p.html||'').length>150000)throw new Error('Contenuto email non valido.');
   var hash=bridgeHex_(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256,JSON.stringify([p.to,p.subject,p.text,p.html,p.name]),Utilities.Charset.UTF_8));
   if(job){
    if(job.hash!==hash)throw new Error('Identificativo riutilizzato con contenuto diverso.');
    result.status=job.status;return bridgeOutput_(result,secret);
   }
   if(result.quota<1){result.status='not_sent';return bridgeOutput_(result,secret);}
   // La marcatura precedente all'invio impedisce reinvii dopo timeout o errori.
   props.setProperty(key,JSON.stringify({status:'uncertain',hash:hash,date:new Date().toISOString()}));
   try {
    MailApp.sendEmail({to:String(p.to),subject:String(p.subject),body:String(p.text),htmlBody:String(p.html),name:String(p.name||'Gestione')});
    props.setProperty(key,JSON.stringify({status:'sent',hash:hash,date:new Date().toISOString()}));
    result.status='sent';result.quota=MailApp.getRemainingDailyQuota();
   } catch(error) { result.status='uncertain'; }
   return bridgeOutput_(result,secret);
  } finally { lock.releaseLock(); }
 } catch(error) {
  return bridgeOutput_({ok:false,nonce:nonce,sender:sender,error:String(error.message||'Richiesta rifiutata').slice(0,180)},secret);
 }
}
