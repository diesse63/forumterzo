<?php
declare(strict_types=1);
function profile_cipher_available(string $cipher): bool {
 if($cipher==='sodium')return function_exists('sodium_crypto_secretbox')&&function_exists('sodium_crypto_secretbox_open');
 return $cipher==='aes-256-gcm'&&function_exists('openssl_encrypt')&&function_exists('openssl_decrypt')&&function_exists('openssl_get_cipher_methods')&&in_array('aes-256-gcm',openssl_get_cipher_methods(),true);
}
function profile_choose_cipher(): string {
 foreach(['sodium','aes-256-gcm'] as $cipher)if(profile_cipher_available($cipher))return $cipher;
 throw new RuntimeException('Cifratura non disponibile: abilitare PHP Sodium oppure OpenSSL con AES-256-GCM.');
}
function profile_encrypt(array $profile,string $key,string $cipher): string {
 if(strlen($key)!==32||!profile_cipher_available($cipher))throw new RuntimeException('Motore di cifratura richiesto non disponibile: '.$cipher.'.');
 $plain=json_encode($profile,JSON_THROW_ON_ERROR);
 if($cipher==='sodium'){
  $nonce=random_bytes(24);
  return base64_encode($nonce.sodium_crypto_secretbox($plain,$nonce,$key));
 }
 $iv=random_bytes(12);$tag='';
 $encrypted=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'poimanager-profile-v1',16);
 if($encrypted===false||strlen($tag)!==16)throw new RuntimeException('Cifratura profilo non riuscita.');
 return 'gcm1:'.base64_encode($iv.$tag.$encrypted);
}
function profile_decrypt(string $value,string $key): array {
 if(strlen($key)!==32)throw new RuntimeException('Chiave utenti non valida.');
 $gcm=str_starts_with($value,'gcm1:');
 $cipher=$gcm?'aes-256-gcm':'sodium';
 if(!profile_cipher_available($cipher))throw new RuntimeException('Per leggere i profili esistenti occorre il motore '.$cipher.'. Non rigenerare la chiave.');
 $raw=base64_decode($gcm?substr($value,5):$value,true);
 if($raw===false||strlen($raw)<($gcm?29:40))throw new RuntimeException('Profilo cifrato non valido.');
 if($gcm)$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'poimanager-profile-v1');
 else $plain=sodium_crypto_secretbox_open(substr($raw,24),substr($raw,0,24),$key);
 if($plain===false)throw new RuntimeException('Chiave o profilo non valido.');
 $profile=json_decode($plain,true,512,JSON_THROW_ON_ERROR);
 if(!is_array($profile))throw new RuntimeException('Profilo non valido.');
 return $profile;
}
