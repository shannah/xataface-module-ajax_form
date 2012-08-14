//require <xatajax.core.js>
//require <xataface/modules/ajax_form/AjaxList.js>
(function(){
	var $ = jQuery;
	var ajax_form = XataJax.load('xataface.modules.ajax_form');
	var AjaxList = ajax_form.AjaxList;
	registerXatafaceDecorator(function(node){
		
		$('table.xf-ajax-list', node).each(function(){
			
			new AjaxList({
				el: this
			});
		});
	
	});

})();