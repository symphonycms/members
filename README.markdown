# Members

- Version: 1.0 Beta 2
- Author: Symphony Team
- Build Date: March 28th 2011
- Requirements: Symphony 2.2.1

Frontend Membership extension for Symphony CMS.

For testing purposes this extension currently requires the Symphony 2 integration branch, which contains
the latest code that will be packaged into the 2.2.1 release. This extension should not be used in production
environments and is provided solely for the purpose of testing and feedback at this stage.

## Installation and Setup

1.	Upload the 'members' folder to your Symphony 'extensions' folder.

2.	Enable it by selecting the "Members", choose Enable from the with-selected menu, then click Apply.

3.	Create a new Section to hold your Members entries, and add a Member: Password, Member: Role
and either a Member: Email or Member: Username field. If you wish to email your users, you can add both.

4.	Go to System > Preferences and select your 'Active Members Section'.

5.	Go to System > Member Roles and setup your Roles as required. There is one default Role, Public that cannot be
removed (but can be edited to suit your needs). This role represents an unauthenticated Member.

6.	On your frontend, Members can login using standard forms. Below is an example:

		<form method="post" autocomplete='off'>
			<label>Username
				<input name="fields[{Member: Username element_name}]" type="text" />
			</label>
			<label>Password
				<input name="fields[{Member: Password element_name}]" type="password" />
			</label>

			<input name="redirect" type="hidden" value="{$root}/account/" />
			<input name="member-action[login]" type="submit" value="Login" />
		</form>

Event information will be returned in the XML similar to the following example:

		<events>
			<member-login-info logged-in="yes" id="72" />
		</events>

The `$member-id` and `$member-role` parameters will be added to the Page
Parameters for you to use in your datasources to get information about the
logged in member.

7.	You can log a Member out using `<a href='?member-action=logout'>Logout</a>`

## Usage

### Fields

This extension provides six additional fields:

- Member: Username
- Member: Email
- Member: Password
- Member: Role
- Member: Timezone
- Member: Activation

### Events

This extension provides four additional events:

- Members: Activate Account
- Members: Regenerate Activation Code
- Members: Reset Password
- Members: Recover Account

Go to Blueprints > Components and click on the event name to view
documentation for that event.

This extension provides three event filters that you can add to your events to
 make them useful to Members:

- Members: Activation
- Members: Register
- Members: Update Password

#### Members: Activation

The Members: Activation filter is to be attached to your own Registration event
to force a Member's activated state to be 'no' when a Member is registering for your
site. This prevents any DOM hacking to make users activate themselves. If you do
not use the Member: Activation field, then you don't this filter on your Registration
event.

#### Members: Register

The Members: Register filter should be used as an additional security measure to
ensure that the user cannot DOM hack their own Role. This filter ensures a newly
registered user will always be of the Default Role. If you do not use the Member: Role
field, you don't need this filter on your Registration event.

#### Members: Update Password

The Members: Update Password filter is useful on Events where the user may update
some of their profile information, and updating their password is optional. It
essentially tells the extension that if the user hasn't provided their password,
yet it's set to required, it's ok, just remember their current password details.

### Roles and Permissions

The Members extension comes with a single default Role, Public. This role
cannot be deleted, but it can be renamed and modified to suit your
installation. This Role is assumed by any Frontend user who is not
authenticated. Roles allow you to set Frontend event and page permissions.

### Email Templates

The [Email Template Filter](http://symphony-cms.com/download/extensions/view/20743/)
or [Email Template Manager](http://symphony-cms.com/download/extensions/view/64322/)
can be used to email information specific to the Members extension such as Member
Registration, Password Reset and Activation Codes. These extensions allow Email
Templates to be added as Event Filters to your events. Check the documentation
for either extension to evaluate them for your situation. All bugs relating to
those extensions should be reported to the respective extension, not the
Members extension.

Please note that the Password Reset event is unique and requires that it's
template be set through the System > Preferences page.