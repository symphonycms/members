jQuery(document).ready(function(){

	var role_permissions = jQuery('table.role-permissions');
	
	// find columns containing form elements
	role_permissions.find('thead th.new, thead th.edit')
	.each(function() {
		// append element for styling
		var text = jQuery(this).text();
		jQuery(this).html('<span>' + text + '</span>');
	})
	.bind('click', function() {
		var index = jQuery(this).index();
		// toggle whether this column is all-on or all-off
		var check_all = jQuery(this).toggleClass('checked').hasClass('checked');
		// remove toggle state from all other columns
		role_permissions.find('th:not(:eq('+index+'))').removeClass('checked');
		// toggle form elements in this column ("active" rows only)
		role_permissions.find('tbody tr:not(.inactive) td:nth-child(' + (index + 1) + ')').each(function() {
			if(check_all) {
				jQuery(this).find('input').attr('checked', 'checked');
			} else {
				jQuery(this).find('input').removeAttr('checked');
			}
		});
	});
	
	// disable form elements of "inactive" rows
	role_permissions.find('tr.inactive input').attr('disabled', 'disabled').removeAttr('checked');

});
