-- Eseguire UNA SOLA VOLTA in manutenzione, dopo backup.
-- Non elimina i vecchi utenti: li separa; nessuna password legacy viene letta.
RENAME TABLE Utenti TO Utenti_legacy_v1;
CREATE TABLE Utenti (
 ID BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 NomeIndice CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 ProfiloCifrato LONGTEXT NOT NULL,
 PasswordHash VARCHAR(255) NOT NULL,
 Ruolo VARCHAR(20) NOT NULL,
 Attivo TINYINT NOT NULL DEFAULT 1,
 VersioneSessione BIGINT NOT NULL DEFAULT 1,
 CreatoIl TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT ck_utenti_ruolo CHECK (Ruolo IN ('admin','user','esternobase','esternoevoluto')),
 CONSTRAINT ck_utenti_attivo CHECK (Attivo IN (0,1)),
 CONSTRAINT ck_utenti_esterni CHECK (Ruolo NOT IN ('esternobase','esternoevoluto') OR Attivo=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE Pannelli (
 Codice VARCHAR(64) NOT NULL PRIMARY KEY,
 Titolo VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE UtentiPermessi (
 UtenteID BIGINT NOT NULL,
 Pannello VARCHAR(64) NOT NULL,
 Livello VARCHAR(10) NOT NULL,
 PRIMARY KEY (UtenteID,Pannello),
 FOREIGN KEY (UtenteID) REFERENCES Utenti(ID) ON DELETE CASCADE,
 FOREIGN KEY (Pannello) REFERENCES Pannelli(Codice) ON DELETE CASCADE,
 CONSTRAINT ck_permessi_livello CHECK (Livello IN ('lettura','scrittura'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE AccessiTentativi (
 Chiave CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 Tentativi INT NOT NULL DEFAULT 0,
 Ultimo BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
