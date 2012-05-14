<?php

	require_once(TOOLKIT . '/class.pagemanager.php');

	class PagePrototypes {

		// Get all prototype pages
		public static function fetchPrototypes() {
			$prototypes = PageManager::fetchPageByType('prototype');
			// Make sure to return an array of pages
			if (!is_array(current($prototypes))) {
				$prototypes = array($prototypes);
			}
			return $prototypes;
		}

		// Get single prototype page
		public static function fetchPrototypeByID($prototype_id) {
			return PageManager::fetchPageByID($prototype_id);
		}

		// Get prototype page of a page
		public static function fetchPrototypeOfPage($page_id) {
			$prototype_id =  Symphony::Database()->fetchVar('prototype_id', 0, sprintf("
					SELECT `prototype_id`
					FROM `tbl_pages_prototypes`
					WHERE `page_id` = '%s'
					LIMIT 1
				",
				Symphony::Database()->cleanValue($page_id)
			));
			return !empty($prototype_id) ? PageManager::fetchPageByID($prototype_id) : null;
		}

		// Get pages that are references of a prototype
		public static function fetchPagesOfPrototype($prototype_id) {
			$page_ids =  Symphony::Database()->fetchCol('page_id', sprintf("
					SELECT `page_id`
					FROM `tbl_pages_prototypes`
					WHERE `prototype_id` = '%s'
				",
				Symphony::Database()->cleanValue($prototype_id)
			));
			return !empty($page_ids) ? PageManager::fetchPageByID($page_ids) : null;
		}

		// Update all pages that are references of a prototype with its settings
		public static function updatePagesOfPrototype($prototype_id, $types) {
			if (empty($prototype_id)) return;

			$prototype = PageManager::fetchPageByID($prototype_id);
			$pages = self::fetchPagesOfPrototype($prototype_id);
			$fields = array();

			if (is_array($pages) && !empty($pages)) {
				foreach ($pages as $page) {
					$fields['params'] = $prototype['params'];
					$fields['events'] = $prototype['events'];
					$fields['data_sources'] = $prototype['data_sources'];
					PageManager::edit($page['id'], $fields, true);
					PageManager::addPageTypesToPage($page['id'], array_values(array_diff($types, array('prototype'))));
				}
			}
		}

		// Update the references between pages and prototypes
		public static function updatePagesPrototypesReferences($page_id, $prototype_id) {
			// Delete existing reference of page
			Symphony::Database()->delete('tbl_pages_prototypes', sprintf("
					`page_id` = '%s'
				",
				Symphony::Database()->cleanValue($page_id)
			));
			// Set new reference if a prototype gets passed
			if (!empty($prototype_id)) {
				Symphony::Database()->insert(array(
					'page_id' => Symphony::Database()->cleanValue($page_id),
					'prototype_id' => Symphony::Database()->cleanValue($prototype_id)
				), 'tbl_pages_prototypes');
			}
		}

		// Check if a page is a prototype
		public static function isPagePrototype($page_id) {
			$page_types = PageManager::fetchPageTypes($page_id);
			return in_array('prototype', $page_types);
		}

		// Ceck if a prototype is used by pages
		public static function hasPrototypePages($page_id) {
			return (boolean)Symphony::Database()->fetchRow(0, sprintf("
					SELECT `id`
					FROM `tbl_pages_prototypes`
					WHERE `prototype_id` = '%s'
					LIMIT 1
				",
				Symphony::Database()->cleanValue($page_id)
			));
		}

		/**
		 * Returns Pages that match the given `$types`. If no `$types` is provided
		 * the function returns the result of `PageManager::fetch`.
		 *
		 * @param array $types
		 *  An array of some of the available Page Types.
		 * @param boolean $negate (optional)
		 *  If true, the logic gets inversed to return Pages that don't match the given `$types`.
		 * @return array|null
		 *  An associative array of Page information with the key being the column
		 *  name from `tbl_pages` and the value being the data. If multiple Pages
		 *  are found, an array of Pages will be returned. If no Pages are found
		 *  null is returned.
		 */
		public static function fetchPageByTypes(array $types = array(), $andOperation = false, $negate = false) {
			if(empty($types)) return PageManager::fetch();

			$types = array_map(array('MySQL', 'cleanValue'), $types);

			// Build SQL parts depending on query parameters. There are four possibilities.
			// 1. Without negation and with OR filter
			if (!$andOperation && !$negate) {
				$join = "LEFT JOIN `tbl_pages_types` AS `pt` ON (p.id = pt.page_id)";
				$where = sprintf("
						AND `pt`.type IN ('%s')
					",
					implode("', '", $types)
				);
			}
			// 2. Without negation and with AND filter
			elseif ($andOperation && !$negate) {
				$join = "";
				$where = "";
				foreach($types as $index => $type) {
					$join .= " LEFT JOIN `tbl_pages_types` AS `pt_{$index}` ON (p.id = pt_{$index}.page_id)";
					$where .= " AND pt_{$index}.type = '" . $type . "'";
				}
			}
			// 3. With negation and with OR filter
			elseif (!$andOperation && $negate) {
				$join = sprintf("
						LEFT JOIN `tbl_pages_types` AS `pt` ON (p.id = pt.page_id AND pt.type IN ('%s'))
					",
					implode("', '", $types)
				);
				$where = "AND `pt`.type IS NULL";
			}
			// 4. With negation and with AND filter
			elseif ($andOperation && $negate) {
				$join = "";
				$where = "AND (";
				foreach($types as $index => $type) {
					$join .= sprintf("
							LEFT JOIN `tbl_pages_types` AS `pt_%s` ON (p.id = pt_%s.page_id AND pt_%s.type IN ('%s'))
						",
						$index, $index, $index,
						$type
					);
					$where .= ($index === 0 ? "" : " OR ") . "pt_{$index}.type IS NULL";
				}
				$where .= ")";
			}

			$pages = Symphony::Database()->fetch(sprintf("
					SELECT
						`p`.*
					FROM
						`tbl_pages` AS `p`
					%s
					WHERE 1
						%s
				",
				$join,
				$where
			));

			return count($pages) == 1 ? array_pop($pages) : $pages;
		}

		/**
		 * Test whether the input string has a negation filter modifier, by searching
		 * for the prefix of `not:` in the given `$string`.
		 *
		 * @param string $string
		 *  The string to test.
		 * @return boolean
		 *  True if the string is prefixed with `not:`, false otherwise.
		 */
		public static function isFilterNegation($string){
			return (preg_match('/^not:/i', $string)) ? true : false;
		}

		public static function isAndOperation($string){
			return (strpos($string, '+') === false) ? false : true;
		}

	}