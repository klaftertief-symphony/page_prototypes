<?php
	class extension_page_prototypes extends Extension {

		public function about() {
			return array(
				'name'			=> 'Page Prototypes',
				'version'		=> '0.6',
				'release-date'	=> '2011-02-09',
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
					'page' => '/blueprints/pages/',
					'delegate' => 'AppendPageContent',
					'callback' => 'appendPageContent'
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

			if (($page instanceof ContentBlueprintsPages) && !($page instanceof contentExtensionPage_prototypesManage) && ($page->_context[0] == 'edit' || $page->_context[0] == 'new' || $page->_context[0] == 'template')) {
				$page->addScriptToHead(URL . '/extensions/page_prototypes/assets/page_prototypes.blueprintspages.js', 565656);
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

		public function appendPageContent($context) {
			$page = $context['parent']->Page;
			if ($page->_context[0] == 'new' || $page->_context[0] == 'edit' || $page->_context[0] == 'template') {
				$form = $context['form'];

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

				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings');
				$fieldset->appendChild(new XMLElement('legend', __('Prototype Settings')));

				$group = new XMLElement('div');
				$group->setAttribute('class', 'group');

				$column = new XMLElement('div');
				$label = Widget::Label(__('Page Prototype'));
				$options = array(
					array('', false, __('None'))
				);
				foreach ($prototypes as $prototype) {
					$options[] = array(
						$prototype['id'], $selected['page_prototype_id'] == $prototype['id'], $prototype['title']
					);
				}
				$label->appendChild(Widget::Select(
					'fields[page_prototype_id]', $options, array('id' => 'page_prototypes-page_prototype_id')
				));
				$column->appendChild($label);
				$group->appendChild($column);

				$column = new XMLElement('div');
				$input = Widget::Input('fields[page_prototype_referenced]', 'no', 'hidden');
				$column->appendChild($input);
				$label = Widget::Label();
				$input = Widget::Input('fields[page_prototype_referenced]', 'yes', 'checkbox', ($selected['page_prototype_id'] && $selected['page_prototype_referenced'] == 'yes') ? array('checked' => 'checked') : NULL);
				$input->setAttribute('id', 'page_prototypes-page_prototype_referenced');
				$label->setValue(__('%s Reference Prototype', array($input->generate(false))));
				$column->appendChild($label);
				$group->appendChild($column);

				$fieldset->appendChild($group);
				$form->prependChild($fieldset);
			}

		}

	}
?>
