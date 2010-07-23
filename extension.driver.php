<?php
/*---------------------------------------------------------------------------*/

	class extension_page_templates extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function about() {
			return array(
				'name'			=> 'Page Templates',
				'version'		=> '0.2',
				'release-date'	=> '2010-07-14',
				'author'		=> array(
					'name'			=> 'Jonas Coch',
					'website'		=> 'http://klaftertief.de/',
					'email'			=> 'jonas@klaftertief.de'
				),
				'description'	=> 'Create pages from predefined templates.'
			);
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
					'callback' => 'initaliseAdminPageHead'
				),
				array(
					'page' => '/administration/',
					'delegate' => 'AdminPagePostGenerate',
					'callback' => 'adminPagePostGenerate'
				)
			);
		}
		
		public function frontendPageResolved(&$context) {
			
			if (!(integer)$page_id = $context['page_data']['id']) {
				return;
			}
			
			$template_id = Symphony::Database()->fetchVar('page_template_id', 0, "
				SELECT
					p.page_template_id
				FROM
					`tbl_pages` AS p
				WHERE
					p.id = '{$page_id}' AND p.page_template_referenced = 'yes'
				LIMIT 1
			");
			
			if ($template_id) {
				$template = Symphony::Database()->fetchRow(0, "
					SELECT
						t.*
					FROM
						`tbl_page_templates` AS t
					WHERE
						t.id = '{$template_id}'
					LIMIT 1
				");
				
				$type = Symphony::Database()->fetchCol('type', "
					SELECT
						t.type
					FROM
						`tbl_page_templates_types` AS t
					WHERE
						t.page_template_id = '{$template_id}'
					ORDER BY
						t.type ASC
				");
				
				$file_abs = PAGES . '/_page_template_' . $template['handle'] . '.xsl';
				$filelocation = is_file($file_abs) ? $file_abs : $context['page_data']['filelocation'];
				
				$context['page_data']['params'] = $template['params'];
				$context['page_data']['data_sources'] = $template['data_sources'];
				$context['page_data']['events'] = $template['events'];
				$context['page_data']['type'] = $type;
				$context['page_data']['filelocation'] = $filelocation;
				
			}
		}
		
		public function fetchNavigation() {
			return array(
				array(
					'location' => __('Blueprints'),
					'name' => __('Page Templates'),
					'link' => '/manage/'
				)
			);
		}

		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_page_templates`");
			Symphony::Database()->query("DROP TABLE `tbl_page_templates_types`");
			Symphony::Database()->query(
				"ALTER TABLE `tbl_pages`
					DROP `page_template_id`,
					DROP `page_template_referenced`"
			);
		}
		
		public function install(){
			
			$templates = Symphony::Database()->query(
				"CREATE TABLE `tbl_page_templates` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`parent` int(11),
					`title` varchar(255) NOT NULL default '',
					`handle` varchar(255),
					`path` varchar(255),
					`params` varchar(255),
					`data_sources` text,
					`events` text,
					`sortorder` int(11) NOT NULL default '0',
					PRIMARY KEY (`id`),
					KEY `parent` (`parent`)
				) TYPE=MyISAM;"
			);

			$templates_types = Symphony::Database()->query(
				"CREATE TABLE `tbl_page_templates_types` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`page_template_id` int(11) unsigned NOT NULL,
					`type` varchar(50) NOT NULL,
					PRIMARY KEY (`id`),
					KEY `page_template_id` (`page_template_id`)
					KEY `type` (`type`)
				) ENGINE=MyISAM"
			);
			
			$pages_templates = Symphony::Database()->query(
				"ALTER TABLE `tbl_pages`
					ADD `page_template_id` int(11) default NULL,
					ADD `page_template_referenced` enum('yes','no') NULL default 'no'"
			);
		
			if($templates && $templates_types && $pages_templates) {
				return true;
			}
			else {
				return false;
			} 
		
		}

		public function initaliseAdminPageHead($context) {
			$page = $context['parent']->Page;

			// Include JS?
			if (($page instanceof ContentBlueprintsPages) && !($page instanceof contentExtensionPage_templatesManage) && ($page->_context[0] == 'edit' || $page->_context[0] == 'template')) {
				$page->addScriptToHead(URL . '/extensions/page_templates/assets/page-edit.js', 565656);
			}
		}

		public function adminPagePostGenerate($context) {
			$page = $context['parent']->Page;
			if (($page instanceof ContentBlueprintsPages) && !($page instanceof contentExtensionPage_templatesManage) && ($page->_context[0] == 'edit' || $page->_context[0] == 'template')) {
				$action = $page->_context[0];

				$dom = new DOMDocument;
				$dom->preserveWhiteSpace = true; 
				$dom->loadHTML($context['output']);
				$form = $dom->getElementsByTagName('form')->item(0);

				if ($action == 'edit') {
					$page_id = $page->_context[1];
					$templates = Symphony::Database()->fetch("
						SELECT
							t.id, t.title
						FROM
							`tbl_page_templates` AS t
						ORDER BY
							t.sortorder ASC
					");
					$selected = Symphony::Database()->fetch("
						SELECT
							p.page_template_id, p.page_template_referenced
						FROM
							`tbl_pages` AS p
						WHERE
							p.id = '{$page_id}'
						LIMIT 1
					");
					$selected = $selected[0];

					$fieldset = $form->getElementsByTagName('fieldset')->item(0);

					$settings = $dom->createElement('fieldset');
					$settings->setAttribute('class', 'settings');
					$legend = $dom->createElement('legend', __('Template Settings'));
					$settings->appendChild($legend);
					
					$group = $dom->createElement('div');
					$group->setAttribute('class', 'group');

					$label = $dom->createElement('label', __('Page Template'));
					$select = $dom->createElement('select');
					$select->setAttribute('id', 'page_templates-page_template_id');
					$select->setAttribute('name', 'fields[page_template_id]');
					$option = $dom->createElement('option', __('None'));
					$select->appendChild($option);
					$option->setAttribute('value', '0');
					foreach ($templates as $template) {
						$option = $dom->createElement('option', $template['title']);
						$option->setAttribute('value', $template['id']);
						if ($selected['page_template_id'] == $template['id']) {
							$option->setAttribute('selected', 'selected');
						}
						$select->appendChild($option);
					}
					$label->appendChild($select);
					$group->appendChild($label);

					$label = $dom->createElement('label');
					$hidden = $dom->createElement('input');
					$hidden->setAttribute('type', 'hidden');
					$hidden->setAttribute('value', 'no');
					$hidden->setAttribute('name', 'fields[page_template_referenced]');
					$group->appendChild($hidden);
					$checkbox = $dom->createElement('input');
					$checkbox->setAttribute('id', 'page_templates-page_template_referenced');
					$checkbox->setAttribute('type', 'checkbox');
					$checkbox->setAttribute('value', 'yes');
					$checkbox->setAttribute('name', 'fields[page_template_referenced]');
					if ($selected['page_template_id'] && $selected['page_template_referenced'] == 'yes') {
						$checkbox->setAttribute('checked', 'checked');
					}
					
					$text = $dom->createTextNode(__('Reference Template'));
					$label->appendChild($checkbox);
					$label->appendChild($text);
					$group->appendChild($label);

					$settings->appendChild($group);

					$fieldset->parentNode->insertBefore($settings, $fieldset);
				}

				$context['output'] = $dom->saveHTML();
			}
		}
		
	}
/*---------------------------------------------------------------------------*/
?>
