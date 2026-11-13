const vm=require('vm'),fs=require('fs'),crypto=require('crypto');
const source=fs.readFileSync('email/Bridge.gs','utf8'),properties=new Map([['BRIDGE_SECRET','a'.repeat(64)]]),cache=new Map();
let sent=0,quota=100;
const bytes=b=>Array.from(b).map(n=>n>127?n-256:n);
const context={
 Utilities:{Charset:{UTF_8:'UTF-8'},DigestAlgorithm:{SHA_256:'sha256'},computeHmacSha256Signature:(v,k)=>bytes(crypto.createHmac('sha256',k).update(v).digest()),computeDigest:(a,v)=>bytes(crypto.createHash('sha256').update(v).digest()),base64Encode:v=>Buffer.from(v).toString('base64'),base64Decode:v=>bytes(Buffer.from(v,'base64')),newBlob:v=>({getDataAsString:()=>Buffer.from(v.map(n=>(n+256)%256)).toString('utf8')})},
 PropertiesService:{getScriptProperties:()=>({getProperty:k=>properties.get(k)||null,setProperty:(k,v)=>properties.set(k,v)})},
 CacheService:{getScriptCache:()=>({get:k=>cache.get(k),put:(k,v)=>cache.set(k,v)})},
 Session:{getEffectiveUser:()=>({getEmail:()=> 'sender@example.invalid'})},
 LockService:{getScriptLock:()=>({waitLock(){},releaseLock(){}})},
 MailApp:{getRemainingDailyQuota:()=>quota,sendEmail:p=>{if(!p.to)throw Error('missing');sent++;}},
 ContentService:{MimeType:{JSON:'json'},createTextOutput:text=>({setMimeType(){return this;},text})},console,Date
};
vm.createContext(context);vm.runInContext(source,context);
function check(v,m){if(!v)throw Error(m);}
function call(payload,signature){
 const data=Buffer.from(JSON.stringify({...payload,nonce:crypto.randomBytes(16).toString('hex'),timestamp:Date.now()/1000,sender:'sender@example.invalid'})).toString('base64');
 const out=context.doPost({postData:{contents:JSON.stringify({data,signature:signature||crypto.createHmac('sha256','a'.repeat(64)).update(data).digest('hex')})}});
 const env=JSON.parse(out.text);check(crypto.createHmac('sha256','a'.repeat(64)).update(env.data).digest('hex')===env.signature,'Risposta non firmata');
 return JSON.parse(Buffer.from(env.data,'base64').toString());
}
check(call({op:'ping'}).quota===100&&sent===0,'Ping invia email');
const job={op:'send',job:'1'.repeat(32),to:'test@example.invalid',subject:'Prova',text:'Testo',html:'<p>Testo</p>',name:'Gestione'};
check(call(job).status==='sent'&&sent===1,'Invio singolo');
check(call(job).status==='sent'&&sent===1,'Reinvio duplicato');
check(!call({...job,text:'Altro'}).ok&&sent===1,'Job riciclato');
check(!call(job,'0'.repeat(64)).ok&&sent===1,'Firma invalida ammessa');
quota=0;check(call({...job,job:'2'.repeat(32)}).status==='not_sent'&&sent===1,'Quota non rispettata');
quota=100;context.MailApp.sendEmail=()=>{sent++;throw Error('Esito incerto');};
const uncertain={...job,job:'3'.repeat(32)};
check(call(uncertain).status==='uncertain','Esito non incerto');
check(call(uncertain).status==='uncertain'&&sent===2,'Invio incerto ripetuto');
console.log('OK: ponte Apps Script simulato, autenticazione, quota, ping senza invio e protezione duplicati.');
