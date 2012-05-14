<?php

	require_once(EXTENSIONS . '/page_prototypes/lib/class.pageprototypes.php');

	class contentExtensionPage_prototypesAjaxpageprototypes extends AjaxPage {

		public function handleFailedAuthorisation(){
			$this->_status = self::STATUS_UNAUTHORISED;
			$this->_Result = json_encode(array('status' => __('You are not authorised to access this page.')));
		}

		public function view(){
			if(!$prototype_id = (integer)$_GET['prototype_id']) {
				$this->_status = self::STATUS_BAD;
				$this->_Result = json_encode(array('status' => __('Missing or wrong parameter.')));
				return;
			}

			$fields = PageManager::fetchPageByID($prototype_id);

			// return datasources and events as arrays
			$fields['data_sources'] = explode(',', $fields['data_sources']);
			$fields['events'] = explode(',', $fields['events']);
			// remove "prototype" from the type array and reorder
			$fields['type'] = array_values(array_diff($fields['type'], array('prototype')));

			$this->_Result = json_encode($fields);
		}

		public function generate(){
			header('Content-Type: application/json');
			echo $this->_Result;
			exit;
		}

	}
