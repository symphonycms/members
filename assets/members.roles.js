/**
 * This code is fairly crude and definitely needs a lookover and tidy to fit
 * with the Symphony pseudo standard!
 * @todo Attack this code.
 */
jQuery(document).ready(function(){

	jQuery("form").submit(function() {
	  if(jQuery("#with-selected").val() == "delete"){
	    return confirm('There are still members in this role, are you sure you want to delete the role and all associated members?');
	  }
	});

	$ = jQuery;

	$.fn.setSliderValue = function() {
		this.each(function(){
			$(this).siblings(".slider").slider("option", "value", $(this).val());
		});
	}

	var permissions = Symphony.Context.get('members-roles');

	$(".global-slider").slider({
		range: "min",
		value: 0,
		min: 0,
		max: 2,
		step: 1,
		slide: function(event, ui) {
			var $self = $(this),
				$parent = $('td.' + $self.parents("td").attr("class"));

			$parent.find('.slider').slider('option', 'value', ui.value);
			$parent.find('span').text(permissions[ui.value]).attr("class", "perm-" + ui.value);

			$parent.find('input').val(ui.value);
			$self.siblings("span").text(permissions[ui.value]).attr("class", "perm-" + ui.value);
		}
	});

	$(".slider").slider({
		range: "min",
		min: 0,
		max: 2,
		step: 1,
		slide: function(event, ui) {
			var $self = $(this);

			$self.siblings("span").text(permissions[ui.value]).attr("class", "perm-" + ui.value);
			$self.siblings("input").val(ui.value);
			$("td." + $self.parents("td").attr("class") + " .global-slider").slider('option', 'value', 0);
		}
	});

	$(".global .add input[type='checkbox']").change(function() {
		$(".add input").attr("checked", $(this).attr('checked'));
	});

	$(".edit input").setSliderValue();

	$("td:has(input) span").each(function() {
		var $self = $(this),
			permission = $self.siblings('input').val();

		$self
			.addClass("perm-" + permission)
			.text(permissions[permission]);
	});

});
