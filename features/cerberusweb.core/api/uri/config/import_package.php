<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2017, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai	    http://webgroup.media
***********************************************************************/

class PageSection_SetupImportPackage extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		
		$visit->set(ChConfigurationPage::ID, 'import_package');
		
		$tpl->display('devblocks:cerberusweb.core::configuration/section/import_package/index.tpl');
	}
	
	function importJsonAction() {
		header('Content-Type: application/json; charset=utf-8');
		
		try {
			$worker = CerberusApplication::getActiveWorker();
			
			if(!$worker || !$worker->is_superuser)
				throw new Exception("You are not a superuser.");
			
			@$json_string = DevblocksPlatform::importGPC($_POST['json'],'string','');
			@$prompts = DevblocksPlatform::importGPC($_POST['prompts'],'array',[]);
			
			if(false == (@$json = json_decode($json_string, true)))
				throw new Exception("Invalid JSON");
			
			$package = $json['package'];
			
			// Requirements
			$requires = $package['requires'];
			
			if(is_array($requires)) {
				@$target_version = $requires['cerb_version'];
				@$target_plugins = $requires['plugins'];
				
				if(!empty($target_version) && is_string($target_version)) {
					if(!version_compare(APP_VERSION, $target_version, '>='))
						throw new Exception(sprintf("This package requires Cerb version %s or later.", $target_version));
				}
				
				if(is_array($target_plugins))
				foreach($target_plugins as $target_plugin_id) {
					if(!DevblocksPlatform::isPluginEnabled($target_plugin_id))
						throw new Exception(sprintf("This package requires the %s plugin to be installed and enabled.", $target_plugin_id));
				}
			}
			
			$placeholders = [];
			
			// Pre-import configuration
			@$configure = $package['configure'];
			
			@$config_prompts = $configure['prompts'];
			@$config_placeholders = $configure['placeholders'];
			
			if(is_array($config_prompts) && $config_prompts) {
				if(!isset($_POST['prompts'])) {
					$tpl = DevblocksPlatform::getTemplateService();
					$tpl->assign('prompts', $config_prompts);
					$html = $tpl->fetch('devblocks:cerberusweb.core::configuration/section/import_package/prompts.tpl');
					
					echo json_encode([
						'status' => false,
						'prompts' => $html,
					]);
					return;
					
				} else {
					foreach($config_prompts as $config_prompt) {
						@$key = $config_prompt['key'];
						
						if(!$key)
							throw new Exception(sprintf("Prompt key is missing."));
						
						@$value = $prompts[$key];
						
						if(empty($value))
							throw new Exception(sprintf("'%s' (%s) is required.", $config_prompt['label'], $key));
						
						switch($config_prompt['type']) {
							case 'chooser':
								$placeholders[$key] = $value;
								break;
								
							case 'text':
								$placeholders[$key] = $value;
								break;
						}
					}
				}
			}
			
			if(is_array($config_placeholders) && $config_placeholders)
			foreach($config_placeholders as $config_placeholder) {
				@$key = $config_placeholder['key'];
				
				if(!$key)
					throw new Exception(sprintf("Placeholder key is missing."));
				
				switch($config_placeholder['type']) {
					case 'random':
						$length = @$config_placeholder['params']['length'] ?: 8;
						$placeholders[$key] = CerberusApplication::generatePassword($length);
						break;
				}
			}
			
			// Objects
			
			@$custom_fieldsets = $json['custom_fieldsets'];
			@$bots = $json['bots'];
			@$workspaces = $json['workspaces'];
			@$portals = $json['portals'];
			@$saved_searches = $json['saved_searches'];
			@$calendars = $json['calendars'];
			@$classifiers = $json['classifiers'];
			
			$bayes = DevblocksPlatform::getBayesClassifierService();
			
			$uids = [];
			$records_created = [];
			
			///////////////////////////////////////////////////////////////
			// Validation pass
			
			if(is_array($custom_fieldsets))
			foreach($custom_fieldsets as $custom_fieldset) {
				$keys_to_require = ['uid','name','context','owner','fields'];
				$diff = array_diff_key(array_flip($keys_to_require), $custom_fieldset);
				if(count($diff))
					throw new Exception(sprintf("Invalid JSON: custom fieldset is missing properties (%s)", implode(', ', array_keys($diff))));
				
				@$fields = $custom_fieldset['fields'];
				$keys_to_require = ['uid','name','type','params'];
				
				// Check fields
				if(is_array($fields))
				foreach($fields as $field) {
					$diff = array_diff_key(array_flip($keys_to_require), $field);
					if(count($diff))
						throw new Exception(sprintf("Invalid JSON: field is missing properties (%s)", implode(', ', array_keys($diff))));
				}
			}
			
			if(is_array($bots))
			foreach($bots as $bot) {
				$keys_to_require = ['uid','name','owner','is_disabled','params','behaviors'];
				$diff = array_diff_key(array_flip($keys_to_require), $bot);
				if(count($diff))
					throw new Exception(sprintf("Invalid JSON: bot is missing properties (%s)", implode(', ', array_keys($diff))));
				
				@$behaviors = $bot['behaviors'];
				$keys_to_require = ['uid','title','is_disabled','is_private','priority','event','nodes'];
				
				// Check behaviors
				if(is_array($behaviors))
				foreach($behaviors as $behavior) {
					$diff = array_diff_key(array_flip($keys_to_require), $behavior);
					if(count($diff))
						throw new Exception(sprintf("Invalid JSON: behavior is missing properties (%s)", implode(', ', array_keys($diff))));
				}
			}
			
			if(is_array($workspaces))
			foreach($workspaces as $workspace) {
				$keys_to_require = ['uid','name','extension_id','tabs'];
				$diff = array_diff_key(array_flip($keys_to_require), $workspace);
				if(count($diff))
					throw new Exception(sprintf("Invalid JSON: workspace is missing properties (%s)", implode(', ', array_keys($diff))));
				
				@$tabs = $bot['tabs'];
				$keys_to_require = ['uid','name','extension_id','params'];
				
				// Check tabs
				if(is_array($tabs))
				foreach($tabs as $tab) {
					$diff = array_diff_key(array_flip($keys_to_require), $tab);
					if(count($diff))
						throw new Exception(sprintf("Invalid JSON: workspace tab is missing properties (%s)", implode(', ', array_keys($diff))));
				}
			}
			
			if(is_array($portals))
			foreach($portals as $portal) {
				$keys_to_require = ['uid','name','extension_id','params'];
				$diff = array_diff_key(array_flip($keys_to_require), $portal);
				if(count($diff))
					throw new Exception(sprintf("Invalid JSON: portal is missing properties (%s)", implode(', ', array_keys($diff))));
			}
			
			if(is_array($saved_searches))
			foreach($saved_searches as $saved_search) {
				$keys_to_require = ['uid','name','context','tag','query'];
				$diff = array_diff_key(array_flip($keys_to_require), $saved_search);
				if(count($diff))
					throw new Exception(sprintf("Invalid JSON: saved search is missing properties (%s)", implode(', ', array_keys($diff))));
			}
			
			if(is_array($calendars))
			foreach($calendars as $calendar) {
				$keys_to_require = ['uid','name','params'];
				$diff = array_diff_key(array_flip($keys_to_require), $calendar);
				if(count($diff))
					throw new Exception(sprintf("Invalid JSON: calendar is missing properties (%s)", implode(', ', array_keys($diff))));
				
				@$events = $calendar['events'];
				$keys_to_require = ['uid','name','is_available','tz','event_start','event_end','recur_start','recur_end','patterns'];
				
				// Check events
				if(is_array($events))
				foreach($events as $event) {
					$diff = array_diff_key(array_flip($keys_to_require), $event);
					if(count($diff))
						throw new Exception(sprintf("Invalid JSON: calendar event is missing properties (%s)", implode(', ', array_keys($diff))));
				}
			}
			
			if(is_array($classifiers))
			foreach($classifiers as $classifier) {
				$keys_to_require = ['uid','name','params'];
				$diff = array_diff_key(array_flip($keys_to_require), $classifier);
				if(count($diff))
					throw new Exception(sprintf("Invalid JSON: classifier is missing properties (%s)", implode(', ', array_keys($diff))));
				
				@$classes = $classifier['classes'];
				$keys_to_require = ['uid','name','expressions'];
				
				// Check classifications
				if(is_array($classes))
				foreach($classes as $class) {
					$diff = array_diff_key(array_flip($keys_to_require), $class);
					if(count($diff))
						throw new Exception(sprintf("Invalid JSON: classification is missing properties (%s)", implode(', ', array_keys($diff))));
					
					@$expressions = $class['expressions'];
					
					if(!is_array($expressions))
						continue;
					
					foreach($expressions as $expression) {
						if(!$bayes::verify($expression))
							throw new Exception(sprintf("Invalid JSON: invalid training in classifier (%s -> %s): %s", $classifier['name'], $class['name'], $expression));
					}
				}
			}
			
			///////////////////////////////////////////////////////////////
			// Insertion pass (when everything is OK)
			
			if(is_array($custom_fieldsets))
			foreach($custom_fieldsets as $custom_fieldset) {
				$uid = $custom_fieldset['uid'];
				
				$custom_fieldset_id = DAO_CustomFieldset::create([
					DAO_CustomFieldset::NAME => $custom_fieldset['name'],
					DAO_CustomFieldset::CONTEXT => $custom_fieldset['context'],
					DAO_CustomFieldset::OWNER_CONTEXT => CerberusContexts::CONTEXT_APPLICATION,
					DAO_CustomFieldset::OWNER_CONTEXT_ID => 0,
				]);
				
				$uids[$custom_fieldset['uid']] = $custom_fieldset_id;
				
				$fields = $custom_fieldset['fields'];
				
				if(is_array($fields))
				foreach($fields as $field) {
					$uid = $field['uid'];
					
					$custom_field_id = DAO_CustomField::create([
						DAO_CustomField::NAME => $uid,
						DAO_CustomField::TYPE => $field['type'],
						DAO_CustomField::PARAMS_JSON => json_encode([]),
						DAO_CustomField::CUSTOM_FIELDSET_ID => $custom_fieldset_id,
					]);
					
					$uids[$field['uid']] = $custom_field_id;
				}
			}
			
			if(is_array($bots))
			foreach($bots as $bot) {
				$uid = $bot['uid'];
				
				$bot_id = DAO_Bot::create([
					DAO_Bot::NAME => $bot['name'],
					DAO_Bot::OWNER_CONTEXT => CerberusContexts::CONTEXT_APPLICATION,
					DAO_Bot::OWNER_CONTEXT_ID => 0,
				]);
				
				$uids[$uid] = $bot_id;
				
				@$behaviors = $bot['behaviors'];
				
				if(is_array($behaviors))
				foreach($behaviors as $behavior) {
					$uid = $behavior['uid'];
					
					$behavior_id = DAO_TriggerEvent::create([
						DAO_TriggerEvent::TITLE => $behavior['title'],
						DAO_TriggerEvent::BOT_ID => $bot_id,
					]);
					
					$uids[$uid] = $behavior_id;
				}
			}
			
			if(is_array($workspaces))
			foreach($workspaces as $workspace) {
				$uid = $workspace['uid'];
				
				$workspace_id = DAO_WorkspacePage::create([
					DAO_WorkspacePage::NAME => $workspace['name'],
					DAO_WorkspacePage::OWNER_CONTEXT => CerberusContexts::CONTEXT_APPLICATION,
					DAO_WorkspacePage::OWNER_CONTEXT_ID => 0,
				]);
				
				$uids[$uid] = $workspace_id;
				
				@$tabs = $workspace['tabs'];
				
				if(is_array($tabs))
				foreach($tabs as $tab) {
					$uid = $tab['uid'];
					
					$tab_id = DAO_WorkspaceTab::create([
						DAO_WorkspaceTab::NAME => $tab['name'],
						DAO_WorkspaceTab::WORKSPACE_PAGE_ID => $workspace_id,
					]);
					
					$uids[$uid] = $tab_id;
				}
			}
			
			if(is_array($portals))
			foreach($portals as $portal) {
				$uid = $portal['uid'];
				
				$portal_code = DAO_CommunityTool::generateUniqueCode(8);
				
				$portal_id = DAO_CommunityTool::create([
					DAO_CommunityTool::NAME => $portal['name'],
					DAO_CommunityTool::CODE => $portal_code,
					DAO_CommunityTool::EXTENSION_ID => $portal['extension_id'],
				]);
				
				$uids[$uid] = $portal_id;
			}
			
			if(is_array($saved_searches))
			foreach($saved_searches as $saved_search) {
				$uid = $saved_search['uid'];
				
				$search_id = DAO_ContextSavedSearch::create([
					DAO_ContextSavedSearch::NAME => $saved_search['name'],
					DAO_ContextSavedSearch::OWNER_CONTEXT => CerberusContexts::CONTEXT_APPLICATION,
					DAO_ContextSavedSearch::OWNER_CONTEXT_ID => 0,
					DAO_ContextSavedSearch::UPDATED_AT => time(),
				]);
				
				$uids[$uid] = $search_id;
			}
			
			if(is_array($calendars))
			foreach($calendars as $calendar) {
				$uid = $calendar['uid'];
				
				$calendar_id = DAO_Calendar::create([
					DAO_Calendar::NAME => $calendar['name'],
					DAO_Calendar::OWNER_CONTEXT => CerberusContexts::CONTEXT_APPLICATION,
					DAO_Calendar::OWNER_CONTEXT_ID => 0,
					DAO_Calendar::UPDATED_AT => time(),
				]);
				
				$uids[$uid] = $calendar_id;
				
				@$events = $calendar['events'];
				
				if(is_array($events))
				foreach($events as $event) {
					$uid = $event['uid'];
					
					$event_id = DAO_CalendarRecurringProfile::create([
						DAO_CalendarRecurringProfile::EVENT_NAME => $event['name'],
						DAO_CalendarRecurringProfile::CALENDAR_ID => $calendar_id,
					]);
					
					$uids[$uid] = $event_id;
				}
			}
			
			if(is_array($classifiers))
			foreach($classifiers as $classifier) {
				$uid = $classifier['uid'];
				
				$classifier_id = DAO_Classifier::create([
					DAO_Classifier::NAME => $classifier['name'],
					DAO_Classifier::OWNER_CONTEXT => CerberusContexts::CONTEXT_APPLICATION,
					DAO_Classifier::OWNER_CONTEXT_ID => 0,
					DAO_Classifier::CREATED_AT => time(),
					DAO_Classifier::UPDATED_AT => time(),
				]);
				
				$uids[$uid] = $classifier_id;
				
				@$classes = $classifier['classes'];
				
				if(is_array($classes))
				foreach($classes as $class) {
					$uid = $class['uid'];
					
					$class_id = DAO_ClassifierClass::create([
						DAO_ClassifierClass::NAME => $class['name'],
						DAO_ClassifierClass::CLASSIFIER_ID => $classifier_id,
						DAO_ClassifierClass::UPDATED_AT => time(),
					]);
					
					$uids[$uid] = $class_id;
				}
			}
			
			$new_json_string = json_encode(array_diff_key($json, ['package'=>true]));
			
			$tpl_builder = DevblocksPlatform::getTemplateBuilder();
			$lexer = array(
				'tag_comment'   => array('{{#', '#}}'),
				'tag_block'     => array('{{%', '%}}'),
				'tag_variable'  => array('{{{', '}}}'),
				'interpolation' => array('#{{', '}}'),
			);
			
			// Add UID placeholders
			$placeholders['uid'] = $uids;
			
			$new_json_string = $tpl_builder->build($new_json_string, $placeholders, $lexer);
			
			$json = json_decode($new_json_string, true);
			
			unset($new_json_string);
			
			///////////////////////////////////////////////////////////////
			// Update pass (finalize data)
			
			@$custom_fieldsets = $json['custom_fieldsets'];
			
			if(is_array($custom_fieldsets))
			foreach($custom_fieldsets as $custom_fieldset) {
				$uid = $custom_fieldset['uid'];
				$id = $uids[$uid];
				
				DAO_CustomFieldset::update($id, [
					DAO_CustomFieldset::NAME => $custom_fieldset['name'],
					DAO_CustomFieldset::CONTEXT => $custom_fieldset['context'],
				]);
				
				$records_created[CerberusContexts::CONTEXT_CUSTOM_FIELDSET][] = [
					'id' => $id,
					'label' => $custom_fieldset['name'],
				];
				
				$custom_fields = $custom_fieldset['fields'];
				
				foreach($custom_fields as $pos => $custom_field) {
					$uid = $custom_field['uid'];
					$id = $uids[$uid];
					
					DAO_CustomField::update($id, [
						DAO_CustomField::NAME => $custom_field['name'],
						DAO_CustomField::TYPE => $custom_field['type'],
						DAO_CustomField::CONTEXT => $custom_fieldset['context'],
						DAO_CustomField::POS => $pos,
						DAO_CustomField::PARAMS_JSON => json_encode($custom_field['params']),
					]);
				}
			}
			
			@$bots = $json['bots'];
			
			if(is_array($bots))
			foreach($bots as $bot) {
				$uid = $bot['uid'];
				$id = $uids[$uid];
				
				DAO_Bot::update($id, [
					DAO_Bot::NAME => $bot['name'],
					DAO_Bot::IS_DISABLED => @$bot['is_disabled'] ? 1 : 0,
					DAO_Bot::CREATED_AT => time(),
					DAO_Bot::UPDATED_AT => time(),
					DAO_Bot::PARAMS_JSON => json_encode($bot['params']),
				]);
				
				$records_created[CerberusContexts::CONTEXT_BOT][] = [
					'id' => $id,
					'label' => $bot['name'],
				];
				
				// Image
				
				if(isset($bot['image']) && !empty($bot['image'])) {
					DAO_ContextAvatar::upsertWithImage(CerberusContexts::CONTEXT_BOT, $id, $bot['image']);
				}
				
				// Behaviors
				
				$behaviors = $bot['behaviors'];
				
				foreach($behaviors as $behavior) {
					$uid = $behavior['uid'];
					$id = $uids[$uid];
					
					@$event_params = isset($behavior['event']['params']) ? $behavior['event']['params'] : '';
					$error = null;
					
					if(false !== (@$event = Extension_DevblocksEvent::get($behavior['event']['key'], true)))
						$event->prepareEventParams(null, $event_params, $error);
					
					DAO_TriggerEvent::update($id, [
						DAO_TriggerEvent::EVENT_POINT => $behavior['event']['key'],
						DAO_TriggerEvent::EVENT_PARAMS_JSON => json_encode($event_params),
						DAO_TriggerEvent::IS_DISABLED => 1, // @$behavior['is_disabled'] ? 1 : 0, // until successfully imported
						DAO_TriggerEvent::IS_PRIVATE => @$behavior['is_private'] ? 1 : 0,
						DAO_TriggerEvent::PRIORITY => @$behavior['priority'],
						DAO_TriggerEvent::TITLE => $behavior['title'],
						DAO_TriggerEvent::UPDATED_AT => time(),
						DAO_TriggerEvent::VARIABLES_JSON => isset($behavior['variables']) ? json_encode($behavior['variables']) : '',
					]);
					
					// Create records for all child nodes and link them to the proper parents
					
					if(isset($behavior['nodes']))
					if(false == DAO_TriggerEvent::recursiveImportDecisionNodes($behavior['nodes'], $id, 0))
						throw new Exception('Failed to import behavior nodes');
					
					// Enable the new behavior since we've succeeded
					
					DAO_TriggerEvent::update($id, array(
						DAO_TriggerEvent::IS_DISABLED => @$behavior['is_disabled'] ? 1 : 0,
					));
				}
			}
			
			@$workspaces = $json['workspaces'];
			
			if(is_array($workspaces))
			foreach($workspaces as $workspace) {
				$uid = $workspace['uid'];
				$id = $uids[$uid];
				
				DAO_WorkspacePage::update($id, [
					DAO_WorkspacePage::NAME => $workspace['name'],
					DAO_WorkspacePage::EXTENSION_ID => $workspace['extension_id'],
				]);
				
				$records_created[CerberusContexts::CONTEXT_WORKSPACE_PAGE][] = [
					'id' => $id,
					'label' => $workspace['name'],
				];
				
				$tabs = $workspace['tabs'];
				
				foreach($tabs as $tab_idx => $tab) {
					$uid = $tab['uid'];
					$id = $uids[$uid];
					
					DAO_WorkspaceTab::update($id, [
						DAO_WorkspaceTab::NAME => $tab['name'],
						DAO_WorkspaceTab::EXTENSION_ID => $tab['extension_id'],
						DAO_WorkspaceTab::POS => $tab_idx,
						DAO_WorkspaceTab::PARAMS_JSON => isset($tab['params']) ? json_encode($tab['params']) : '',
					]);
					
					if(false == ($extension = Extension_WorkspaceTab::get($tab['extension_id']))) /* @var $extension Extension_WorkspaceTab */
						throw new Exception('Failed to instantiate workspace tab extension: ' . $tab['extension_id']);
					
					if(false == ($model = DAO_WorkspaceTab::get($id)))
						throw new Exception('Failed to load workspace tab model: ' . $tab['extension_id']);
					
					$import_json = ['tab' => $tab];
					$extension->importTabConfigJson($import_json, $model);
				}
			}
			
			@$portals = $json['portals'];
			
			if(is_array($portals))
			foreach($portals as $portal) {
				$uid = $portal['uid'];
				$id = $uids[$uid];
				
				DAO_CommunityTool::update($id, [
					DAO_CommunityTool::NAME => $portal['name'],
					DAO_CommunityTool::EXTENSION_ID => $portal['extension_id'],
				]);
				
				$portal_model = DAO_CommunityTool::get($id);
				
				$records_created[CerberusContexts::CONTEXT_PORTAL][] = [
					'id' => $id,
					'label' => $portal['name'],
					'code' => $portal_model->code,
				];
				
				$params = $portal['params'];
				
				if(is_array($params))
				foreach($params as $k => $v) {
					$uid = $tab['uid'];
					$id = $uids[$uid];
					
					DAO_CommunityToolProperty::set($portal_model->code, $k, $v);
				}
			}
			
			@$saved_searches = $json['saved_searches'];
			
			if(is_array($saved_searches))
			foreach($saved_searches as $saved_search) {
				$uid = $saved_search['uid'];
				$id = $uids[$uid];
				
				DAO_ContextSavedSearch::update($id, [
					DAO_ContextSavedSearch::NAME => $saved_search['name'],
					DAO_ContextSavedSearch::CONTEXT => $saved_search['context'],
					DAO_ContextSavedSearch::TAG => $saved_search['tag'],
					DAO_ContextSavedSearch::QUERY => $saved_search['query'],
					DAO_ContextSavedSearch::OWNER_CONTEXT => CerberusContexts::CONTEXT_APPLICATION,
					DAO_ContextSavedSearch::OWNER_CONTEXT_ID => 0,
				]);
				
				$records_created[CerberusContexts::CONTEXT_SAVED_SEARCH][] = [
					'id' => $id,
					'label' => $saved_search['name'],
				];
			}
			
			@$calendars = $json['calendars'];
			
			if(is_array($calendars))
			foreach($calendars as $calendar) {
				$uid = $calendar['uid'];
				$id = $uids[$uid];
				
				DAO_Calendar::update($id, [
					DAO_Calendar::NAME => $calendar['name'],
					DAO_Calendar::PARAMS_JSON => isset($calendar['params']) ? json_encode($calendar['params']) : '',
					DAO_Calendar::UPDATED_AT => time(),
					DAO_Calendar::OWNER_CONTEXT => CerberusContexts::CONTEXT_APPLICATION,
					DAO_Calendar::OWNER_CONTEXT_ID => 0,
				]);
				
				$records_created[CerberusContexts::CONTEXT_CALENDAR][] = [
					'id' => $id,
					'label' => $calendar['name'],
				];
				
				$calendar_id = $id;
				@$events = $calendar['events'];
				
				if(is_array($events))
				foreach($events as $event) {
					$uid = $event['uid'];
					$id = $uids[$uid];
					
					$event_id = DAO_CalendarRecurringProfile::update($id, [
						DAO_CalendarRecurringProfile::EVENT_NAME => $event['name'],
						DAO_CalendarRecurringProfile::CALENDAR_ID => $calendar_id,
						DAO_CalendarRecurringProfile::IS_AVAILABLE => @$event['is_available'] ? 1 : 0,
						DAO_CalendarRecurringProfile::TZ => $event['tz'],
						DAO_CalendarRecurringProfile::EVENT_START => $event['event_start'],
						DAO_CalendarRecurringProfile::EVENT_END => $event['event_end'],
						DAO_CalendarRecurringProfile::RECUR_START => $event['recur_start'],
						DAO_CalendarRecurringProfile::RECUR_END => $event['recur_end'],
						DAO_CalendarRecurringProfile::PATTERNS => implode("\n", is_array(@$event['patterns']) ? $event['patterns'] : []),
					]);
				}
			}
			
			@$classifiers = $json['classifiers'];
			
			if(is_array($classifiers))
			foreach($classifiers as $classifier) {
				$uid = $classifier['uid'];
				$id = $uids[$uid];
				$classifier_id = $id;
				
				DAO_Classifier::update($id, [
					DAO_Classifier::NAME => $classifier['name'],
					DAO_Classifier::PARAMS_JSON => isset($classifier['params']) ? json_encode($classifier['params']) : '',
					DAO_Classifier::UPDATED_AT => time(),
					DAO_Classifier::OWNER_CONTEXT => CerberusContexts::CONTEXT_APPLICATION,
					DAO_Classifier::OWNER_CONTEXT_ID => 0,
				]);
				
				$records_created[CerberusContexts::CONTEXT_CLASSIFIER][] = [
					'id' => $id,
					'label' => $classifier['name'],
				];
				
				@$classes = $classifier['classes'];
				
				if(is_array($classes))
				foreach($classes as $class) {
					$uid = $class['uid'];
					$id = $uids[$uid];
					$class_id = $id;
					
					DAO_ClassifierClass::update($id, [
						DAO_ClassifierClass::NAME => $class['name'],
						DAO_ClassifierClass::CLASSIFIER_ID => $classifier_id,
						DAO_ClassifierClass::UPDATED_AT => time(),
					]);
					
					@$expressions = $class['expressions'];
					
					if(!is_array($expressions))
						continue;
					
					foreach($expressions as $expression) {
						DAO_ClassifierExample::create([
							DAO_ClassifierExample::CLASSIFIER_ID => $classifier_id,
							DAO_ClassifierExample::CLASS_ID => $class_id,
							DAO_ClassifierExample::EXPRESSION => $expression,
							DAO_ClassifierExample::UPDATED_AT => time(),
						]);
						
						$bayes::train($expression, $classifier_id, $class_id, true);
					}
				}
				
				$bayes::build($classifier_id);
			}
			
			$tpl = DevblocksPlatform::getTemplateService();
			$tpl->assign('records_created', $records_created);
			$results_html = $tpl->fetch('devblocks:cerberusweb.core::configuration/section/import_package/results.tpl');
			
			echo json_encode(array('status' => true, 'results_html' => $results_html));
			return;
				
		} catch(Exception $e) {
			// [TODO] On failure, delete temporary UIDs?
			
			echo json_encode(array('status' => false, 'error' => $e->getMessage()));
			return;
		}
	}
};
