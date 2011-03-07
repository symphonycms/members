<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Error</h2><p>You cannot directly access this file</p>');

	Class eventMembers_Check_Recovery_Code extends Event {

		const ROOTELEMENT = 'members-check-recovery-code';

		public static function about(){
			return array(
				'name' => 'Members: Check Recovery Code',
				'author' => array(
					'name' => 'Symphony CMS',
					'website' => 'http://symphony-cms.com',
					'email' => 'team@symphony-cms.com'),
				'version' => '1.0',
				'release-date' => '2011-03-07'
			);
		}

		public function load(){
			if(isset($_POST['action'][self::ROOTELEMENT])) return $this->__trigger();
		}

		public static function documentation(){
			return '
				<p>This event takes a recovery code and a new password for a user. A recovery code is
				sent to the Member\'s email when they trigger an Event with the Member: Reset Password
				filter</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. An input field
				accepts the member\'s recovery code, two password fields (one for password, one to confirm)
				will allow the user to change their password.</p>
				<pre class="XML"><code>
				&lt;form method="post"&gt;
					&lt;label&gt;Recovery Code: &lt;input name="fields[recovery-code]" type="text" value="{$code}"/&gt;&lt;/label&gt;
					&lt;label&gt;Password: &lt;input name="fields[password][password]" type="password" /&gt;&lt;/label&gt;
					&lt;label&gt;Confirm Password: &lt;input name="fields[password][confirm]" type="password" /&gt;&lt;/label&gt;
					&lt;input type="submit" name="action['.self::ROOTELEMENT.']" value="Recover Account"/&gt;
					&lt;input type="hidden" name="redirect" value="{$root}/"/&gt;
				&lt;/form&gt;
				</code></pre>
			';
		}

		protected function __trigger(){
			$result = new XMLElement(self::ROOTELEMENT);

			SymphonyMember::checkRecoveryCode($_REQUEST['fields'], $result);

			return $result;
		}
	}

