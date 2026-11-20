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
$selected=(int)($_GET['id']??0);
$edit=null;
$perms=[];
$editError='';
$listError='';
$rows=[];

if($selected){
 try{
  $q=$db->prepare('SELECT * FROM Utenti WHERE ID=?');
  $q->execute([$selected]);
  $candidate=$q->fetch();
  if($candidate){
   $candidate['Profilo']=user_decrypt($candidate['ProfiloCifrato']);
   $q=$db->prepare('SELECT Pannello,Livello FROM UtentiPermessi WHERE UtenteID=?');
   $q->execute([$selected]);
   $perms=$q->fetchAll(PDO::FETCH_KEY_PAIR);
   $edit=$candidate;
  }
 }catch(Throwable $e){
  error_log('POImanager: apertura utente ID '.$selected.' fallita ('.get_class($e).').');
  $editError='Impossibile aprire questo utente in modifica. Puoi creare un nuovo utente.';
 }
}

try{
 $rows=$db->query('SELECT ID, ProfiloCifrato, Ruolo, Attivo FROM Utenti ORDER BY ID')->fetchAll();
}catch(PDOException $e){
 error_log('POImanager: lettura elenco Utenti fallita (SQLSTATE '.$e->getCode().').');
 $listError='Impossibile leggere l’elenco degli utenti dal database.';
}

page_start('Gestione utenti');
if($message)echo '<aside>'.uh($message).'</aside>';
if($editError)echo '<aside>'.uh($editError).'</aside>';
if(isset($_GET['salvato']))echo '<aside>Utente e permessi salvati. Le sue sessioni precedenti sono invalidate.</aside>';
echo '<p><a href="/utenti.php">Nuovo utente</a>. Ruoli esterni sempre disattivati. Ghost è configurato fuori dal database.</p>';
if($listError)echo '<aside>'.uh($listError).'</aside>';
echo '<table><tr><th>Nome</th><th>Email</th><th>Ruolo</th><th>Stato</th><th></th></tr>';
foreach($rows as $row){
 $id=(int)$row['ID'];
 $action='';
 try{
  $profile=user_decrypt($row['ProfiloCifrato']);
  $name=$profile['nome']??'';
  $email=$profile['email']??'';
  $action='<a href="?id='.$id.'">Modifica</a>';
 }catch(Throwable $e){
  error_log('POImanager: profilo utente ID '.$id.' illeggibile ('.get_class($e).').');
  $name='Profilo non leggibile (ID '.$id.')';
  $email='';
 }
 echo '<tr><td>'.uh($name).'</td><td>'.uh($email).'</td><td>'.uh($row['Ruolo']).'</td><td>'.((int)$row['Attivo']?'Attivo':'Disattivato').'</td><td>'.$action.'</td></tr>';
}
echo '</table>'; ?>
<?php require __DIR__.'/includes/utenti_form.php'; ?>
</main></body></html>
