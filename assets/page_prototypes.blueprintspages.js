jQuery(document).ready(function($) {
	var $prototypes = $('#prototype_pages-prototype_id'),
		prototype_id = $prototypes.val(),
		$params = $('input[name="fields[params]"]'),
		$type = $('input[name="fields[type]"]'),
		$events = $('select[name="fields[events][]"]'),
		$data_sources = $('select[name="fields[data_sources][]"]'),
		$fixables = $params.add($type).add($events).add($data_sources),
		$tags = $('.tags'),
		$submit = $('input[type="submit"]');

	$prototypes.change(function() {
		updateFields($(this).val());
	}).change();

	$type.bind('keyup change focusout', function() {
		updatePrototypes($(this).val());
	}).change();

	// Set field values from selected prototype and dis- or enable fields
	function updateFields(prototype_id) {
		$tags.symphonyTags();
		if (prototype_id) {
			$fixables.attr('disabled', 'disabled');
			$tags.undelegate('li', 'click.tags');
			$.getJSON(
				Symphony.Context.get('root') + '/symphony/extension/page_prototypes/ajaxpageprototypes/',
				{prototype_id: prototype_id},
				function(json, textStatus) {
					$params.val(json.params);
					$type.val(json.type.join(', '));
					$events.find('option').each(function(index) {
						var $this = $(this);
						if ($.inArray($this.val(), json.events) != -1) {
							$this.attr('selected', 'selected');
						} else {
							$this.removeAttr('selected');
						};
					});
					$data_sources.find('option').each(function(index) {
						var $this = $(this);
						if ($.inArray($this.val(), json.data_sources) != -1) {
							$this.attr('selected', 'selected');
						} else {
							$this.removeAttr('selected');
						};
					});
				}
			);
		} else {
			$fixables.removeAttr('disabled');
			$tags.symphonyTags();
		};
	}

	// Dis- or enable prototype select box
	function updatePrototypes(value) {
		var types = value.split(',');

		types = $.map(types, function(item, index) {
			return $.trim(item);
		});

		if ($.inArray('prototype', types) != -1) {
			$prototypes.attr('disabled', 'disabled');
		} else {
			$prototypes.removeAttr('disabled');
		}
	}
});
