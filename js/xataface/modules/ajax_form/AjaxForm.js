//require <jquery.packed.js>
//require <xatajax.core.js>
//    require <json/json.js>
//require-css <xataface/modules/ajax_form/AjaxForm.css>
(function(){

	var CODE_VALIDATION_ERROR = 88501;
	var CODE_RECORD_NOT_FOUND = 88404;
	var CODE_PERMISSION_DENIED = 88400;

	var $ = jQuery;
	var ajax_form = XataJax.load('xataface.modules.ajax_form');
	ajax_form.AjaxForm = AjaxForm;
	ajax_form.Portal = Portal;
	
	function AjaxForm(o){
		
		this.el = o.el;
		$(this.el).data('xataface.modules.ajax_form.AjaxForm',this);
		
		this.wrapper = $(this.el).closest('.xf-ajax-form-wrapper');
		if ( this.wrapper.size() == 0 ) this.wrapper = $(this.el).parent();
		this.portals = {};
		this.recordId = $(this.el).attr('data-xf-record-id');
		this.portalRecordId = $(this.el).attr('data-xf-portal-record-id');
		this.tableName = $(this.el).attr('data-xf-tablename');
		this.isNew = parseInt($(this.el).attr('data-xf-new')) ? true:false;
		this.init();
		
	}
	
	(function(){
		$.extend(AjaxForm.prototype, {
			refresh: refresh,
			save: save,
			getPortal: getPortal,
			getPortals: getPortals,
			clear: clear,
			init: init
			
			
			
		});
		
		function refresh(){}
		
		/**
		 * @description Saves the content of the form.
		 */
		function save(callback){
			
			var data = {};
			try {
				$('input,textarea,select', this.el).each(function(){
					var val = $(this).val();
					var name = $(this).attr('name');
					if ( !name ) return;
					if ( name.indexOf('[') != -1 ){
						var parts = name.split('[');
						var node = data;
						while ( parts.length > 0 ){
							var part = parts.shift();
							part = part.replace(']','');
							if ( typeof(node[part]) == 'undefined' && parts.length > 0 ){
								node[part] = {};
							} else if ( parts.length == 0 ){
								node[part] = val;
							}
							if ( parts.length > 0 ){
								node = node[part];
							}
							
							
						}
					} else {
						data[name] = val;
					}
					
				});
			} catch (e){
				console.log(e);
				return false;
			}
			
			
			var q = {
				'-action': 'ajax_form_save',
				'-table': this.tableName,
				'--data': JSON.stringify(data)
			};
			if ( !this.isNew ){
				q['--recordId'] = this.recordId;
			}
			if ( this.portalRecordId ){
				q['-portal-context'] = this.portalRecordId;
			}
			
			var self = this;
			
			var progress = $('<div/>')
				.addClass('xf-ajax-form-progress-pane')
				.width($(self.wrapper).width())
				.height($(self.wrapper).height())
				.css({
					top: $(self.wrapper).offset().top,
					left: $(self.wrapper).offset().left,
					display: 'none'
				
				})
				.append('<span class="xf-progress-please-wait">Saving Changes...</span>')
				
				
				;
			
			$('.xf-progress-please-wait', progress).css({
			
				'margin-top': $(progress).height()/2-20
			});
			$(self.wrapper).append(progress);
			$(progress).fadeIn();
			
			$.post(DATAFACE_SITE_HREF, q, function(res){
				$(progress).fadeOut(function(){
					$(this).remove();
				});
				try {
				
					if ( res.code == 200 ){
						var saveResponse = res;
						$(self).trigger('afterSave', saveResponse);
						if ( saveResponse.preventDefault ){
							return;
						}
						// Record was saved successfully
						// Let's let the user know and reload the form.
						var placeholder = $('<div/>');
						
						placeholder.appendTo(self.wrapper);
						var q = {
							'--recordId': res.recordId,
							'-action': 'ajax_form_html',
							'-table': self.tableName,
							'-portal-context': self.portalRecordId
						};
						
						
						var progressRefresh = $('<div/>')
							.addClass('xf-ajax-form-progress-pane')
							.width($(self.wrapper).width())
							.height($(self.wrapper).height())
							.css({
								top: $(self.wrapper).offset().top,
								left: $(self.wrapper).offset().left,
								display: 'none'
							
							})
							.append('<span class="xf-progress-please-wait">Refreshing form ...</span>')
							
							
							;
						
						$('.xf-progress-please-wait', progressRefresh).css({
						
							'margin-top': $(progressRefresh).height()/2-20
						});
						$(self.wrapper).append(progressRefresh);
						$(progressRefresh).fadeIn();
						placeholder.load(DATAFACE_SITE_HREF, q, function(res){
							decorateXatafaceNode(placeholder.get(0));
							$(self).trigger('afterSaveReload', saveResponse);
							if ( saveResponse.preventDefault ) return;
							$(self.el).remove();
							$(progressRefresh).fadeOut(function(){
								$(this).remove();
							});
						});
						
						
						
						
						
						
					} else if ( res.code == CODE_VALIDATION_ERROR ){
						
						// There was a validation error in trying to save
						// Let's find the field and highlight it
						$('.xf-error-message', self.el).remove();
						$.each(res.fieldErrors, function(key,val){
							$('input[name="'+key+'"]', self.el).each(function(){	
								var fld = this;
								var widgetWrapper = $(fld).closest('.xf-field-widget');
								if ( widgetWrapper.size() == 0 ){
									widgetWrapper = $(fld).parent();
								}
								widgetWrapper
									.addClass('xf-field-validation-error');
								var errorMessage = $('<div>')
									.addClass('xf-field-error-message')
									.addClass('xf-error-message')
									.text(val);
								widgetWrapper.append(errorMessage);
							});
						});
						
						
					}
				} catch (e){
					alert(e);
				}
				
			});
			
			
		
		}
		function getPortal(name){
			return this.portals[name];
		}
		function getPortals(){
			return this.portals;
		}
		
		function clear(){}
		
		function init(){
			var self = this;
			$('.xf-portal', this.wrapper).each(function(){
				self.portals[$(this).attr('data-xf-relationship')] = new Portal({
					el: this,
					parentForm: self
				});
			});
			
			$(this.el).submit(function(){
				self.save();
				return false;
			});
			
			$(this.el).append('<button>Submit</button>');
		
		}
		
		
		
	})();
	
	
	function Portal(o){
		this.parentForm = o.parentForm;
		this.loaded = false;
		this.el = o.el;
		this.name = $(this.el).attr('data-xf-relationship');
		this.label = $(this.el).attr('data-xf-portal-label');
		this.minCardinality = parseInt($(this.el).attr('data-xf-min-cardinality'));
		this.maxCardinality = $(this.el).attr('data-xf-max-cardinality');
		try {
			this.maxCardinality = parseInt(this.maxCardinality);
		} catch (e){}
		
		this.multiplicity = $(this.el).attr('data-xf-multiplicity');
		this.init();
		
	
	}
	
	
	(function(){
	
		$.extend(Portal.prototype, {
		
			load: load,
			refresh: refresh,
			save: save,
			collapse:collapse,
			expand: expand,
			toggle: toggle,
			show: show,
			hide: hide,
			openInNewWindow: openInNewWindow,
			init: init,
			getBody: getBody,
			decoratePortal:decoratePortal
		
		});
		
		function load(callback){
			if ( typeof(callback) != 'function' ) callback = function(){};
			var self = this;
			if ( this.loaded ){
				callback.call(this);
				return;
			}
			var q = {
				'-action': 'ajax_form_load_portal',
				'--recordId': this.parentForm.recordId,
				'--name': this.name,
				'-portal-context': this.parentForm.recordId
			};
			$(this.getBody()).load(DATAFACE_SITE_HREF, q, function(res){
				decorateXatafaceNode(this);
				self.decoratePortal(this);
				if ( typeof(callback)== 'function' ) callback.call(self);
			});
			
		}
		
		function decoratePortal(node){
			var self = this;
			$('form', node).each(function(){
				var form = $(this).data('xataface.modules.ajax_form.AjaxForm');
				$(form).bind('afterSave', function(e,data){
				
					self.refresh();
				});
			});
		}
		
		function refresh(callback){
			this.loaded = false;
			this.load(callback);
		
		}
		function save(){}
		
		function getBody(){
			var body = $(this.el).children('fieldset').children('.xf-portal-body').get(0);
			if ( !body ) body = $(this.el).children('fieldset').get(0);
			if ( !body ) body = $(this.el).chidren().get(0);
			return body;
		}
		
		function collapse(callback){
			$(this.el).addClass('collapsed');
			$(this.getBody()).slideUp();
			if ( typeof(callback) != 'function' ) callback = function(){};
			callback.call(this);
		}
		function expand(callback){
			$(this.el).removeClass('collapsed');
			$(this.getBody()).slideDown();
			this.load(callback);
		}
		
		function toggle(callback){
			if ( $(this.el).hasClass('collapsed') ){

				this.expand(callback);
			} else {
				
				this.collapse(callback);
				
			}
		}
		
		function show(callback){
			$(this.getBody()).show();
			this.load(callback);
			
				
		}
		
		
		
		
		function hide(callback){
			$(this.el).hide();
			if ( typeof(callback) != 'function' ) callback = function(){};
			callback.call(this);
		}
		function openInNewWindow(){}
		
		
		function init(){
			var self = this;
			$(this.el).addClass('collapsed');
			var legend = $(this.el).children('fieldset').children('legend');
			legend.click(function(){
				self.toggle();
			});
		
		}
		
	})();
	
	
	
	

})();