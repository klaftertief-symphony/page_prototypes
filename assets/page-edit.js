jQuery(document).ready(function($) {
	var $referenced = $('#page_templates-page_template_referenced'),
		$templates = $('#page_templates-page_template_id'),
		referenced = $referenced.is(':checked'),
		template_id = $templates.val(),
		$params = $('input[name=fields\\[params\\]]'),
		$type = $('input[name=fields\\[type\\]]'),
		$events = $('select[name=fields\\[events\\]\\[\\]]'),
		$data_sources = $('select[name=fields\\[data_sources\\]\\[\\]]'),
		$fixables = $params.add($type).add($events).add($data_sources),
		$tags = $('.tags > li'),
		$submit = $('input[type="submit"]');
	
	if (referenced) {
		$fixables.attr('disabled', 'disabled');
		$tags.addClass(' ');
	};
	
	$referenced.change(function() {
		if ($(this).is(':checked')) {
			$fixables.attr('disabled', 'disabled');
			$tags.addClass(' ');
		} else {
			$fixables.removeAttr('disabled');
			$tags.removeAttr('class');
		}
	});
	
	$templates.change(function() {
		template_id = $(this).val();
		if (template_id > 0) {
			$.getJSON(Symphony.WEBSITE + '/symphony/extension/page_templates/ajaxpagetemplates/' + template_id + '/', function(json, textStatus) {
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
	});
	
	$submit.mousedown(function() {
		$fixables.removeAttr('disabled');
	});
});
