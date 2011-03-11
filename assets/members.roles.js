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
			var $parent = $('.' + $(this).parents("td").attr("class"));

			$parent.find('.slider').slider('option', 'value', ui.value);
			$parent.find('span').text(permissions[ui.value]).attr("class", "perm-" + ui.value);
			$parent.find('input').val(ui.value);

			$(this).siblings("span").text(permissions[ui.value]).attr("class", "perm-" + ui.value);
		}
	});

	$(".slider").slider({
		range: "min",
		min: 0,
		max: 2,
		step: 1,
		slide: function(event, ui) {
			$(this).siblings("span").text(permissions[ui.value]).attr("class", "perm-" + ui.value);
			$(this).siblings("input").val(ui.value);
			$("." + $(this).parents("td").attr("class") + " .global-slider").slider('option', 'value', 0);
			$(".global ." + $(this).parents("td").attr("class") + " span").text('n/a').attr("class", "perm-0");
		}
	});

	$(".global .add input[type='checkbox']").change(function() {
		$(".add input").attr("checked", $(this).attr('checked'));
	});

	$(".edit input, .delete input").setSliderValue();

	$("td span").text(function() {
		return permissions[$(this).siblings("input").val()];
	}).attr("class", function() {
		return "perm-" + $(this).siblings("input").val();
	});
});
