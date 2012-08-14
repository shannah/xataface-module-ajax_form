//require <xataface/modules/ajax_form/AjaxForm.js>
(function(){
	var $ = jQuery;
	var ajax_form = XataJax.load('xataface.modules.ajax_form');
	var AjaxForm = ajax_form.AjaxForm;
	var Portal = ajax_form.Portal;
	
	registerXatafaceDecorator(function(node){
		
		$('form.xf-ajax-form', node).each(function(){
			
			var form = new AjaxForm({
				el: this
			});
		});
	
	});

})();