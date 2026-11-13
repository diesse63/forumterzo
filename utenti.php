<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$actor=require_access('utenti','scrittura');$db=poimanager_db();$message='';
$panels=$db->query('SELECT * FROM Pannelli ORDER BY Titolo')->fetchAll();
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();$id=(int)($_POST['id']??0);$name=trim((string)($_POST['nome']??''));$email=trim((string)($_POST['email']??''));
  $role=(string)($_POST['ruolo']??'');$active=isset($_POST['attivo'])?1:0;$pass=(string)($_POST['password']??'');
  if(!preg_match('/^[a-zA-Z0-9_.-]{1,100}$/D',$name)||strtolower($name)==='ghost')throw new RuntimeException('Nome non valido o riservato.');
  if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Email non valida.');
  if(!in_array($role,['admin','user','esternobase','esternoevoluto'],true))throw new RuntimeException('Ruolo non valido.');
  if(in_array($role,['esternobase','esternoevoluto'],true))$active=0;
  if(!$id||$pass!=='')password_policy($pass);
  $db->beginTransaction();
  $admins=$db->query("SELECT ID FROM Utenti WHERE Ruolo='admin' AND Attivo=1 ORDER BY ID FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
  if($id){
   $q=$db->prepare('SELECT * FROM Utenti WHERE ID=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();
   if(!$old)throw new RuntimeException('Utente non trovato.');
   if($old['Ruolo']==='admin'&&(int)$old['Attivo']===1&&($role!=='admin'||!$active)&&count($admins)<=1)throw new RuntimeException('Deve rimanere almeno un amministratore attivo.');
   $hash=$pass!==''?password_hash($pass,PASSWORD_DEFAULT):$old['PasswordHash'];
   $q=$db->prepare('UPDATE Utenti SET NomeIndice=?,ProfiloCifrato=?,PasswordHash=?,Ruolo=?,Attivo=?,VersioneSessione=VersioneSessione+1 WHERE ID=?');
   $q->execute([user_index($name),user_encrypt(['nome'=>$name,'email'=>$email]),$hash,$role,$active,$id]);
  }else{
   $q=$db->prepare('INSERT INTO Utenti (NomeIndice,ProfiloCifrato,PasswordHash,Ruolo,Attivo) VALUES (?,?,?,?,?)');
   $q->execute([user_index($name),user_encrypt(['nome'=>$name,'email'=>$email]),password_hash($pass,PASSWORD_DEFAULT),$role,$active]);$id=(int)$db->lastInsertId();
  }
  $q=$db->prepare('DELETE FROM UtentiPermessi WHERE UtenteID=?');$q->execute([$id]);
  if($role==='user'){
   $submitted=$_POST['permessi']??[];if(!is_array($submitted))throw new RuntimeException('Permessi non validi.');
   foreach($panels as $panel){
    if(in_array($panel['Codice'],['utenti','importazione'],true))continue;
    $level=$submitted[$panel['Codice']]??'nessuno';
    if(!in_array($level,['nessuno','lettura','scrittura'],true))throw new RuntimeException('Livello non valido.');
    if($level!=='nessuno'){$q=$db->prepare('INSERT INTO UtentiPermessi (UtenteID,Pannello,Livello) VALUES (?,?,?)');$q->execute([$id,$panel['Codice'],$level]);}
   }
  }
  $db->commit();header('Location: /utenti.php?salvato=1');exit;
 }
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();$message=$e instanceof PDOException?'Salvataggio non riuscito: nome già usato o errore database.':$e->getMessage();}
$selected=(int)($_GET['id']??0);$edit=null;$perms=[];
if($selected){
 $q=$db->prepare('SELECT * FROM Utenti WHERE ID=?');$q->execute([$selected]);$edit=$q->fetch();
 if($edit){$edit['Profilo']=user_decrypt($edit['ProfiloCifrato']);$q=$db->prepare('SELECT Pannello,Livello FROM UtentiPermessi WHERE UtenteID=?');$q->execute([$selected]);$perms=$q->fetchAll(PDO::FETCH_KEY_PAIR);}
}
page_start('Gestione utenti');
if($message)echo '<aside>'.uh($message).'</aside>';
if(isset($_GET['salvato']))echo '<aside>Utente e permessi salvati. Le sue sessioni precedenti sono invalidate.</aside>';
echo '<p><a href="/utenti.php">Nuovo utente</a>. Ruoli esterni sempre disattivati. Ghost è configurato fuori dal database.</p><table><tr><th>Nome</th><th>Email</th><th>Ruolo</th><th>Stato</th><th></th></tr>';
foreach($db->query('SELECT * FROM Utenti ORDER BY ID') as $row){
 $p=user_decrypt($row['ProfiloCifrato']);echo '<tr><td>'.uh($p['nome']).'</td><td>'.uh($p['email']).'</td><td>'.uh($row['Ruolo']).'</td><td>'.((int)$row['Attivo']?'Attivo':'Disattivato').'</td><td><a href="?id='.(int)$row['ID'].'">Modifica</a></td></tr>';
}
echo '</table>'; ?>
<h2><?= $edit?'Modifica utente':'Nuovo utente' ?></h2>
<form method="post">
<input type="hidden" name="csrf" value="<?=uh(csrf())?>">
<input type="hidden" name="id" value="<?=(int)($edit['ID']??0)?>">
<label>Nome di accesso<input name="nome" maxlength="100" required value="<?=uh($edit['Profilo']['nome']??'')?>"></label>
<label>Email<input type="email" name="email" value="<?=uh($edit['Profilo']['email']??'')?>"></label>
<label>Ruolo<select name="ruolo"><?php foreach(['admin','user','esternobase','esternoevoluto'] as $r)echo '<option value="'.$r.'" '.(($edit['Ruolo']??'user')===$r?'selected':'').'>'.uh($r==='esternoevoluto'?'esterno evoluto':$r).'</option>'; ?></select></label>
<label><input type="checkbox" name="attivo" <?=(!$edit||(int)$edit['Attivo'])?'checked':''?>> Attivo (ignorato per i ruoli esterni)</label>
<label><?= $edit?'Nuova password, lasciare vuota per mantenerla':'Password' ?><input type="password" name="password" autocomplete="new-password" <?= $edit?'':'required' ?>></label>
<p>Minimo 7 caratteri e uno speciale. Permessi solo per user; nessuna selezione significa nessun accesso.</p>
<table><tr><th>Schermata</th><th>Accesso</th></tr>
<?php foreach($panels as $p):if(in_array($p['Codice'],['utenti','importazione'],true))continue; ?>
<tr><td><?=uh($p['Titolo'])?></td><td><select name="permessi[<?=uh($p['Codice'])?>]"><?php foreach(['nessuno'=>'Nessuno','lettura'=>'Sola lettura','scrittura'=>'Lettura e scrittura'] as $v=>$label)echo '<option value="'.$v.'" '.(($perms[$p['Codice']]??'nessuno')===$v?'selected':'').'>'.uh($label).'</option>'; ?></select></td></tr>
<?php endforeach; ?></table><button>Salva utente e permessi</button></form></main></html>
