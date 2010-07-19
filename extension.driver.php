<?php
/*---------------------------------------------------------------------------*/

	class extension_page_templates extends Extension {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function about() {
			return array(
				'name'			=> 'Page Templates',
				'version'		=> '0.1',
				'release-date'	=> '2010-07-14',
				'author'		=> array(
					'name'			=> 'Jonas Coch',
					'website'		=> 'http://klaftertief.de/',
					'email'			=> 'jonas@klaftertief.de'
				),
				'description'	=> 'Create pages from predefined templates.'
			);
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
			$this->_Parent->Database->query("DROP TABLE `tbl_page_templates`");
			$this->_Parent->Database->query("DROP TABLE `tbl_page_templates_types`");
		}
		
		public function install(){
			@mkdir(PAGES . '/templates', Symphony::Configuration()->get('write_mode', 'directory'));

			return $this->_Parent->Database->query(
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

			return $this->_Parent->Database->query(
				"CREATE TABLE `tbl_page_templates_types` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`page_template_id` int(11) unsigned NOT NULL,
					`type` varchar(50) NOT NULL,
					PRIMARY KEY (`id`),
					KEY `page_template_id` (`page_template_id`,`type`)
				) ENGINE=MyISAM"
			);
		
		}
		
	}
/*---------------------------------------------------------------------------*/
?>
