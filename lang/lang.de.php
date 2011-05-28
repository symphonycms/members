<?php

	$about = array(
		'name' => 'Deutsch',
		'author' => array(
			'name' => 'Michael Eichelsdoerfer',
			'email' => 'info@michael-eichelsdoerfer.de',
			'website' => ''
		),
		'release-date' => '2011-04-27'
	);

	/**
	 * Members
	 */
	$dictionary = array(

		'Member Roles' =>
		'Mitgliederrollen',

		'Members Timezone' =>
		'Mitglieder-Zeitzone',

		'Members: Lock Role' =>
		false,

		'Members: Lock Activation' =>
		false,

		'Members: Update Password' =>
		false,

		'Members' =>
		'Mitglieder',

		'Active Members Section' =>
		'Aktiver Mitgliederbereich',

		'A Members section will at minimum contain either a Member: Email or a Member: Username field' =>
		'Ein Mitgliederbereich wird mindestens ein Mitglied: Email Feld oder ein Mitglied: Benutzername Feld enthalten',

		'Email Template Filter' =>
		false,

		'Email Template Manager' =>
		false,

		'Generate Recovery Code Email Template' =>
		false,

		'Activate Account Email Template' =>
		false,

		'Regenerate Activation Code Email Template' =>
		false,

		'The page you have requested has restricted access permissions.' =>
		'Die angeforderte Seite hat beschränkte Zugriffsrechte.',

		'You are not authorised to perform this action.' =>
		'Sie sind nicht befugt diese Aktion auszuführen.',

		'There is no Member: Role field in the active Members section. <a href="%s%d/">Add Member: Role field?</a>' =>
		'Es gibt kein Member: Role Feld im aktiven Mitgliederbereich. <a href="%s%d/">Member: Role Feld hinzufügen?</a',

		'Create a Role' =>
		'Rolle erzeugen',

		'No Member section has been specified in %s. Please do this first.' =>
		'In %s wurde kein Mitgliederbereich festgelegt. Bitte tun Sie dies zuerst.',

		'This is the role assumed by the general public.' =>
		'Das ist die Rolle, die der Allgemeinheit unterstellt wird.',

		'Delete Members' =>
		'Mitglieder löschen',

		'Move Members To' =>
		'Mitglieder bewegen nach',

		'The role you requested to edit does not exist.' =>
		'Die Rolle die Sie ändern wollten existiert nicht.',

		'Role not found' =>
		'Rolle nicht gefunden',

		'Symphony &ndash; Member Roles' =>
		'Symphony &ndash; Mitgliederrollen',

		'Symphony &ndash; Member Roles &ndash; ' =>
		'Symphony &ndash; Mitgliederrollen &ndash; ',

		'Event Level Permissions' =>
		'Berechtigungen auf Ereignisebene',

		'User can create new entries' =>
		'Benutzer kann neue Einträge erstelllen',

		'User cannot edit existing entries' =>
		'Benutzer kann keine bestehenden Einträge ändern',

		'User can edit their own entries only' =>
		'Benutzer kann nur eigene Einträge ändern',

		'User can edit all entries' =>
		'Benutzer kann alle Einträge ändern',

		'Event' =>
		'Ereignis',

		'Toggle all' =>
		'Alle umschalten',

		'No Edit' =>
		'Nicht ändern',

		'Edit Own' =>
		'Eigene ändern',

		'Edit All' =>
		'Alle ändern',

		'Page Level Permissions' =>
		'Berechtigungen auf Seiten-Ebene',

		'Deny Access' =>
		'Zugang verweigern',

		'Delete this Role' =>
		'Diese Rolle löschen',

		'A role with the name <code>%s</code> already exists.' =>
		'Eine Rolle mit dem Namen <code>%s</code> existiert bereits',

		'The Public role cannot be removed' =>
		'Die Rolle für die Allgemeinheit kann nicht gelöscht werden',

		'The role you requested to delete does not exist.' =>
		'Die Rolle die Sie löschen wollten existiert nicht.',

		'No Activation field found.' =>
		'Kein Aktivierungsfeld gefunden.',

		'No Identity field found.' =>
		'Kein Identitätsfeld gefunden.',

		'%s is a required field.' =>
		'%s ist ein Pflichtfeld.',

		'Member not found.' =>
		'Benutzer nicht gefunden.',

		'Member is already activated.' =>
		'Mitglied ist bereits aktiviert.',

		'Activation error. Code was invalid or has expired.' =>
		'Aktivierungsfehler. Dieser Code ist ungültig oder abgelaufen.',

		'You cannot generate a recovery code while being logged in.' =>
		'Ein Notfallcode kann nicht erzeugt werden wenn man angemeldet ist.',

		'No Authentication field found.' =>
		'Kein Authentifizierungsfeld gefunden.',

		'Recovery code is a required field.' =>
		'Notfallcode ist ein Pflichtfeld.',

		'No recovery code found.' =>
		'Kein Notfallcode gefunden.',

		'Recovery code has expired.' =>
		'Notfallcode ist abgelaufen.',

		'Member: Activation' =>
		'Mitglied: Aktivierung',

		'Activation Code Expiry' =>
		'Ablauf des Aktivierungscodes',

		'How long a member\'s activation code will be valid for before it expires' =>
		'Wie lange der Aktivierungscode eines Mitglieds gültig ist bevor er erlischt',

		'Role for Members who are awaiting activation' =>
		'Rolle für Mitglieder die auf die Aktivierung warten',

		'%s Prevent unactivated members from logging in' =>
		'%s Hindere nicht aktivierte Mitglieder an der Anmeldung',

		'%s Automatically log the member in after activation' =>
		'%s Melde das Mitglied nach der Aktivierung automatisch an',

		'Not Activated' =>
		'Nicht aktiviert',

		'Activated' =>
		'Aktiviert',

		'Activation code %s' =>
		'Aktivierungscode %s',

		'Activation code expired %s' =>
		'Aktivierungscode abgelaufen %s',

		'Account will be activated when entry is saved' =>
		'Das Konto wird aktiviert wenn der Eintrag gespeichert wird',

		'Member will assume the role <strong>%s</strong> when activated.' =>
		'Nach der Aktivierung wird das Mitglied die Rolle <strong>%s</strong> annehmen.',

		'Activated %s' =>
		'Aktiviert %s',

		'Weak' =>
		'Schwach',

		'Good' =>
		'Gut',

		'Strong' =>
		'Stark',

		'Member: Email' =>
		'Benutzer: E-Mail',

		'%s contains invalid characters.' =>
		'%s enthält ungültige Zeichen.',

		'%s is already taken.' =>
		'%s ist bereits vergeben.',

		'Member: Password' =>
		'Benutzer: Passwort',

		'Invalid %s.' =>
		'%s ungültig.',

		'Minimum Length' =>
		'Mindestlänge',

		'Minimum Strength' =>
		'Mindestsicherheit',

		'Password Salt' =>
		'Passwort-Salz',

		'A salt gives your passwords extra security. It cannot be changed once set' =>
		'Ein Salz sorgt für zusätzliche Sicherheit der Passwörter. Es kann nicht mehr geändert werden wenn es einmal festgelegt wurde',

		'Recovery Code Expiry' =>
		'Ablauf des Notfallcodes',

		'How long a member\'s recovery code will be valid for before it expires' =>
		'Wie lange der Notfallcode eines Mitglieds gültig ist bevor er erlischt',

		'%s Automatically log the member in after changing their password' =>
		'%s Melde das Mitglied nach einer Passwortänderung automatisch an',

		'Leave new password field blank to keep the current password' =>
		'Lassen Sie das Feld für ein neues Passwort leer um das aktuelle Passwort zu behalten',

		'%s confirmation does not match.' =>
		'%s-Bestätigung stimmt nicht.',

		'%s is too short. It must be at least %d characters.' =>
		'%s ist zu kurz. Es muss mindestens %d Zeichen lang sein.',

		'%s is not strong enough.' =>
		'%s ist nicht sicher genug.',

		'%s cannot be blank.' =>
		'%s darf nicht leer sein.',

		'Save' =>
		'Speichern',

		'Confirm' =>
		'Bestätigen',

		'Member: Role' =>
		'Mitglied: Rolle',

		'Default Member Role' =>
		'Standardrolle für Mitglieder',

		'Member: Timezone' =>
		'Mitglied: Zeitzone',

		'Available Zones' =>
		'Verfügbare Zonen',

		'Member: Username' =>
		'Mitglied: Benutzername',

		'Member is not activated.' =>
		'Mitglied ist nicht aktiviert.',

		'Event updated at {$time}. <a href="{$new_url}" accesskey="c">Create another?</a> <a href="{$url}" accesskey="a">View all Events</a>' =>
		'Ereignis um {$time} aktualisiert. <a href="{$new_url}" accesskey="c">Ein neues erstellen?</a> <a href="{$url}">Zeige alle Ereignisse</a>',

		'An error occurred while processing this form.' =>
		'Beim Verarbeiten dieses Formulars ist ein Fehler aufgetreten.',

	);
