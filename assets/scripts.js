jQuery(document).ready(function(){
	jQuery("form").submit(function() {
	  if(jQuery("#with-selected").val() == "delete"){
	    return confirm('There are still members in this role, are you sure you want to delete the role and all associated members?');
	  }
	});
})