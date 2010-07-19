<?php
/*---------------------------------------------------------------------------*/

require_once(TOOLKIT . '/class.entrymanager.php');
require_once(TOOLKIT . '/class.sectionmanager.php');
	
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
				array(
					'location' => __('Blueprints'),
					'name' => __('Page Templates'),
					'link' => '/templates/'
				);
		}

		public function uninstall(){
			$this->_Parent->Database->query("DROP TABLE `tbl_page_templates`");
		}
		
		public function install(){
			@mkdir(PAGES . '/templates', Symphony::Configuration()->get('write_mode', 'file'));
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
		}
		
	}
/*---------------------------------------------------------------------------*/
?>
