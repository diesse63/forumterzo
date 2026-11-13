<h2><?= $edit?'Modifica utente':'Nuovo utente' ?></h2>
<form method="post">
<input type="hidden" name="csrf" value="<?=uh(csrf())?>">
<input type="hidden" name="id" value="<?=(int)($edit['ID']??0)?>">
<label>Nome di accesso<input name="nome" maxlength="100" required value="<?=uh($edit['Profilo']['nome']??'')?>"></label>
<label>Email<input type="email" name="email" value="<?=uh($edit['Profilo']['email']??'')?>"></label>
<label>Ruolo<select name="ruolo" id="user-role"><?php foreach(['admin','user','esternobase','esternoevoluto'] as $r)echo '<option value="'.$r.'" '.(($edit['Ruolo']??'user')===$r?'selected':'').'>'.uh($r==='esternoevoluto'?'esterno evoluto':$r).'</option>'; ?></select></label>
<label><input type="checkbox" name="attivo" <?=(!$edit||(int)$edit['Attivo'])?'checked':''?>> Attivo (ignorato per i ruoli esterni)</label>
<label><?= $edit?'Nuova password, lasciare vuota per mantenerla':'Password' ?> <span class="password-requirement">(Minimo 7 caratteri e uno speciale)</span><input type="password" name="password" autocomplete="new-password" <?= $edit?'':'required' ?>></label>
<fieldset id="user-permissions" class="user-permissions" <?=($edit['Ruolo']??'user')==='user'?'':'hidden disabled'?>>
<p>Permessi solo per user; nessuna selezione significa nessun accesso.</p>
<table><tr><th>Schermata</th><th>Accesso</th></tr>
<?php foreach($panels as $p):if(in_array($p['Codice'],['utenti','importazione'],true))continue; ?>
<tr><td><?=uh($p['Titolo'])?></td><td><select name="permessi[<?=uh($p['Codice'])?>]"><?php foreach(['nessuno'=>'Nessuno','lettura'=>'Sola lettura','scrittura'=>'Lettura e scrittura'] as $v=>$label)echo '<option value="'.$v.'" '.(($perms[$p['Codice']]??'nessuno')===$v?'selected':'').'>'.uh($label).'</option>'; ?></select></td></tr>
<?php endforeach; ?></table></fieldset><button>Salva utente e permessi</button></form>
<script src="/assets/utenti.js" defer></script>
