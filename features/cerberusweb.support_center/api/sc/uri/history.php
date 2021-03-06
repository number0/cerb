<?php
class UmScHistoryController extends Extension_UmScController {
	const PARAM_WORKLIST_COLUMNS_JSON = 'history.worklist.columns';
	
	function isVisible() {
		$umsession = ChPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		return !empty($active_contact);
	}
	
	function renderSidebar(DevblocksHttpResponse $response) {
//		$tpl = DevblocksPlatform::getTemplateSandboxService();
	}
	
	function writeResponse(DevblocksHttpResponse $response) {
		$tpl = DevblocksPlatform::getTemplateSandboxService();
		
		$umsession = ChPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		
		$stack = $response->path;
		array_shift($stack); // history
		$mask = array_shift($stack);

		$shared_address_ids = DAO_SupportCenterAddressShare::getContactAddressesWithShared($active_contact->id, true);
		if(empty($shared_address_ids))
			$shared_address_ids = array(-1);
		
		if(empty($mask)) {
			// Ticket history
			if(null == ($history_view = UmScAbstractViewLoader::getView('', 'sc_history_list'))) {
				$history_view = new UmSc_TicketHistoryView();
				$history_view->id = 'sc_history_list';
				$history_view->name = "";
				$history_view->renderSortBy = SearchFields_Ticket::TICKET_UPDATED_DATE;
				$history_view->renderSortAsc = false;
				$history_view->renderLimit = 10;
				
				$history_view->addParams(array(
					new DevblocksSearchCriteria(SearchFields_Ticket::VIRTUAL_STATUS,'in',array('open','waiting')),
				), true);
			}
			
			@$params_columns = DAO_CommunityToolProperty::get(ChPortalHelper::getCode(), self::PARAM_WORKLIST_COLUMNS_JSON, '[]', true);
			
			if(empty($params_columns))
				$params_columns = array(
					SearchFields_Ticket::TICKET_LAST_WROTE_ID,
					SearchFields_Ticket::TICKET_UPDATED_DATE,
				);
				
			$history_view->view_columns = $params_columns;
			
			// Lock to current visitor
			$history_view->addParamsRequired(array(
				'_acl_reqs' => new DevblocksSearchCriteria(SearchFields_Ticket::REQUESTER_ID,'in',$shared_address_ids),
				'_acl_status' => new DevblocksSearchCriteria(SearchFields_Ticket::TICKET_STATUS_ID,'!=',Model_Ticket::STATUS_DELETED),
			), true);
			
			UmScAbstractViewLoader::setView($history_view->id, $history_view);
			$tpl->assign('view', $history_view);

			$tpl->display("devblocks:cerberusweb.support_center:portal_".ChPortalHelper::getCode() . ":support_center/history/index.tpl");
			
		} else {
			// If this is an invalid ticket mask, deny access
			if(false == ($ticket = DAO_Ticket::getTicketByMask($mask)))
				return;
			
			$participants = $ticket->getRequesters();
			
			// See if the current account is one of the participants on this ticket
			$matching_participants = array_intersect(array_keys($participants), $shared_address_ids);
			
			// If none of the participants on the ticket match this account, deny access
			if(!is_array($matching_participants) || empty($matching_participants))
				return;
			
			$messages = DAO_Message::getMessagesByTicket($ticket->id);
			$messages = array_reverse($messages, true);
			
			$tpl->assign('ticket', $ticket);
			$tpl->assign('participants', $participants);
			$tpl->assign('messages', $messages);
			
			$attachments = DAO_Attachment::getByContextIds(CerberusContexts::CONTEXT_MESSAGE, array_keys($messages), false);
			$tpl->assign('attachments', $attachments);
			
			$badge_extensions = DevblocksPlatform::getExtensions('cerberusweb.support_center.message.badge', true);
			$tpl->assign('badge_extensions', $badge_extensions);
			
			$tpl->display("devblocks:cerberusweb.support_center:portal_".ChPortalHelper::getCode() . ":support_center/history/display.tpl");
		}
	}
	
	function configure(Model_CommunityTool $instance) {
		$tpl = DevblocksPlatform::getTemplateSandboxService();

		$params = array(
			'columns' => DAO_CommunityToolProperty::get($instance->code, self::PARAM_WORKLIST_COLUMNS_JSON, '[]', true),
		);
		$tpl->assign('history_params', $params);
		
		$view = new View_Ticket();
		$view->id = View_Ticket::DEFAULT_ID;
		
		$columns = array_filter(
			$view->getColumnsAvailable(),
			function($column) {
				return !empty($column->db_label);
			}
		);
		
		DevblocksPlatform::sortObjects($columns, 'db_label');
		
		$tpl->assign('history_columns', $columns);
		
		$tpl->display("devblocks:cerberusweb.support_center::portal/sc/config/module/history.tpl");
	}
	
	function saveConfiguration(Model_CommunityTool $instance) {
		@$columns = DevblocksPlatform::importGPC($_POST['history_columns'],'array',array());

		$columns = array_filter($columns, function($column) {
			return !empty($column);
		});
		
		DAO_CommunityToolProperty::set($instance->code, self::PARAM_WORKLIST_COLUMNS_JSON, $columns, true);
	}
	
	// [TODO] JSON
	function saveTicketPropertiesAction() {
		@$mask = DevblocksPlatform::importGPC($_REQUEST['mask'],'string','');
		@$subject = DevblocksPlatform::importGPC($_REQUEST['subject'],'string','');
		@$participants = DevblocksPlatform::importGPC($_REQUEST['participants'],'string','');
		@$is_closed = DevblocksPlatform::importGPC($_REQUEST['is_closed'],'integer','0');
		
		$umsession = ChPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);

		$shared_address_ids = DAO_SupportCenterAddressShare::getContactAddressesWithShared($active_contact->id, true);
		if(empty($shared_address_ids))
			$shared_address_ids = array(-1);
		
		CerberusContexts::pushActivityDefaultActor(CerberusContexts::CONTEXT_CONTACT, $active_contact->id);
		
		if(false == ($ticket = DAO_Ticket::getTicketByMask($mask)))
			return;
		
		$participants_old = $ticket->getRequesters();
		
		// Only allow access if mask has one of the valid requesters
		$allowed_requester_ids = array_intersect(array_keys($participants_old), $shared_address_ids);
		
		if(empty($allowed_requester_ids))
			return;
		
		$fields = array();
		
		if(!empty($subject))
			$fields[DAO_Ticket::SUBJECT] = $subject;
		
		// Status: Ignore deleted/waiting
		if($is_closed && !in_array($ticket->status_id, array(Model_Ticket::STATUS_CLOSED, Model_Ticket::STATUS_DELETED))) {
			$fields[DAO_Ticket::STATUS_ID] = Model_Ticket::STATUS_CLOSED;
		} elseif (!$is_closed && ($ticket->status_id == Model_Ticket::STATUS_CLOSED)) {
			$fields[DAO_Ticket::STATUS_ID] = Model_Ticket::STATUS_OPEN;
		}
		
		if($fields)
			DAO_Ticket::update($ticket->id, $fields);
		
		CerberusContexts::popActivityDefaultActor();
		
		// Participants
		$participants_new = DAO_Address::lookupAddresses(DevblocksPlatform::parseCrlfString($participants), true);
		$participants_removed = array_diff(array_keys($participants_old), array_keys($participants_new));
		$participants_added = array_diff(array_keys($participants_new), array_keys($participants_old));
		
		if(!empty($participants_removed)) {
			DAO_Ticket::removeParticipantIds($ticket->id, $participants_removed);
		}
		
		if(!empty($participants_added)) {
			DAO_Ticket::addParticipantIds($ticket->id, $participants_added);
		}
		
		// Redirect
		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',ChPortalHelper::getCode(),'history', $ticket->mask)));
	}
	
	function doReplyAction() {
		@$from = DevblocksPlatform::importGPC($_REQUEST['from'],'string','');
		@$mask = DevblocksPlatform::importGPC($_REQUEST['mask'],'string','');
		@$content = DevblocksPlatform::importGPC($_REQUEST['content'],'string','');
		
		$umsession = ChPortalHelper::getSession();
		if(false == ($active_contact = $umsession->getProperty('sc_login', null)))
			return false;
		
		// Load contact addresses
		$shared_address_ids = DAO_SupportCenterAddressShare::getContactAddressesWithShared($active_contact->id, true);
		if(empty($shared_address_ids))
			$shared_address_ids = array(-1);
		
		// Validate FROM address
		if(null == ($from_address = DAO_Address::lookupAddress($from, false)))
			return false;
		
		if($from_address->contact_id != $active_contact->id)
			return false;
		
		if(false == ($ticket = DAO_Ticket::getTicketByMask($mask)))
			return;
		
		// Only allow access if mask has one of the valid requesters
		$requesters = $ticket->getRequesters();
		$allowed_requester_ids = array_intersect(array_keys($requesters), $shared_address_ids);
		
		if(empty($allowed_requester_ids))
			return;
		
		$messages = DAO_Message::getMessagesByTicket($ticket->id);
		$last_message = array_pop($messages); /* @var $last_message Model_Message */
		$last_message_headers = $last_message->getHeaders();
		unset($messages);

		// Ticket group settings
		$group = DAO_Group::get($ticket->group_id);
		@$group_replyto = $group->getReplyTo($ticket->bucket_id);
		
		// Headers
		$message = new CerberusParserMessage();
		$message->headers['from'] = $from_address->email;
		$message->headers['to'] = $group_replyto->email;
		$message->headers['date'] = date('r');
		$message->headers['subject'] = 'Re: ' . $ticket->subject;
		$message->headers['message-id'] = CerberusApplication::generateMessageId();
		$message->headers['in-reply-to'] = @$last_message_headers['message-id'];
		
		$message->body = sprintf(
			"%s",
			$content
		);

		// Attachments
		if(is_array($_FILES) && !empty($_FILES))
		foreach($_FILES as $name => $files) {
			// field[]
			if(is_array($files['name'])) {
				foreach($files['name'] as $idx => $name) {
					if(empty($name))
						continue;
					
					$attach = new ParserFile();
					$attach->setTempFile($files['tmp_name'][$idx],'application/octet-stream');
					$attach->file_size = filesize($files['tmp_name'][$idx]);
					$message->files[$name] = $attach;
				}
				
			} else {
				if(!isset($files['name']) || empty($files['name']))
					continue;
				
				$attach = new ParserFile();
				$attach->setTempFile($files['tmp_name'],'application/octet-stream');
				$attach->file_size = filesize($files['tmp_name']);
				$message->files[$files['name']] = $attach;
			}
		}
		
		CerberusParser::parseMessage($message, array('no_autoreply'=>true));
		
		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal', ChPortalHelper::getCode(), 'history', $ticket->mask)));
	}
};

class UmSc_TicketHistoryView extends C4_AbstractView {
	const DEFAULT_ID = 'sc_history';
	
	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = 'Tickets';
		$this->renderSortBy = SearchFields_Ticket::TICKET_UPDATED_DATE;
		$this->renderSortAsc = false;

		$this->view_columns = array(
			SearchFields_Ticket::TICKET_UPDATED_DATE,
			SearchFields_Ticket::TICKET_SUBJECT,
			SearchFields_Ticket::TICKET_LAST_WROTE_ID,
		);
		
		$this->addParamsHidden(array(
			SearchFields_Ticket::TICKET_ID,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$columns = array_merge($this->view_columns, array($this->renderSortBy));
		
		$objects = DAO_Ticket::search(
			$columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_Ticket');
		
		return $objects;
	}

	function render() {
		//$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateSandboxService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);
		
		$buckets = DAO_Bucket::getAll();
		$tpl->assign('buckets', $buckets);
		
		$workers = DAO_Worker::getAll();
		$tpl->assign('workers', $workers);
		
		$custom_fields = DAO_CustomField::getAll();
		$tpl->assign('custom_fields', $custom_fields);
		
		$results = $this->getData();
		$tpl->assign('results', $results);
		$tpl->assign('total', $results[1]);
		$tpl->assign('data', $results[0]);
		
		// Bulk lazy load first wrote
		$object_first_wrotes = [];
		if(in_array('t_first_wrote_address_id', $this->view_columns)) {
			$first_wrote_ids = DevblocksPlatform::extractArrayValues($results, 't_first_wrote_address_id');
			$object_first_wrotes = DAO_Address::getIds($first_wrote_ids);
			$tpl->assign('object_first_wrotes', $object_first_wrotes);
		}
		
		// Bulk lazy load last wrote
		$object_last_wrotes = [];
		if(in_array('t_last_wrote_address_id', $this->view_columns)) {
			$last_wrote_ids = DevblocksPlatform::extractArrayValues($results, 't_last_wrote_address_id');
			$object_last_wrotes = DAO_Address::getIds($last_wrote_ids);
			$tpl->assign('object_last_wrotes', $object_last_wrotes);
		}
		
		// Bulk lazy load orgs
		$object_orgs = [];
		if(in_array('t_org_id', $this->view_columns)) {
			$org_ids = DevblocksPlatform::extractArrayValues($results, 't_org_id');
			$object_orgs = DAO_ContactOrg::getIds($org_ids);
			$tpl->assign('object_orgs', $object_orgs);
		}
		
		$tpl->display("devblocks:cerberusweb.support_center:portal_".ChPortalHelper::getCode() . ":support_center/history/view.tpl");
	}

	function getFields() {
		return SearchFields_Ticket::getFields();
	}
	
	function getSearchFields() {
		$fields = SearchFields_Ticket::getFields();

		foreach($fields as $key => $field) {
			switch($key) {
				case SearchFields_Ticket::FULLTEXT_MESSAGE_CONTENT:
				case SearchFields_Ticket::REQUESTER_ID:
				case SearchFields_Ticket::TICKET_MASK:
				case SearchFields_Ticket::TICKET_SUBJECT:
				case SearchFields_Ticket::TICKET_CREATED_DATE:
				case SearchFields_Ticket::TICKET_UPDATED_DATE:
				case SearchFields_Ticket::VIRTUAL_STATUS:
					break;
				default:
					unset($fields[$key]);
			}
		}
		
		return $fields;
	}
	
	function renderCriteria($field) {
		$umsession = ChPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		$tpl = DevblocksPlatform::getTemplateSandboxService();
		
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_Ticket::TICKET_MASK:
			case SearchFields_Ticket::TICKET_SUBJECT:
				$tpl->display('devblocks:cerberusweb.support_center::support_center/internal/view/criteria/__string.tpl');
				break;
			case 'placeholder_number':
				$tpl->display('devblocks:cerberusweb.support_center::support_center/internal/view/criteria/__number.tpl');
				break;
			case SearchFields_Ticket::TICKET_CREATED_DATE:
			case SearchFields_Ticket::TICKET_UPDATED_DATE:
				$tpl->display('devblocks:cerberusweb.support_center::support_center/internal/view/criteria/__date.tpl');
				break;
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.support_center::support_center/internal/view/criteria/__bool.tpl');
				break;
			case SearchFields_Ticket::FULLTEXT_MESSAGE_CONTENT:
				$tpl->display('devblocks:cerberusweb.support_center::support_center/internal/view/criteria/__fulltext.tpl');
				break;
			case SearchFields_Ticket::REQUESTER_ID:
				$shared_addresses = DAO_SupportCenterAddressShare::getContactAddressesWithShared($active_contact->id, false);
				$tpl->assign('requesters', $shared_addresses);
				$tpl->display('devblocks:cerberusweb.support_center::support_center/history/criteria/requester.tpl');
				break;
			case SearchFields_Ticket::VIRTUAL_STATUS:
				$tpl->display('devblocks:cerberusweb.support_center::support_center/history/criteria/status.tpl');
				break;
			default:
				break;
		}
	}
	
	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		$translate = DevblocksPlatform::getTranslationService();
		
		switch($field) {
			// Overload
			case SearchFields_Ticket::REQUESTER_ID:
				$strings = array();
				if(empty($values) || !is_array($values))
					break;
				$addresses = DAO_Address::getWhere(sprintf("%s IN (%s)", DAO_Address::ID, implode(',', $values)));
				
				foreach($values as $val) {
					if(isset($addresses[$val]))
						$strings[] = DevblocksPlatform::strEscapeHtml($addresses[$val]->email);
				}
				echo implode('</b> or <b>', $strings);
				break;
				
			// Overload
			case SearchFields_Ticket::VIRTUAL_STATUS:
				$strings = array();

				foreach($values as $val) {
					switch($val) {
						case 'open':
							$strings[] = DevblocksPlatform::strEscapeHtml($translate->_('status.waiting'));
							break;
						case 'waiting':
							$strings[] = DevblocksPlatform::strEscapeHtml($translate->_('status.open'));
							break;
						case 'closed':
							$strings[] = DevblocksPlatform::strEscapeHtml($translate->_('status.closed'));
							break;
					}
				}
				echo implode(", ", $strings);
				break;

			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}
	
	function doSetCriteria($field, $oper, $value) {
		$umsession = ChPortalHelper::getSession();
		$active_contact = $umsession->getProperty('sc_login', null);
		
		$criteria = null;

		switch($field) {
			case SearchFields_Ticket::TICKET_MASK:
			case SearchFields_Ticket::TICKET_SUBJECT:
				// force wildcards if none used on a LIKE
				if(($oper == DevblocksSearchCriteria::OPER_LIKE || $oper == DevblocksSearchCriteria::OPER_NOT_LIKE)
				&& false === (strpos($value,'*'))) {
					$value = $value.'*';
				}
				$criteria = new DevblocksSearchCriteria($field, $oper, $value);
				break;
				
			case SearchFields_Ticket::FULLTEXT_MESSAGE_CONTENT:
				@$scope = DevblocksPlatform::importGPC($_REQUEST['scope'],'string','expert');
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_FULLTEXT,array($value,$scope));
				break;
				
			case SearchFields_Ticket::VIRTUAL_STATUS:
				@$statuses = DevblocksPlatform::importGPC($_REQUEST['value'],'array',array());
				$criteria = new DevblocksSearchCriteria($field, $oper, $statuses);
				break;
				
			case SearchFields_Ticket::TICKET_CREATED_DATE:
			case SearchFields_Ticket::TICKET_UPDATED_DATE:
				@$from = DevblocksPlatform::importGPC($_REQUEST['from'],'string','');
				@$to = DevblocksPlatform::importGPC($_REQUEST['to'],'string','');

				if(empty($from) || (!is_numeric($from) && @false === strtotime(str_replace('.','-',$from))))
					$from = 0;
					
				if(empty($to) || (!is_numeric($to) && @false === strtotime(str_replace('.','-',$to))))
					$to = 'now';

				$criteria = new DevblocksSearchCriteria($field,$oper,array($from,$to));
				break;
				
			case 'placeholder_number':
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_Ticket::REQUESTER_ID:
				@$requester_ids = DevblocksPlatform::importGPC($_REQUEST['requester_ids'],'array',array());
				
				// If blank, this is pointless.
				if(empty($active_contact) || empty($requester_ids))
					break;
				
				$shared_address_ids = DAO_SupportCenterAddressShare::getContactAddressesWithShared($active_contact->id, true);
				if(empty($shared_address_ids))
					$shared_address_ids = array(-1);
					
				// Sanitize the selections to make sure they only include verified addresses on this contact
				$intersect = array_intersect(array_keys($shared_address_ids), $requester_ids);
				
				if(empty($intersect))
					break;
				
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$intersect);
				break;
				
//			default:
//				// Custom Fields
//				if(substr($field,0,3)=='cf_') {
//					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
//				}
//				break;
		}

		if(!empty($criteria)) {
			$param_key = null;
			$results = ($this->findParam($criteria->field, $this->getEditableParams()));
			
			if(!empty($results))
				$param_key = key($results);
			
			$this->addParam($criteria, $param_key);
			$this->renderPage = 0;
		}
	}
};