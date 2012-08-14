//require <jquery.packed.js>
(function(){
	
	var $ = jQuery;
	
	$(document).ready(function(){
	
		$('#form-wrapper').load(DATAFACE_SITE_HREF+'?-action=ajax_form_html&--time='+(Math.random()), function(res){
			
			decorateXatafaceNode(this);
			
			
		});
	});

})();