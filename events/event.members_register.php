<?php

	require_once(TOOLKIT . '/class.event.php');

	Class eventmembers_Register extends Event{

		const ROOTELEMENT = 'members-register';

		public static function about(){
			return array(
					 'name' => 'Members: Register',
					 'author' => array(
							'name' => 'Symphony Team',
							'website' => 'http://symphony-cms.com',
							'email' => 'team@symphony-cms.com'),
					 'version' => '1.0',
					 'release-date' => '2011-03-05T02:35:13+00:00',
					 'trigger-condition' => 'action[members-register]');
		}

		public static function getSource(){
			return extension_Members::getMembersSection();
		}

		public static function allowEditorToParse(){
			return false;
		}

		/**
		 * @todo This documentation needs to be figured out
		 */
		public static function documentation(){
			return '
				<p>This event allows new members to register.</p>
				<h3>Example Front-end Form Markup</h3>
				<p>This is an example of the form markup you can use on your front end. Be sure to adjust the inputs and field names to correspond to your own member section.</p>
				<pre class="XML"><code>&lt;form method="post" action=""&gt;
	&lt;label&gt;Name
		&lt;input name="fields[name]" type="text" /&gt;
	&lt;/label&gt;
	&lt;label&gt;Username
		&lt;input name="fields[username]" type="text" /&gt;
	&lt;/label&gt;
	&lt;label&gt;Password
		&lt;input name="fields[password]" type="password" /&gt;
	&lt;/label&gt;
	&lt;label&gt;Email Address
		&lt;input name="fields[email-address]" type="text" /&gt;
	&lt;/label&gt;
	&lt;label&gt;Timezone Offset
		&lt;input name="fields[timezone-offset]" type="text" /&gt;
	&lt;/label&gt;
	&lt;input name="action['.self::ROOTELEMENT.']" type="submit" value="Submit" /&gt;
&lt;/form&gt;</code></pre>
				<h3>Example Response XML</h3>
				<p>On success...</p>
				<pre class="XML"><code>&lt;'.self::ROOTELEMENT.' id="{new member id}" result="success" type="created"&gt;
	&lt;filter name="permission" status="passed" /&gt;
	&lt;message&gt;Entry created successfully.&lt;/message&gt;
	&lt;post-values&gt;
		&lt;!-- User-submitted POST values --&gt;
		&lt;role&gt;ID of default new member role&lt;/role&gt;
	&lt;/post-values&gt;
&lt;/'.self::ROOTELEMENT.'&gt;</code></pre>
				<p>On failure...</p>
				<pre class="XML"><code>&lt;'.self::ROOTELEMENT.' result="error"&gt;
	&lt;filter name="permission" status="{passed | failed}" /&gt;
	&lt;message&gt;Entry encountered errors when saving.&lt;/message&gt;
	&lt;field-name type="{invalid | missing}" message="{Field validation message}" /&gt;
	&lt;post-values&gt;
		&lt;!-- User-submitted POST values --&gt;
		&lt;role&gt;ID of default new member role&lt;/role&gt;
	&lt;/post-values&gt;
&lt;/'.self::ROOTELEMENT.'&gt;</code></pre>
			';
		}

		public function load(){
			if(isset($_POST['action']['members-register'])) return $this->__trigger();
		}

		protected function __trigger(){
			$fieldManager = new FieldManager(Symphony::Engine());
			if(!is_null($fieldManager->fetch(extension_Members::getConfigVar('role')))) {
				$role = $fieldManager->fetch(extension_Members::getConfigVar('role'));
				$_POST['fields'][$role->get('element_name')] = $role->get('default_role');
			}

			include(TOOLKIT . '/events/event.section.php');
			return $result;
		}
	}
