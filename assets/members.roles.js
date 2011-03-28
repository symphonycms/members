jQuery(document).ready(function(){

	var role_permissions = jQuery('table.role-permissions');
	
	role_permissions.find('thead th').bind('click', function() {
		var index = jQuery(this).index();
		var check_all = jQuery(this).toggleClass('checked').hasClass('checked');
		role_permissions.find('tbody tr td:nth-child(' + (index + 1) + ')').each(function() {
			if(check_all) {
				jQuery(this).find('input').attr('checked', 'checked');
			} else {
				jQuery(this).find('input').removeAttr('checked');
			}
		});
	});

});
