<?php
class ChTimeTrackingPlugin extends DevblocksPlugin {
	function load(DevblocksPluginManifest $manifest) {
	}
};

class ChTimeTrackingPatchContainer extends DevblocksPatchContainerExtension {
	function __construct($manifest) {
		parent::__construct($manifest);
		
		/*
		 * [JAS]: Just add a sequential build number here (and update plugin.xml) and
		 * write a case in runVersion().  You should comment the milestone next to your build 
		 * number.
		 */

		$file_prefix = realpath(dirname(__FILE__) . '/../patches');
		
		$this->registerPatch(new DevblocksPatch('cerberusweb.timetracking',1,$file_prefix.'/1.0.0.php',''));
	}
};

if (class_exists('Extension_AppPreBodyRenderer',true)):
	class ChTimeTrackingPreBodyRenderer extends Extension_AppPreBodyRenderer {
		function render() {
			$tpl = DevblocksPlatform::getTemplateService();
			$tpl_path = realpath(dirname(__FILE__).'/../templates') . DIRECTORY_SEPARATOR;
			$tpl->assign('path', $tpl_path);
			$tpl->cache_lifetime = "0";
			
			$tpl->assign('current_timestamp', time());
			
			$tpl->display('file:' . $tpl_path . 'timetracking/renderers/prebody.tpl.php');
		}
	};
endif;

if (class_exists('Extension_TicketToolbarItem',true)):
	class ChTimeTrackingTicketToolbarTimer extends Extension_TicketToolbarItem {
		function render(CerberusTicket $ticket) {
			$tpl = DevblocksPlatform::getTemplateService();
			$tpl_path = realpath(dirname(__FILE__).'/../templates') . DIRECTORY_SEPARATOR;
			$tpl->assign('path', $tpl_path);
			$tpl->cache_lifetime = "0";
			
			$tpl->assign('ticket', $ticket); /* @var $ticket CerberusTicket */
			
//			if(null != ($first_wrote_address_id = $ticket->first_wrote_address_id)
//				&& null != ($first_wrote_address = DAO_Address::get($first_wrote_address_id))) {
//				$tpl->assign('tt_first_wrote', $first_wrote_address);
//			}
			
			$tpl->display('file:' . $tpl_path . 'timetracking/renderers/ticket_toolbar_timer.tpl.php');
		}
	};
endif;

if (class_exists('Extension_ReplyToolbarItem',true)):
	class ChTimeTrackingReplyToolbarTimer extends Extension_ReplyToolbarItem {
		function render(CerberusMessage $message) { 
			$tpl = DevblocksPlatform::getTemplateService();
			$tpl_path = realpath(dirname(__FILE__).'/../templates') . DIRECTORY_SEPARATOR;
			$tpl->assign('path', $tpl_path);
			$tpl->cache_lifetime = "0";
			
			$tpl->assign('message', $message); /* @var $message CerberusMessage */
			
//			if(null != ($first_wrote_address_id = $ticket->first_wrote_address_id)
//				&& null != ($first_wrote_address = DAO_Address::get($first_wrote_address_id))) {
//				$tpl->assign('tt_first_wrote', $first_wrote_address);
//			}
			
			$tpl->display('file:' . $tpl_path . 'timetracking/renderers/reply_toolbar_timer.tpl.php');
		}
	};
endif;

class DAO_TimeTrackingEntry extends DevblocksORMHelper {
	const ID = 'id';
	const TIME_ACTUAL_MINS = 'time_actual_mins';
	const LOG_DATE = 'log_date';
	const WORKER_ID = 'worker_id';
	const ACTIVITY_ID = 'activity_id';
	const DEBIT_ORG_ID = 'debit_org_id';
	const IS_CLOSED = 'is_closed';
	const NOTES = 'notes';
	const SOURCE_EXTENSION_ID = 'source_extension_id';
	const SOURCE_ID = 'source_id';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$id = $db->GenID('generic_seq');
		
		$sql = sprintf("INSERT INTO timetracking_entry (id) ".
			"VALUES (%d)",
			$id
		);
		$db->Execute($sql);
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		parent::_update($ids, 'timetracking_entry', $fields);
	}
	
	/**
	 * @param string $where
	 * @return Model_TimeTrackingEntry[]
	 */
	static function getWhere($where=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "SELECT id, time_actual_mins, log_date, worker_id, activity_id, debit_org_id, is_closed, notes, source_extension_id, source_id ".
			"FROM timetracking_entry ".
			(!empty($where) ? sprintf("WHERE %s ",$where) : "").
			"ORDER BY id asc";
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_TimeTrackingEntry	 */
	static function get($id) {
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * @param ADORecordSet $rs
	 * @return Model_TimeTrackingEntry[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while(!$rs->EOF) {
			$object = new Model_TimeTrackingEntry();
			$object->id = $rs->fields['id'];
			$object->time_actual_mins = $rs->fields['time_actual_mins'];
			$object->log_date = $rs->fields['log_date'];
			$object->worker_id = $rs->fields['worker_id'];
			$object->activity_id = $rs->fields['activity_id'];
			$object->debit_org_id = $rs->fields['debit_org_id'];
			$object->is_closed = $rs->fields['is_closed'];
			$object->notes = $rs->fields['notes'];
			$object->source_extension_id = $rs->fields['source_extension_id'];
			$object->source_id = $rs->fields['source_id'];
			$objects[$object->id] = $object;
			$rs->MoveNext();
		}
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		$ids_list = implode(',', $ids);
		
		$db->Execute(sprintf("DELETE FROM timetracking_entry WHERE id IN (%s)", $ids_list));
		
		return true;
	}

};

class Model_TimeTrackingEntry {
	public $id;
	public $time_actual_mins;
	public $log_date;
	public $worker_id;
	public $activity_id;
	public $debit_org_id;
	public $is_closed;
	public $notes;
	public $source_extension_id;
	public $source_id;
};

class DAO_TimeTrackingActivity extends DevblocksORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const RATE = 'rate';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$id = $db->GenID('generic_seq');
		
		$sql = sprintf("INSERT INTO timetracking_activity (id) ".
			"VALUES (%d)",
			$id
		);
		$db->Execute($sql);
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		parent::_update($ids, 'timetracking_activity', $fields);
	}
	
	/**
	 * @param string $where
	 * @return Model_TimeTrackingActivity[]
	 */
	static function getWhere($where=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "SELECT id, name, rate ".
			"FROM timetracking_activity ".
			(!empty($where) ? sprintf("WHERE %s ",$where) : "").
			"ORDER BY name ASC";
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_TimeTrackingActivity	 */
	static function get($id) {
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * @param ADORecordSet $rs
	 * @return Model_TimeTrackingActivity[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while(!$rs->EOF) {
			$object = new Model_TimeTrackingActivity();
			$object->id = $rs->fields['id'];
			$object->name = $rs->fields['name'];
			$object->rate = $rs->fields['rate'];
			$objects[$object->id] = $object;
			$rs->MoveNext();
		}
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		$ids_list = implode(',', $ids);
		
		$db->Execute(sprintf("DELETE FROM timetracking_activity WHERE id IN (%s)", $ids_list));
		
		return true;
	}

};

class Model_TimeTrackingActivity {
	public $id;
	public $name;
	public $rate;
};

//class ChTimeTrackingTab extends Extension_TicketTab {
//	function showTab() {
//		@$ticket_id = DevblocksPlatform::importGPC($_REQUEST['ticket_id'],'integer',0);
//		
//		$tpl = DevblocksPlatform::getTemplateService();
//		$tpl_path = realpath(dirname(__FILE__).'/../templates') . DIRECTORY_SEPARATOR;
//		$tpl->assign('path', $tpl_path);
//		$tpl->cache_lifetime = "0";
//
////		$ticket = DAO_Ticket::getTicket($ticket_id);
//		$tpl->assign('ticket_id', $ticket_id);
//		
////		if(null == ($view = C4_AbstractViewLoader::getView('', 'ticket_opps'))) {
////			$view = new C4_CrmOpportunityView();
////			$view->id = 'ticket_opps';
////		}
////
////		if(!empty($address->contact_org_id)) { // org
////			@$org = DAO_ContactOrg::get($address->contact_org_id);
////			
////			$view->name = "Org: " . $org->name;
////			$view->params = array(
////				SearchFields_CrmOpportunity::ORG_ID => new DevblocksSearchCriteria(SearchFields_CrmOpportunity::ORG_ID,'=',$org->id) 
////			);
////		}
////		
////		C4_AbstractViewLoader::setView($view->id, $view);
////		
////		$tpl->assign('view', $view);
//		
//		$tpl->display('file:' . $tpl_path . 'timetracking/ticket_tab/index.tpl.php');
//	}
//	
//	function saveTab() {
//		@$ticket_id = DevblocksPlatform::importGPC($_REQUEST['ticket_id'],'integer',0);
//		
//		$ticket = DAO_Ticket::getTicket($ticket_id);
//		
//		if(isset($_SESSION['timetracking'])) {
//			@$time = intval($_SESSION['timetracking']);
////			echo "Ran for ", (time()-$time) , "secs <BR>";
//			unset($_SESSION['timetracking']);
//		} else {
//			$_SESSION['timetracking'] = time();
//		}
//		
//		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('display',$ticket->mask,'timetracking')));
//	}
//};

//class ChTimeTrackingEventListener extends DevblocksEventListenerExtension {
//    function __construct($manifest) {
//        parent::__construct($manifest);
//    }
//
//    /**
//     * @param Model_DevblocksEvent $event
//     */
//    function handleEvent(Model_DevblocksEvent $event) {
//        switch($event->id) {
////            case 'cron.maint':
////            	DAO_TicketAuditLog::maint();
////            	break;
//            	
//            case 'ticket.reply.outbound':
//            	@$ticket_id = $event->params['ticket_id'];
//            	@$message_id = $event->params['message_id'];
//            	@$worker_id = $event->params['worker_id'];
//            	
//            	if(null == ($ticket = DAO_Ticket::getTicket($ticket_id)))
//            		return;
//
//            	$requester_list = array();
//            	$ticket_requesters = $ticket->getRequesters();
//            	
//            	if(is_array($ticket_requesters))
//            	foreach($ticket_requesters as $addy) { /* @var $addy Model_Address */
//            		$requester_list[] = $addy->email;
//            	}
//            	
//            	self::logToTimeTracking(sprintf("-- %s --\r\nReplied to %s on ticket: [#%s] %s",
//            		date('r', time()),
//            		implode(', ', $requester_list),
//            		$ticket->mask,
//            		$ticket->subject
//            	));
//            		
//            	break;
//        }
//    }
//    
//    // [TODO] Where does this static best belong?
//    static function logToTimeTracking($log) {
//    	if(!isset($_SESSION['timetracking_worklog']))
//        	$_SESSION['timetracking_worklog'] = array();
//        	
//        $_SESSION['timetracking_worklog'][] = $log;
//    }
//};

class TimeTrackingPage extends CerberusPageExtension {
	private $plugin_path = '';
	
	function __construct($manifest) {
		parent::__construct($manifest);

		$this->plugin_path = realpath(dirname(__FILE__).'/../') . DIRECTORY_SEPARATOR;
	}
		
	function isVisible() {
		// check login
		$session = DevblocksPlatform::getSessionService();
		$visit = $session->getVisit();
		
		if(empty($visit)) {
			return false;
		} else {
			return true;
		}
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl_path = $this->plugin_path . '/templates/';
		$tpl->assign('path', $tpl_path);

		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		
		// [TODO] Temporary
		$entries = DAO_TimeTrackingEntry::getWhere();
		$tpl->assign('entries', $entries);
		
		$tpl->display($tpl_path . 'timetracking/time/index.tpl.php');
	}
};

class ChTimeTrackingAjaxController extends DevblocksControllerExtension {
	function __construct($manifest) {
		parent::__construct($manifest);
		
		$router = DevblocksPlatform::getRoutingService();
		$router->addRoute('timetracking','timetracking.controller.ajax');
	}
	
	function isVisible() {
		// check login
		$session = DevblocksPlatform::getSessionService();
		$visit = $session->getVisit();
		
		if(empty($visit)) {
			return false;
		} else {
			return true;
		}
	}
	
	/*
	 * Request Overload
	 */
	function handleRequest(DevblocksHttpRequest $request) {
		if(!$this->isVisible())
			return;
		
	    $path = $request->path;
		$controller = array_shift($path); // timetracking

	    @$action = DevblocksPlatform::strAlphaNumDash(array_shift($path)) . 'Action';

	    switch($action) {
	        case NULL:
	            // [TODO] Index/page render
	            break;
	            
	        default:
			    // Default action, call arg as a method suffixed with Action
				if(method_exists($this,$action)) {
					call_user_func(array(&$this, $action));
				}
	            break;
	    }
	}
	
	private function _startTimer() {
		if(!isset($_SESSION['timetracking_started'])) {
			$_SESSION['timetracking_started'] = time();	
		}
	}
	
	private function _stopTimer() {
		@$time = intval($_SESSION['timetracking_started']);
		
		// If a timer was running
		if(!empty($time)) {
			$elapsed = time() - $time;
			unset($_SESSION['timetracking_started']);
			@$_SESSION['timetracking_total'] = intval($_SESSION['timetracking_total']) + $elapsed;
		}

		@$total = $_SESSION['timetracking_total'];
		if(empty($total))
			return false;
		
		return $total;
	}
	
	private function _destroyTimer() {
		unset($_SESSION['timetracking_source_ext_id']);
		unset($_SESSION['timetracking_source_id']);
		unset($_SESSION['timetracking_started']);
		unset($_SESSION['timetracking_total']);
		unset($_SESSION['timetracking_link']);
	}
	
	function startTimerAction() {
		@$source_ext_id = urldecode(DevblocksPlatform::importGPC($_REQUEST['source_ext_id'],'string',''));
		@$source_id = intval(DevblocksPlatform::importGPC($_REQUEST['source_id'],'integer',0));
		
		if(!empty($source_ext_id) && !isset($_SESSION['timetracking_source_ext_id'])) {
			$_SESSION['timetracking_source_ext_id'] = $source_ext_id;
			$_SESSION['timetracking_source_id'] = $source_id;
		}
		
		$this->_startTimer();
	}
	
	function pauseTimerAction() {
		$total = $this->_stopTimer();
	}
	
	function getStopTimerPanelAction() {
		$total_secs = $this->_stopTimer();
//		$this->_destroyTimer();
		$this->_stopTimer();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl_path = realpath(dirname(__FILE__).'/../templates') . DIRECTORY_SEPARATOR;
		$tpl->assign('path', $tpl_path);
		$tpl->cache_lifetime = "0";

		// Activities
		// [TODO] Cache
		$billable_activities = DAO_TimeTrackingActivity::getWhere(sprintf("%s!=0",DAO_TimeTrackingActivity::RATE));
		$tpl->assign('billable_activities', $billable_activities);
		$nonbillable_activities = DAO_TimeTrackingActivity::getWhere(sprintf("%s=0",DAO_TimeTrackingActivity::RATE));
		$tpl->assign('nonbillable_activities', $nonbillable_activities);
		
		// Time
		$tpl->assign('total_secs', $total_secs);
		$tpl->assign('total_mins', ceil($total_secs/60));
		
		@$source_ext_id = strtolower($_SESSION['timetracking_source_ext_id']);
		@$source_id = intval($_SESSION['timetracking_source_id']);
		
		$tpl->assign('source_ext_id', $source_ext_id);
		$tpl->assign('source_id', $source_id);
		
		switch($source_ext_id) {
			// Message
			case 'timetracking.source.message':
				if(null == ($message = DAO_Ticket::getMessage($source_id)))
					break;
					
				if(null == ($ticket = DAO_Ticket::getTicket($message->ticket_id)))
					break;
					
				if(null == ($address = DAO_Address::get($message->address_id)))
					break;
				
				//$requesters = $ticket->getRequesters();
					
				// Timeslip Org
				if(!empty($address->contact_org_id) 
					&& null != ($org = DAO_ContactOrg::get($address->contact_org_id))) {
						$tpl->assign('org', $org->name);
				}

				// Timeslip reference
				$tpl->assign('reference', sprintf("Ticket #%s", 
					$ticket->mask
				));
				
				// Timeslip note
				$tpl->assign('note', sprintf("Replied to %s", 
					(!empty($address->email) ? $address->email : '') 
				));
					
				break;
			
			// Ticket
			case 'timetracking.source.ticket':
				if(null != ($ticket = DAO_Ticket::getTicket($source_id))) {
					
					// Timeslip Responsible Party
					if(null != ($address = DAO_Address::get($ticket->first_wrote_address_id))) {
//						$tpl->assign('performed_for', $address->email);

						// Timeslip Org
						if(!empty($address->contact_org_id) 
							&& null != ($org = DAO_ContactOrg::get($address->contact_org_id))) {
								$tpl->assign('org', $org->name);
						}
					}
					
					// Timeslip reference
					$tpl->assign('reference', sprintf("Ticket #%s", 
						$ticket->mask 
						//((strlen($ticket->subject)>45) ? (substr($ticket->subject,0,45).'...') : $ticket->subject)
					));
					
					// Timeslip note
//					$tpl->assign('note', sprintf("Replied to %s", 
//						$ticket->mask, 
//						(!empty($address->email) ? $address->email : '') 
//					));
				} 
				break;
		}
		
		$tpl->display('file:' . $tpl_path . 'timetracking/rpc/time_entry_panel.tpl.php');
	}
	
//	function writeResponse(DevblocksHttpResponse $response) {
//		if(!$this->isVisible())
//			return;
//	}

	function saveEntryAction() {
		$active_worker = CerberusApplication::getActiveWorker();
		
		// Make sure we're an active worker
		if(empty($active_worker) || empty($active_worker->id))
			return;
		
		// timeslip source_extension_id and source_id
		@$source_ext_id = strtolower($_SESSION['timetracking_source_ext_id']);
		@$source_id = intval($_SESSION['timetracking_source_id']);
		
		@$activity_id = DevblocksPlatform::importGPC($_POST['activity_id'],'integer',0);
		@$time_actual_mins = DevblocksPlatform::importGPC($_POST['time_actual_mins'],'integer',0);
		@$notes = DevblocksPlatform::importGPC($_POST['notes'],'string','');
		@$org_str = DevblocksPlatform::importGPC($_POST['org'],'string','');
		
		// Translate org string into org id, if exists
		$org_id = 0;
		if(!empty($org_str)) {
			$org_id = DAO_ContactOrg::lookup($org_str, true);
		}
		
		$fields = array(
			DAO_TimeTrackingEntry::ACTIVITY_ID => intval($activity_id),
			DAO_TimeTrackingEntry::LOG_DATE => time(),
			DAO_TimeTrackingEntry::TIME_ACTUAL_MINS => intval($time_actual_mins),
			DAO_TimeTrackingEntry::WORKER_ID => intval($active_worker->id),
			DAO_TimeTrackingEntry::NOTES => $notes,
			DAO_TimeTrackingEntry::DEBIT_ORG_ID => intval($org_id),
			DAO_TimeTrackingEntry::SOURCE_EXTENSION_ID => $source_ext_id,
			DAO_TimeTrackingEntry::SOURCE_ID => intval($source_id),
			DAO_TimeTrackingEntry::IS_CLOSED => 0,
		);
		DAO_TimeTrackingEntry::create($fields);
		
		switch($source_ext_id) {
			// If ticket, add a comment about the timeslip to the ticket
			case 'timetracking.source.message':
			case 'timetracking.source.ticket':
				$ticket_id = intval($source_id);
				
				// If message, translate source_id to ticket ID
				if('timetracking.source.message' == $source_ext_id) {
					if(null == ($message = DAO_Ticket::getMessage($source_id)))
						return;
					$ticket_id = $message->ticket_id; 
				}
				
				if(null != ($worker_address = DAO_Address::lookupAddress($active_worker->email, false))) {
					if(!empty($activity_id)) {
						$activity = DAO_TimeTrackingActivity::get($activity_id);
					}
					
					if(!empty($org_id))
						$org = DAO_ContactOrg::get($org_id);
					
					$comment = sprintf(
						"== Time Tracking ==\n".
						"Worker: %s\n".
						"Time spent: %d min%s\n".
						"Activity: %s (%s)\n".
						"Organization: %s\n".
						"Notes: %s\n",
						$active_worker->getName(),
						$time_actual_mins,
						($time_actual_mins != 1 ? 's' : ''), // pluralize ([TODO] not I18N friendly)
						(!empty($activity) ? $activity->name : ''),
						((!empty($activity) && $activity->rate > 0.00) ? 'Billable' : 'Non-Billable'),
						(!empty($org) ? $org->name : '(not set)'),
						$notes
					);
					
					$fields = array(
						DAO_TicketComment::ADDRESS_ID => intval($worker_address->id),
						DAO_TicketComment::COMMENT => $comment,
						DAO_TicketComment::CREATED => time(),
						DAO_TicketComment::TICKET_ID => intval($ticket_id),
					);
					DAO_TicketComment::create($fields);
				}
				break;
		}
	}
	
	function clearEntryAction() {
		$this->_destroyTimer();
	}
};

class ChTimeTrackingConfigActivityTab extends Extension_ConfigTab {
	const ID = 'timetracking.config.tab.activities';
	
	function showTab() {
		$settings = CerberusSettings::getInstance();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl_path = realpath(dirname(__FILE__) . '/../templates') . DIRECTORY_SEPARATOR;
		$tpl->assign('path', $tpl_path);
		$tpl->cache_lifetime = "0";

		$billable_activities = DAO_TimeTrackingActivity::getWhere(sprintf("%s!=0",DAO_TimeTrackingActivity::RATE));
		$tpl->assign('billable_activities', $billable_activities);
		
		$nonbillable_activities = DAO_TimeTrackingActivity::getWhere(sprintf("%s=0",DAO_TimeTrackingActivity::RATE));
		$tpl->assign('nonbillable_activities', $nonbillable_activities);
		
		$tpl->display('file:' . $tpl_path . 'config/activities/index.tpl.php');
	}
	
	function saveTab() {
		$settings = CerberusSettings::getInstance();
		@$plugin_id = DevblocksPlatform::importGPC($_REQUEST['plugin_id'],'string');

		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$name = DevblocksPlatform::importGPC($_REQUEST['name'],'string','');
		@$rate = floatval(DevblocksPlatform::importGPC($_REQUEST['rate'],'string',''));
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'],'integer',0);

		if(empty($id)) { // Add
			$fields = array(
				DAO_TimeTrackingActivity::NAME => $name,
				DAO_TimeTrackingActivity::RATE => $rate,
			);
			$activity_id = DAO_TimeTrackingActivity::create($fields);
			
		} else { // Edit
			if($do_delete) { // Delete
				DAO_TimeTrackingActivity::delete($id);
				
			} else { // Modify
				$fields = array(
					DAO_TimeTrackingActivity::NAME => $name,
					DAO_TimeTrackingActivity::RATE => $rate,
				);
				DAO_TimeTrackingActivity::update($id, $fields);
			}
			
		}
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('config','timetracking.activities')));
		exit;
	}
	
	function getActivityAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'], 'integer', 0);
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl_path = realpath(dirname(__FILE__) . '/../templates') . DIRECTORY_SEPARATOR;
		$tpl->assign('path', $tpl_path);
		$tpl->cache_lifetime = "0";
		
		if(!empty($id) && null != ($activity = DAO_TimeTrackingActivity::get($id)))
			$tpl->assign('activity', $activity);
		
		$tpl->display('file:' . $tpl_path . 'config/activities/edit_activity.tpl.php');
	}
	
};

if (class_exists('Extension_ReportGroup',true)):
class ChReportGroupTimeTracking extends Extension_ReportGroup {
	function __construct($manifest) {
		parent::__construct($manifest);
	}
};
endif;

if (class_exists('Extension_Report',true)):
class ChReportTimeSpentWorker extends Extension_Report {
	private $tpl_path = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->tpl_path = realpath(dirname(__FILE__).'/../templates');
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		$tpl->assign('start', '-30 days');
		$tpl->assign('end', 'now');
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$tpl->display('file:' . $this->tpl_path . '/reports/time_spent_worker/index.tpl.php');
	}
	
	function getTimeSpentWorkerReportAction() {
		$db = DevblocksPlatform::getDatabaseService();

		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);

		// import dates from form
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','');
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','');
		
		// use date range if specified, else use duration prior to now
		$start_time = 0;
		$end_time = 0;
		
		if (empty($start) && empty($end)) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		} else {
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		}
				
		if($start_time === false || $end_time === false) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
			
			$tpl->assign('invalidDate', true);
		}
		
		// reload variables in template
		$tpl->assign('start', $start);
		$tpl->assign('end', $end);
		
		$workers = DAO_Worker::getAll();
		$tpl->assign('workers', $workers);
		
		$sql = sprintf("SELECT tte.log_date, tte.time_actual_mins, tte.worker_id, tte.notes, ".
				"tta.name activity_name, o.name org_name, o.id org_id ".
				"FROM timetracking_entry tte ".
				"INNER JOIN timetracking_activity tta ON tte.activity_id = tta.id ".
				"LEFT JOIN contact_org o ON o.id = tte.debit_org_id ".
				"WHERE log_date > %d AND log_date <= %d ".
				"ORDER BY worker_id, org_name ",
			$start_time,
			$end_time
		);
		//echo $sql;
		$rs = $db->Execute($sql);
	
		$time_entries = array();
		while(!$rs->EOF) {
			$mins = intval($rs->fields['time_actual_mins']);
			$worker_id = intval($rs->fields['worker_id']);
			$org_id = intval($rs->fields['org_id']);
			$activity = $rs->fields['activity_name'];
			$org_name = $rs->fields['org_name'];
			$log_date = intval($rs->fields['log_date']);
			$notes = $rs->fields['notes'];
			
			
			if(!isset($time_entries[$worker_id]))
				$time_entries[$worker_id] = array();
			if(!isset($time_entries[$worker_id]['orgs']))
				$time_entries[$worker_id]['orgs'] = array();
			
			if(!isset($time_entries[$worker_id]['orgs'][$org_id]))
				$time_entries[$worker_id]['orgs'][$org_id] = array();
			if(!isset($time_entries[$worker_id]['orgs'][$org_id]['entries']))
				$time_entries[$worker_id]['orgs'][$org_id]['entries'] = array();
				
				
			unset($time_entry);
			$time_entry['activity_name'] = $activity;
			//$time_entry['org_name'] = $org_name;
			$time_entry['mins'] = $mins;
			$time_entry['log_date'] = $log_date;
			$time_entry['notes'] = $notes;
			//$time_entry['name'] = $workers[$worker_id]->getName();
			
			//$time_entries[$worker_id]['entries'][] = $time_entry;
			//@$time_entries[$worker_id]['total_mins'] = intval($time_entries[$worker_id]['total_mins']) + $mins;
			
			$time_entries[$worker_id]['orgs'][$org_id]['entries'][] = $time_entry;
			@$time_entries[$worker_id]['total_mins'] = intval($time_entries[$worker_id]['total_mins']) + $mins;
			@$time_entries[$worker_id]['orgs'][$org_id]['total_mins'] = intval($time_entries[$worker_id]['orgs'][$org_id]['total_mins']) + $mins;
			@$time_entries[$worker_id]['orgs'][$org_id]['org_name'] = $org_name;
			
			$rs->MoveNext();
		}
		//print_r($time_entries);
		$tpl->assign('time_entries', $time_entries);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/time_spent_worker/html.tpl.php');
	}
	
	function getTimeSpentWorkerChartAction() {
		// import dates from form
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','');
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','');
		@$countonly = DevblocksPlatform::importGPC($_REQUEST['countonly'],'integer',0);
		
		// use date range if specified, else use duration prior to now
		$start_time = 0;
		$end_time = 0;
		if (empty($start) && empty($end)) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		} else {
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		}
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$groups = DAO_Group::getAll();
		
		$sql = sprintf("SELECT sum(tte.time_actual_mins) mins, tte.worker_id, w.first_name, w.last_name ".
				"FROM timetracking_entry tte ".
				"INNER JOIN worker w ON tte.worker_id = w.id ".
				"WHERE log_date > %d AND log_date <= %d ".
				"GROUP BY tte.worker_id, w.first_name, w.last_name ".
				"ORDER BY w.first_name desc, w.last_name desc ",
				$start_time,
				$end_time
				);
		$rs = $db->Execute($sql);

		if($countonly) {
			echo intval($rs->RecordCount());
			return;
		}
		
	    while(!$rs->EOF) {
	    	$mins = intval($rs->fields['mins']);
			$worker_name = $rs->fields['first_name'] . ' ' . $rs->fields['last_name'];
			
			echo $worker_name, "\t", $mins . "\n";
			
		    $rs->MoveNext();
	    }
	}
};
endif;

if (class_exists('Extension_Report',true)):
class ChReportTimeSpentOrg extends Extension_Report {
	private $tpl_path = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->tpl_path = realpath(dirname(__FILE__).'/../templates');
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		$tpl->assign('start', '-30 days');
		$tpl->assign('end', 'now');
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$tpl->display('file:' . $this->tpl_path . '/reports/time_spent_org/index.tpl.php');
	}
	
	function getTimeSpentOrgReportAction() {
		$db = DevblocksPlatform::getDatabaseService();

		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);

		// import dates from form
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','');
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','');
		
		// use date range if specified, else use duration prior to now
		$start_time = 0;
		$end_time = 0;
		
		if (empty($start) && empty($end)) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		} else {
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		}
				
		if($start_time === false || $end_time === false) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
			
			$tpl->assign('invalidDate', true);
		}
		
		// reload variables in template
		$tpl->assign('start', $start);
		$tpl->assign('end', $end);

		$sql = sprintf("SELECT tte.log_date, tte.time_actual_mins, tte.notes, ".
				"tta.name activity_name, o.name org_name, o.id org_id ".
				"FROM timetracking_entry tte ".
				"INNER JOIN timetracking_activity tta ON tte.activity_id = tta.id ".
				"LEFT JOIN contact_org o ON o.id = tte.debit_org_id ".
				"WHERE log_date > %d AND log_date <= %d ".
				"ORDER BY worker_id, org_name ",
			$start_time,
			$end_time
		);
		//echo $sql;
		$rs = $db->Execute($sql);
	
		$time_entries = array();
		while(!$rs->EOF) {
			$mins = intval($rs->fields['time_actual_mins']);
			$org_id = intval($rs->fields['org_id']);
			$activity = $rs->fields['activity_name'];
			$org_name = $rs->fields['org_name'];
			$log_date = intval($rs->fields['log_date']);
			$notes = $rs->fields['notes'];
			
			if(!isset($time_entries[$org_id]))
				$time_entries[$org_id] = array();
			if(!isset($time_entries[$org_id]['entries']))
				$time_entries[$org_id]['entries'] = array();
				
				
			unset($time_entry);
			$time_entry['activity_name'] = $activity;
			$time_entry['mins'] = $mins;
			$time_entry['log_date'] = $log_date;
			$time_entry['notes'] = $notes;

			$time_entries[$org_id]['entries'][] = $time_entry;
			@$time_entries[$org_id]['total_mins'] = intval($time_entries[$org_id]['total_mins']) + $mins;
			@$time_entries[$org_id]['org_name'] = $org_name;
			
			$rs->MoveNext();
		}
		//print_r($time_entries);
		$tpl->assign('time_entries', $time_entries);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/time_spent_org/html.tpl.php');
	}
	
	function getTimeSpentOrgChartAction() {
		// import dates from form
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','');
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','');
		@$countonly = DevblocksPlatform::importGPC($_REQUEST['countonly'],'integer',0);
		
		// use date range if specified, else use duration prior to now
		$start_time = 0;
		$end_time = 0;
		if (empty($start) && empty($end)) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		} else {
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		}
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$groups = DAO_Group::getAll();
		
		$sql = sprintf("SELECT sum(tte.time_actual_mins) mins, o.id org_id, o.name org_name ".
				"FROM timetracking_entry tte ".
				"LEFT JOIN contact_org o ON tte.debit_org_id = o.id ".
				"WHERE log_date > %d AND log_date <= %d ".
				"GROUP BY org_id, org_name ".
				"ORDER BY org_name desc ",
				$start_time,
				$end_time
				);
		$rs = $db->Execute($sql);

		if($countonly) {
			echo intval($rs->RecordCount());
			return;
		}
		
	    while(!$rs->EOF) {
	    	$mins = intval($rs->fields['mins']);
			$org_name = $rs->fields['org_name'];
			if(empty($org_name)) $org_name = '(no org)';
			
			echo $org_name, "\t", $mins . "\n";
			
		    $rs->MoveNext();
	    }
	}
};
endif;

if (class_exists('Extension_Report',true)):
class ChReportTimeSpentActivity extends Extension_Report {
	private $tpl_path = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->tpl_path = realpath(dirname(__FILE__).'/../templates');
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);
		
		$tpl->assign('start', '-30 days');
		$tpl->assign('end', 'now');
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$tpl->display('file:' . $this->tpl_path . '/reports/time_spent_activity/index.tpl.php');
	}
	
	function getTimeSpentActivityReportAction() {
		$db = DevblocksPlatform::getDatabaseService();

		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->cache_lifetime = "0";
		$tpl->assign('path', $this->tpl_path);

		// import dates from form
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','');
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','');
		
		// use date range if specified, else use duration prior to now
		$start_time = 0;
		$end_time = 0;
		
		if (empty($start) && empty($end)) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		} else {
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		}
				
		if($start_time === false || $end_time === false) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
			
			$tpl->assign('invalidDate', true);
		}
		
		// reload variables in template
		$tpl->assign('start', $start);
		$tpl->assign('end', $end);

		$sql = sprintf("SELECT tte.log_date, tte.time_actual_mins, tte.notes, ".
				"tta.id activity_id, tta.name activity_name ".
				"FROM timetracking_entry tte ".
				"INNER JOIN timetracking_activity tta ON tte.activity_id = tta.id ".
				"WHERE log_date > %d AND log_date <= %d ".
				"ORDER BY activity_name ",
			$start_time,
			$end_time
		);
		//echo $sql;
		$rs = $db->Execute($sql);
	
		$time_entries = array();
		while(!$rs->EOF) {
			$mins = intval($rs->fields['time_actual_mins']);
			$activity = $rs->fields['activity_name'];
			$log_date = intval($rs->fields['log_date']);
			$activity_id = intval($rs->fields['activity_id']);
			$notes = $rs->fields['notes'];
			
			if(!isset($time_entries[$activity_id]))
				$time_entries[$activity_id] = array();
			if(!isset($time_entries[$activity_id]['entries']))
				$time_entries[$activity_id]['entries'] = array();
				
				
			unset($time_entry);
			$time_entry['mins'] = $mins;
			$time_entry['log_date'] = $log_date;
			$time_entry['notes'] = $notes;

			$time_entries[$activity_id]['entries'][] = $time_entry;
			@$time_entries[$activity_id]['total_mins'] = intval($time_entries[$activity_id]['total_mins']) + $mins;
			@$time_entries[$activity_id]['activity_name'] = $activity;
			
			$rs->MoveNext();
		}
		//print_r($time_entries);
		$tpl->assign('time_entries', $time_entries);
		
		$tpl->display('file:' . $this->tpl_path . '/reports/time_spent_activity/html.tpl.php');
	}
	
	function getTimeSpentActivityChartAction() {
		// import dates from form
		@$start = DevblocksPlatform::importGPC($_REQUEST['start'],'string','');
		@$end = DevblocksPlatform::importGPC($_REQUEST['end'],'string','');
		@$countonly = DevblocksPlatform::importGPC($_REQUEST['countonly'],'integer',0);
		
		// use date range if specified, else use duration prior to now
		$start_time = 0;
		$end_time = 0;
		if (empty($start) && empty($end)) {
			$start = "-30 days";
			$end = "now";
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		} else {
			$start_time = strtotime($start);
			$end_time = strtotime($end);
		}
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$groups = DAO_Group::getAll();
		
		$sql = sprintf("SELECT sum(tte.time_actual_mins) mins, tta.name activity_name ".
				"FROM timetracking_entry tte ".
				"LEFT JOIN timetracking_activity tta ON tte.activity_id = tta.id ".
				"WHERE log_date > %d AND log_date <= %d ".
				"GROUP BY activity_name ".
				"ORDER BY activity_name desc ",
				$start_time,
				$end_time
				);
		$rs = $db->Execute($sql);

		if($countonly) {
			echo intval($rs->RecordCount());
			return;
		}
		
	    while(!$rs->EOF) {
	    	$mins = intval($rs->fields['mins']);
			$activity = $rs->fields['activity_name'];
			if(empty($activity)) $activity = '(no activity)';
			
			echo $activity, "\t", $mins . "\n";
			
		    $rs->MoveNext();
	    }
	}
};
endif;
?>
