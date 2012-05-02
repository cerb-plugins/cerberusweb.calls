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

class DAO_CallEntry extends C4_ORMHelper {
	const ID = 'id';
	const SUBJECT = 'subject';
	const PHONE = 'phone';
	const CREATED_DATE = 'created_date';
	const UPDATED_DATE = 'updated_date';
	const IS_OUTGOING = 'is_outgoing';
	const IS_CLOSED = 'is_closed';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("INSERT INTO call_entry () ".
			"VALUES ()"
		);
		$db->Execute($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		parent::_update($ids, 'call_entry', $fields);
		
	    // Log the context update
   		DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_CALL, $ids);
	}
	
	/**
	 * @param string $where
	 * @return Model_CallEntry[]
	 */
	static function getWhere($where=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "SELECT id, subject, phone, created_date, updated_date, is_outgoing, is_closed ".
			"FROM call_entry ".
			(!empty($where) ? sprintf("WHERE %s ",$where) : "").
			"ORDER BY updated_date desc";
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_CallEntry	 */
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
	 * @param resource $rs
	 * @return Model_CallEntry[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_CallEntry();
			$object->id = $row['id'];
			$object->subject = $row['subject'];
			$object->phone = $row['phone'];
			$object->created_date = $row['created_date'];
			$object->updated_date = $row['updated_date'];
			$object->is_outgoing = $row['is_outgoing'];
			$object->is_closed = $row['is_closed'];
			$objects[$object->id] = $object;
		}
		
		mysql_free_result($rs);
		
		return $objects;
	}

	static function maint() {
		// Fire event
	    $eventMgr = DevblocksPlatform::getEventService();
	    $eventMgr->trigger(
	        new Model_DevblocksEvent(
	            'context.maint',
                array(
                	'context' => CerberusContexts::CONTEXT_CALL,
                	'context_table' => 'call_entry',
                	'context_key' => 'id',
                )
            )
	    );
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		$ids_list = implode(',', $ids);
		
		$db->Execute(sprintf("DELETE FROM call_entry WHERE id IN (%s)", $ids_list));
		
		// Fire event
	    $eventMgr = DevblocksPlatform::getEventService();
	    $eventMgr->trigger(
	        new Model_DevblocksEvent(
	            'context.delete',
                array(
                	'context' => CerberusContexts::CONTEXT_CALL,
                	'context_ids' => $ids
                )
            )
	    );
		
		return true;
	}

	public static function random() {
		return self::_getRandom('call_entry');
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_CallEntry::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]) || !in_array($sortBy,$columns))
			$sortBy=null;

        list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"c.id as %s, ".
			"c.subject as %s, ".
			"c.phone as %s, ".
			"c.created_date as %s, ".
			"c.updated_date as %s, ".
			"c.is_outgoing as %s, ".
			"c.is_closed as %s ",
			    SearchFields_CallEntry::ID,
			    SearchFields_CallEntry::SUBJECT,
			    SearchFields_CallEntry::PHONE,
			    SearchFields_CallEntry::CREATED_DATE,
			    SearchFields_CallEntry::UPDATED_DATE,
			    SearchFields_CallEntry::IS_OUTGOING,
			    SearchFields_CallEntry::IS_CLOSED
			 );
		
		$join_sql = 
			"FROM call_entry c ".

		// [JAS]: Dynamic table joins
			(isset($tables['context_link']) ? "INNER JOIN context_link ON (context_link.to_context = 'cerberusweb.contexts.call' AND context_link.to_context_id = c.id) " : " ").
			(isset($tables['ftcc']) ? "INNER JOIN comment ON (comment.context = 'cerberusweb.contexts.call' AND comment.context_id = c.id) " : " ").
			(isset($tables['ftcc']) ? "INNER JOIN fulltext_comment_content ftcc ON (ftcc.id=comment.id) " : " ")
			;
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'c.id',
			$select_sql,
			$join_sql
		);
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql =	(!empty($sortBy) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ");
		
		// Translate virtual fields
		
		array_walk_recursive(
			$params,
			array('DAO_CallEntry', '_translateVirtualParameters'),
			array(
				'join_sql' => &$join_sql,
				'where_sql' => &$where_sql,
				'has_multiple_values' => &$has_multiple_values
			)
		);
		
		$result = array(
			'primary_table' => 'c',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
		
		return $result;
	}	
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
		
		$param_key = $param->field;
		settype($param_key, 'string');
		switch($param_key) {
			case SearchFields_CallEntry::VIRTUAL_WATCHERS:
				$args['has_multiple_values'] = true;
				$from_context = 'cerberusweb.contexts.call';
				$from_index = 'c.id';
				
				self::_searchComponentsVirtualWatchers($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		}
	}
	
    /**
     * Enter description here...
     *
     * @param DevblocksSearchCriteria[] $params
     * @param integer $limit
     * @param integer $page
     * @param string $sortBy
     * @param boolean $sortAsc
     * @param boolean $withCounts
     * @return array
     */
    static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();

		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql = 
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY c.id ' : '').
			$sort_sql;
		
		$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); 
		
		$results = array();
		
		while($row = mysql_fetch_assoc($rs)) {
			$result = array();
			foreach($row as $f => $v) {
				$result[$f] = $v;
			}
			$id = intval($row[SearchFields_CallEntry::ID]);
			$results[$id] = $result;
		}
		
		// [JAS]: Count all
		$total = -1;
		if($withCounts) {
			$count_sql = "SELECT count(*) " . $join_sql . $where_sql;
			$total = $db->GetOne($count_sql);
		}
		
		mysql_free_result($rs);
		
		return array($results,$total);
    }
	
};

class Model_CallEntry {
	public $id;
	public $subject;
	public $phone;
	public $created_date;
	public $updated_date;
	public $is_outgoing;
	public $is_closed;
};

class SearchFields_CallEntry {
	const ID = 'c_id';
	const SUBJECT = 'c_subject';
	const PHONE = 'c_phone';
	const CREATED_DATE = 'c_created_date';
	const UPDATED_DATE = 'c_updated_date';
	const IS_OUTGOING = 'c_is_outgoing';
	const IS_CLOSED = 'c_is_closed';
	
	// Context Links
	const CONTEXT_LINK = 'cl_context_from';
	const CONTEXT_LINK_ID = 'cl_context_from_id';
	
	// Comment Content
	const FULLTEXT_COMMENT_CONTENT = 'ftcc_content';

	// Virtuals
	const VIRTUAL_WATCHERS = '*_workers';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'c', 'id', $translate->_('common.id'), Model_CustomField::TYPE_NUMBER),
			self::SUBJECT => new DevblocksSearchField(self::SUBJECT, 'c', 'subject', $translate->_('message.header.subject'), Model_CustomField::TYPE_SINGLE_LINE),
			self::PHONE => new DevblocksSearchField(self::PHONE, 'c', 'phone', $translate->_('call_entry.model.phone'), Model_CustomField::TYPE_SINGLE_LINE),
			self::CREATED_DATE => new DevblocksSearchField(self::CREATED_DATE, 'c', 'created_date', $translate->_('common.created'), Model_CustomField::TYPE_DATE),
			self::UPDATED_DATE => new DevblocksSearchField(self::UPDATED_DATE, 'c', 'updated_date', $translate->_('common.updated'), Model_CustomField::TYPE_DATE),
			self::IS_OUTGOING => new DevblocksSearchField(self::IS_OUTGOING, 'c', 'is_outgoing', $translate->_('call_entry.model.is_outgoing'), Model_CustomField::TYPE_CHECKBOX),
			self::IS_CLOSED => new DevblocksSearchField(self::IS_CLOSED, 'c', 'is_closed', $translate->_('call_entry.model.is_closed'), Model_CustomField::TYPE_CHECKBOX),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null, null),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null, null),
			
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS'),
		);
		
		$tables = DevblocksPlatform::getDatabaseTables();
		if(isset($tables['fulltext_comment_content'])) {
			$columns[self::FULLTEXT_COMMENT_CONTENT] = new DevblocksSearchField(self::FULLTEXT_COMMENT_CONTENT, 'ftcc', 'content', $translate->_('comment.filters.content'), 'FT');
		}
		
		// Custom Fields
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CALL);
		if(is_array($fields))
		foreach($fields as $field_id => $field) {
			$key = 'cf_'.$field_id;
			$columns[$key] = new DevblocksSearchField($key,$key,'field_value',$field->name,$field->type);
		}
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;		
	}
};

class View_CallEntry extends C4_AbstractView implements IAbstractView_Subtotals {
	const DEFAULT_ID = 'call_entries';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('calls.activity.tab');
		$this->renderLimit = 10;
		$this->renderSortBy = SearchFields_CallEntry::CREATED_DATE;
		$this->renderSortAsc = false;

		$this->view_columns = array(
			SearchFields_CallEntry::IS_OUTGOING,
			SearchFields_CallEntry::PHONE,
			SearchFields_CallEntry::UPDATED_DATE,
		);
		$this->addColumnsHidden(array(
			SearchFields_CallEntry::ID,
			SearchFields_CallEntry::CONTEXT_LINK,
			SearchFields_CallEntry::CONTEXT_LINK_ID,
			SearchFields_CallEntry::FULLTEXT_COMMENT_CONTENT,
			SearchFields_CallEntry::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
			SearchFields_CallEntry::ID,
			SearchFields_CallEntry::CONTEXT_LINK,
			SearchFields_CallEntry::CONTEXT_LINK_ID,
		));

		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_CallEntry::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		return $objects;
	}

	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_CallEntry', $ids);
	}
	
	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable();
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Booleans
				case SearchFields_CallEntry::IS_CLOSED:
				case SearchFields_CallEntry::IS_OUTGOING:
					$pass = true;
					break;
					
				// Watchers
				case SearchFields_CallEntry::VIRTUAL_WATCHERS:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if('cf_' == substr($field_key,0,3))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = array();
		$fields = $this->getFields();

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_CallEntry::IS_CLOSED:
			case SearchFields_CallEntry::IS_OUTGOING:
				$counts = $this->_getSubtotalCountForBooleanColumn('DAO_CallEntry', $column);
				break;

			case SearchFields_CallEntry::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_CallEntry', $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_CallEntry', $column, 'c.id');
				}
				
				break;
		}
		
		return $counts;
	}	
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		$workers = DAO_Worker::getAll();
		$tpl->assign('workers', $workers);
		
		switch($this->renderTemplate) {
			case 'contextlinks_chooser':
			default:
				$tpl->assign('view_template', 'devblocks:cerberusweb.calls::calls/view.tpl');
				$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
				break;
		}
	}
	
	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		switch($key) {
			case SearchFields_CallEntry::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}	

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		switch($field) {
			case SearchFields_CallEntry::SUBJECT:
			case SearchFields_CallEntry::PHONE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
			case SearchFields_CallEntry::CREATED_DATE:
			case SearchFields_CallEntry::UPDATED_DATE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
			case SearchFields_CallEntry::IS_CLOSED:
			case SearchFields_CallEntry::IS_OUTGOING:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
			case SearchFields_CallEntry::FULLTEXT_COMMENT_CONTENT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__fulltext.tpl');
				break;
			case SearchFields_CallEntry::VIRTUAL_WATCHERS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
				break;
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_CallEntry::IS_CLOSED:
			case SearchFields_CallEntry::IS_OUTGOING:
				$this->_renderCriteriaParamBoolean($param);
				break;
			
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_CallEntry::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_CallEntry::SUBJECT:
			case SearchFields_CallEntry::PHONE:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
			
			case SearchFields_CallEntry::IS_CLOSED:
			case SearchFields_CallEntry::IS_OUTGOING:
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_CallEntry::CREATED_DATE:
			case SearchFields_CallEntry::UPDATED_DATE:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case SearchFields_CallEntry::FULLTEXT_COMMENT_CONTENT:
				@$scope = DevblocksPlatform::importGPC($_REQUEST['scope'],'string','expert');
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_FULLTEXT,array($value,$scope));
				break;
				
			case SearchFields_CallEntry::VIRTUAL_WATCHERS:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$worker_ids);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria);
			$this->renderPage = 0;
		}
	}
	
	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m
		
		$change_fields = array();
		$custom_fields = array();

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				case 'is_closed':
					if(!empty($v)) { // completed
						$change_fields[DAO_CallEntry::IS_CLOSED] = 1;
					} else { // active
						$change_fields[DAO_CallEntry::IS_CLOSED] = 0;
					}
					break;
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
			}
		}
		
		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_CallEntry::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_CallEntry::ID,
				true,
				false
			);
			 
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			DAO_CallEntry::update($batch_ids, $change_fields);
			
			// Custom Fields
			self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_CALL, $custom_fields, $batch_ids);
			
			// Scheduled behavior
			if(isset($do['behavior']) && is_array($do['behavior'])) {
				$behavior_id = $do['behavior']['id'];
				@$behavior_when = strtotime($do['behavior']['when']) or time();
				@$behavior_params = isset($do['behavior']['params']) ? $do['behavior']['params'] : array();
				
				if(!empty($batch_ids) && !empty($behavior_id))
				foreach($batch_ids as $batch_id) {
					DAO_ContextScheduledBehavior::create(array(
						DAO_ContextScheduledBehavior::BEHAVIOR_ID => $behavior_id,
						DAO_ContextScheduledBehavior::CONTEXT => CerberusContexts::CONTEXT_CALL,
						DAO_ContextScheduledBehavior::CONTEXT_ID => $batch_id,
						DAO_ContextScheduledBehavior::RUN_DATE => $behavior_when,
						DAO_ContextScheduledBehavior::VARIABLES_JSON => json_encode($behavior_params),
					));
				}
			}
			
			// Watchers
			if(isset($do['watchers']) && is_array($do['watchers'])) {
				$watcher_params = $do['watchers'];
				foreach($batch_ids as $batch_id) {
					if(isset($watcher_params['add']) && is_array($watcher_params['add']))
						CerberusContexts::addWatchers(CerberusContexts::CONTEXT_CALL, $batch_id, $watcher_params['add']);
					if(isset($watcher_params['remove']) && is_array($watcher_params['remove']))
						CerberusContexts::removeWatchers(CerberusContexts::CONTEXT_CALL, $batch_id, $watcher_params['remove']);
				}
			}
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_Call extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek, IDevblocksContextImport {
	function getRandom() {
		return DAO_CallEntry::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=call&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$call = DAO_CallEntry::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($call->subject);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $call->id,
			'name' => $call->subject,
			'permalink' => $url,
		);
	}
	
	function getContext($call, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Call:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CALL);

		// Polymorph
		if(is_numeric($call)) {
			$call = DAO_CallEntry::get($call);
		} elseif($call instanceof Model_CallEntry) {
			// It's what we want already.
		} else {
			$call = null;
		}
		
		// Token labels
		$token_labels = array(
			'created|date' => $prefix.$translate->_('common.created'),
			'is_closed' => $prefix.$translate->_('call_entry.model.is_closed'),
			'is_outgoing' => $prefix.$translate->_('call_entry.model.is_outgoing'),
			'phone' => $prefix.$translate->_('call_entry.model.phone'),
			'subject' => $prefix.$translate->_('message.header.subject'),
			'updated|date' => $prefix.$translate->_('common.updated'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		if(is_array($fields))
		foreach($fields as $cf_id => $field) {
			$token_labels['custom_'.$cf_id] = $prefix.$field->name;
		}

		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_CALL;
		
		// Call token values
		if($call) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $call->subject;
			$token_values['id'] = $call->id;
			$token_values['created'] = $call->created_date;
			$token_values['is_closed'] = $call->is_closed;
			$token_values['is_outgoing'] = $call->is_outgoing;
			$token_values['phone'] = $call->phone;
			$token_values['subject'] = $call->subject;
			$token_values['updated'] = $call->updated_date;
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=call&id=%d-%s",$call->id, DevblocksPlatform::strToPermalink($call->subject)), true);
		}
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_CALL;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values);
		}
		
		switch($token) {
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(substr($token,0,7) == 'custom_') {
					$fields = $this->_lazyLoadCustomFields($context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}	
	
	function getChooserView($view_id=null) {
		$active_worker = CerberusApplication::getActiveWorker();

		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
	
		// View
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Calls';
		$view->view_columns = array(
			SearchFields_CallEntry::IS_OUTGOING,
			SearchFields_CallEntry::PHONE,
			SearchFields_CallEntry::UPDATED_DATE,
		);
		$view->addParams(array(
			SearchFields_CallEntry::IS_CLOSED => new DevblocksSearchCriteria(SearchFields_CallEntry::IS_CLOSED,'=',0),
		), true);
		$view->renderSortBy = SearchFields_CallEntry::UPDATED_DATE;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderFilters = false;
		$view->renderTemplate = 'contextlinks_chooser';
		
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array()) {
		$view_id = str_replace('.','_',$this->id);
		
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id; 
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Calls';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_CallEntry::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_CallEntry::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='') {
		$id = $context_id; // [TODO] Rename below and remove
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		if(!empty($id) && null != ($call = DAO_CallEntry::get($id))) {
			$tpl->assign('model', $call);
		}
		
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CALL);
		$tpl->assign('custom_fields', $custom_fields);
		
		if(!empty($id)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_CALL, $id);
			if(isset($custom_field_values[$id]))
				$tpl->assign('custom_field_values', $custom_field_values[$id]);
		}

		// Comments
		$comments = DAO_Comment::getByContext(CerberusContexts::CONTEXT_CALL, $id);
		$last_comment = array_shift($comments);
		unset($comments);
		$tpl->assign('last_comment', $last_comment);
		
		$tpl->display('devblocks:cerberusweb.calls::calls/ajax/peek.tpl');
	}
	
	function importGetKeys() {
		// [TODO] Translate
	
		$keys = array(
			'created_date' => array(
				'label' => 'Created Date',
				'type' => Model_CustomField::TYPE_DATE,
				'param' => SearchFields_CallEntry::CREATED_DATE,
			),
			'is_closed' => array(
				'label' => 'Is Closed',
				'type' => Model_CustomField::TYPE_CHECKBOX,
				'param' => SearchFields_CallEntry::IS_CLOSED,
			),
			'is_outgoing' => array(
				'label' => 'Is Outgoing',
				'type' => Model_CustomField::TYPE_CHECKBOX,
				'param' => SearchFields_CallEntry::IS_OUTGOING,
			),
			'phone' => array(
				'label' => 'Phone',
				'type' => Model_CustomField::TYPE_SINGLE_LINE,
				'param' => SearchFields_CallEntry::PHONE,
				'required' => true,
			),
			'subject' => array(
				'label' => 'Subject',
				'type' => Model_CustomField::TYPE_SINGLE_LINE,
				'param' => SearchFields_CallEntry::SUBJECT,
				'required' => true,
			),
			'updated_date' => array(
				'label' => 'Updated Date',
				'type' => Model_CustomField::TYPE_DATE,
				'param' => SearchFields_CallEntry::UPDATED_DATE,
			),
		);
	
		$cfields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CALL);
	
		foreach($cfields as $cfield_id => $cfield) {
			$keys['cf_' . $cfield_id] = array(
				'label' => $cfield->name,
				'type' => $cfield->type,
				'param' => 'cf_' . $cfield_id,
			);
		}
	
		DevblocksPlatform::sortObjects($keys, '[label]', true);
	
		return $keys;
	}
	
	function importKeyValue($key, $value) {
		switch($key) {
		}
	
		return $value;
	}
	
	function importSaveObject(array $fields, array $custom_fields, array $meta) {
		// If new...
		if(!isset($meta['object_id']) || empty($meta['object_id'])) {
			// Make sure we have a name
			if(!isset($fields[DAO_CallEntry::SUBJECT])) {
				$fields[DAO_CallEntry::SUBJECT] = 'New ' . $this->manifest->name;
			}
	
			// Create
			$meta['object_id'] = DAO_CallEntry::create($fields);
	
		} else {
			// Update
			DAO_CallEntry::update($meta['object_id'], $fields);
		}
	
		// Custom fields
		if(!empty($custom_fields) && !empty($meta['object_id'])) {
			DAO_CustomFieldValue::formatAndSetFieldValues($this->manifest->id, $meta['object_id'], $custom_fields, false, true, true); //$is_blank_unset (4th)
		}
	}	
};