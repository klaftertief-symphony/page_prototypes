jQuery(document).ready(function($) {
	var $referenced = $('#page_prototypes-page_prototype_referenced'),
		$prototypes = $('#page_prototypes-page_prototype_id'),
		referenced = $referenced.is(':checked'),
		prototype_id = $prototypes.val(),
		$params = $('input[name="fields\\[params\\]"]'),
		$type = $('input[name="fields\\[type\\]"]'),
		$events = $('select[name="fields\\[events\\]\\[\\]"]'),
		$data_sources = $('select[name="fields\\[data_sources\\]\\[\\]"]'),
		$fixables = $params.add($type).add($events).add($data_sources),
		$tags = $('.tags'),
		$submit = $('input[type="submit"]');
	
	if (referenced) {
		$fixables.attr('disabled', 'disabled');
		$tags.undelegate('li', 'click.tags');
	};
	
	if (!prototype_id) {
		$referenced.removeAttr('checked').attr('disabled', 'disabled');
	} else {
		updateFields(prototype_id);
	};
	
	$referenced.change(function() {
		if ($(this).is(':checked')) {
			$fixables.attr('disabled', 'disabled');
			$tags.undelegate('li', 'click.tags');
			$prototypes.trigger('change');
		} else {
			$fixables.removeAttr('disabled');
			$tags.symphonyTags();
		}
	});
	
	$prototypes.change(function() {
		updateFields($(this).val());
	});
	
	$submit.mousedown(function() {
		$fixables.removeAttr('disabled');
	});
	
	function updateFields(prototype_id) {
		if (prototype_id > 0) {
			$.getJSON(Symphony.WEBSITE + '/symphony/extension/page_prototypes/ajaxpageprototypes/' + prototype_id + '/', function(json, textStatus) {
				$referenced.removeAttr('disabled');
				$params.val(json.params);
				$type.val(json.type);
				$('option', $events[0]).each(function(index) {
					var $this = $(this);
					if ($.inArray($this.val(), json.events) != -1) {
						$this.attr('selected', 'selected');
					} else {
						$this.removeAttr('selected');
					};
				});
				$('option', $data_sources[0]).each(function(index) {
					var $this = $(this);
					if ($.inArray($this.val(), json.data_sources) != -1) {
						$this.attr('selected', 'selected');
					} else {
						$this.removeAttr('selected');
					};
				});
			});
		} else {
			$referenced.removeAttr('checked').attr('disabled', 'disabled');
			$fixables.removeAttr('disabled');
		};
	}
});
