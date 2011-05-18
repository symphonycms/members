jQuery(document).ready(function() {
	var EVENTS_NEW_URL = Symphony.Context.get('root') + '/symphony/blueprints/events/new/',
		EVENTS_URL = Symphony.Context.get('root') + '/symphony/blueprints/components/';

	// Save our the Event's email preference
	jQuery('form').live('submit', function() {
		jQuery.ajax({
			type: 'post',
			url: Symphony.Context.get('root') + '/symphony/extension/members/emailtemplates/',
			async: false,
			data: jQuery('form').serialize(),
			success: function(data, response) {
				if(response == "success") {
					Symphony.Message.post('Event updated. <a href="' + EVENTS_NEW_URL + '" accesskey="c">Create another?</a> <a href="' + EVENTS_URL + '" accesskey="a">View all Events</a>', 'success');
				}
				else {
					Symphony.Message.post('An error occurred while processing this form.', 'error');
				}
			},
			error: function(data, response) {
				Symphony.Message.post('An error occurred while processing this form.', 'error');
			}
		});

		return false;
	});
});
