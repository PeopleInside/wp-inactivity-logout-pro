=== WP Inactivity Logout Pro ===
Contributors: PeopleInside
Tags: logout, inactivity, security, session, closed tab, inactivity logout
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.3
License: MIT License
License URI: https://github.com/PeopleInside/wp-inactivity-logout-pro?tab=MIT-1-ov-file

Disconnessione automatica per inattività con protezione a scheda chiusa lato server, avviso popup con conto alla rovescia e supporto bilingue (IT/EN).

== Description ==

WP Inactivity Logout Pro protegge le sessioni degli utenti di WordPress su qualsiasi ruolo (amministratori, editori, clienti ed abbonati).

A differenza dei tradizionali plugin che funzionano solo via JavaScript mentre la scheda del browser è aperta, WP Inactivity Logout Pro introduce una **doppia barriera di sicurezza**:
1. **Client-Side Engine**: Monitora costantemente l'attività utente (mouse, tastiera, touch, scroll) senza mostrare il popup se l'utente è attivo. Se l'utente è realmente inattivo, mostra un popup con conto alla rovescia sincronizzato tra tutte le schede aperte via BroadcastChannel e localStorage.
2. **Server-Side Closed-Tab Guard**: Registra l'ultimo timestamp attivo. Se un utente chiude il browser o la scheda e ritorna dopo il tempo consentito (es. 30 minuti), la sessione viene distrutta immediatamente lato server su 'init' con l'API nativa wp_logout(), senza errori 500 o redirect loop.

== Key Features ==
* **Rilevamento Continuo di Attività**: Nessun popup mostrato prematuramente finché l'utente interagisce con la pagina.
* **Avviso & Conteggio Personalizzabili**: Imposta il logout (es. 30 min) e l'avviso con conto alla rovescia (es. dopo 15 min).
* **Disattivazione con 0**: Imposta a 0 i minuti dell'avviso per disattivare completamente il popup ed eseguire il logout diretto.
* **Controllo di Validità**: L'avviso non può avere un valore superiore o uguale al tempo totale di logout.
* **Chiusura a Scheda Chiusa**: Protezione totale se l'utente chiude la finestra e lascia il PC incustodito.
* **Navigazione a Schede Fluida**: Tab admin 100% funzionanti con JavaScript nativo integrato.
* **Bilingue Automatico**: Se WordPress è in Inglese il plugin e i messaggi sono in Inglese; se in Italiano sono in Italiano.
* **Pronto per PHP 8.5**: Compatibile al 100% con PHP 8.0, 8.1, 8.2, 8.3, 8.4 e 8.5.
* **Zero Vulnerabilità**: Nonce crittografici, sanitizzazione rigorosa, check permessi manage_options e logout nativo sicuro con wp_logout().

== Installation ==
1. Scarica il file `wp-inactivity-logout-pro.zip`.
2. Nel tuo pannello WordPress vai su **Plugin > Aggiungi nuovo > Carica plugin**.
3. Seleziona lo zip e clicca su **Installa ora**, quindi **Attiva**.
4. Vai su **Impostazioni > Inactivity Logout** per configurare timeout e messaggi.
