<form action="{devblocks_url}{/devblocks_url}" id="frmWorkspacePage{$page->id}" method="POST" style="margin-top:5px;">
	<input type="hidden" name="c" value="internal">
	<input type="hidden" name="a" value="">
	<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

	{$menu_json = DAO_WorkerPref::get($active_worker->id, 'menu_json', json_encode(array()))}
	{$menu = json_decode($menu_json, true)}
	{$in_menu = in_array($page->id, $menu)}
	
	<div style="float:left;">
		<h2>{$page->name}</h2>
	</div>

	<div style="float:right;">
		<button class="add" type="button" page_id="{$page->id}" page_label="{$page->name|lower}" page_url="{devblocks_url}c=pages&page={$page->id}-{$page->name|devblocks_permalink}{/devblocks_url}">{if $in_menu}<span class="glyphicons glyphicons-circle-minus" style="color:rgb(200,0,0);"></span>{else}<span class="glyphicons glyphicons-circle-plus" style="color:rgb(0,180,0);"></span>{/if} Menu</button>
	
		<div style="display:inline-block;">
			<button class="config-page split-left" type="button"><span class="glyphicons glyphicons-cogwheel"></span></button><!--
			--><button class="config-page split-right" type="button"><span class="glyphicons glyphicons-chevron-down" style="font-size:12px;color:white;"></span></button>
			<ul class="cerb-popupmenu cerb-float">
				{if Context_WorkspacePage::isWriteableByActor($page, $active_worker)}
					<li><a href="javascript:;" class="edit-page">Edit Page</a></li>
					{if $page->extension_id == 'core.workspace.page.workspace'}<li><a href="javascript:;" class="edit-tab">Edit Tab</a></li>{/if}
				{/if}
				<li><a href="javascript:;" class="export-page">Export Page</a></li>
				{if $page->extension_id == 'core.workspace.page.workspace'}<li><a href="javascript:;" class="export-tab">Export Tab</a></li>{/if}
			</ul>
		</div>
	</div>

	<div style="clear:both;"></div>
</form>

<div style="margin-top:5px;">
	{if $page_extension instanceof Extension_WorkspacePage}
		{$page_extension->renderPage($page)}
	{/if}
</div>

<script type="text/javascript">
	$(function() {
		var $workspace = $('#frmWorkspacePage{$page->id}');
		var $frm = $('form#frmWorkspacePage{$page->id}');
		var $menu = $frm.find('ul.cerb-popupmenu');
		
		// Menu
		
		$menu
			.hover(
				function() {
				},
				function() {
					$(this).hide();
				}
			)
			;
		
		$menu.find('> li').click(function(e) {
			if($(e.target).is('a'))
				return;
			
			e.stopPropagation();
			$(this).find('> a').click();
		});
		
		$menu.siblings('button.config-page').click(function(e) {
			var $menu = $(this).siblings('ul.cerb-popupmenu');
			$menu.toggle();
			
			if($menu.is(':visible')) {
				var $div = $menu.closest('div');
				$menu.css('left', $div.position().left + $div.outerWidth() - $menu.outerWidth());
			}
		});
		
		// Edit workspace actions
		
		{if Context_WorkspacePage::isWriteableByActor($page, $active_worker)}
			// Edit page
			$workspace.find('a.edit-page').click(function(e) {
				e.stopPropagation();
				
				$popup = genericAjaxPopup('peek','c=pages&a=showEditWorkspacePage&id={$page->id}',null,true,'600');
				$popup.one('workspace_save',function(e) {
					window.location.href = '{devblocks_url}c=pages&id={$page->id}-{$page->name|devblocks_permalink}{/devblocks_url}';
				});
				$popup.one('workspace_delete',function(e) {
					window.location.href = '{devblocks_url}c=pages{/devblocks_url}';
				});
			});
			
			// Edit tab
			$workspace.find('a.edit-tab').click(function(e) {
				e.stopPropagation();
				
				var $tabs = $("#pageTabs{$page->id}");
				var $selected_tab = $tabs.find('li.ui-tabs-active').first();
				
				if(0 == $selected_tab.length)
					return;
				
				var tab_id = $selected_tab.attr('tab_id');
				
				if(null == tab_id)
					return;
				
				var $popup = genericAjaxPopup('peek','c=pages&a=showEditWorkspaceTab&id=' + tab_id,null,true,'600');
				
				$popup.one('workspace_save',function(json) {
					if(0 != $tabs) {
						var selected_idx = $tabs.tabs('option','active');
						$tabs.tabs('load', selected_idx);
						
						if(null != json.name) {
							var $selected_tab = $tabs.find('> ul > li.ui-tabs-active');
							$selected_tab.find('a').text(json.name);
						}
					}
				});
				
				$popup.one('workspace_delete',function(e) {
					if(0 != $tabs.length) {
						var tab = $tabs.find('.ui-tabs-nav li:eq(' + $tabs.tabs('option','active') + ')').remove();
						var panelId = tab.attr('aria-controls');
						$('#' + panelId).remove();
						$tabs.tabs('refresh');
					}
				});
			});
		{/if}
		
		// Export page
		$workspace.find('a.export-page').click(function(e) {
			e.stopPropagation();
			genericAjaxPopup('peek','c=pages&a=showExportWorkspacePage&id={$page->id}',null,true,'600');
		});
		
		// Export tab
		$workspace.find('a.export-tab').click(function(e) {
			e.stopPropagation();
			
			var $tabs = $("#pageTabs{$page->id}");
			var $selected_tab = $tabs.find('li.ui-tabs-active').first();
			
			if(0 == $selected_tab.length)
				return;
			
			var tab_id = $selected_tab.attr('tab_id');
			
			if(null == tab_id)
				return;
			
			genericAjaxPopup('peek','c=pages&a=showExportWorkspaceTab&id=' + encodeURIComponent(tab_id),null,true,'600');
		});
		
		// Add/Remove in menu
		$workspace.find('button.add').click(function(e) {
			var $this = $(this);
		
			var $menu = $('BODY UL.navmenu:first');
			var $item = $menu.find('li.drag[page_id="'+$this.attr('page_id')+'"]');
			
			// Remove
			if($item.length > 0) {
				// Is the page already in the menu?
				$item.css('visibility','hidden');
				
				if($item.length > 0) {
					$item.effect('transfer', { to:$this, className:'effects-transfer' }, 500, function() {
						$(this).remove();
					});
					
					$this.html('<span class="glyphicons glyphicons-circle-plus" style="color:rgb(0,180,0);"></span> Menu');
				}
				
				genericAjaxGet('', 'c=pages&a=doToggleMenuPageJson&page_id=' + $this.attr('page_id') + '&toggle=0');
				
			// Add
			} else {
				var $li = $('<li class="drag"/>').attr('page_id',$this.attr('page_id'));
				$li.append($('<a/>').attr('href',$this.attr('page_url')).text($this.attr('page_label')));
				$li.css('visibility','hidden');
				
				var $marker = $menu.find('li.add');
		
				if(0 == $marker.length) {
					$li.prependTo($menu);
					
				} else {
					$li.insertBefore($marker);
					
				}
				
				$this.effect('transfer', { to:$li, className:'effects-transfer' }, 500, function() {
					$li.css('visibility','visible');
				});
				
				$this.html('<span class="glyphicons glyphicons-circle-minus" style="color:rgb(200,0,0);"></span> Menu');
		
				genericAjaxGet('', 'c=pages&a=doToggleMenuPageJson&page_id=' + $this.attr('page_id') + '&toggle=1');
			}
		});
		
	});
</script>
