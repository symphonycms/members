jQuery(document).ready(function() {
	var notifier = jQuery('#header').find('.notifier');

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
				beforeSend: function() {
					notifier.find('.members').trigger('detach.notify');
				},
				success: function(data, response) {
					if(response === "success") {
						notifier.trigger('attach.notify', [
							Symphony.Language.get('Event updated at {$time}. <a href="{$new_url}" accesskey="c">Create another?</a> <a href="{$url}" accesskey="a">View all Events</a>', {
								time: jQuery(data).find('timestamp').text(),
								new_url: Symphony.Context.get('root') + '/symphony/blueprints/events/new/',
								url: Symphony.Context.get('root') + '/symphony/blueprints/events/'
							}),
							'success members'
						]);
					}
					else {
						notifier.trigger('attach.notify', [
							Symphony.Language.get('An error occurred while processing this form.'),
							'error members'
						]);
					}
				},
				error: function(data, response) {
					notifier.trigger('attach.notify', [
						Symphony.Language.get('An error occurred while processing this form.'),
						'error members'
					]);
				}
			});

			return false;
		}
	};

	// Save our the Event's email preference
	jQuery('form').on('submit', Symphony.Members.memberEventSave);
});
