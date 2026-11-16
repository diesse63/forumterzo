<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_access('gestione_email','lettura');
require_once __DIR__.'/includes/email.php';
$p=poimanager_db();$id=(int)($_GET['id']??0);$logs=em_logs($p,$id);$q=trim((string)($_GET['esiti_q']??''));$state=trim((string)($_GET['esiti_stato']??''));
$rows=array_values(array_filter($logs,fn($r)=>($q===''||dc_contains($r['Nome'].' '.$r['Email'].' '.$r['EmailEffettiva'].' '.$r['Oggetto'],$q))&&($state===''||$r['Stato']===$state)));
$subject=$logs[0]['Oggetto']??'(Senza oggetto)';$esc=static fn(string $v):string=>str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$v);
$lines=['REPORT ESITI MAIL','Oggetto: '.$subject,'Lista ID: '.$id,'Filtri: ricerca='.($q!==''?$q:'nessuna').' | stato='.($state!==''?$state:'tutti'),'Risultati: '.count($rows).' di '.count($logs),''];foreach($rows as $r)$lines[]=$r['Oggetto'].' | '.$r['Nome'].' | '.$r['EmailEffettiva'].' | '.$r['Stato'].' | Invio: '.($r['InviatoIl']??'—').' | Conferma: '.($r['ConfermatoIl']??'—');
$content="BT\n/F1 9 Tf\n50 800 Td\n";foreach($lines as $line)$content.='('.$esc(substr($line,0,140)).") Tj\n0 -14 Td\n";$content.="ET\n";
$objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',"<< /Length ".strlen($content)." >>\nstream\n".$content."endstream"];
$pdf="%PDF-1.4\n";$offsets=[];foreach($objects as $n=>$obj){$offsets[]=strlen($pdf);$pdf.=($n+1).' 0 obj'."\n".$obj."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";foreach($offsets as $o)$pdf.=sprintf("%010d 00000 n \n",$o);$pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="report_esiti_'.$id.'.pdf"');echo $pdf;
