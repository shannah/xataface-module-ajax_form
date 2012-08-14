//require <jquery.packed.js>
//require <xatajax.core.js>
/**
 
 
 Features:
 
 1. Single row loading possible.
 2. 
 
 
*/
(function(){
	
	var $ = jQuery;
	var ajax_form = XataJax.load('xataface.modules.ajax_form');
	ajax_form.AjaxList = AjaxList;
	
	
	function AjaxList(o){
		var self = this;
		this.el = o.el;
		$(this.el).data('xataface.modules.ajax_form.AjaxList', this);
		this.relationshipName = null;
		this.tableName = null;
		this.recordId = null;
		this.start = 0;
		this.limit = 30;
		this.changed = false;
		this.requiresRefresh = false;
		
		this.pendingRemovals = [];
		this.addFormDiv = null;
		
		
		if ( $(this.el).attr('data-xf-start') ){
			try {
				this.start = parseInt($(this.el).attr('data-xf-start'));
			} catch (e){}
		}
		
		if ( $(this.el).attr('data-xf-limit') ){
			try {
				this.limit = parseInt($(this.el).attr('data-xf-limit'));
			} catch (e){}
		}
		
		if ( $(this.el).attr('data-xf-relationship') ){
			this.relationshipName = $(this.el).attr('data-xf-relationship');
		}
		
		if ( $(this.el).attr('data-xf-table') ){
			this.tableName = $(this.el).attr('data-xf-table');
		}
		
		if ( $(this.el).attr('data-xf-record-id') ){
			this.recordId = $(this.el).attr('data-xf-record-id');
		}
		
		$(this.el).children('tbody').children('tr').each(function(){
			self.decorateRow(this);
		});
		
		var btn = $('<button>').text('Add Row');
		btn.click(function(){
			
			self.showAddForm();
		
		});
		btn.insertAfter(this.el);
		
	
	}
	
	
	(function(){
	
		$.extend(AjaxList.prototype, {
			countColumns: countColumns,
			isRelatedList: isRelatedList,
			setStart: setStart,
			getStart: getStart,
			setLimit: setLimit,
			getLimit: getLimit,
			setQuery: setQuery,
			getQuery: getQuery,
			hideColumn: hideColumn,
			showColumn: showColumn,
			sort: sort,
			selectRow: selectRow,
			deselectRow: deselectRow,
			isRowSelected: isRowSelected,
			getSelectedRows: getSelectedRows,
			getRowRecordId: getRowRecordId,
			editRow: editRow,
			showAddForm: showAddForm,
			removeRow: removeRow,
			removeRows: removeRows,
			removeSelectedRows: removeSelectedRows,
			revert:revert,
			refresh: refresh,
			commit: commit,
			isChanged: isChanged,
			setChanged: setChanged,
			isRefreshRequired: isRefreshRequired,
			setRefreshRequired: setRefreshRequired,
			getTemplateHtml: getTemplateHtml,
			decorateRow: decorateRow
		
		
		});
		
		
		/**
		 * @param Checks to see if this list is a related list.
		 */
		function isRelatedList(){
			return $(this.el).hasClass('xf-ajax-related-list');
		}
		
		/**
		 * @description Sets the starting point that is being displayed
		 * in this list.
		 *
		 */
		function setStart(start){
			if ( start != this.start ){
				this.start = start;
				this.setChanged(true);
			}
			
		}
		
		/**
		 * @description Returns the number of columns in the table.  This includes
		 * any non-data columns.  It is useful if you need to create a cell that
		 * spans the entire table.  
		 *
		 * Note:  This takes into account any columns that span multiple columns
		 * so that if the table has one column where the cells use colspan=10, then
		 * this method will return 10 and not 1.
		 */
		function countColumns(){
			
			var i = 0;
			$(this.el).children('thead').children('tr').first().children().each(function(){
				var colspan = 1;
				if ( $(this).attr('colspan') ) colspan = parseInt($(this).attr('colspan'));
				i += colspan;
			});
			return i;
		
		}
		
		function getStart(){}
		function setLimit(limit){}
		function getLimit(){}
		function setQuery(query){}
		function getQuery(){}
		function hideColumn(name){}
		function showColumn(name){}
		function sort(columns){}
		function selectRow(tr){}
		function deselectRow(tr){}
		function isRowSelected(tr){}
		function getSelectedRows(){}
		
		function decorateRow(tr){
			var self = this;
			$(tr).click(function(){
				self.editRow(this);
			});
		}
		
		function getRowRecordId(tr){
			return $(tr).attr('data-xf-record-id');
		}
		
		function editRow(tr){
			var self = this;
			var editCell = $('<td colspan="'+this.countColumns()+'"/>')
				.addClass('xf-ajax-list-edit-cell');
			var editRow = $('<tr>').append(editCell).insertAfter(tr);
			
			// TODO: load the portal from the server and add a submit
			// handler to the contained form.
			
			var q = {};
			
			if ( this.isRelatedList() ){
				$.extend(q, {
					'-action': 'ajax_form_load_portal',
					'--recordId': this.getRowRecordId(tr)
				});
			} else {
			
				$.extend(q, {
					'-action': 'ajax_form_html',
					'-table': this.tableName,
					'--recordId': this.getRowRecordId(tr)
				
				});
			}
			
			var waitMessage = $('<div class="xf-please-wait">Loading ... Please Wait ...</div>');
			
			var formWrapper = $('<div>').hide();
			editCell.append(waitMessage).append(formWrapper);
			formWrapper.load(DATAFACE_SITE_HREF, q, function(res){
				$(waitMessage).remove();
				$(this).slideDown();
				decorateXatafaceNode(this);
				
				$('form', this).each(function(){
					var form = $(this).data('xataface.modules.ajax_form.AjaxForm');
					$(form).bind('afterSaveReload', function(e,data){
						
						$(editRow).remove();
						// Prepare to load the added record as a single row
						var q = {
							'-action': 'ajax_form_list',
							'-table': self.tableName,
							'--templateHtml': self.getTemplateHtml(),
							'--single-row': self.getRowRecordId(tr)
						
						};
						
						if ( self.isRelatedList() ){
							// If it is a related list we need to pass these in
							// the query parameters.
							
							$.extend(q, {
								'-relationship': self.relationshipName
							});
							
						}
						
						
						// Issue post request to retrieve the row that was added.
						// We issue a post request because the --templateHtml parameter
						// could potentially be very large and GET parameters
						// limit the size of query parameters.
						$.post(DATAFACE_SITE_HREF, q, function(res){
						
							// This should return an entire table but with only one
							// row.
							// Go through each row and add it to the existing table.
							
							$('tbody > tr', res).each(function(){
							
								// First decorate the row (xataface callback).
								decorateXatafaceNode(this);
								
								// Next we do our own row decoration.  This 
								// shouldn't be covered by the decorateXatafaceNode()
								// call because the global decorate hook only looks
								// for ajax-lists (the full table), not individual rows.
								
								self.decorateRow(this);
								
								// Now we append the row to the table.
								$(tr).replaceWith(this);
								
							});
							
						});
						
						
						
						
					});
				});
				
			
			});
			
			
		
		}
		
		/**
		 * @description Retrieves the HTML template that was used to 
		 * construct this list.  An HTML template is really just a collection
		 * of HTML <input> elements whose names correspond to fields
		 * that should be included in the table.
		 *
		 * This method generates it based on the columns currently in the table.
		 */
		function getTemplateHtml(){
			var columns = [];
			$(this.el).children('thead').find('th[data-xf-column-field]').each(function(){
				columns.push($(this).attr('data-xf-column-field'));
			});
			var out = [];
			out.push('<form>');
			$.each(columns, function(k,v){
				out.push('<input type="text" name="'+v+'"/>');
			});
			out.push('</form>');
			return out.join("\r\n");
		}
		
		/**
		 * @description  Shows the form to add a new record to this list.
		 */
		function showAddForm(){
			var self = this;
			if ( this.addFormDiv != null ){
				// The add form is already showing.
				return;
			}
			
			
			
			this.addFormDiv = $('<div>')
				.addClass('xf-ajax-list-new-form-wrapper')
				.insertAfter(this.el);
			
			var q = {};
			
			if ( this.isRelatedList() ){
				$.extend(q, {
					'-action': 'ajax_form_new_related_record',
					'-table': this.tableName,
					'-relationship': this.relationshipName,
					'--recordId': this.recordId
				});
			} else {
			
				$.extend(q, {
					'-action': 'ajax_form_html',
					'-table': this.tableName
				
				});
			}
			
			// Load the add form
			$(this.addFormDiv).load(DATAFACE_SITE_HREF, q, function(res){
				// Decorate the add form once it has been loaded
				decorateXatafaceNode(self.addFormDiv);
				
				// Go through each <form> tag in the loaded content
				// and attach an afterSave handler to the AjaxForm object.
				// (The AjaxForm object should have been created and attached
				// in the decorateXatafaceNode step.
				$('form', self.addFormDiv).each(function(){
					var form = $(this).data('xataface.modules.ajax_form.AjaxForm');
					
					// Bind the afterSave handler to the form so that the form
					// will be removed after a save is complete, and the
					// resulting record will be added to the list.
					$(form).bind('afterSaveReload', function(e, data){
						
						// After a save is complete we should update the row
						
						// Remove the form
						$(self.addFormDiv).remove();
						self.addFormDiv = null;
						
						
						// Prepare to load the added record as a single row
						var q = {
							'-action': 'ajax_form_list',
							'-table': self.tableName,
							'--templateHtml': self.getTemplateHtml()
						
						};
						
						
						
						if ( self.isRelatedList() ){
							// If it is a related list we need to pass these in
							// the query parameters.
							
							$.extend(q, {
								'-relationship': self.relationshipName,
								'--single-row': data.relatedIds[self.relationshipName][0]
							});
							
						} else {
							// If it is not a related list, we just pass the record id
							// that was added.
							
							$.extend(q, {
								'--single-row': data.recordId
							});
						}
						
						
						// Issue post request to retrieve the row that was added.
						// We issue a post request because the --templateHtml parameter
						// could potentially be very large and GET parameters
						// limit the size of query parameters.
						$.post(DATAFACE_SITE_HREF, q, function(res){
						
							// This should return an entire table but with only one
							// row.
							// Go through each row and add it to the existing table.
							
							$('tbody > tr', res).each(function(){
							
								// First decorate the row (xataface callback).
								decorateXatafaceNode(this);
								
								// Next we do our own row decoration.  This 
								// shouldn't be covered by the decorateXatafaceNode()
								// call because the global decorate hook only looks
								// for ajax-lists (the full table), not individual rows.
								
								self.decorateRow(this);
								
								// Now we append the row to the table.
								$(self.el).append(this);
							});
							
						});
						
					});
				
				});
			
			});
		
		}
		
		/**
		 * @description Removes a row from this list.  The actual
		 * removal won't take place until commit() is called.  In the 
		 * interim, the row will be stored in the pendingRemovals array
		 *
		 * This doesn't actually remove the row from the DOM either.  It just
		 * marks it as removed with the xf-ajax-list-pending-removal CSS
		 * class.
		 *
		 * This also marks the list as changed.
		 */
		function removeRow(tr){
			this.pendingRemovals.push(tr);
			$(tr).hide();
			$(tr).addClass('xf-ajax-list-pending-removal');
			this.setChanged(true);
		}
		
		/**
		 * @description Removes multiple rows from the table.
		 */
		function removeRows(rows){
			var self = this;
			$.each(rows, function(i,row){
				self.removeRow(row);
			});
		}
		
		/**
		 * @description Removes all selected rows from the table.
		 */
		function removeSelectedRows(rows){
			this.removeRows(this.getSelectedRows());
		}
		
		function revert(){}
		function refresh(){}
		function commit(){}
		
		/**
		 * @description Checks if there are unsaved changes in this list.
		 */
		function isChanged(){
			return this.changed;
		}
		function setChanged(changed){
			if ( changed != this.changed ){
				this.changed = changed;
				if ( changed ){
					$(this.el).addClass('xf-ajax-list-changed');
				} else {
					$(this.el).removeClass('xf-ajax-list-changed');
				}
			}
		}
		
		/**
		 * @description Checks if the view is out of sync.  This would happen
		 * if we have specified a different query, sort, start, or limit
		 * and it requires a refresh.
		 */
		function isRefreshRequired(){
			return this.requiresRefresh;
		}
		
		
		function setRefreshRequired(req){
			if ( req != this.requiresRefresh ){
				this.requiresRefresh = req;
				if ( req ){
					$(this.el).addClass('xf-ajax-list-refresh-required');
				} else {
					$(this.el).removeClass('xf-ajax-list-refresh-required');
				}
			}
		}
		
		
	})();

})();