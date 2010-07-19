<?php
	
	require_once(CONTENT . '/content.blueprintspages.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	
	class contentExtensionStatic_content_managerTemplates extends contentBlueprintsPages {
		protected $_driver = null;
		protected $_uri = null;
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$this->_uri = URL . '/symphony/extension/static_content_manager';
			$this->_driver = $this->_Parent->ExtensionManager->create('static_content_manager');
		}
		
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Page Templates'))));
			
			$heading = NULL;
			
			$this->appendSubheading(__('Page Templates') . $heading, Widget::Anchor(
				__('Create New'), Administration::instance()->getCurrentPageURL() . 'new/',
				__('Create a new page template'), 'create button'
			));
			
			$aTableHead = array(
				array(__('Title'), 'col'),
				array(__('Template'), 'col'),
				array(__('Actions'), 'col')
			);	
			
			$sql = "
				SELECT
					t.*
				FROM
					`tbl_page_templates` AS t
				ORDER BY
					t.sortorder ASC
			";
		
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
					$page_title = $this->resolvePageTemplateTitle($page['id']);
					$page_edit_url = $this->_uri . '/templates/edit/' . $page['id'] . '/';
					
					$col_title = Widget::TableData(Widget::Anchor(
						$page_title, $page_edit_url, $page['handle']
					));
					$col_title->appendChild(Widget::Input("items[{$page['id']}]", null, 'checkbox'));
					
					$col_template = Widget::TableData('TODO');
					
					$col_actions = Widget::TableData('TODO');
					
					if($bOdd) $class[] = 'odd';
					if(in_array($page['id'], $this->_hilights)) $class[] = 'failed';
					
					$columns = array($col_title, $col_template, $col_actions);
					
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
			
			// Verify template exists:
			if($this->_context[0] == 'edit') {
				if(!$template_id = $this->_context[1]) redirect($this->_uri . '/templates/');
				
				$existing = Symphony::Database()->fetchRow(0, "
					SELECT
						t.*
					FROM
						`tbl_page_templates` AS t
					WHERE
						t.id = '{$template_id}'
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
				
				switch($flag){
					
					case 'saved':
						
						$this->pageAlert(
							__(
								'Page template updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Pages</a>', 
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__), 
									$this->_uri . '/templates/new/' . $link_suffix,
									$this->_uri . '/templates/' . $link_suffix,
								)
							), 
							Alert::SUCCESS);
													
						break;
						
					case 'created':

						$this->pageAlert(
							__(
								'Page template created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Page templates</a>', 
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__), 
									$this->_uri . '/templates/new/' . $link_suffix,
									$this->_uri . '/templates/' . $link_suffix,
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
			
			$title = $fields['title'];
			if(trim($title) == '') $title = $existing['title'];
			
			$this->setTitle(__(
				($title ? '%1$s &ndash; %2$s &ndash; %3$s' : '%1$s &ndash; %2$s'),
				array(
					__('Symphony'),
					__('Page templates'),
					$title
				)
			));
			$this->appendSubheading(($title ? $title : __('Untitled')));
			
			// Title --------------------------------------------------------------

				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings');
				$fieldset->appendChild(new XMLElement('legend', __('Page Template Settings')));

				$label = Widget::Label(__('Title'));		
				$label->appendChild(Widget::Input(
					'fields[title]', General::sanitize($fields['title'])
				));

				if(isset($this->_errors['title'])) {
					$label = $this->wrapFormElementWithError($label, $this->_errors['title']);
				}

				$fieldset->appendChild($label);

			// Parameters ---------------------------------------------------------

				$group = new XMLElement('div');
				$group->setAttribute('class', 'group');
				$column = new XMLElement('div');

				$label = Widget::Label(__('URL Parameters'));
				$label->appendChild(Widget::Input(
					'fields[params]', $fields['params']
				));
				$column->appendChild($label);

				$group->appendChild($column);

			// Type -----------------------------------------------------------

				$column = new XMLElement('div');
				$label = Widget::Label(__('Page Type'));
				$label->appendChild(Widget::Input('fields[type]', $fields['type']));

				if(isset($this->_errors['type'])) {
					$label = $this->wrapFormElementWithError($label, $this->_errors['type']);
				}

				$column->appendChild($label);

				$tags = new XMLElement('ul');
				$tags->setAttribute('class', 'tags');

				if($types = $this->__fetchAvailablePageTypes()) {
					foreach($types as $type) $tags->appendChild(new XMLElement('li', $type));
				}
				$column->appendChild($tags);

				$group->appendChild($column);
				$fieldset->appendChild($group);
				$this->Form->appendChild($fieldset);

			// Events -------------------------------------------------------------

				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings');
				$fieldset->appendChild(new XMLElement('legend', __('Page Template Resources')));

				$group = new XMLElement('div');
				$group->setAttribute('class', 'group');

				$label = Widget::Label(__('Events'));

				$manager = new EventManager($this->_Parent);
				$events = $manager->listAll();

				$options = array();

				if(is_array($events) && !empty($events)) {		
					foreach ($events as $name => $about) $options[] = array(
						$name, @in_array($name, $fields['events']), $about['name']
					);
				}

				$label->appendChild(Widget::Select('fields[events][]', $options, array('multiple' => 'multiple')));		
				$group->appendChild($label);

			// Data Sources -------------------------------------------------------

				$label = Widget::Label(__('Data Sources'));

				$manager = new DatasourceManager($this->_Parent);
				$datasources = $manager->listAll();

				$options = array();

				if(is_array($datasources) && !empty($datasources)) {
					foreach ($datasources as $name => $about) $options[] = array(
						$name, @in_array($name, $fields['data_sources']), $about['name']
					);
				}

				$label->appendChild(Widget::Select('fields[data_sources][]', $options, array('multiple' => 'multiple')));
				$group->appendChild($label);
				$fieldset->appendChild($group);
				$this->Form->appendChild($fieldset);
			
		// Controls -----------------------------------------------------------
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input(
				'action[save]', ($this->_context[0] == 'edit' ? __('Save Changes') : __('Create Page Template')),
				'submit', array('accesskey' => 's')
			));
			
			if($this->_context[0] == 'edit'){
				$button = new XMLElement('button', __('Delete'));
				$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'confirm delete', 'title' => __('Delete this Page Template')));
				$div->appendChild($button);
			}
			
			$this->Form->appendChild($div);
			
		}
		
		public function __actionEdit() {
			if($this->_context[0] != 'new' && !$template_id = (integer)$this->_context[1]) {
				redirect($this->_uri . '/templates/');
			}
				
			if(@array_key_exists('delete', $_POST['action'])) {
				$this->__actionDelete($template_id, $this->_uri . '/templates/');
			}
			
			if(@array_key_exists('save', $_POST['action'])) {
				
				$fields = $_POST['fields'];
				$this->_errors = array();
				
				$current = Symphony::Database()->fetchRow(0, "
					SELECT
						t.*
					FROM
						`tbl_page_templates` AS t
					WHERE
						t.id = '{$template_id}'
					LIMIT 1
				");
				
				if(!isset($fields['title']) || trim($fields['title']) == '') {
					$this->_errors['title'] = __('Title is a required field');
				}
				
				if(trim($fields['type']) != '' && preg_match('/(index|404|403)/i', $fields['type'])) {
					$types = preg_split('/\s*,\s*/', strtolower($fields['type']), -1, PREG_SPLIT_NO_EMPTY);
					
					if(in_array('index', $types) && $this->__typeUsed($template_id, 'index')) {
						$this->_errors['type'] = __('An index type page template already exists.');
					}
					
					elseif(in_array('404', $types) && $this->__typeUsed($template_id, '404')) {	
						$this->_errors['type'] = __('A 404 type page template already exists.');
					}
					
					elseif(in_array('403', $types) && $this->__typeUsed($template_id, '403')) {	
						$this->_errors['type'] = __('A 403 type page template already exists.');
					}
				}
				
				if(empty($this->_errors)) {
					$autogenerated_handle = false;
					
					if(empty($current)) {
						$fields['sortorder'] = Symphony::Database()->fetchVar('next', 0, "
							SELECT
								MAX(t.sortorder) + 1 AS `next`
							FROM
								`tbl_page_templates` AS t
							LIMIT 1
						");
						
						if(empty($fields['sortorder']) || !is_numeric($fields['sortorder'])) {
							$fields['sortorder'] = 1;
						}
					}
					
					$fields['handle'] = $fields['title'];
					$autogenerated_handle = true;
					
					$fields['handle'] = Lang::createHandle($fields['handle']);		

					if($fields['params']) {
						$fields['params'] = trim(preg_replace('@\/{2,}@', '/', $fields['params']), '/');
					}
					
					// Clean up type list
					$types = preg_split('/\s*,\s*/', $fields['type'], -1, PREG_SPLIT_NO_EMPTY);
					$types = @array_map('trim', $types);
					unset($fields['type']);
					
					$fields['parent'] = null;
					$fields['data_sources'] = is_array($fields['data_sources']) ? implode(',', $fields['data_sources']) : NULL;
					$fields['events'] = is_array($fields['events']) ? implode(',', $fields['events']) : NULL;
					$fields['path'] = null;
					
					// Check for duplicates:
					$duplicate = Symphony::Database()->fetchRow(0, "
						SELECT
							t.*
						FROM
							`tbl_page_templates` AS t
						WHERE
							t.id != '{$template_id}'
							AND t.handle = '" . $fields['handle'] . "'
							AND t.path " . ($fields['path'] ? " = '" . $fields['path'] . "'" : ' IS NULL') .  " 
						LIMIT 1
					");
					
					// Create or move files:
					if(empty($current)) {
						$file_created = $this->__updatePageTemplateFiles(
							$fields['path'], $fields['handle']
						);
						
					} else {
						$file_created = $this->__updatePageTemplateFiles(
							$fields['path'], $fields['handle'],
							$current['path'], $current['handle']
						);
					}
					
					if(!$file_created) {
						$redirect = null;
						$this->pageAlert(
							__('Page template could not be written to disk. Please check permissions on <code>/workspace/pages/templates</code>.'),
							Alert::ERROR
						);
					}
					
					if($duplicate) {
						if($autogenerated_handle) {
							$this->_errors['title'] = __('A page template with that title already exists');
							
						} else {
							$this->_errors['handle'] = __('A page template with that handle already exists'); 
						}
						
					// Insert the new data:
					} elseif(empty($current)) {
						if(!Symphony::Database()->insert($fields, 'tbl_page_templates')) {
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
							$template_id = Symphony::Database()->getInsertID();
							$redirect = $this->_uri . "/templates/edit/{$template_id}/created/";
						}
						
					// Update existing:
					} else {
						if(!Symphony::Database()->update($fields, 'tbl_pages', "`id` = '$template_id'")) {
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
							Symphony::Database()->delete('tbl_pages_types', " `page_id` = '$template_id'");
							$redirect = $this->_uri . "/templates/edit/{$template_id}/saved/";
						}
					}
					
					// Assign page types:
					if(is_array($types) && !empty($types)) {
						foreach ($types as $type) Symphony::Database()->insert(
							array(
								'page_id' => $template_id,
								'type' => $type
							),
							'tbl_pages_types'
						);
					}
					
					if($redirect) redirect($redirect);
				}
				
				if(is_array($this->_errors) && !empty($this->_errors)) {
					$this->pageAlert(
						__('An error occurred while processing this form. <a href="#error">See below for details.</a>'),
						Alert::ERROR
					);
				}
			}			
		}
		
		protected function __updatePageTemplateFiles($new_path, $new_handle, $old_path = null, $old_handle = null) {
			$new = PAGES . '/templates' . '/' . $this->__createHandle($new_path, $new_handle) . '.xsl';
			$old = PAGES . '/templates' . '/' . $this->__createHandle($old_path, $old_handle) . '.xsl';
			$data = null;
			
			// Nothing to do:
			if(file_exists($new) && $new == $old) return true;
			
			// Old file doesn't exist, use template:
			if(!file_exists($old)) {
				$data = file_get_contents(TEMPLATE . '/page.xsl');
				
			}
			else{
				$data = file_get_contents($old); @unlink($old);
			}
			
			return General::writeFile($new, $data, Symphony::Configuration()->get('write_mode', 'file'));
		}
		
		protected function __deletePageTemplateFiles($path, $handle) {
			$file = PAGES . '/templates' . '/' . trim(str_replace('/', '_', $path . '_' . $handle), '_') . '.xsl';
			
			// Nothing to do:
			if(!file_exists($file)) return true;
			
			// Delete it:
			if(@unlink($file)) return true;
			
			return false;
		}
		
		public function resolvePageTemplateTitle($page_id) {
			$path = $this->resolvePageTemplate($page_id, 'title');
			
			return @implode(': ', $path);
		}
		
		public function resolvePageTemplate($page_id, $column) {
			$page = Symphony::$Database->fetchRow(0, "
				SELECT
					p.{$column},
					p.parent
				FROM 
					`tbl_page_templates` AS p
				WHERE
					p.id = '{$page_id}'
					OR p.handle = '{$page_id}'
				LIMIT 1
			");
			
			$path = array(
				$page[$column]
			);
			
			if ($page['parent'] != null) {
				$next_parent = $page['parent'];
				
				while (
					$parent = Symphony::$Database->fetchRow(0, "
						SELECT
							p.*
						FROM
							`tbl_page_templates` AS p
						WHERE
							p.id = '{$next_parent}'
					")
				) {
					array_unshift($path, $parent[$column]);
					
					$next_parent = $parent['parent'];
				}
			}
			
			return $path;
		}
		
		
	}
	
?>