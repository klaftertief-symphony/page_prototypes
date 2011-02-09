<?php
	
	class contentExtensionPage_prototypesAjaxpageprototypes extends AjaxPage {
		
		var $_context;
		
		function __construct(&$parent){
			$this->_Parent = $parent;
			$this->_status = self::STATUS_OK;
			$this->addHeaderToPage('Content-Type', 'application/json');
		}
		
		public function handleFailedAuthorisation(){
			$this->_status = self::STATUS_UNAUTHORISED;
			$this->_Result = json_encode(array('status' => __('You are not authorised to access this page.')));
		}
		
		public function view(){
			if(!$prototype_id = (integer)$this->_context[0]) {
				$this->_status = self::STATUS_BAD;
				$this->_Result = json_encode(array('status' => __('Missing or wrong parameter.')));
				return;
			}
			
			$existing = Symphony::Database()->fetchRow(0, "
				SELECT
					p.*
				FROM
					`tbl_page_prototypes` AS p
				WHERE
					p.id = '{$prototype_id}'
				LIMIT 1
			");
			
			if(!$existing) {
				$this->_status = self::STATUS_ERROR;
				$this->_Result = json_encode(array('status' => __('The page prototype you requested does not exist.')));
				return;
			}
			
			$fields = $existing;
			
			$types = Symphony::Database()->fetchCol('type', "
				SELECT
					p.type
				FROM
					`tbl_page_prototypes_types` AS p
				WHERE
					p.page_prototype_id = '{$prototype_id}'
				ORDER BY
					p.type ASC
			");
			$fields['type'] = @implode(', ', $types);
			
			$fields['data_sources'] = @explode(',', $fields['data_sources']);
			$fields['events'] = @explode(',', $fields['events']);
			
			$this->_Result = json_encode($fields);
		}
		
		public function generate(){
			switch($this->_status){
				case self::STATUS_OK:
					$status_message = '200 OK';
					break;
				case self::STATUS_BAD:
				case self::STATUS_ERROR:
					$status_message = '400 Bad Request';
					break;
				case self::STATUS_UNAUTHORISED:
					$status_message = '401 Unauthorized';
					break;
			}
			
			$this->addHeaderToPage('HTTP/1.0 ' . $status_message);
			$this->__renderHeaders();
			echo $this->_Result;
			exit;
		}
		
		// copy from class.page.php
		protected function __renderHeaders(){
			if(!is_array($this->_headers) || empty($this->_headers)) return;
			foreach($this->_headers as $value){
				header($value);
			}
		}
		
	}
