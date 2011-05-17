jQuery(document).ready(function() {
	var EVENTS_NEW_URL = Symphony.Context.get('root') + '/symphony/blueprints/events/new/',
		EVENTS_URL = Symphony.Context.get('root') + '/symphony/blueprints/components/';

	// Given a type (success/error), this function will add to the notice
	// at the top of the Symphony (or create one if it does not exist)
	var addPageNotice = function(type, message) {
		var $header = jQuery("#header"),
			$notice = jQuery("p#notice");

		if($notice.length) {
			$notice
				.addClass(type)
				.html(message);
		}
		else {
			$header.prepend(
				jQuery('<p />')
					.attr('id', 'notice')
					.addClass(type)
					.html(message)
			);
		}

		return false;
	}

	// Save our the Event's email preference
	jQuery('form').live('submit', function() {
		jQuery.ajax({
			type: 'post',
			url: Symphony.Context.get('root') + '/symphony/extension/members/emailtemplates/',
			async: false,
			data: jQuery('form').serialize(),
			success: function(data, response) {
				if(response == "success") {
					addPageNotice('success', 'Event updated. <a href="' + EVENTS_NEW_URL + '" accesskey="c">Create another?</a> <a href="' + EVENTS_URL + '" accesskey="a">View all Events</a>');
				}
				else {
					addPageNotice('error','An error occurred while processing this form.');
				}
			},
			error: function(data, response) {
				addPageNotice('error', 'An error occurred while processing this form.');
			}
		});

		return false;
	});
});
