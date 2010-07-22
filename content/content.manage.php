<?php
	
	require_once(CONTENT . '/content.blueprintspages.php');
	
	class contentExtensionPage_templatesManage extends contentBlueprintsPages {
		protected $_driver = null;
		protected $_uri = null;
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$this->_uri = URL . '/symphony/extension/page_templates';
			$this->_driver = $this->_Parent->ExtensionManager->create('page_templates');
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
				array(__('<acronym title="Universal Resource Locator">URL</acronym> Parameters'), 'col'),
				array(__('Type'), 'col'),
				array(__('Action'), 'col')
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
					$page_edit_url = $this->_uri . '/manage/edit/' . $page['id'] . '/';
					$page_spawn_url = $this->_uri . '/manage/spawn/' . $page['id'] . '/';
					$page_template = $this->__createHandle($page['path'], $page['handle']);
					$page_template_url = Administration::instance()->getCurrentPageURL() . 'template/' . $page_template;
					$page_types = Symphony::Database()->fetchCol('type', "
						SELECT
							t.type
						FROM
							`tbl_page_templates_types` AS t
						WHERE
							t.page_template_id = '".$page['id']."'
						ORDER BY
							t.type ASC
					");
					
					$col_title = Widget::TableData(Widget::Anchor(
						$page_title, $page_edit_url, $page['handle']
					));
					$col_title->appendChild(Widget::Input("items[{$page['id']}]", null, 'checkbox'));
					
					$col_template = Widget::TableData(Widget::Anchor(
						$page_template . '.xsl',
						$page_template_url
					));
					
					if($page['params']) {
						$col_params = Widget::TableData(trim($page['params'], '/'));
						
					} else {
						$col_params = Widget::TableData(__('None'), 'inactive');
					}
					
					if(!empty($page_types)) {
						$col_types = Widget::TableData(implode(', ', $page_types));
						
					} else {
						$col_types = Widget::TableData(__('None'), 'inactive');
					}
					
					$col_actions = Widget::TableData(Widget::Anchor(
						__('Create a %1$s Page', array($page_title)), $page_spawn_url
					));
					
					if($bOdd) $class[] = 'odd';
					if(in_array($page['id'], $this->_hilights)) $class[] = 'failed';
					
					$columns = array($col_title, $col_template, $col_params, $col_types, $col_actions);
					
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
		
		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);
			
			if(is_array($checked) && !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						$this->__actionDelete($checked, $this->_uri . '/manage/');
						break; 
				}
			}
		}	
		
		public function __viewEdit() {
			$this->setPageType('form');
			$fields = array();
			
			// Verify template exists:
			if($this->_context[0] == 'edit') {
				if(!$template_id = $this->_context[1]) redirect($this->_uri . '/manage/');
				
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
								'Page template updated at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Page Templates</a> <a href="%4$s">Create Page from Template</a>',
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__), 
									$this->_uri . '/manage/new/',
									$this->_uri . '/manage/',
									$this->_uri . '/manage/spawn/' . $template_id,
								)
							), 
							Alert::SUCCESS);
													
						break;
						
					case 'created':

						$this->pageAlert(
							__(
								'Page template created at %1$s. <a href="%2$s">Create another?</a> <a href="%3$s">View all Page Templates</a> <a href="%4$s">Create Page from Template</a>', 
								array(
									DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__), 
									$this->_uri . '/manage/new/',
									$this->_uri . '/manage/',
									$this->_uri . '/manage/spawn/' . $template_id,
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
				$types = Symphony::Database()->fetchCol('type', "
					SELECT
						p.type
					FROM
						`tbl_page_templates_types` AS p
					WHERE
						p.page_template_id = '{$template_id}'
					ORDER BY
						p.type ASC
				");
				
				$fields['type'] = @implode(', ', $types);
				$fields['data_sources'] = preg_split('/,/i', $fields['data_sources'], -1, PREG_SPLIT_NO_EMPTY);
				$fields['events'] = preg_split('/,/i', $fields['events'], -1, PREG_SPLIT_NO_EMPTY);
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
			if($existing) {
				$template_name = $fields['handle'];
				$this->appendSubheading(__($title ? $title : __('Untitled')), Widget::Anchor(__('Edit Template'), $this->_uri . '/manage/template/' . $template_name, __('Edit Page Template'), 'button'));
			}
			else {
				$this->appendSubheading(($title ? $title : __('Untitled')));
			}
			
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
				redirect($this->_uri . '/manage/');
			}
				
			if(@array_key_exists('delete', $_POST['action'])) {
				$this->__actionDelete($template_id, $this->_uri . '/manage/');
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
							$redirect = $this->_uri . "/manage/edit/{$template_id}/created/";
						}
						
					// Update existing:
					} else {
						if(!Symphony::Database()->update($fields, 'tbl_page_templates', "`id` = '$template_id'")) {
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
							Symphony::Database()->delete('tbl_page_templates_types', " `page_template_id` = '$template_id'");
							$redirect = $this->_uri . "/manage/edit/{$template_id}/saved/";
						}
					}
					
					// Assign page types:
					if(is_array($types) && !empty($types)) {
						foreach ($types as $type) Symphony::Database()->insert(
							array(
								'page_template_id' => $template_id,
								'type' => $type
							),
							'tbl_page_templates_types'
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
		
		protected function __actionDelete($page_templates, $redirect) {
			$success = true;
			
			if(!is_array($page_templates)) $page_templates = array($page_templates);
			
			foreach ($page_templates as $template_id) {
				$template = Symphony::Database()->fetchRow(0, "
					SELECT
						t.*
					FROM
						`tbl_page_templates` AS t
					WHERE
						t.id = '{$template_id}'
					LIMIT 1
				");
				
				if(empty($template)) {
					$success = false;
					$this->pageAlert(
						__('Page Template could not be deleted because it does not exist.'),
						Alert::ERROR
					);
					
					break;
				}
				
				$references = Symphony::Database()->fetchCol('page_id', "
					SELECT
						t.page_id
					FROM
						`tbl_pages_page_templates` AS t
					WHERE
						t.page_template_id = '{$template_id}'
				");
				
				if (is_array($references) && !empty($references)) {
					$success = false;
					$page_links = array();
					
					foreach ($references as $page_id) {
						$title = Symphony::Database()->fetchVar('title', 0, "
							SELECT
								p.title
							FROM
								`tbl_pages` AS p
							WHERE
								p.id = '{$page_id}'
							LIMIT 1
						");
						if ($title) {
							$page_links[] = '<a href="' . URL . '/symphony/blueprints/pages/edit/' . $page_id . '/">' . $title . '</a>';
						}
					}
					$page_links = implode(', ', $page_links);
					
					$this->pageAlert(
						__('Template could not be deleted because it is referenced by the following pages.') . $page_links,
						Alert::ERROR
					);
					
					break;
				}
				
				if(!$this->__deletePageTemplateFiles($template['path'], $template['handle'])) {
					$this->_hilights[] = $template['id'];
					$success = false;
					$this->pageAlert(
						__('One or more page templates could not be deleted. Please check permissions on <code>/workspace/pages</code>.'),
						Alert::ERROR
					);
					
					continue;
				}
				
				Symphony::Database()->delete('tbl_page_templates', " `id` = '{$template_id}'");
				// Symphony::Database()->delete('tbl_page_templates_types', " `page_template_id` = '{$page_id}'");
				Symphony::Database()->query("
					UPDATE
						tbl_page_templates
					SET
						`sortorder` = (`sortorder` + 1)
					WHERE
						`sortorder` < '$template_id'
				");
			}
			
			if($success) redirect($redirect);
		}
		
		public function __viewTemplate() {
			$this->setPageType('form');
			$this->Form->setAttribute('action', $this->_uri . '/manage/template/' . $this->_context[1] . '/');
			
			$filename = $this->_context[1] . '.xsl';
			$file_abs = PAGES . '/_page_template_' . $filename;
			
			$is_child = strrpos($this->_context[1],'_');
			$pagename = ($is_child != false ? substr($this->_context[1], $is_child + 1) : $this->_context[1]);

			$pagedata = $this->_Parent->Database->fetchRow(0, "
					SELECT
						t.*
					FROM
						`tbl_page_templates` AS t
					WHERE
						t.handle = '{$pagename}'
					LIMIT 1
				");
			
			if(!is_file($file_abs)) redirect($this->_uri . '/manage/');
			
			$fields['body'] = @file_get_contents($file_abs);
			
			$formHasErrors = (is_array($this->_errors) && !empty($this->_errors));			
			if($formHasErrors) $this->pageAlert(__('An error occurred while processing this form. <a href="#error">See below for details.</a>'), Alert::ERROR);
			
			// Status message:
			if(isset($this->_context[2])) {
				$this->pageAlert(
					__(
						'Page Template updated at %s. <a href="%s">View all Page Templates</a>',
						array(
							DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
							$this->_uri . '/manage/'
						)
					),
					Alert::SUCCESS
				);
			}
			
			$this->setTitle(__(
				($filename ? '%1$s &ndash; %2$s &ndash; %3$s' : '%1$s &ndash; %2$s'),
				array(
					__('Symphony'),
					__('Page Templates'),
					$filename
				)
			));
			$this->appendSubheading(__($filename ? $filename : __('Untitled')), Widget::Anchor(__('Edit Configuration'), $this->_uri . '/manage/edit/' . $pagedata['id'], __('Edit Page Confguration'), 'button'));
			
			if(!empty($_POST)) $fields = $_POST['fields'];
			
			$fields['body'] = General::sanitize($fields['body']);
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'primary');
			
			$label = Widget::Label(__('Body'));
			$label->appendChild(Widget::Textarea(
				'fields[body]', 30, 80, $fields['body'],
				array(
					'class'	=> 'code'
				)
			));
			
			if(isset($this->_errors['body'])) {
				$label = $this->wrapFormElementWithError($label, $this->_errors['body']);
			}
			
			$fieldset->appendChild($label);
			$this->Form->appendChild($fieldset);
			
			$utilities = General::listStructure(UTILITIES, array('xsl'), false, 'asc', UTILITIES);
			$utilities = $utilities['filelist'];
			
			if(is_array($utilities) && !empty($utilities)) {
				$div = new XMLElement('div');
				$div->setAttribute('class', 'secondary');
				
				$h3 = new XMLElement('h3', __('Utilities'));
				$h3->setAttribute('class', 'label');
				$div->appendChild($h3);
				
				$ul = new XMLElement('ul');
				$ul->setAttribute('id', 'utilities');
				
				foreach ($utilities as $index => $util) {
					$li = new XMLElement('li');
					
					if($index % 2 != 1) $li->setAttribute('class', 'odd');
					
					$li->appendChild(Widget::Anchor($util, URL . '/symphony/blueprints/utilities/edit/' . str_replace('.xsl', '', $util) . '/', NULL));
					$ul->appendChild($li);
				}
				
				$div->appendChild($ul);
				$this->Form->appendChild($div);
			}
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input(
				'action[save]', __('Save Changes'),
				'submit', array('accesskey' => 's')
			));
			
			$this->Form->appendChild($div);
		}
		
		public function __actionTemplate() {
			$filename = $this->_context[1] . '.xsl';
			$file_abs = PAGES . '/_page_template_' . $filename;
			$fields = $_POST['fields'];
			$this->_errors = array();
			
			if(!isset($fields['body']) || trim($fields['body']) == '') {
				$this->_errors['body'] = __('Body is a required field.');
				
			} elseif(!General::validateXML($fields['body'], $errors, false, new XSLTProcess())) {
				$this->_errors['body'] = __('This document is not well formed. The following error was returned: <code>%s</code>', array($errors[0]['message']));
			}
			
			if(empty($this->_errors)) {
				if(!$write = General::writeFile($file_abs, $fields['body'], Symphony::Configuration()->get('write_mode', 'file'))) {
					$this->pageAlert(__('Utility could not be written to disk. Please check permissions on <code>/workspace/utilities</code>.'), Alert::ERROR);
					
				} else {
					redirect($this->_uri . '/manage/template/' . $this->_context[1] . '/saved/');
				}
			}
		}
		
		public function __viewSpawn() {
			$this->setPageType('form');
			
			$fields = array();
			
			// Verify template exists:
			if($this->_context[0] == 'spawn') {
				if(!$template_id = $this->_context[1]) redirect($this->_uri . '/manage/');
				
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
			if ($this->_context[0] == 'spawn') {
				$fields = $existing;
				$types = Symphony::Database()->fetchCol('type', "
					SELECT
						p.type
					FROM
						`tbl_page_templates_types` AS p
					WHERE
						p.page_template_id = '{$template_id}'
					ORDER BY
						p.type ASC
				");
				
				$fields['type'] = @implode(', ', $types);
				$fields['data_sources'] = preg_split('/,/i', $fields['data_sources'], -1, PREG_SPLIT_NO_EMPTY);
				$fields['events'] = preg_split('/,/i', $fields['events'], -1, PREG_SPLIT_NO_EMPTY);
			}
			
			$title = $fields['title'];
			if(trim($title) == '') $title = $existing['title'];
			
			$this->setTitle(__(
				($title ? '%1$s &ndash; %2$s &ndash; %3$s &ndash; %4$s' : '%1$s &ndash; %2$s'),
				array(
					__('Symphony'),
					__('Page Templates'),
					__('Spawn'),
					$title
				)
			));
			if($existing) {
				$template_name = $fields['handle'];
				$this->appendSubheading($title ? __('Create a %1$s page', array($title)) : __('Untitled'));
			}
			else {
				$this->appendSubheading($title ? __('Create a %1$s page', array($title)) : __('Untitled'));
			}
			
		// Title --------------------------------------------------------------

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Page Settings')));

			$label = Widget::Label(__('Title'));		
			$label->appendChild(Widget::Input(
				'fields[title]', General::sanitize(__('A %1$s page', array($title)))
			));

			if(isset($this->_errors['title'])) {
				$label = $this->wrapFormElementWithError($label, $this->_errors['title']);
			}

			$fieldset->appendChild($label);

		// Handle -------------------------------------------------------------

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			$column = new XMLElement('div');

			$label = Widget::Label(__('URL Handle'));
			$label->appendChild(Widget::Input(
				'fields[handle]'
			));

			if(isset($this->_errors['handle'])) {
				$label = $this->wrapFormElementWithError($label, $this->_errors['handle']);
			}

			$column->appendChild($label);

		// Parent ---------------------------------------------------------

			$label = Widget::Label(__('Parent Page'));

			$pages = Symphony::Database()->fetch("
				SELECT
					p.*
				FROM
					`tbl_pages` AS p
				WHERE
					p.id != '{$page_id}'
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

		// Parameters ---------------------------------------------------------

			$column = new XMLElement('div');
			$label = Widget::Label(__('URL Parameters'));
			$label->appendChild(Widget::Input(
				'fields[params]', $fields['params']
			));				
			$column->appendChild($label);

		// Type -----------------------------------------------------------

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
			$fieldset->appendChild(new XMLElement('legend', __('Page Resources')));

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
				'action[save]', ($this->_context[0] == 'edit' ? __('Save Changes') : __('Create %1$s page', array($title))),
				'submit', array('accesskey' => 's')
			));

			$this->Form->appendChild($div);

			if(isset($_REQUEST['parent']) && is_numeric($_REQUEST['parent'])){
				$this->Form->appendChild(new XMLElement('input', NULL, array('type' => 'hidden', 'name' => 'parent', 'value' => $_REQUEST['parent'])));
			}
			
		}
		
		public function __actionSpawn() {
			if($this->_context[0] == 'spawn' && !$template_id = (integer)$this->_context[1]) {
				redirect($this->_uri . '/manage/');
			}
			
			if ($this->_context[0] == 'spawn') {
				$template = Symphony::Database()->fetchRow(0, "
					SELECT
						t.*
					FROM
						`tbl_page_templates` AS t
					WHERE
						t.id = '{$template_id}'
					LIMIT 1
				");

				if(!$template) {
					$this->_Parent->customError(
						E_USER_ERROR, __('Page not found'),
						__('The page template you requested to spawn from does not exist.'),
						false, true, 'error', array(
							'header'	=> 'HTTP/1.0 404 Not Found'
						)
					);
				}
			}
			
			
			$parent_link_suffix = NULL;
			if(isset($_REQUEST['parent']) && is_numeric($_REQUEST['parent'])){
				$parent_link_suffix = '?parent=' . $_REQUEST['parent'];
			}
		
			if(@array_key_exists('save', $_POST['action'])) {
				
				$fields = $_POST['fields'];
				$this->_errors = array();
				
				if(!isset($fields['title']) || trim($fields['title']) == '') {
					$this->_errors['title'] = __('Title is a required field');
				}
				
				if(trim($fields['type']) != '' && preg_match('/(index|404|403)/i', $fields['type'])) {
					$types = preg_split('/\s*,\s*/', strtolower($fields['type']), -1, PREG_SPLIT_NO_EMPTY);
					
					if(in_array('index', $types) && $this->__typeUsed(0, 'index')) {
						$this->_errors['type'] = __('An index type page already exists.');
					}
					
					elseif(in_array('404', $types) && $this->__typeUsed(0, '404')) {	
						$this->_errors['type'] = __('A 404 type page already exists.');
					}
					
					elseif(in_array('403', $types) && $this->__typeUsed(0, '403')) {	
						$this->_errors['type'] = __('A 403 type page already exists.');
					}
				}
				
				if(empty($this->_errors)) {
					$autogenerated_handle = false;
					
					$fields['sortorder'] = Symphony::Database()->fetchVar('next', 0, "
						SELECT
							MAX(p.sortorder) + 1 AS `next`
						FROM
							`tbl_pages` AS p
						LIMIT 1
					");
					
					if(empty($fields['sortorder']) || !is_numeric($fields['sortorder'])) {
						$fields['sortorder'] = 1;
					}
					
					if(trim($fields['handle'] ) == '') { 
						$fields['handle'] = $fields['title'];
						$autogenerated_handle = true;
					}
					
					$fields['handle'] = Lang::createHandle($fields['handle']);

					if($fields['params']) {
						$fields['params'] = trim(preg_replace('@\/{2,}@', '/', $fields['params']), '/');
					}
					
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
							p.handle = '" . $fields['handle'] . "'
							AND p.path " . ($fields['path'] ? " = '" . $fields['path'] . "'" : ' IS NULL') .  " 
						LIMIT 1
					");
					
					// Copy file:
					$file_created = $this->__updatePageFiles(
						$fields['path'], $fields['handle'],
						null, $template['handle']
					);
					
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
					}
					else {
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
							$redirect = "/symphony/blueprints/pages/edit/{$page_id}/created/{$parent_link_suffix}";
						}
					}
					
					// Assign page types:
					if($page_id && is_array($types) && !empty($types)) {
						foreach ($types as $type) Symphony::Database()->insert(
							array(
								'page_id' => $page_id,
								'type' => $type
							),
							'tbl_pages_types'
						);
					}
					
					if($redirect) redirect(URL . $redirect);
				}
				
				if(is_array($this->_errors) && !empty($this->_errors)) {
					$this->pageAlert(
						__('An error occurred while processing this form. <a href="#error">See below for details.</a>'),
						Alert::ERROR
					);
				}
			}			
		}
		
		protected function __updatePageFiles($new_path, $new_handle, $old_path = null, $old_handle = null) {
			$new = PAGES . '/' . $this->__createHandle($new_path, $new_handle) . '.xsl';
			$old = PAGES . '/_page_template_' . $this->__createHandle($old_path, $old_handle) . '.xsl';
			$data = null;
			
			// Nothing to do:
			if(file_exists($new) && $new == $old) return true;
			
			// Old file doesn't exist, use template:
			if(!file_exists($old)) {
				$data = file_get_contents(TEMPLATE . '/page.xsl');
				
			}
			else{
				$data = file_get_contents($old);
			}
			
			return General::writeFile($new, $data, Symphony::Configuration()->get('write_mode', 'file'));
		}
		
		protected function __updatePageTemplateFiles($new_path, $new_handle, $old_path = null, $old_handle = null) {
			$new = PAGES . '/_page_template_' . $this->__createHandle($new_path, $new_handle) . '.xsl';
			$old = PAGES . '/_page_template_' . $this->__createHandle($old_path, $old_handle) . '.xsl';
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
			$file = PAGES . '/_page_template_' . trim(str_replace('/', '_', $path . '_' . $handle), '_') . '.xsl';
			
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