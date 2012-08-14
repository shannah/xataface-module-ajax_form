//require <xatajax.core.js>
//require <xataface/modules/ajax_form/AjaxList.js>
(function(){
	var $ = jQuery;
	var ajax_form = XataJax.load('xataface.modules.ajax_form');
	var AjaxList = ajax_form.AjaxList;
	
	//
	
	
		
	
	registerXatafaceDecorator(function(node){
		$('div.search_form form').append('<input type="hidden" name="-action" value="ajax_form_list"/>');
		
		$('table.xf-ajax-list', node).each(function(){
			
			new AjaxList({
				el: this
			});
		});
	
	});

})();