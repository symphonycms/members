<?php

	$about = array(
		'name' => '',
		'author' => array(
			'name' => 'Danila Susak',
			'email' => 'danilasusak@gmail.com',
			'website' => ''
		),
		'release-date' => '2011-04-25'
	);

	/**
	 * Members
	 */
	$dictionary = array(

		'Member Roles' =>
		'Пользовательские роли',

		'Members Timezone' =>
		'Часовой пояс',

		'Members: Lock Role' =>
		'Пользователь: Оставить роль',

		'Members: Lock Activation' =>
		'Пользователь: Оставить активацию учетной записи',

		'Members: Update Password' =>
		'Пользователь: Обновить пароль',

		'Members' =>
		'Пользователи',

		'Active Members Section' =>
		'Сущность с Пользователями',

		'A Members section will at minimum contain either a Member: Email or a Member: Username field' =>
		'Пользовательская сущность должна содержать как минимум одно из этих полей: `Пользователь: Электронная почта` или `Пользователь: имя пользователя`',

		'Email Template Filter' =>
		false,

		'Email Template Manager' =>
		false,

		'Generate Recovery Code Email Template' =>
		'Email Шаблон для кода восстановления пароля',

		'Reset Password Email Template' =>
		'Email Шаблон для сброса пароля',

		'Activate Account Email Template' =>
		'Email Шаблон для активация учетной записи',

		'Regenerate Activation Code Email Template' =>
		'Email Шаблон для повторной активации учетной записи',

		'The page you have requested has restricted access permissions.' =>
		'Для просмотра страницы, которую вы запрашиваете, у вас недостаточно прав',

		'You are not authorised to perform this action.' =>
		'Вы не авторизованы для выполнения этого действия',

		'There is no Member: Role field in the active Members section. <a href="%s%d/">Add Member: Role field?</a>' =>
		'Для сущности Пользователи не выбрана ни одна Роль. <a href="%s%d/">Добавит роль?</a>',

		'Create a Role' =>
		'Создать Роль',

		'No Member section has been specified in %s. Please do this first.' =>
		'Не определено ни одной сущности с Пользователями.Пожалуйста, сделайте это в первую очередь',

		'This is the role assumed by the general public.' =>
		'Эта роль определена по умолчанию для всех пользователей',

		'Delete Members' =>
		'Удалить пользователя',

		'Move Members To' =>
		'Переместить пользователя в',

		'The role you requested to edit does not exist.' =>
		'Роль, которую вы хотите отредактировать не существует',

		'Role not found' =>
		'Роль не найдена',

		'Role updated at %1$s. <a href="%2$s" accesskey="c">Create another?</a> <a href="%3$s" accesskey="a">View all Roles</a>' =>
		'Роль обновлена в %1$. <a href="%2$s" accesskey="c">Добавить еще?</a> <a href="%3$s" accesskey="a">Посмотреть все Роли</a>',

		'Role created at %1$s. <a href="%2$s" accesskey="c">Create another?</a> <a href="%3$s" accesskey="a">View all Roles</a>' =>
		'Роль создана в %1$. <a href="%2$s" accesskey="c">Добавить еще?</a> <a href="%3$s" accesskey="a">Посмотреть все Роли</a>',

		'Symphony &ndash; Member Roles' =>
		'Symphony &ndash; Пользовательские роли',

		'Symphony &ndash; Member Roles &ndash; ' =>
		'Symphony &ndash;Пользовательские роли &ndash; ',

		'Event Level Permissions' =>
		'Разрешения для событий',

		'User can create new entries' =>
		'Пользователь может создавать новые записи',

		'User cannot edit existing entries' =>
		'Пользователь не может редактировать существующие записи',

		'User can edit their own entries only' =>
		'Пользователь может редактировать только свои существующие записи',

		'User can edit all entries' =>
		'Пользователь может редактировать все записи',

		'Event' =>
		'Событие',

		'Toggle all' =>
		'Выбрать все',

		'No Edit' =>
		'Не редактировать',

		'Edit Own' =>
		'Редактировать свои',

		'Edit All' =>
		'Редактировать все',

		'Page Level Permissions' =>
		'Разрешения для страниц',

		'Deny Access' =>
		'Доступ закрыт',

		'Delete this Role' =>
		'Удалить эту Роль',

		'A role with the name <code>%s</code> already exists.' =>
		'Роль с данным именем <code>%s</code> уже существует.',

		'The Public role cannot be removed' =>
		'Гостевая роль не может быть удалена',

		'The role you requested to delete does not exist.' =>
		'Роль, которую вы собираетесь удалить не существует.',

		'No Activation field found.' =>
		'Не найдено поле для активации учетной записи',

		'No Identity field found.' =>
		'Не найдено поля с определением пользователя',

		'%s is a required field.' =>
		'%s это поле обязательно для заполнения.',

		'Member not found.' =>
		'Пользователь не найден',

		'Member is already activated.' =>
		'Пользователь уже активирован',

		'Activation error. Code was invalid or has expired.' =>
		'Ошибка активации. Код активации неверный или устарел.',

		'You cannot generate a recovery code while being logged in.' =>
		'Вы не можете запросить новый код для восстановления, пока вы не осуществите вход',

		'No Authentication field found.' =>
		'Не найден поле с кодом активации',

		'Recovery code is a required field.' =>
		'Поле Код для восстановления обязательно для заполнения.',

		'No recovery code found.' =>
		'Не найден код для восстановления',

		'Recovery code has expired.' =>
		'Код активации устарел',

		'Member: Activation' =>
		'Пользователь: Код активации',

		'Activation Code Expiry' =>
		'Срок кода активации',

		'How long a member\'s activation code will be valid for before it expires' =>
		'Сколько по времени будет актуальным срок активации учетной записи',

		'Role for Members who are awaiting activation' =>
		'Роли, ожидающие активацию',

		'%s Prevent unactivated members from logging in' =>
		'%s Запретить не активированным пользователям осуществлять вход',

		'%s Automatically log the member in after activation' =>
		'%s Осуществлять автоматический вход после активации',

		'Not Activated' =>
		'Не активированный',

		'Activated' =>
		'Активированный',

		'Activation code %s' =>
		'Код активации учетной записи: %s',

		'Activation code expired %s' =>
		'Код активации учетной записи истекает в: %s',

		'Account will be activated when entry is saved' =>
		'Учетная запись будет активирована после сохранения.',

		'Activated %s' =>
		'Активированы: %s',

		'Weak' =>
		'Слабый',

		'Good' =>
		'Хороший',

		'Strong' =>
		'Сильный',

		'Member: Email' =>
		'Пользователь: Электронная почта',

		'%s contains invalid characters.' =>
		'%s содержит недопустимые символы',

		'That %s is already taken.' =>
		'%s уже существует.',

		'Member: Password' =>
		'Пользователь: Пароль',

		'Invalid %s.' =>
		'Неверный %s',

		'Minimum Length' =>
		'Минимальная длина',

		'Minimum Strength' =>
		'Минимальная сила',

		'Password Salt' =>
		'Секретное слово для пароля',

		'A salt gives your passwords extra security. It cannot be changed once set' =>
		'Секретное слово дает Вашему паролю дополнительную защиту. Вы не можете поменять это в будущем.',

		'Recovery Code Expiry' =>
		'Код восстановления устарел',

		'How long a member\'s recovery code will be valid for before it expires' =>
		'Сколько по времени будет актуальным код восстановления учетной записи',

		'Leave new password field blank to keep the current password' =>
		'Оставить поле пароля пустым, чтобы сохранить существующий пароль нетронутым.',

		'%s confirmation does not match.' =>
		'%s подтверждения пароля не совпадает.',

		'%s is too short. It must be at least %d characters.' =>
		'%s слишком короткий. Как минимум должно содержать в себе %d символов.',

		'%s is not strong enough.' =>
		'%s недостаточно сильный',

		'%s cannot be blank.' =>
		'%s не может быть пустым.',

		'Confirm' =>
		'Подтверждение',

		'Member: Role' =>
		'Пользователь: Роль',

		'Default Member Role' =>
		'Роль по умолчанию',

		'Member: Timezone' =>
		'Пользователь: Часовой пояс',

		'Available Zones' =>
		'Доступные зоны',

		'Member: Username' =>
		'Пользователь: Имя пользователя',

		'Member is not activated.' =>
		'Пользователь не активирован.'

	);
