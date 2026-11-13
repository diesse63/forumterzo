<section class="email-card"><h2>Configurazione modificabile</h2>
<p>Ogni salvataggio crea una versione. Le campagne già preparate mantengono la precedente configurazione. Conservare i relativi file privati e i vecchi deployment finché ci sono invii pendenti.</p>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><input type="hidden" name="action" value="config">
<label>URL ponte Apps Script<input type="url" name="url" required value="<?=em_h($config['URLPonte']??'')?>" placeholder="https://script.google.com/macros/s/…/exec"></label>
<label>Account Gmail mittente<input type="email" name="sender" required value="<?=em_h($config['Mittente']??'')?>"></label>
<label>Nome visualizzato<input name="sender_name" maxlength="200" value="<?=em_h($config['NomeMittente']??'Gestione')?>"></label>
<label>Modalità<select name="mode"><option value="test" <?=($config['Modalita']??'test')==='test'?'selected':''?>>Test — solo destinatario di prova</option><option value="produzione" <?=($config['Modalita']??'')==='produzione'?'selected':''?>>Produzione — destinatari reali</option></select></label>
<label><input type="checkbox" name="production_confirm"> Confermo l’uso dei destinatari reali se scelgo Produzione.</label>
<label>Destinatario di prova<input type="email" name="test_email" required value="<?=em_h($config['EmailTest']??'')?>"></label>
<label>Dimensione lotto<input type="number" name="batch" min="1" max="20" required value="<?=(int)($config['Lotto']??20)?>"></label>
<label>Credenziale del ponte<input type="password" name="secret" id="email-secret" autocomplete="new-password" placeholder="<?=$config?'Lasciare vuoto per conservare la credenziale':'Generare e copiare nelle proprietà Apps Script'?>"></label>
<div class="email-row"><button type="button" id="email-generate-secret">Genera nuova credenziale</button><button type="button" id="email-show-secret">Mostra/nascondi nuova credenziale</button></div>
<p class="email-muted">Copia la nuova credenziale nella proprietà script BRIDGE_SECRET. Non è la password Gmail. Le credenziali già salvate non vengono mostrate. Cambiando account usare un nuovo progetto/deployment e una nuova versione qui.</p><button>Salva nuova versione configurazione</button></form></section>
<?php if($config):?>
<section class="email-card"><h2>Prove della configurazione attiva</h2>
<form method="post"><input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><input type="hidden" name="action" value="ping"><button>Test connessione — nessuna email</button></form>
<p>Ultima verifica: <?=em_h($config['VerificataIl']??'mai')?> UTC</p>
<form method="post"><input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><input type="hidden" name="action" value="testmail"><input type="hidden" name="send_nonce" value="<?=em_h(em_send_nonce())?>"><label><input type="checkbox" name="confirm" required> Autorizzo una singola email di prova a <?=em_h($config['EmailTest'])?>.</label><button <?=$config['VerificataIl']?'':'disabled'?>>Invia email di prova</button></form></section>
<?php endif;?>
