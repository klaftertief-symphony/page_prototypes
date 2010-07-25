<?php
	class extension_page_prototypes extends Extension {

		public function about() {
			return array(
				'name'			=> 'Page Prototypes',
				'version'		=> '0.5',
				'release-date'	=> '2010-07-25',
				'author'		=> array(
					'name'			=> 'Jonas Coch',
					'website'		=> 'http://klaftertief.de/',
					'email'			=> 'jonas@klaftertief.de'
				),
				'description'	=> 'Create pages from predefined prototypes.'
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

		public function install(){

			$prototypes = Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_page_prototypes` (
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

			$prototypes_types = Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_page_prototypes_types` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`page_prototype_id` int(11) unsigned NOT NULL,
					`type` varchar(50) NOT NULL,
					PRIMARY KEY (`id`),
					KEY `page_prototype_id` (`page_prototype_id`),
					KEY `type` (`type`)
				) ENGINE=MyISAM"
			);

			$pages_prototypes = Symphony::Database()->query(
				"ALTER TABLE `tbl_pages`
					ADD `page_prototype_id` int(11) default NULL,
					ADD `page_prototype_referenced` enum('yes','no') NULL default 'no'"
			);

			if($prototypes && $prototypes_types && $pages_prototypes) {
				return true;
			}
			else {
				return false;
			}

		}

		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_page_prototypes`");
			Symphony::Database()->query("DROP TABLE `tbl_page_prototypes_types`");
			Symphony::Database()->query(
				"ALTER TABLE `tbl_pages`
					DROP `page_prototype_id`,
					DROP `page_prototype_referenced`"
			);
		}

		public function fetchNavigation() {
			return array(
				array(
					'location' => __('Blueprints'),
					'name' => __('Page Prototypes'),
					'link' => '/manage/'
				)
			);
		}

		public function initaliseAdminPageHead($context) {
			$page = $context['parent']->Page;

			if (($page instanceof ContentBlueprintsPages) && !($page instanceof contentExtensionPage_prototypesManage) && ($page->_context[0] == 'edit' || $page->_context[0] == 'template')) {
				$page->addScriptToHead(URL . '/extensions/page_prototypes/assets/page-edit.js', 565656);
			}
		}

		public function frontendPageResolved(&$context) {

			if (!(integer)$page_id = $context['page_data']['id']) {
				return;
			}

			$prototype_id = Symphony::Database()->fetchVar('page_prototype_id', 0, "
				SELECT
					p.page_prototype_id
				FROM
					`tbl_pages` AS p
				WHERE
					p.id = '{$page_id}' AND p.page_prototype_referenced = 'yes'
				LIMIT 1
			");

			if ($prototype_id) {
				$prototype = Symphony::Database()->fetchRow(0, "
					SELECT
						p.*
					FROM
						`tbl_page_prototypes` AS p
					WHERE
						p.id = '{$prototype_id}'
					LIMIT 1
				");

				$type = Symphony::Database()->fetchCol('type', "
					SELECT
						t.type
					FROM
						`tbl_page_prototypes_types` AS t
					WHERE
						t.page_prototype_id = '{$prototype_id}'
					ORDER BY
						t.type ASC
				");

				$file_abs = PAGES . '/_page_prototype_' . $prototype['handle'] . '.xsl';
				$filelocation = is_file($file_abs) ? $file_abs : $context['page_data']['filelocation'];

				$context['page_data']['params'] = $prototype['params'];
				$context['page_data']['data_sources'] = $prototype['data_sources'];
				$context['page_data']['events'] = $prototype['events'];
				$context['page_data']['type'] = $type;
				$context['page_data']['filelocation'] = $filelocation;

			}
		}

		public function adminPagePostGenerate($context) {
			$page = $context['parent']->Page;
			if (($page instanceof ContentBlueprintsPages) && !($page instanceof contentExtensionPage_prototypesManage) && ($page->_context[0] == 'edit' || $page->_context[0] == 'template')) {
				$action = $page->_context[0];

				$dom = new DOMDocument;
				$dom->preserveWhiteSpace = true;
				$dom->loadHTML($context['output']);
				$form = $dom->getElementsByTagName('form')->item(0);

				if ($action == 'edit') {
					$page_id = $page->_context[1];
					$prototypes = Symphony::Database()->fetch("
						SELECT
							p.id,p.title
						FROM
							`tbl_page_prototypes` AS p
						ORDER BY
							p.sortorder ASC
					");
					$selected = Symphony::Database()->fetch("
						SELECT
							p.page_prototype_id, p.page_prototype_referenced
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
					$legend = $dom->createElement('legend', __('Prototype Settings'));
					$settings->appendChild($legend);

					$group = $dom->createElement('div');
					$group->setAttribute('class', 'group');

					$label = $dom->createElement('label', __('Page Prototype'));
					$select = $dom->createElement('select');
					$select->setAttribute('id', 'page_prototypes-page_prototype_id');
					$select->setAttribute('name', 'fields[page_prototype_id]');
					$option = $dom->createElement('option', __('None'));
					$select->appendChild($option);
					$option->setAttribute('value', '0');
					foreach ($prototypes as $prototype) {
						$option = $dom->createElement('option', $prototype['title']);
						$option->setAttribute('value', $prototype['id']);
						if ($selected['page_prototype_id'] == $prototype['id']) {
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
					$hidden->setAttribute('name', 'fields[page_prototype_referenced]');
					$group->appendChild($hidden);
					$checkbox = $dom->createElement('input');
					$checkbox->setAttribute('id', 'page_prototypes-page_prototype_referenced');
					$checkbox->setAttribute('type', 'checkbox');
					$checkbox->setAttribute('value', 'yes');
					$checkbox->setAttribute('name', 'fields[page_prototype_referenced]');
					if ($selected['page_prototype_id'] && $selected['page_prototype_referenced'] == 'yes') {
						$checkbox->setAttribute('checked', 'checked');
					}

					$text = $dom->createTextNode(__('Reference Prototype'));
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
?>
