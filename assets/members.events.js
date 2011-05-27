jQuery(document).ready(function() {

	Symphony.Language.add({
		'Event updated at {$time}. <a href="{$new_url}" accesskey="c">Create another?</a> <a href="{$url}" accesskey="a">View all Events</a>': false,
		'An error occurred while processing this form.': false
	});

	Symphony.Members = {
		memberEventSave: function() {
			jQuery.ajax({
				type: 'post',
				url: Symphony.Context.get('root') + '/symphony/extension/members/events/',
				async: false,
				data: jQuery('form').serialize(),
				success: function(data, response) {
					if(response == "success") {
						Symphony.Message.post(
							Symphony.Language.get('Event updated at {$time}. <a href="{$new_url}" accesskey="c">Create another?</a> <a href="{$url}" accesskey="a">View all Events</a>', {
								time: jQuery(data).find('timestamp').text(),
								new_url: Symphony.Context.get('root') + '/symphony/blueprints/events/new/',
								url: Symphony.Context.get('root') + '/symphony/blueprints/components/'
							}),
							'success'
						);
					}
					else {
						Symphony.Message.post(
							Symphony.Language.get('An error occurred while processing this form.'),
							'error'
						);
					}
					Symphony.Members.applyMessage();
				},
				error: function(data, response) {
					Symphony.Message.post(
						Symphony.Language.get('An error occurred while processing this form.'),
						'error'
					);
					Symphony.Members.applyMessage();
				}
			});

			return false;
		},
		applyMessage: function() {
			// Dim system messages
			Symphony.Message.fade('silence', 10000);

			// Relative times in system messages
			jQuery('abbr.timeago').each(function() {
				var time = jQuery(this).parent();
				time.html(time.html().replace(Symphony.Language.get('at') + ' ', ''));
			});
			Symphony.Message.timer();
		}
	}

	// Save our the Event's email preference
	jQuery('form').live('submit', Symphony.Members.memberEventSave);
});
