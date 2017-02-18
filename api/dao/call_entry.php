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

class DAO_CallEntry extends Cerb_ORMHelper {
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
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		/*
		 * Log the activity of a new call being created
		 */
		
		if(isset($fields[DAO_CallEntry::SUBJECT])) {
			$entry = array(
				//{{actor}} created call {{target}}
				'message' => 'activities.call_entry.created',
				'variables' => array(
					'target' => $fields[DAO_CallEntry::SUBJECT],
					),
				'urls' => array(
					'target' => sprintf("ctx://%s:%d", CerberusContexts::CONTEXT_CALL, $id),
					)
			);
			CerberusContexts::logActivity('call_entry.created', CerberusContexts::CONTEXT_CALL, $id, $entry, null, null);
		}
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_CALL, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'call_entry', $fields);
			
			// Send events
			if($check_deltas) {
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.call_entry.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_CALL, $batch_ids);
			}
		}
	}
	
	/**
	 * @param Model_ContextBulkUpdate $update
	 * @return boolean
	 */
	static function bulkUpdate(Model_ContextBulkUpdate $update) {
		$do = $update->actions;
		$ids = $update->context_ids;

		// Make sure we have actions
		if(empty($ids) || empty($do))
			return false;
		
		$update->markInProgress();
		
		$change_fields = array();
		$custom_fields = array();

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

		if(!empty($change_fields))
			DAO_CallEntry::update($ids, $change_fields);
		
		// Custom Fields
		if(!empty($custom_fields))
			C4_AbstractView::_doBulkSetCustomFields(CerberusContexts::CONTEXT_CALL, $custom_fields, $ids);
		
		// Scheduled behavior
		if(isset($do['behavior']))
			C4_AbstractView::_doBulkScheduleBehavior(CerberusContexts::CONTEXT_CALL, $do['behavior'], $ids);
		
		// Watchers
		if(isset($do['watchers']))
			C4_AbstractView::_doBulkChangeWatchers(CerberusContexts::CONTEXT_CALL, $do['watchers'], $ids);
		
		$update->markCompleted();
		return true;
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
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_CallEntry	 */
	static function get($id) {
		if(empty($id))
			return null;
		
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
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
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
		
		mysqli_free_result($rs);
		
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
		
		$db->ExecuteMaster(sprintf("DELETE FROM call_entry WHERE id IN (%s)", $ids_list));
		
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
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_CallEntry', $sortBy);
		
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
			"FROM call_entry c ";
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
		
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_CallEntry');
		
		// Translate virtual fields
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
		);
		
		array_walk_recursive(
			$params,
			array('DAO_CallEntry', '_translateVirtualParameters'),
			$args
		);
		
		$result = array(
			'primary_table' => 'c',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'sort' => $sort_sql,
		);
		
		return $result;
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		$from_context = 'cerberusweb.contexts.call';
		$from_index = 'c.id';
		
		switch($param_key) {
			case SearchFields_CallEntry::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
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
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			$sort_sql;
		
		if(false == ($rs = $db->SelectLimit($sql,$limit,$page*$limit)))
			return false;
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$id = intval($row[SearchFields_CallEntry::ID]);
			$results[$id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql = "SELECT count(*) " . $join_sql . $where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
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

class SearchFields_CallEntry extends DevblocksSearchFields {
	const ID = 'c_id';
	const SUBJECT = 'c_subject';
	const PHONE = 'c_phone';
	const CREATED_DATE = 'c_created_date';
	const UPDATED_DATE = 'c_updated_date';
	const IS_OUTGOING = 'c_is_outgoing';
	const IS_CLOSED = 'c_is_closed';
	
	// Comment Content
	const FULLTEXT_COMMENT_CONTENT = 'ftcc_content';

	// Virtuals
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_WATCHERS = '*_workers';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'c.id';
	}
	
	static function getCustomFieldContextKeys() {
		return array(
			CerberusContexts::CONTEXT_CALL => new DevblocksSearchFieldContextKeys('c.id', self::ID),
		);
	}
	
	static function getWhereSQL(DevblocksSearchCriteria $param) {
		switch($param->field) {
			case self::FULLTEXT_COMMENT_CONTENT:
				return self::_getWhereSQLFromCommentFulltextField($param, Search_CommentContent::ID, CerberusContexts::CONTEXT_CALL, self::getPrimaryKey());
				break;
				
			case self::VIRTUAL_CONTEXT_LINK:
				return self::_getWhereSQLFromContextLinksField($param, CerberusContexts::CONTEXT_CALL, self::getPrimaryKey());
				break;
				
			case self::VIRTUAL_WATCHERS:
				return self::_getWhereSQLFromWatchersField($param, CerberusContexts::CONTEXT_CALL, self::getPrimaryKey());
				break;
			
			default:
				if('cf_' == substr($param->field, 0, 3)) {
					return self::_getWhereSQLFromCustomFields($param);
				} else {
					return $param->getWhereSQL(self::getFields(), self::getPrimaryKey());
				}
				break;
		}
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		if(is_null(self::$_fields))
			self::$_fields = self::_getFields();
		
		return self::$_fields;
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function _getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'c', 'id', $translate->_('common.id'), Model_CustomField::TYPE_NUMBER, true),
			self::SUBJECT => new DevblocksSearchField(self::SUBJECT, 'c', 'subject', $translate->_('message.header.subject'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::PHONE => new DevblocksSearchField(self::PHONE, 'c', 'phone', $translate->_('call_entry.model.phone'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::CREATED_DATE => new DevblocksSearchField(self::CREATED_DATE, 'c', 'created_date', $translate->_('common.created'), Model_CustomField::TYPE_DATE, true),
			self::UPDATED_DATE => new DevblocksSearchField(self::UPDATED_DATE, 'c', 'updated_date', $translate->_('common.updated'), Model_CustomField::TYPE_DATE, true),
			self::IS_OUTGOING => new DevblocksSearchField(self::IS_OUTGOING, 'c', 'is_outgoing', $translate->_('call_entry.model.is_outgoing'), Model_CustomField::TYPE_CHECKBOX, true),
			self::IS_CLOSED => new DevblocksSearchField(self::IS_CLOSED, 'c', 'is_closed', $translate->_('common.is_closed'), Model_CustomField::TYPE_CHECKBOX, true),
			
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null, false),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null, false),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS', false),
				
			self::FULLTEXT_COMMENT_CONTENT => new DevblocksSearchField(self::FULLTEXT_COMMENT_CONTENT, 'ftcc', 'content', $translate->_('comment.filters.content'), 'FT', false),
		);
		
		// Fulltext indexes
		
		$columns[self::FULLTEXT_COMMENT_CONTENT]->ft_schema = Search_CommentContent::ID;
		
		// Custom fields with fieldsets
		
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array_keys(self::getCustomFieldContextKeys()));
		
		if(is_array($custom_columns))
			$columns = array_merge($columns, $custom_columns);
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class View_CallEntry extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
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
			SearchFields_CallEntry::FULLTEXT_COMMENT_CONTENT,
			SearchFields_CallEntry::VIRTUAL_CONTEXT_LINK,
			SearchFields_CallEntry::VIRTUAL_HAS_FIELDSET,
			SearchFields_CallEntry::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
			SearchFields_CallEntry::ID,
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
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_CallEntry');
		
		return $objects;
	}

	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_CallEntry', $ids);
	}
	
	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
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
				case SearchFields_CallEntry::VIRTUAL_CONTEXT_LINK:
				case SearchFields_CallEntry::VIRTUAL_HAS_FIELDSET:
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
		$context = CerberusContexts::CONTEXT_CALL;

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_CallEntry::IS_CLOSED:
			case SearchFields_CallEntry::IS_OUTGOING:
				$counts = $this->_getSubtotalCountForBooleanColumn($context, $column);
				break;

			case SearchFields_CallEntry::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn($context, $column);
				break;
				
			case SearchFields_CallEntry::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn($context, $column);
				break;
				
			case SearchFields_CallEntry::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn($context, $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn($context, $column);
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_CallEntry::getFields();
		
		$fields = array(
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_CallEntry::SUBJECT, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'comments' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_FULLTEXT,
					'options' => array('param_key' => SearchFields_CallEntry::FULLTEXT_COMMENT_CONTENT),
				),
			'created' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_CallEntry::CREATED_DATE),
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_CallEntry::ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_CALL, 'q' => ''],
					]
				),
			'isClosed' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_BOOL,
					'options' => array('param_key' => SearchFields_CallEntry::IS_CLOSED),
				),
			'isOutgoing' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_BOOL,
					'options' => array('param_key' => SearchFields_CallEntry::IS_OUTGOING),
				),
			'phone' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_CallEntry::PHONE, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'subject' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_CallEntry::SUBJECT, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_CallEntry::UPDATED_DATE),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_CallEntry::VIRTUAL_WATCHERS),
				),
		);
		
		// Add quick search links
		
		$fields = self::_appendVirtualFiltersFromQuickSearchContexts('links', $fields, 'links');
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_CALL, $fields, null);
		
		// Engine/schema examples: Comments
		
		$ft_examples = array();
		
		if(false != ($schema = Extension_DevblocksSearchSchema::get(Search_CommentContent::ID))) {
			if(false != ($engine = $schema->getEngine())) {
				$ft_examples = $engine->getQuickSearchExamples($schema);
			}
		}
		
		if(!empty($ft_examples))
			$fields['comments']['examples'] = $ft_examples;
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}
	
	function getParamFromQuickSearchFieldTokens($field, $tokens) {
		switch($field) {
			default:
				if($field == 'links' || substr($field, 0, 6) == 'links.')
					return DevblocksSearchCriteria::getContextLinksParamFromTokens($field, $tokens);
				
				$search_fields = $this->getQuickSearchFields();
				return DevblocksSearchCriteria::getParamFromQueryFieldTokens($field, $tokens, $search_fields);
				break;
		}
		
		return false;
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
			case SearchFields_CallEntry::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_CallEntry::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;

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
			case SearchFields_CallEntry::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
			case SearchFields_CallEntry::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_CALL);
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
				
			case SearchFields_CallEntry::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_CallEntry::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
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
};

class Context_CallEntry extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek, IDevblocksContextImport {
	static function isReadableByActor($models, $actor) {
		// Everyone can view
		return CerberusContexts::allowEverything($models);
	}
	
	static function isWriteableByActor($models, $actor) {
		// Everyone can modify
		return CerberusContexts::allowEverything($models);
	}
	
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
			'updated' => $call->updated_date,
		);
	}
	
	function getPropertyLabels(DevblocksDictionaryDelegate $dict) {
		$labels = $dict->_labels;
		$prefix = $labels['_label'];
		
		if(!empty($prefix)) {
			array_walk($labels, function(&$label, $key) use ($prefix) {
				$label = preg_replace(sprintf("#^%s #", preg_quote($prefix)), '', $label);
				
				// [TODO] Use translations
				switch($key) {
				}
				
				$label = mb_convert_case($label, MB_CASE_LOWER);
				$label[0] = mb_convert_case($label[0], MB_CASE_UPPER);
			});
		}
		
		asort($labels);
		
		return $labels;
	}
	
	function getDefaultProperties() {
		return array(
			'phone',
			'is_outgoing',
			'is_closed',
			'created',
			'updated',
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
		} elseif(is_array($call)) {
			$call = Cerb_ORMHelper::recastArrayToModel($call, 'Model_CallEntry');
		} else {
			$call = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'created' => $prefix.$translate->_('common.created'),
			'is_closed' => $prefix.$translate->_('common.is_closed'),
			'is_outgoing' => $prefix.$translate->_('call_entry.model.is_outgoing'),
			'phone' => $prefix.$translate->_('call_entry.model.phone'),
			'subject' => $prefix.$translate->_('message.header.subject'),
			'updated' => $prefix.$translate->_('common.updated'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'created' => Model_CustomField::TYPE_DATE,
			'is_closed' => Model_CustomField::TYPE_CHECKBOX,
			'is_outgoing' => Model_CustomField::TYPE_CHECKBOX,
			'phone' => Model_CustomField::TYPE_SINGLE_LINE,
			'subject' => Model_CustomField::TYPE_SINGLE_LINE,
			'updated' => Model_CustomField::TYPE_DATE,
			'record_url' => Model_CustomField::TYPE_URL,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_CALL;
		$token_values['_types'] = $token_types;
		
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
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($call, $token_values);
			
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
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, true);
		}
		
		switch($token) {
			case 'links':
				$links = $this->_lazyLoadLinks($context, $context_id);
				$values = array_merge($values, $links);
				break;
			
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(DevblocksPlatform::strStartsWith($token, 'custom_')) {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
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
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;

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
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Calls';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_CallEntry::VIRTUAL_CONTEXT_LINK,'in',array($context.':'.$context_id)),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		$context = CerberusContexts::CONTEXT_CALL;
		
		if(!empty($context_id)) {
			$model = DAO_CallEntry::get($context_id);
		}
		
		if(empty($context_id) || $edit) {
			if(isset($model))
				$tpl->assign('model', $model);
			
			// Custom fields
			$custom_fields = DAO_CustomField::getByContext($context, false);
			$tpl->assign('custom_fields', $custom_fields);
	
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds($context, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
			
			$types = Model_CustomField::getTypes();
			$tpl->assign('types', $types);
			
			// View
			$tpl->assign('id', $context_id);
			$tpl->assign('view_id', $view_id);
			$tpl->display('devblocks:cerberusweb.calls::calls/ajax/peek_edit.tpl');
			
		} else {
			// Counts
			$activity_counts = array(
				'comments' => DAO_Comment::count($context, $context_id),
			);
			$tpl->assign('activity_counts', $activity_counts);
			
			// Links
			$links = array(
				$context => array(
					$context_id => 
						DAO_ContextLink::getContextLinkCounts(
							$context,
							$context_id,
							array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
						),
				),
			);
			$tpl->assign('links', $links);
			
			// Timeline
			if($context_id) {
				$timeline_json = Page_Profiles::getTimelineJson(Extension_DevblocksContext::getTimelineComments($context, $context_id));
				$tpl->assign('timeline_json', $timeline_json);
			}

			// Context
			if(false == ($context_ext = Extension_DevblocksContext::get($context)))
				return;
			
			// Dictionary
			$labels = array();
			$values = array();
			CerberusContexts::getContext($context, $model, $labels, $values, '', true, false);
			$dict = DevblocksDictionaryDelegate::instance($values);
			$tpl->assign('dict', $dict);
			
			$properties = $context_ext->getCardProperties();
			$tpl->assign('properties', $properties);
			
			$tpl->display('devblocks:cerberusweb.calls::calls/ajax/peek.tpl');
		}
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
		
		$fields = SearchFields_CallEntry::getFields();
		self::_getImportCustomFields($fields, $keys);
	
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