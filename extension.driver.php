<?php

	require_once(EXTENSIONS . '/page_prototypes/lib/class.pageprototypes.php');

	class extension_page_prototypes extends Extension {

		protected $prototype_id;

		public function install(){
			return Symphony::Database()->query("
				CREATE TABLE `tbl_pages_prototypes` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`page_id` int(11) unsigned NOT NULL,
					`prototype_id` int(11) unsigned NOT NULL,
					PRIMARY KEY  (`id`),
					UNIQUE KEY `page_id` (`page_id`),
					KEY `prototype_id` (`prototype_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

		public function uninstall(){
			return Symphony::Database()->query("DROP TABLE `tbl_pages_prototypes`");
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendPageResolved',
					'callback' => 'frontendPageResolved'
				),
				array(
					'page' => '/backend/',
					'delegate' => 'InitaliseAdminPageHead',
					'callback' => 'addScriptToHead'
				),
				array(
					'page' => '/backend/',
					'delegate' => 'AppendPageAlert',
					'callback' => 'appendPageAlert'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'AppendPageContent',
					'callback' => 'addPrototypeFieldset'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePreCreate',
					'callback' => 'setFieldValuesFromPrototype'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePreEdit',
					'callback' => 'setFieldValuesFromPrototype'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePostCreate',
					'callback' => 'updatePagesPrototypesReferences'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePostEdit',
					'callback' => 'updatePagesPrototypesReferences'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PageTypePreCreate',
					'callback' => 'setTypesAndUpdatePagesOfPrototype'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePreDelete',
					'callback' => 'pagePreDelete'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePostDelete',
					'callback' => 'pagePostDelete'
				)
			);
		}

		public function addScriptToHead($context) {
			$page = Administration::instance()->Page;
			$page_context = $page->getContext();

			if ($page instanceof ContentBlueprintsPages && ($page_context[0] == 'edit' || $page_context[0] == 'new')) {
				$page->addScriptToHead(URL . '/extensions/page_prototypes/assets/page_prototypes.blueprintspages.js', 565656);
			}
		}

		public function addPrototypeFieldset($context) {
			$page = Administration::instance()->Page;
			$page_context = $page->getContext();
			$form = $context['form'];

			if ($page_context[0] == 'new' || $page_context[0] == 'edit') {

				// Add prototype page type
				$elements = $form->getChildren();
				$fieldset = $elements[0]->getChildren();
				$group = $fieldset[2]->getChildren();
				$div = $group[1]->getChildren();
				$types = $div[2]->getChildren();

				// Search for existing prototype type
				$flag = false;
				foreach($types as $type) {
					if($type->getValue() == 'prototype') {
						$flag = true;
					}
				}

				// Append maintenance type
				if($flag == false) {
					$mode = new XMLElement('li', 'prototype');
					$div[2]->appendChild($mode);
				}

				// Add prototype fieldset
				$page_id = $page_context[1];

				if (isset($page_id)) {
					$page_prototype = PagePrototypes::fetchPrototypeOfPage($page_id);
					$prototype_id = $page_prototype['id'];
				}

				$prototypes = PagePrototypes::fetchPrototypes();

				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings');
				$fieldset->appendChild(new XMLElement('legend', __('Prototype')));
				$fieldset->appendChild(new XMLElement('p', __('This page will inherit the template and settings from the selected prototype.'), array('class' => 'help')));

				$div = new XMLElement('div');
				$label = Widget::Label(__('Prototype Page'));
				$options = array(
					array('', false, __('None'))
				);
				if (!empty($prototypes)) {
					foreach ($prototypes as $prototype) {
						$options[] = array(
							$prototype['id'], $prototype_id == $prototype['id'], $prototype['title']
						);
					}
				}
				$label->appendChild(Widget::Select(
					'fields[prototype_id]', $options, array('id' => 'prototype_pages-prototype_id')
				));
				$div->appendChild($label);
				$fieldset->appendChild($div);

				$form->prependChild($fieldset);
			}

		}

		public function setFieldValuesFromPrototype($context) {
			$this->prototype_id = $context['fields']['prototype_id'];
			// Unset $context['fields']['prototype_id'] to prevent writing its value to the pages table.
			unset($context['fields']['prototype_id']);
			// Use prototype settings if applicable
			if (!empty($this->prototype_id)) {
				$fields = PagePrototypes::fetchPrototypeByID($this->prototype_id);

				$context['fields']['params'] = $fields['params'];
				$context['fields']['events'] = $fields['events'];
				$context['fields']['data_sources'] = $fields['data_sources'];
			}
		}

		public function updatePagesPrototypesReferences($context) {
			$page_id = $context['page_id'];

			PagePrototypes::updatePagesPrototypesReferences($page_id, $this->prototype_id);
		}

		public function setTypesAndUpdatePagesOfPrototype($context) {
			// Set types if this page references a prototype
			if (!empty($this->prototype_id)) {
				$prototype = PagePrototypes::fetchPrototypeByID($this->prototype_id);
				$context['types'] = array_values(array_diff($prototype['type'], array('prototype', '404', '403', 'index', 'maintenance')));
			}

			// If this page is a prototype, remove "special" types and update pages that use this page as a prototype
			if (in_array('prototype', $context['types'])) {
				$context['types'] = array_diff($context['types'], array('404', '403', 'index', 'maintenance'));
				PagePrototypes::updatePagesOfPrototype($context['page_id'], $context['types']);
			}
			// If this page is not a prototype but possibly was one, remove it from the references table
			else {
				Symphony::Database()->delete('tbl_pages_prototypes', sprintf("
						`prototype_id` = '%s'
					",
					Symphony::Database()->cleanValue($context['page_id'])
				));
			}
		}

		public function pagePreDelete($context) {
			$page_ids = $context['page_ids'];
			$redirect = $context['redirect'];
			$page = Administration::instance()->Page;
			$page_context = $page->getContext();

			if (is_array($page_ids) && !empty($page_ids)) {
				// Remove pages that are used as prototypes from list
				foreach ($page_ids as $index => $page_id) {
					if (PagePrototypes::hasPrototypePages($page_id)) {
						unset($page_ids[$index]);
						// Add parameter to redirect for page alert on list view
						if (empty($page_context)) {
							$context['redirect'] = "{$redirect}?alert=used-as-prototype";
						}
						// Set redirect on editing pages when page is used as a prototype
						elseif($page_context[0] = 'edit' && isset($page_context[1])){
							$parent_link_suffix = NULL;
							if(isset($_REQUEST['parent']) && is_numeric($_REQUEST['parent'])){
								$parent_link_suffix = '?parent=' . $_REQUEST['parent'];
							}
							$context['redirect'] = "{$redirect}edit/{$page_context[1]}/used-as-prototype/{$parent_link_suffix}";
						}
					}
				}

				// Update $context
				$context['page_ids'] = $page_ids;
			}
		}

		public function pagePostDelete($context) {
			$page_ids = $context['page_ids'];
			if (is_array($page_ids) && !empty($page_ids)) {
				$page_ids = array_map(array('MySQL', 'cleanValue'), $page_ids);
				$page_ids = implode("', '", $page_ids);
				Symphony::Database()->delete('tbl_pages_prototypes', sprintf("
						`page_id` IN ('%s') OR `prototype_id` IN ('%s')
					",
					$page_ids, $page_ids
				));
			}
		}

		public function appendPageAlert($context) {
			$page = Administration::instance()->Page;
			$page_context = $page->getContext();

			if ($page instanceof ContentBlueprintsPages) {
				// Page alert for edit view
				if(isset($page_context[2]) && $page_context[2] == 'used-as-prototype'){
					$page->pageAlert(
						__('Page could not be deleted because it is used as a prototype for other pages.'),
						Alert::ERROR
					);
				}

				// Page alert for list view
				if($_REQUEST['alert'] == 'used-as-prototype'){
					$page->pageAlert(
						__('On or more pages could not be deleted because they are used as a prototype for other pages.'),
						Alert::ERROR
					);
				}
			}
		}

		public function frontendPageResolved($context) {

			if (!(integer)$page_id = $context['page_data']['id']) {
				return;
			}

			// Don't show prototype pages to normal visitors
			if(!Frontend::instance()->isLoggedIn() && PagePrototypes::isPagePrototype($page_id)){
				$forbidden = PageManager::fetchPageByType('403');
			
				// User has no access to this page, so look for a custom 403 page
				if(!empty($forbidden)) {
					$forbidden['type'] = FrontendPage::fetchPageTypes($forbidden['id']);
					$forbidden['filelocation'] = FrontendPage::resolvePageFileLocation($forbidden['path'], $forbidden['handle']);

					$context['page_data'] = $forbidden;
					return;
				}
				// No custom 403, just throw default 403
				else {
					GenericExceptionHandler::$enabled = true;
					throw new SymphonyErrorPage(
						__('The page you have requested has restricted access permissions.'),
						__('Forbidden'),
						'generic',
						array('header' => 'HTTP/1.0 403 Forbidden')
					);
				}
			}

			// Override context if the page is connected to a prototype.
			// This is not really necesary because when a prototype gets changed in the backend, the referenced pages get changed as well.
			$prototype = PagePrototypes::fetchPrototypeOfPage($page_id);
			if (!empty($prototype)) {
				$context['page_data']['params'] = $prototype['params'];
				$context['page_data']['data_sources'] = $prototype['data_sources'];
				$context['page_data']['events'] = $prototype['events'];
				$context['page_data']['type'] = $prototype['type'];
				$context['page_data']['filelocation'] = PageManager::resolvePageFileLocation($prototype['path'], $prototype['handle']);
			}
		}

	}
