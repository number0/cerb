{$guid = uniqid()}

<form id="frm{$guid}" action="#" style="margin-bottom:5px;width:98%;">
	<div style="float:left;">
		<span style="font-weight:bold;font-size:150%;">{$calendar_properties.calendar_date|devblocks_date:'F Y'}</span>
		{if !empty($calendar)}
			{if !empty($create_contexts)}
			<div style="display:inline-block;margin-left:10px;">
				{if count($create_contexts) > 1}
				<button type="button" class="reply split-left" onclick="$(this).next('button.split-right').click();"><span class="cerb-sprite2 sprite-plus-circle"></span> </button><!--
				--><button type="button" class="split-right" onclick="$ul=$(this).next('ul');$ul.toggle();"><span class="cerb-sprite sprite-arrow-down-white"></span></button>
				<ul class="cerb-popupmenu cerb-float" style="margin-top:-5px;">
					{foreach from=$create_contexts item=create_context}
					<li><a href="javascript:;" class="create-event" context="{$create_context->id}">{$create_context->name}</a></li>
					{/foreach}
				</ul>
				{else}
				{$create_context = reset($create_contexts)}
				<button type="button" class="create-event" context="{$create_context->id}"><span class="cerb-sprite2 sprite-plus-circle"></span></button>
				{/if}
			</div>
			{/if}
			
			<span style="margin-left:10px;">
				Availability based on <a href="{devblocks_url}c=profiles&what=calendar&id={$calendar->id}-{$calendar->name|devblocks_permalink}{/devblocks_url}" style="font-weight:bold;">{$calendar->name}</a>
			</span>
		{/if}
	</div>

	<div style="float:right;">
		<button type="button" onclick="genericAjaxGet($(this).closest('div.ui-tabs-panel'), 'c=internal&a=handleSectionAction&section=calendars&action=showCalendarAvailabilityTab&id={$calendar->id}&month={$calendar_properties.prev_month}&year={$calendar_properties.prev_year}');">&lt;</button>
		<button type="button" onclick="genericAjaxGet($(this).closest('div.ui-tabs-panel'), 'c=internal&a=handleSectionAction&section=calendars&action=showCalendarAvailabilityTab&id={$calendar->id}&month=&year=');">Today</button>
		<button type="button" onclick="genericAjaxGet($(this).closest('div.ui-tabs-panel'), 'c=internal&a=handleSectionAction&section=calendars&action=showCalendarAvailabilityTab&id={$calendar->id}&month={$calendar_properties.next_month}&year={$calendar_properties.next_year}');">&gt;</button>
	</div>
	
	<br clear="all">
</form>

<table cellspacing="0" cellpadding="0" border="0" class="calendar">
<tr class="heading">
	<th>Sun</th>
	<th>Mon</th>
	<th>Tue</th>
	<th>Wed</th>
	<th>Thu</th>
	<th>Fri</th>
	<th>Sat</th>
</tr>
{$now = strtotime('now')}
{foreach from=$calendar_properties.calendar_weeks item=week name=weeks}
<tr class="week">
	{foreach from=$week item=day name=days}
		{$is_today = $calendar_properties.today == $day.timestamp}
		<td class="{if $is_today}today{/if}{if $day.is_padding} inactive{/if}{if $smarty.foreach.days.last} cellborder_r{/if}{if $smarty.foreach.weeks.last} cellborder_b{/if}">
			<div class="day_header">
				{if $is_today}
				<a href="javascript:;" onclick="">Today, {$calendar_properties.today|devblocks_date:"M d"}</a>
				{else}
				<a href="javascript:;" onclick="">{$day.dom}</a>
				{/if}
			</div>
			<div class="day_contents">
				{if $calendar_events.{$day.timestamp}}
					{foreach from=$calendar_events.{$day.timestamp} item=event}
						<div class="event" style="background-color:{$event.color|default:'#C8C8C8'};" link="{$event.link}">
							{if $is_today && $now >= $event.ts && $now <= $event.ts_end}<span class="cerb-sprite2 sprite-tick-circle"></span>{/if}
							<span style="color:rgb(0,0,0);">{$event.label}</span>
							{*<a href="javascript:;" style="color:rgb(0,0,0);">{$event.label}</a>*}
						</div>
					{/foreach}
				{/if}
			</div>
		</td>
	{/foreach}
</tr>
{/foreach}
</table>

<script type="text/javascript">
$frm = $('#frm{$guid}');
$tab = $frm.closest('div.ui-tabs-panel');

$openEvtPopupEvent = function(e) {
	e.stopPropagation();
	
	var $this = $(this);
	var link = '';
	
	if($this.is('.create-event')) {
		$this.closest('div').find('ul.cerb-popupmenu').hide();
		context = $this.attr('context');
		link = 'ctx://' + context + ':0';
	
	} else if($this.is('div.event')) {
		//link = $this.attr('link');
		return;
	}
	
	if(link.substring(0,6) == 'ctx://') {
		var context_parts = link.substring(6).split(':');
		var context = context_parts[0];
		var context_id = context_parts[1];
		
		$popup = genericAjaxPopup('peek','c=internal&a=showPeekPopup&context=' + context + '&context_id=' + context_id  + '&calendar_id={$calendar->id}',null,false,'600');
		
		$popup.one('popup_saved calendar_event_save calendar_event_delete', function(event) {
			var month = (event.month) ? event.month : '{$calendar_properties.month}';
			var year = (event.year) ? event.year : '{$calendar_properties.year}';
			
			genericAjaxGet($('#frm{$guid}').closest('div.ui-tabs-panel'), 'c=internal&a=handleSectionAction&section=calendars&action=showCalendarAvailabilityTab&id={$calendar->id}&month=' + month + '&year=' + year);
			event.stopPropagation();
		});
		
	} else {
		// [TODO] Regular link
	}
}

{if !empty($create_contexts)}
$frm.find('ul.cerb-popupmenu > li').click(function(e) {
	e.stopPropagation();
	e.preventDefault();
	$(this).find('a.create-event').click();
});

$frm.find('button.create-event, a.create-event').click($openEvtPopupEvent);
{/if}
</script>