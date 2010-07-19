<?php
	
	require_once(CONTENT . '/content.blueprintspages.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	
	class contentExtensionStatic_content_managerPages extends contentBlueprintsPages {
		protected $_driver = null;
		protected $_uri = null;
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$this->_uri = URL . '/symphony/extension/static_content_manager';
			$this->_driver = $this->_Parent->ExtensionManager->create('static_content_manager');
		}
		
		private static function __countChildren($id){
			$children = Symphony::Database()->fetchCol('id', "
				SELECT
					p.id
				FROM
					`tbl_pages` AS p
				LEFT JOIN
					`tbl_pages_types` as t ON p.id = t.page_id
				WHERE
					p.parent = {$id}
				AND
					t.type = 'static'
			");
			$count = count($children);
			
			if(count($children) > 0){
				foreach($children as $c){
					$count += self::__countChildren($c);
				}
			}
			
			return $count;
		}
		
		private static function __buildParentBreadcrumb($id, $last=true){
			$page = Symphony::Database()->fetchRow(0, "
				SELECT 
					p.title, p.id, p.parent
				FROM
					`tbl_pages` AS p
				LEFT JOIN
					`tbl_pages_types` as t ON p.id = t.page_id
				WHERE
					p.id = {$id}
				AND
					t.type = 'static'
			");
			
			if(!is_array($page) || empty($page)) return NULL;
			
			if($last != true){			
				$anchor = Widget::Anchor(
					$page['title'], Administration::instance()->getCurrentPageURL() . '?parent=' . $page['id']
				);
			}
			
			$result = (!is_null($page['parent']) ? self::__buildParentBreadcrumb($page['parent'], false) . ' &gt; ' : NULL) . ($anchor instanceof XMLElement ? $anchor->generate() : $page['title']);
			
			return $result;
			
		}
		
		// copy from content.publish.php
		private function __wrapFieldWithDiv(Field $field, Entry $entry, $fieldnamePrefix = NULL){
			$div = new XMLElement('div', NULL, array('class' => 'field field-'.$field->handle().($field->get('required') == 'yes' ? ' required' : '')));
			$field->displayPublishPanel(
				$div, $entry->getData($field->get('id')),
				(isset($this->_errors[$field->get('id')]) ? $this->_errors[$field->get('id')] : NULL),
				$fieldnamePrefix,null, (is_numeric($entry->get('id')) ? $entry->get('id') : NULL)
			);
			return $div;
		}

		function __switchboard($type='view'){
			
			if(!isset($this->_context[0]) || trim($this->_context[0]) == '') $context = 'index';
			else $context = $this->_context[0];
			
			$function = ($type == 'action' ? '__action' : '__view') . ucfirst($context);
			
			if(!method_exists($this, $function)) {
				
				## If there is no action function, just return without doing anything
				if($type == 'action') return;
				
				$this->_Parent->errorPageNotFound();
				
			}
			
			$this->$function();

		}

		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Pages'))));
			
			$nesting = (Symphony::Configuration()->get('pages_table_nest_children', 'symphony') == 'yes');
			
			$heading = NULL;
			if($nesting == true && isset($_GET['parent']) && is_numeric($_GET['parent'])){
				$parent = (int)$_GET['parent'];
				$heading = ' &mdash; ' . self::__buildParentBreadcrumb($parent);
			}
			
			$this->appendSubheading(__('Pages') . $heading, Widget::Anchor(
				__('Create New'), Administration::instance()->getCurrentPageURL() . 'new/' . ($nesting == true && isset($parent) ? "?parent={$parent}" : NULL),
				__('Create a new page'), 'create button'
			));
			
			$pages = $this->_Parent->Database->fetch("
				SELECT
					p.*
				FROM
					`tbl_pages` AS p
				LEFT JOIN
					`tbl_pages_types` as t ON p.id = t.page_id
				WHERE
					t.type = 'static'
				ORDER BY
					p.sortorder ASC
			");
			
			$aTableHead = array(
				array(__('Title'), 'col'),
				array(__('<acronym title="Universal Resource Locator">URL</acronym>'), 'col'),
				array(__('Entries'), 'col')
			);	
			
			$sql = "
				SELECT
					p.*
				FROM
					`tbl_pages` AS p
				LEFT JOIN
					`tbl_pages_types` as t ON p.id = t.page_id
				WHERE
					t.type = 'static'
				%s
				ORDER BY
					p.sortorder ASC
			";
		
			if($nesting == true){
				$aTableHead[] = array(__('Children'), 'col');
				$sql = sprintf($sql, ' AND p.parent ' . (isset($parent) ? " = {$parent} " : ' IS NULL '));
			}
		
			else{
				$sql = sprintf($sql, NULL);
			}
		
			$pages = Symphony::Database()->fetch($sql);
		
			$aTableBody = array();
			
			if(!is_array($pages) or empty($pages)) {
				$aTableBody = array(Widget::TableRow(array(
					Widget::TableData(__('None found.'), 'inactive', null, count($aTableHead))
				), 'odd'));
				
			}
			else {
				$bOdd = true;
				
				foreach ($pages as $page) {
					$class = array();
					$page_title = ($nesting == true ? $page['title'] : $this->_Parent->resolvePageTitle($page['id']));
					$page_url = URL . '/' . $this->_Parent->resolvePagePath($page['id']) . '/';
					$page_edit_url = Administration::instance()->getCurrentPageURL() . 'edit/' . $page['id'] . '/';
					$entries_content_url = Administration::instance()->getCurrentPageURL() . 'content/' . $page['id'] . '/';
					$entries_count = $this->_driver->countEntries($page['id']);
					
					$col_title = Widget::TableData(Widget::Anchor(
						$page_title, $page_edit_url, $page['handle']
					));
					$col_title->appendChild(Widget::Input("items[{$page['id']}]", null, 'checkbox'));
					
					$col_url = Widget::TableData(Widget::Anchor($page_url, $page_url));
					
					$col_entries = Widget::TableData(Widget::Anchor(
						$entries_count . ' &rarr;', $entries_content_url
					));
					
					if($bOdd) $class[] = 'odd';
					if(in_array($page['id'], $this->_hilights)) $class[] = 'failed';
					
					$columns = array($col_title, $col_url, $col_entries);
					
					if($nesting == true){
						if($this->__hasChildren($page['id'])){
							$col_children = Widget::TableData(
								Widget::Anchor(self::__countChildren($page['id']) . ' &rarr;', 
								$this->_uri . '/pages/?parent=' . $page['id'])
							);
						}
						else{
							$col_children = Widget::TableData(__('None'), 'inactive');
						}
						
						$columns[] = $col_children;
					}
					
					$aTableBody[] = Widget::TableRow(
						$columns,
						implode(' ', $class)
					);

					$bOdd = !$bOdd;
				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($aTableHead), null, 
				Widget::TableBody($aTableBody), 'orderable'
			);
			
			$this->Form->appendChild($table);
			
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, __('With Selected...')),
				array('delete', false, __('Delete'))
			);
			
			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			
			$this->Form->appendChild($tableActions);
		}
		
		public function __viewEdit() {
			$this->setPageType('form');
			$fields = array();
			
			$nesting = (Symphony::Configuration()->get('pages_table_nest_children', 'symphony') == 'yes');
			
			// Verify page exists:
			if($this->_context[0] == 'edit') {
				if(!$page_id = $this->_context[1]) redirect($this->_uri . '/pages/');
				
				$existing = Symphony::Database()->fetchRow(0, "
					SELECT
						p.*
					FROM
						`tbl_pages` AS p
					LEFT JOIN
						`tbl_pages_types` as t ON p.id = t.page_id
					WHERE
						p.id = '{$page_id}'
					AND
						t.type = 'static'
					LIMIT 1
				");
				
				if(!$existing) {
					$this->_Parent->customError(
						E_USER_ERROR, __('Page not found'),
						__('The page you requested to edit does not exist.'),
						false, true, 'error', array(
							'header'	=> 'HTTP/1.0 404 Not Found'
						)
					);
				}
			}
			
			// Status message:
			$flag = $this->_context[2];
			if(isset($flag)){
				
				if(isset($_REQUEST['parent']) && is_numeric($_REQUEST['parent'])){
					$link_suffix = "?parent=" . $_REQUEST['parent'];
				}
				
				elseif($nesting == true && isset($existing) && !is_null($existing['parent'])){
					$link_suffix = '?parent=' . $existing['parent'];
				}
				
				switch($flag){
					
					case 'saved':
						
						$this->pageAlert(
							__(
								'Page updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Pages</a>', 
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__), 
									$this->_uri . '/pages/new/' . $link_suffix,
									$this->_uri . '/pages/' . $link_suffix,
								)
							), 
							Alert::SUCCESS);
													
						break;
						
					case 'created':

						$this->pageAlert(
							__(
								'Page created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Pages</a>', 
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__), 
									$this->_uri . '/pages/new/' . $link_suffix,
									$this->_uri . '/pages/' . $link_suffix,
								)
							), 
							Alert::SUCCESS);
							
						break;
					
				}
			}
			
			// Find values:
			if(isset($_POST['fields'])) {
				$fields = $_POST['fields'];
			}
			
			elseif ($this->_context[0] == 'edit') {
				$fields = $existing;
			}
			
			elseif(isset($_REQUEST['parent']) && is_numeric($_REQUEST['parent'])){
				$fields['parent'] = $_REQUEST['parent'];
			}
			
			$title = $fields['title'];
			if(trim($title) == '') $title = $existing['title'];
			
			$this->setTitle(__(
				($title ? '%1$s &ndash; %2$s &ndash; %3$s' : '%1$s &ndash; %2$s'),
				array(
					__('Symphony'),
					__('Pages'),
					$title
				)
			));
			$this->appendSubheading(($title ? $title : __('Untitled')));
			
		// Title --------------------------------------------------------------
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Page Settings')));
			
			$label = Widget::Label(__('Title'));		
			$label->appendChild(Widget::Input(
				'fields[title]', General::sanitize($fields['title'])
			));
			
			if (isset($this->_errors['title'])) {
				$label = $this->wrapFormElementWithError($label, $this->_errors['title']);
			}
			
			$fieldset->appendChild($label);
			
		// Handle -------------------------------------------------------------
			
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			$column = new XMLElement('div');
			
			$label = Widget::Label(__('URL Handle'));
			$label->appendChild(Widget::Input(
				'fields[handle]', $fields['handle']
			));
			
			if(isset($this->_errors['handle'])) {
				$label = $this->wrapFormElementWithError($label, $this->_errors['handle']);
			}
			
			$column->appendChild($label);
			$group->appendChild($column);
			
		// Parent ---------------------------------------------------------

			$column = new XMLElement('div');
		
			$label = Widget::Label(__('Parent Page'));

			$pages = Symphony::Database()->fetch("
				SELECT
					p.*
				FROM
					`tbl_pages` AS p
				LEFT JOIN
					`tbl_pages_types` as t ON p.id = t.page_id
				WHERE
					p.id != '{$page_id}'
				AND
					t.type = 'static'
				ORDER BY
					p.title ASC
			");

			$options = array(
				array('', false, '/')
			);

			if(is_array($pages) && !empty($pages)) {
				if(!function_exists('__compare_pages')) {
					function __compare_pages($a, $b) {
						return strnatcasecmp($a[2], $b[2]);
					}
				}

				foreach ($pages as $page) {
					$options[] = array(
						$page['id'], $fields['parent'] == $page['id'],
						'/' . $this->_Parent->resolvePagePath($page['id'])
					);
				}

				usort($options, '__compare_pages');
			}

			$label->appendChild(Widget::Select(
				'fields[parent]', $options
			));
			
			$column->appendChild($label);
			$group->appendChild($column);
			$fieldset->appendChild($group);
			$this->Form->appendChild($fieldset);
			
		// Meta Sections ------------------------------------------------------
		
		// find sections from navigation group
		$sectionManager = new SectionManager($this->_Parent);
		$sections = $sectionManager->fetch(NULL, 'ASC', 'sortorder');

		// TODO: configuration for navigation group(s)
		if(is_array($sections) && !empty($sections)){
			foreach($sections as $s){
				if ($s->get('navigation_group') == 'Meta') {
					$meta_sections[] = $s->get('id');
				};
			}
		}

		// iterate over each section and display fields
		foreach ($meta_sections as $section_id) {
			$sectionManager = new SectionManager($this->_Parent);
			$section = $sectionManager->fetch($section_id);
			
			$pages_fields = $section->fetchFields('pages');
			$pages_field_id = $pages_fields[0]->get('id');

			$entry_id = Symphony::Database()->fetch("
				SELECT
					e.id
				FROM
					`sym_entries` AS e
				LEFT JOIN
					`sym_entries_data_{$pages_field_id}` AS p ON p.`entry_id` = e.id
				WHERE
					e.section_id = '{$section_id}'
					AND p.page_id = '{$page_id}'
				ORDER BY
					e.id ASC
			");
			$entry_id = $entry_id[0]['id'];
			
			$entryManager = new EntryManager($this->_Parent);
			$entryManager->setFetchSorting('id', 'DESC');

			$existingEntry = $entryManager->fetch($entry_id);
			$existingEntry = $existingEntry[0];

			// If there is post data floating around, due to errors, create an entry object
			if (isset($_POST['fields'][$section->get('handle')])) {
				$fields = $_POST['fields'][$section->get('handle')];

				$entry =& $entryManager->create();
				$entry->set('section_id', $existingEntry->get('section_id'));
				$entry->set('id', $entry_id);

				$entry->setDataFromPost($fields, $error, true);
			}
			// Editing an entry, so need to create some various objects
			else if ($existingEntry) {
				$entry = $existingEntry;

				if (!$section) {
					$section = $sectionManager->fetch($entry->get('section_id'));
				}
			}
			// Brand new entry, so need to create some various objects
			else {
				$entry = $entryManager->create();
				$entry->set('section_id', $section_id);
			}
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __($section->get('name'))));
			
			$primary = new XMLElement('fieldset');
			$primary->setAttribute('class', 'primary');

			$sidebar_fields = $section->fetchFields(NULL, 'sidebar');
			$main_fields = $section->fetchFields(NULL, 'main');

			// remove pages type fields
			if(is_array($main_fields) && !empty($main_fields)){
				foreach($main_fields as $id => $field){
					if($field->get('type') == 'pages') {
						unset($main_fields[$id]);
					};
				}
				// re-key the array
				$main_fields = array_values($main_fields);
			}
			if(is_array($sidebar_fields) && !empty($sidebar_fields)){
				foreach($sidebar_fields as $id => $field){
					if($field->get('type') == 'pages') {
						unset($sidebar_fields[$id]);
					};
				}
				// re-key the array
				$sidebar_fields = array_values($sidebar_fields);
			}

			if((!is_array($main_fields) || empty($main_fields)) && (!is_array($sidebar_fields) || empty($sidebar_fields))){
				$primary->appendChild(new XMLElement('p', __('It looks like your trying to create an entry. Perhaps you want fields first? <a href="%s">Click here to create some.</a>', array(URL . '/symphony/blueprints/sections/edit/'. $section->get('id') . '/'))));
			}

			else{

				if(is_array($main_fields) && !empty($main_fields)){
					foreach($main_fields as $field){
						$primary->appendChild($this->__wrapFieldWithDiv($field, $entry, "_{$section->get('handle')}"));
					}

					$fieldset->appendChild($primary);
				}

				if(is_array($sidebar_fields) && !empty($sidebar_fields)){
					$sidebar = new XMLElement('fieldset');
					$sidebar->setAttribute('class', 'secondary');

					foreach($sidebar_fields as $field){
						$sidebar->appendChild($this->__wrapFieldWithDiv($field, $entry, "_{$section->get('handle')}"));
					}

					$fieldset->appendChild($sidebar);

					$fieldset->appendChild(Widget::Input(
						"fields_{$section->get('handle')}['id']",
						$entry->get('id'),
						'hidden')
					);

				}

			}

			$this->Form->appendChild($fieldset);
		}
		
		
		// Controls -----------------------------------------------------------
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input(
				'action[save]', ($this->_context[0] == 'edit' ? __('Save Changes') : __('Create Page')),
				'submit', array('accesskey' => 's')
			));
			
			if($this->_context[0] == 'edit'){
				$button = new XMLElement('button', __('Delete'));
				$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'confirm delete', 'title' => __('Delete this page')));
				$div->appendChild($button);
			}
			
			$this->Form->appendChild($div);
			
			if(isset($_REQUEST['parent']) && is_numeric($_REQUEST['parent'])){
				$this->Form->appendChild(new XMLElement('input', NULL, array('type' => 'hidden', 'name' => 'parent', 'value' => $_REQUEST['parent'])));
			}
		}
		
		public function __actionEdit() {
			if($this->_context[0] != 'new' && !$page_id = (integer)$this->_context[1]) {
				redirect($this->_uri . '/pages/');
			}
			
			$parent_link_suffix = NULL;
			if(isset($_REQUEST['parent']) && is_numeric($_REQUEST['parent'])){
				$parent_link_suffix = '?parent=' . $_REQUEST['parent'];
			}
			
			if(@array_key_exists('delete', $_POST['action'])) {
				$this->__actionDelete($page_id, $this->_uri . '/pages/' . $parent_link_suffix);
			}
			
			if(@array_key_exists('save', $_POST['action'])) {
				// page data
				$fields = $_POST['fields'];
				$this->_errors = array();
				
				$current = Symphony::Database()->fetchRow(0, "
					SELECT
						p.*
					FROM
						`tbl_pages` AS p
					LEFT JOIN
						`tbl_pages_types` as t ON p.id = t.page_id
					WHERE
						p.id = '{$page_id}'
					AND
						t.type = 'static'
					LIMIT 1
				");
				
				if(!isset($fields['title']) || trim($fields['title']) == '') {
					$this->_errors['title'] = __('Title is a required field');
				}
				
				if(empty($this->_errors)) {
					$autogenerated_handle = false;
					
					if(empty($current)) {
						$fields['sortorder'] = Symphony::Database()->fetchVar('next', 0, "
							SELECT
								MAX(p.sortorder) + 1 AS `next`
							FROM
								`tbl_pages` AS p
							LIMIT 1
						");
						
						if (empty($fields['sortorder']) || !is_numeric($fields['sortorder'])) {
							$fields['sortorder'] = 1;
						}
					}
					
					if (trim($fields['handle'] ) == '') { 
						$fields['handle'] = $fields['title'];
						$autogenerated_handle = true;
					}
					
					$fields['handle'] = Lang::createHandle($fields['handle']);		

					if ($fields['params']) {
						$fields['params'] = trim(preg_replace('@\/{2,}@', '/', $fields['params']), '/');
					}
					
					// Set default values TODO: configurable
					$fields['type'] = 'static';
					$fields['data_sources'] = 'static';
					
					// Clean up type list
					$types = preg_split('/\s*,\s*/', $fields['type'], -1, PREG_SPLIT_NO_EMPTY);
					$types = @array_map('trim', $types);
					unset($fields['type']);
					
					$fields['parent'] = ($fields['parent'] != __('None') ? $fields['parent'] : null);
					$fields['data_sources'] = is_array($fields['data_sources']) ? implode(',', $fields['data_sources']) : NULL;
					$fields['events'] = is_array($fields['events']) ? implode(',', $fields['events']) : NULL;
					$fields['path'] = null;
					
					if($fields['parent']) {
						$fields['path'] = $this->_Parent->resolvePagePath((integer)$fields['parent']);
					}
					
					// Check for duplicates:
					$duplicate = Symphony::Database()->fetchRow(0, "
						SELECT
							p.*
						FROM
							`tbl_pages` AS p
						WHERE
							p.id != '{$page_id}'
							AND p.handle = '" . $fields['handle'] . "'
							AND p.path " . ($fields['path'] ? " = '" . $fields['path'] . "'" : ' IS NULL') .  " 
						LIMIT 1
					");
					
					// Create or move files:
					if(empty($current)) {
						$file_created = $this->__updatePageFiles(
							$fields['path'], $fields['handle']
						);
						
					}
					else {
						$file_created = $this->__updatePageFiles(
							$fields['path'], $fields['handle'],
							$current['path'], $current['handle']
						);
					}
					
					if(!$file_created) {
						$redirect = null;
						$this->pageAlert(
							__('Page could not be written to disk. Please check permissions on <code>/workspace/pages</code>.'),
							Alert::ERROR
						);
					}
					
					if($duplicate) {
						if($autogenerated_handle) {
							$this->_errors['title'] = __('A page with that title already exists');
							
						} else {
							$this->_errors['handle'] = __('A page with that handle already exists'); 
						}
						
					// Insert the new data:
					} elseif (empty($current)) {
						if(!Symphony::Database()->insert($fields, 'tbl_pages')) {
							$this->pageAlert(
								__(
									'Unknown errors occurred while attempting to save. Please check your <a href="%s">activity log</a>.',
									array(
										URL . '/symphony/system/log/'
									)
								),
								Alert::ERROR
							);
							
						} else {
							$page_id = Symphony::Database()->getInsertID();
							$redirect = "/symphony/extension/static_content_manager/pages/edit/{$page_id}/created/{$parent_link_suffix}";
						}
						
					// Update existing:
					} else {
						if(!Symphony::Database()->update($fields, 'tbl_pages', "`id` = '$page_id'")) {
							$this->pageAlert(
								__(
									'Unknown errors occurred while attempting to save. Please check your <a href="%s">activity log</a>.',
									array(
										URL . '/symphony/system/log/'
									)
								),
								Alert::ERROR
							);
							
						} else {
							Symphony::Database()->delete('tbl_pages_types', " `page_id` = '$page_id'");
							$redirect = "/symphony/extension/static_content_manager/pages/edit/{$page_id}/saved/{$parent_link_suffix}";
						}
					}
					
					// Assign page types:
					if(is_array($types) && !empty($types)) {
						foreach ($types as $type) Symphony::Database()->insert(
							array(
								'page_id' => $page_id,
								'type' => $type
							),
							'tbl_pages_types'
						);
					}
					
					// Find and update children:
					if($this->_context[0] == 'edit') {
						$this->__updatePageChildren($page_id, $fields['path'] . '/' . $fields['handle']);
					}
					
					if($redirect) redirect(URL . $redirect);
				}
				
				if(is_array($this->_errors) && !empty($this->_errors)) {
					$this->pageAlert(
						__('An error occurred while processing this form. <a href="#error">See below for details.</a>'),
						Alert::ERROR
					);
				}

				// meta entries
				// TODO: integrate into page code
				// find sections from navigation group
				$sectionManager = new SectionManager($this->_Parent);
				$sections = $sectionManager->fetch(NULL, 'ASC', 'sortorder');

				// TODO: configuration for navigation group(s)
				if(is_array($sections) && !empty($sections)){
					foreach($sections as $s){
						if ($s->get('navigation_group') == 'Meta') {
							$meta_sections[] = $s->get('id');
						};
					}
				}

				// iterate over each section
				foreach ($meta_sections as $section_id) {
					$sectionManager = new SectionManager($this->_Parent);
					$section = $sectionManager->fetch($section_id);

					$post = General::getPostData();
					$fields = $post['fields_'.$section->get('handle')];
					$entry_id = intval($this->_context['entry_id']);
					
					var_dump($fields);
					exit;

					$entryManager = new EntryManager($this->_Parent);

					if(!$ret = $entryManager->fetch($entry_id)) $this->_Parent->customError(E_USER_ERROR, __('Unknown Entry'), __('The entry you are looking for could not be found.'), false, true);

					$entry = $ret[0];

					if(__ENTRY_FIELD_ERROR__ == $entry->checkPostData($fields, $this->_errors)):
						$this->pageAlert(__('Some errors were encountered while attempting to save.'), Alert::ERROR);

					elseif(__ENTRY_OK__ != $entry->setDataFromPost($fields, $error)):
						$this->pageAlert($error['message'], Alert::ERROR);

					else:


						###
						# Delegate: EntryPreEdit
						# Description: Just prior to editing of an Entry.
						$this->_Parent->ExtensionManager->notifyMembers('EntryPreEdit', '/publish/edit/', array('section' => $section, 'entry' => &$entry, 'fields' => $fields));

						if(!$entry->commit()){
							define_safe('__SYM_DB_INSERT_FAILED__', true);
							$this->pageAlert(NULL, Alert::ERROR);

						}

						else{

							###
							# Delegate: EntryPostEdit
							# Description: Editing an entry. Entry object is provided.
							$this->_Parent->ExtensionManager->notifyMembers('EntryPostEdit', '/publish/edit/', array('section' => $section, 'entry' => $entry, 'fields' => $fields));


							$prepopulate_field_id = $prepopulate_value = NULL;
							if(isset($_POST['prepopulate'])){
								$prepopulate_field_id = array_shift(array_keys($_POST['prepopulate']));
								$prepopulate_value = stripslashes(rawurldecode(array_shift($_POST['prepopulate'])));
							}

							//redirect(URL . '/symphony/publish/' . $this->_context['section_handle'] . '/edit/' . $entry_id . '/saved/');

							redirect(sprintf(
								'%s/symphony/publish/%s/edit/%d/saved%s/',
								URL,
								$this->_context['section_handle'],
								$entry->get('id'),
								(!is_null($prepopulate_field_id) ? ":{$prepopulate_field_id}:{$prepopulate_value}" : NULL)
							));

						}

					endif;
				}
				
			}

		}
		
		public function __viewContent() {
			$this->setPageType('table');

			// Verify page exists:
			if(!$page_id = $this->_context[1]) redirect($this->_uri . '/pages/');
			
			//TODO: configuration for page type
			$existing = Symphony::Database()->fetchRow(0, "
				SELECT
					p.*
				FROM
					`tbl_pages` AS p
				LEFT JOIN
					`tbl_pages_types` as t ON p.id = t.page_id
				WHERE
					p.id = '{$page_id}'
				AND
					t.type = 'static'
				LIMIT 1
			");
			
			if(!$existing) {
				$this->_Parent->customError(
					E_USER_ERROR, __('Page not found'),
					__('The page you requested to edit does not exist.'),
					false, true, 'error', array(
						'header'	=> 'HTTP/1.0 404 Not Found'
					)
				);
			}
			
			$page = $existing;
			
			$title = $page['title'];
			
			$this->setTitle(__('%1$s &ndash; %2$s &ndash; %3$s &ndash; %4$s', array(__('Symphony'), __('Pages'), $title, __('Content'))));
			$this->appendSubheading($title);

			// find sections from navigation group
			// TODO: configuration for navigation group(s)
			$sectionManager = new SectionManager($this->_Parent);
			$sections = $sectionManager->fetch(NULL, 'ASC', 'sortorder');

			// TODO: configuration for navigation group(s)
			if(is_array($sections) && !empty($sections)){
				foreach($sections as $s){
					if ($s->get('navigation_group') == 'Static Content') {
						$static_sections[] = $s->get('id');
					};
				}
			}
			
			// iterate over each section and display entries table
			foreach ($static_sections as $section_id) {
				$sectionManager = new SectionManager($this->_Parent);
				$section = $sectionManager->fetch($section_id);
				
				$entryManager = new EntryManager($this->_Parent);

				$filter = $filter_value = $where = $joins = NULL;
				$current_page = (isset($_REQUEST['pg']) && is_numeric($_REQUEST['pg']) ? max(1, intval($_REQUEST['pg'])) : 1);

				if(isset($_REQUEST['filter'])){

					list($field_handle, $filter_value) = explode(':', $_REQUEST['filter'], 2);

					$field_names = explode(',', $field_handle);

					foreach($field_names as $field_name) {

						$filter_value = rawurldecode($filter_value);

						$filter = Symphony::Database()->fetchVar('id', 0, "SELECT `f`.`id`
											  FROM `tbl_fields` AS `f`, `tbl_sections` AS `s`
											  WHERE `s`.`id` = `f`.`parent_section`
											  AND f.`element_name` = '$field_name'
											  AND `s`.`handle` = '".$section->get('handle')."' LIMIT 1");
						$field =& $entryManager->fieldManager->fetch($filter);

						if(is_object($field)){
							$field->buildDSRetrivalSQL(array($filter_value), $joins, $where, false);
							$filter_value = rawurlencode($filter_value);
						}

					}

					if ($where != null) {
						$where = str_replace('AND', 'OR', $where); // multiple fields need to be OR
						$where = trim($where);
						$where = ' AND (' . substr($where, 2, strlen($where)) . ')'; // replace leading OR with AND
					}

				}

				if(isset($_REQUEST['sort']) && is_numeric($_REQUEST['sort'])){
					$sort = intval($_REQUEST['sort']);
					$order = ($_REQUEST['order'] ? strtolower($_REQUEST['order']) : 'asc');

					if($section->get('entry_order') != $sort || $section->get('entry_order_direction') != $order){
						$sectionManager->edit($section->get('id'), array('entry_order' => $sort, 'entry_order_direction' => $order));
						redirect($this->_Parent->getCurrentPageURL().($filter ? "?filter=$field_handle:$filter_value" : ''));
					}
				}

				elseif(isset($_REQUEST['unsort'])){
					$sectionManager->edit($section->get('id'), array('entry_order' => NULL, 'entry_order_direction' => NULL));
					redirect($this->_Parent->getCurrentPageURL());
				}

				$this->Form->setAttribute('action', $this->_Parent->getCurrentPageURL(). '?pg=' . $current_page.($filter ? "&amp;filter=$field_handle:$filter_value" : ''));

				## Remove the create button if there is a section link field, and no filtering set for it
				// $section_links = $section->fetchFields('sectionlink');

				// if(count($section_links) > 1 || (!$filter && $section_links) || (is_object($section_links[0]) && $filter != $section_links[0]->get('id'))){
				// 	$this->appendSubheading($section->get('name'));
				// }
				// else{
				// 	$this->appendSubheading($section->get('name'), Widget::Anchor(__('Create New'), $this->_Parent->getCurrentPageURL().'new/'.($filter ? '?prepopulate['.$filter.']=' . $filter_value : ''), __('Create a new entry'), 'create button'));
				// }

				if(is_null($entryManager->getFetchSorting()->field) && is_null($entryManager->getFetchSorting()->direction)){
					$entryManager->setFetchSortingDirection('DESC');
				}

				$section_schema = $section->fetchFieldsSchema();
				foreach($section_schema as $info){
					if ($info['type'] == 'pages') {
						$pagesField_id = $info['id'];
					}
				}
				$joins = "LEFT JOIN tbl_entries_data_{$pagesField_id} AS p ON (`p`.`entry_id` = `e`.`id`) ";
				$where = " AND `p`.`page_id` = {$page_id}";
				
				$entries = $entryManager->fetchByPage($current_page, $section_id, Symphony::Configuration()->get('pagination_maximum_rows', 'symphony'), $where, $joins);

				$aTableHead = array();

				$visible_columns = $section->fetchVisibleColumns();

				// remove pages type fields
				if(is_array($visible_columns) && !empty($visible_columns)){
					foreach($visible_columns as $id => $column){
						if($column->get('type') == 'pages') {
							unset($visible_columns[$id]);
						};
					}
					// re-key the array
					$visible_columns = array_values($visible_columns);
				}

				if(is_array($visible_columns) && !empty($visible_columns)){
					foreach($visible_columns as $column){
						$label = $column->get('label');

						if($column->isSortable()) {

							if($column->get('id') == $section->get('entry_order')){
								$link = $this->_Parent->getCurrentPageURL() . '?pg='.$current_page.'&amp;sort='.$column->get('id').'&amp;order='. ($section->get('entry_order_direction') == 'desc' ? 'asc' : 'desc').($filter ? "&amp;filter=$field_handle:$filter_value" : '');
								$anchor = Widget::Anchor($label, $link, __('Sort by %1$s %2$s', array(($section->get('entry_order_direction') == 'desc' ? __('ascending') : __('descending')), strtolower($column->get('label')))), 'active');
							}

							else{
								$link = $this->_Parent->getCurrentPageURL() . '?pg='.$current_page.'&amp;sort='.$column->get('id').'&amp;order=asc'.($filter ? "&amp;filter=$field_handle:$filter_value" : '');
								$anchor = Widget::Anchor($label, $link, __('Sort by %1$s %2$s', array(__('ascending'), strtolower($column->get('label')))));
							}

							$aTableHead[] = array($anchor, 'col');
						}

						else $aTableHead[] = array($label, 'col');
					}
				}

				else $aTableHead[] = array(__('ID'), 'col');

				$child_sections = NULL;

				$associated_sections = $section->fetchAssociatedSections();
				if(is_array($associated_sections) && !empty($associated_sections)){
					$child_sections = array();
					foreach($associated_sections as $key => $as){
						$child_sections[$key] = $sectionManager->fetch($as['child_section_id']);
						$aTableHead[] = array($child_sections[$key]->get('name'), 'col');
					}
				}

				## Table Body
				$aTableBody = array();

				if(!is_array($entries['records']) || empty($entries['records'])){

					$aTableBody = array(
						Widget::TableRow(array(Widget::TableData(__('None found.'), 'inactive', NULL, count($aTableHead))), 'odd')
					);
				}

				else{

					$bOdd = true;


					$field_pool = array();
					if(is_array($visible_columns) && !empty($visible_columns)){
						foreach($visible_columns as $column){
							$field_pool[$column->get('id')] = $column;
						}
					}

					foreach($entries['records'] as $entry){

						$tableData = array();

						## Setup each cell
						if(!is_array($visible_columns) || empty($visible_columns)){
							$tableData[] = Widget::TableData(Widget::Anchor($entry->get('id'), $this->_Parent->getCurrentPageURL() . 'edit/' . $entry->get('id') . '/'));
						}

						else{

							$link = Widget::Anchor(
								'None',
								$this->_Parent->getCurrentPageURL() . $section->get('handle') . '/edit/' . $entry->get('id') . '/',
								$entry->get('id'),
								'content'
							);

							foreach ($visible_columns as $position => $column) {
								$data = $entry->getData($column->get('id'));
								$field = $field_pool[$column->get('id')];

								$value = $field->prepareTableValue($data, ($position == 0 ? $link : null), $entry->get('id'));

								if (!is_object($value) && strlen(trim($value)) == 0) {
									$value = ($position == 0 ? $link->generate() : __('None'));
								}

								if ($value == 'None') {
									$tableData[] = Widget::TableData($value, 'inactive');

								} else {
									$tableData[] = Widget::TableData($value);
								}

								unset($field);
							}
						}

						if(is_array($child_sections) && !empty($child_sections)){
							foreach($child_sections as $key => $as){

								$field = $entryManager->fieldManager->fetch((int)$associated_sections[$key]['child_section_field_id']);

								$parent_section_field_id = (int)$associated_sections[$key]['parent_section_field_id'];

								if(!is_null($parent_section_field_id)){
									$search_value = $field->fetchAssociatedEntrySearchValue(
										$entry->getData($parent_section_field_id),
										$parent_section_field_id,
										$entry->get('id')
									);
								}

								else{
									$search_value = $entry->get('id');
								}

								$associated_entry_count = $field->fetchAssociatedEntryCount($search_value);

								$tableData[] = Widget::TableData(
									Widget::Anchor(
										sprintf('%d &rarr;', max(0, intval($associated_entry_count))),
										sprintf(
											'%s/symphony/publish/%s/?filter=%s:%s',
											URL,
											$as->get('handle'),
											$field->get('element_name'),
											rawurlencode($search_value)
										),
										$entry->get('id'),
										'content')
								);
							}
						}

						$tableData[count($tableData) - 1]->appendChild(Widget::Input('items['.$entry->get('id').']', NULL, 'checkbox'));

						## Add a row to the body array, assigning each cell to the row
						$aTableBody[] = Widget::TableRow($tableData, ($bOdd ? 'odd' : NULL));

						$bOdd = !$bOdd;

					}
				}

				$table = Widget::Table(
					Widget::TableHead($aTableHead),
					NULL,
					Widget::TableBody($aTableBody)
				);

				$h3 = new XMLElement('h3', __($section->get('name')), array('style' => 'margin: 0.75em 17px;'));
				$h3->appendChild(Widget::Anchor(__('Create New'), $this->_Parent->getCurrentPageURL().$section->get('handle').'/new/', __('Create a new entry'), 'create button'));
				// TODO: create ne button for each section
				$this->Form->appendChild($h3);

				$this->Form->appendChild($table);


				$tableActions = new XMLElement('div');
				$tableActions->setAttribute('class', 'actions');

				$options = array(
					array(NULL, false, __('With Selected...')),
					array('delete', false, __('Delete'))
				);

				$toggable_fields = $section->fetchToggleableFields();

				if (is_array($toggable_fields) && !empty($toggable_fields)) {
					$index = 2;

					foreach ($toggable_fields as $field) {
						$options[$index] = array('label' => __('Set %s', array($field->get('label'))), 'options' => array());

						foreach ($field->getToggleStates() as $value => $state) {
							$options[$index]['options'][] = array('toggle-' . $field->get('id') . '-' . $value, false, $state);
						}

						$index++;
					}
				}

				$tableActions->appendChild(Widget::Select('with-selected', $options));
				$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));

				$this->Form->appendChild($tableActions);

				if($entries['total-pages'] > 1){

					$ul = new XMLElement('ul');
					$ul->setAttribute('class', 'page');

					## First
					$li = new XMLElement('li');
					if($current_page > 1) $li->appendChild(Widget::Anchor(__('First'), $this->_Parent->getCurrentPageURL(). '?pg=1'.($filter ? "&amp;filter=$field_handle:$filter_value" : '')));
					else $li->setValue(__('First'));
					$ul->appendChild($li);

					## Previous
					$li = new XMLElement('li');
					if($current_page > 1) $li->appendChild(Widget::Anchor(__('&larr; Previous'), $this->_Parent->getCurrentPageURL(). '?pg=' . ($current_page - 1).($filter ? "&amp;filter=$field_handle:$filter_value" : '')));
					else $li->setValue(__('&larr; Previous'));
					$ul->appendChild($li);

					## Summary
					$li = new XMLElement('li', __('Page %1$s of %2$s', array($current_page, max($current_page, $entries['total-pages']))));
					$li->setAttribute('title', __('Viewing %1$s - %2$s of %3$s entries', array(
						$entries['start'],
						($current_page != $entries['total-pages']) ? $current_page * Symphony::Configuration()->get('pagination_maximum_rows', 'symphony') : $entries['total-entries'],
						$entries['total-entries']
					)));
					$ul->appendChild($li);

					## Next
					$li = new XMLElement('li');
					if($current_page < $entries['total-pages']) $li->appendChild(Widget::Anchor(__('Next &rarr;'), $this->_Parent->getCurrentPageURL(). '?pg=' . ($current_page + 1).($filter ? "&amp;filter=$field_handle:$filter_value" : '')));
					else $li->setValue(__('Next &rarr;'));
					$ul->appendChild($li);

					## Last
					$li = new XMLElement('li');
					if($current_page < $entries['total-pages']) $li->appendChild(Widget::Anchor(__('Last'), $this->_Parent->getCurrentPageURL(). '?pg=' . $entries['total-pages'].($filter ? "&amp;filter=$field_handle:$filter_value" : '')));
					else $li->setValue(__('Last'));
					$ul->appendChild($li);

					$this->Form->appendChild($ul);

				}
			}

		}
		
		public function __viewContentEdit() {
			$this->setPageType('form');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Pages'))));
			
			$nesting = (Symphony::Configuration()->get('pages_table_nest_children', 'symphony') == 'yes');
			
			$heading = NULL;
			if($nesting == true && isset($_GET['parent']) && is_numeric($_GET['parent'])){
				$parent = (int)$_GET['parent'];
				$heading = ' &mdash; ' . self::__buildParentBreadcrumb($parent);
			}
			
			$this->appendSubheading(__('Pages') . $heading, Widget::Anchor(
				__('Create New'), Administration::instance()->getCurrentPageURL() . 'new/' . ($nesting == true && isset($parent) ? "?parent={$parent}" : NULL),
				__('Create a new page'), 'create button'
			));
			
		}
		
	}
	
?>