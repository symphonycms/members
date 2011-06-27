<?php

	$about = array(
		'name' => 'Italiano',
		'author' => array(
			'name' => 'Simone Economo',
			'email' => 'my.ekoes@gmail.com',
			'website' => 'http://www.lineheight.net'
		),
		'release-date' => '2011-06-08'
	);

	/**
	 * Members
	 */
	$dictionary = array(

		'Member Roles' => 
		'Ruoli utenza',

		'Members Timezone' => 
		'Fuso orario',

		'Members: Lock Role' => 
		'Utenza: blocca ruolo',

		'Members: Lock Activation' => 
		'Utenza: blocca attivazione',

		'Members: Update Password' => 
		'Utenza: aggiorna la password',

		'Email Template Filter' => 
		false,

		'Email Template Manager' => 
		false,

		'Members' => 
		'Utenti',

		'Active Members Section' => 
		'Sezione per l\'utenza',

		'A Members section will at minimum contain either a Member: Email or a Member: Username field' => 
		'Una sezione &#232; valida se contiene come minimo i campi "Utenza: Email" o "Utenza: Nome utente"',

		'The page you have requested has restricted access permissions.' => 
		'La pagina richiesta ha permessi d\'accesso limitati.',

		'You are not authorised to perform this action.' => 
		'Non sei autorizzato ad eseguire l\'azione.',

		'Event updated at {$time}. <a href="{$new_url}" accesskey="c">Create another?</a> <a href="{$url}" accesskey="a">View all Events</a>' => 
		false,

		'An error occurred while processing this form.' => 
		'&#200; stato riscontrato un errore durante il salvataggio delle modifiche.',

		'There is no Member: Role field in the active Members section. <a href="%s%d/">Add Member: Role field?</a>' => 
		'Non esiste alcun campo "Utenza: Ruolo" nella sezione attiva. <a href="%s%d/">Vuoi aggiungerlo?</a>',

		'Create a Role' => 
		'Crea un nuovo ruolo',

		'No Member section has been specified in <a href="%s">Preferences</a>. Please do this first.' => 
		'Prima di proseguire, assicurati di specificare una sezione per l\'utenza nelle <a href="%s">Preferenze</a>.',

		'No Member section has been specified in %s. Please do this first.' => 
		'Prima di proseguire, assicurati di specificare una sezione per l\'utenza in %s.',

		'This is the role assumed by the general public.' => 
		'Questo &#232; il ruolo di default a cui apparterr&#224; un nuovo utente.',

		'Delete Members' => 
		'Elimina utenti',

		'Move Members To' => 
		'Sposta utenti in',

		'The role you requested to edit does not exist.' => 
		'Il ruolo che vuoi modificare non esiste.',

		'Role not found' => 
		'Ruolo non trovato',

		'Role updated at %1$s. <a href="%2$s" accesskey="c">Create another?</a> <a href="%3$s" accesskey="a">View all Roles</a>' => 
		'Ruolo aggiornato %1$s <a href="%2$s" accesskey="c">Vuoi crearne un altro?</a> <a href="%3$s" accesskey="a">Visualizza tutti i ruoli</a>',

		'Role created at %1$s. <a href="%2$s" accesskey="c">Create another?</a> <a href="%3$s" accesskey="a">View all Roles</a>' => 
		'Ruolo creato %1$s <a href="%2$s" accesskey="c">Vuoi crearne un altro?</a> <a href="%3$s" accesskey="a">Visualizza tutti i ruoli</a>',

		'Symphony &ndash; Member Roles' => 
		'Symphony &ndash; Ruoli utenza',

		'Symphony &ndash; Member Roles &ndash; ' => 
		'Symphony &ndash; Ruoli utenza &ndash; ',

		'Event Level Permissions' => 
		'Permessi associati agli eventi',

		'User can create new entries' => 
		'L\'utente pu&#242; creare nuove voci',

		'User cannot edit existing entries' => 
		'L\' utente non pu&#242; modificare le voci esistenti',

		'User can edit their own entries only' => 
		'L\'utente pu&#242; modificare solo le voci di cui &#232; autore',

		'User can edit all entries' => 
		'L\'utente pu&#242; modificare qualsiasi voce',

		'Event' => 
		'Evento',

		'Toggle all' => 
		'Riduci tutti',

		'No Edit' => 
		'Nessuna voce',

		'Edit Own' => 
		'Proprie voci',

		'Edit All' => 
		'Tutte le voci',

		'Page Level Permissions' => 
		'Permessi associati alle pagine',

		'Deny Access' => 
		'Vieta l\'accesso alle seguenti pagine',

		'Delete this Role' => 
		'Elimina questo ruolo',

		'A role with the name <code>%s</code> already exists.' => 
		'Un ruolo denominato <code>%s</code> esiste gi&#224;.',

		'The Public role cannot be removed' => 
		'Il ruolo principale non pu&#242; essere rimosso',

		'The role you requested to delete does not exist.' => 
		'Il ruolo che vuoi eliminare non esiste.',

		'Activate Account Email Template' => 
		'Template email per l\'attivazione dell\'account',

		'%s Automatically log the member in after activation' => 
		'%s Connetti in automatico l\'utente in seguito all\'attivazione',

		'No Activation field found.' => 
		'Nessun campo Attivazione trovato.',

		'No Identity field found.' => 
		'Nessun campo Identit&#224; trovato.',

		'%s is a required field.' => 
		'\'%s\' &#232; un campo obbligatorio.',

		'Member not found.' => 
		'Utente non trovato.',

		'Member is already activated.' => 
		'L\'attivazione per questo utente &#232; stata gi&#224; confermata.',

		'Activation error. Code was invalid or has expired.' => 
		'Errore nell\'attivazione. Il codice d\'attivazione non era valido oppure &#232; scaduto.',

		'Generate Recovery Code Email Template' => 
		'Template email per il codice di recupero password',

		'You cannot generate a recovery code while being logged in.' => 
		'Non puoi generare un codice di recupero password mentre sei connesso.',

		'Regenerate Activation Code Email Template' => 
		'Template email per il nuovo codice d\'attivazione',

		'Reset Password Email Template' => 
		'Template email per il ripristino password',

		'%s Automatically log the member in after changing their password' => 
		'%s Connetti in automatico l\'utente in seguito al cambio password',

		'No Authentication field found.' => 
		'Nessun campo Autenticazione trovato.',

		'Recovery code is a required field.' => 
		'Il codice di recupero password &#232; obbligatorio.',

		'No recovery code found.' => 
		'Nessun codice di recupero password trovato.',

		'Recovery code has expired.' => 
		'Il codice di recupero password &#232; scaduto.',

		'Member: Activation' => 
		'Utenza: Attivazione',

		'Activation Code Expiry' => 
		'Scadenza codice d\'attivazione',

		'How long a member\'s activation code will be valid for before it expires' => 
		'Quanto a lungo il codice d\'attivazione rimane valido prima di scadere',

		'How long a member\'s activation code will be valid for before it expires (in minutes)' => 
		'Quanto a lungo il codice d\'attivazione rimane valido prima di scadere (in minuti)',

		'Role for Members who are awaiting activation' => 
		'Ruolo di default per gli utenti in attesa di attivazione',

		'%s Prevent unactivated members from logging in' => 
		'%s Impedisci agli utenti in attesa di connettersi',

		'Code expiry must be a unit of time, such as <code>1 day</code> or <code>2 hours</code>' => 
		'Il valore della scadenza deve essere un\'unit&#224; di tempo valida come <code>1 day</code> o <code>2 ore</code>',

		'Code expiry must be a valid value for minutes, such as <code>60</code> (1 hour) or <code>1440</code> (1 day)' => 
		'Il valore della scadenza deve essere un numero valido in minuti come <code>60</code> (1 ora) o <code>1440</code> (1 giorno)',

		'Not Activated' => 
		'Non attivato',

		'Activated' => 
		'Attivato',

		'Activation code %s' => 
		'Codice d\'attivazione %s',

		'Activation code expired %s' => 
		'Codice d\'attivazione scaduto in data %s',

		'Account will be activated when entry is saved' => 
		'L\'account verr&#224; attivato dopo il salvataggio',

		'Activated %s' => 
		'Attivato in data %s',

		'Member: Email' => 
		'Utenza: Email',

		'%s contains invalid characters.' => 
		'\'%s\' contiene caratteri non validi.',

		'%s is already taken.' => 
		'%s non &#232; disponibile.',

		'Member: Password' => 
		'Utenza: Password',

		'Weak' => 
		'Debole',

		'Good' => 
		'Buona',

		'Strong' => 
		'Robusta',

		'Invalid %s.' => 
		'%s non valido.',

		'Minimum Length' => 
		'Lunghezza minima',

		'Minimum Strength' => 
		'Robustezza minima',

		'Password Salt' => 
		'Sale per la password',

		'A salt gives your passwords extra security. It cannot be changed once set' => 
		'Un sale fornisce misure di sicurezza pi&#249; affidabili. Una volta impostato, non pu&#242; essere cambiato.',

		'Recovery Code Expiry' => 
		'Scadenza codice di recupero password',

		'How long a member\'s recovery code will be valid for before it expires' => 
		'Quanto a lungo il codice di recupero password rimane valido prima di scadere',

		'How long a member\'s recovery code will be valid for before it expires (in minutes)' => 
		'Quanto a lungo il codice di recupero password rimane valido prima di scadere (in minuti)',

		'Leave new password field blank to keep the current password' => 
		'Lascia il campo per la nuova password vuoto al fine di mantenere quella attuale',

		'%s confirmation does not match.' => 
		'Le password nel campo \'%s\' non corrispondono.',
		
		'Passwords don\'t match.' => 
		'Le password non corrispondono.',

		'%s is too short. It must be at least %d characters.' => 
		'La password nel campo \'%s\' &#232; troppo breve, deve essere lunga almeno %d caratteri.',
		
		'Password is too short. It must be at least %d characters.' => 
		'La password &#232; troppo breve, deve essere lunga almeno %d caratteri.',

		'%s is not strong enough.' => 
		'La password nel campo \'%s\' non &#232; abbastanza robusta.',
		
		'Password is not strong enough.' => 
		'La password non &#232; abbastanza robusta.',

		'%s cannot be blank.' => 
		'%s non pu&#242; essere vuoto.',

		'Confirm' => 
		'Conferma',

		'Member: Role' => 
		'Utenza: Ruolo',

		'Default Member Role' => 
		'Ruolo di default',

		'Member will assume the role <strong>%s</strong> when activated.' => 
		'Una volta confermata l\'attivazione, l\'utente assumer&#224; il ruolo <strong>%s</strong>.',

		'Member: Timezone' => 
		'Utenza: Fuso orario',

		'Available Zones' => 
		'Zone disponibili',

		'Member: Username' => 
		'Utenza: Nome utente',

		'Member is not activated.' => 
		'L\'utente non &#232; stato ancora attivato.',

	);
