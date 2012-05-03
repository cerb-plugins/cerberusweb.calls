<?php
/***********************************************************************
| Cerberus Helpdesk(tm) developed by WebGroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2012, WebGroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerberusweb.com	  http://www.webgroupmedia.com/
***********************************************************************/

class PageSection_ProfilesCall extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		$translate = DevblocksPlatform::getTranslationService();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$response = DevblocksPlatform::getHttpResponse();
		$stack = $response->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // call
		$id = array_shift($stack); // 123

		@$id = intval($id);
		
		if(null == ($call = DAO_CallEntry::get($id))) {
			return;
		}
		$tpl->assign('call', $call);
	
		// Tab persistence
		
		$point = 'profiles.call.tab';
		$tpl->assign('point', $point);
		
		if(null == (@$tab_selected = $stack[0])) {
			$tab_selected = $visit->get($point, '');
		}
		$tpl->assign('tab_selected', $tab_selected);
	
		// Custom fields
			
		$custom_fields = DAO_CustomField::getAll();
		$tpl->assign('custom_fields', $custom_fields);
			
		// Properties
			
		$properties = array();
			
		$properties['is_closed'] = array(
			'label' => ucfirst($translate->_('call_entry.model.is_closed')),
			'type' => Model_CustomField::TYPE_CHECKBOX,
			'value' => $call->is_closed,
		);
			
		$properties['is_outgoing'] = array(
			'label' => ucfirst($translate->_('call_entry.model.is_outgoing')),
			'type' => Model_CustomField::TYPE_CHECKBOX,
			'value' => $call->is_outgoing,
		);
			
		$properties['phone'] = array(
			'label' => ucfirst($translate->_('call_entry.model.phone')),
			'type' => Model_CustomField::TYPE_SINGLE_LINE,
			'value' => $call->phone,
		);
			
		$properties['created'] = array(
			'label' => ucfirst($translate->_('common.created')),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $call->created_date,
		);
			
		$properties['updated'] = array(
			'label' => ucfirst($translate->_('common.updated')),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $call->updated_date,
		);
			
		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_CALL, $call->id)) or array();
	
		foreach($custom_fields as $cf_id => $cfield) {
			if(!isset($values[$cf_id]))
				continue;
				
			$properties['cf_' . $cf_id] = array(
				'label' => $cfield->name,
				'type' => $cfield->type,
				'value' => $values[$cf_id],
			);
		}
			
		$tpl->assign('properties', $properties);
			
		// Macros
		$macros = DAO_TriggerEvent::getByOwner(CerberusContexts::CONTEXT_WORKER, $active_worker->id, 'event.macro.call');
		$tpl->assign('macros', $macros);

		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, CerberusContexts::CONTEXT_CALL);
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Template
		$tpl->display('devblocks:cerberusweb.calls::calls/profile.tpl');
	}
};