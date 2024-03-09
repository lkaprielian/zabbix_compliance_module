<?php declare(strict_types = 1);

/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

namespace Modules\Compliance\Actions;

use CController;
use CSettingsHelper;
use API;
use CArrayHelper;
use CUrl;
use CPagerHelper;

abstract class CControllerBGHost extends CController {

	// Filter idx prefix.
	const FILTER_IDX = 'web.monitoring.hosts';

	// Filter fields default values.
	const FILTER_FIELDS_DEFAULT = [
		'name' => '',
		'groupids' => [],
		'ip' => '',
		'dns' => '',
		'port' => '',
		'status' => -1,
		'evaltype' => TAG_EVAL_TYPE_AND_OR,
		'tags' => [],
		'severities' => [],
		'show_suppressed' => ZBX_PROBLEM_SUPPRESSED_FALSE,
		'maintenance_status' => HOST_MAINTENANCE_STATUS_ON,
		'page' => null,
		'sort' => 'name',
		'sortorder' => ZBX_SORT_UP
	];
	/**
	 * Get host list results count for passed filter.
	 *
	 * @param array  $filter                        Filter options.
	 * @param string $filter['name']                Filter hosts by name.
	 * @param array  $filter['groupids']            Filter hosts by host groups.
	 * @param string $filter['ip']                  Filter hosts by IP.
	 * @param string $filter['dns']	                Filter hosts by DNS.
	 * @param string $filter['port']                Filter hosts by port.
	 * @param string $filter['status']              Filter hosts by status.
	 * @param string $filter['evaltype']            Filter hosts by tags.
	 * @param string $filter['tags']                Filter hosts by tag names and values.
	 * @param string $filter['severities']          Filter problems on hosts by severities.
	 * @param string $filter['show_suppressed']     Filter suppressed problems.
	 * @param int    $filter['maintenance_status']  Filter hosts by maintenance.
	 *
	 * @return int
	 */
	protected function getCount(array $filter): int {
		$groupids = $filter['groupids'] ? getSubGroups($filter['groupids']) : null;

		// $subgroup = getSubGroups($filter['groupids']);
		// if (empty($subgroup)){
		// 	$groupids = $filter['groupids'] ? $filter['groupids'] : null;
		// } else {
		// 	$groupids = $filter['groupids'] ? getSubGroups($filter['groupids']) : null;
		// }

		return (int) API::Host()->get([
			'countOutput' => true,
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'],
			'inheritedTags' => true,
			// 'groupids' => $filter['groupids']  ? $filter['groupids']  : null,
			'groupids' => $groupids,
			'severities' => $filter['severities'] ? $filter['severities'] : null,
			'withProblemsSuppressed' => $filter['severities']
				? (($filter['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE) ? null : false)
				: null,
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name'],
				'ip' => ($filter['ip'] === '') ? null : $filter['ip'],
				'dns' => ($filter['dns'] === '') ? null : $filter['dns']
			],
			'filter' => [
				'status' => ($filter['status'] == -1) ? null : $filter['status'],
				'port' => ($filter['port'] === '') ? null : $filter['port'],
				'maintenance_status' => ($filter['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON)
					? null
					: HOST_MAINTENANCE_STATUS_OFF
			],
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
		]);
	}

	/**
	 * Prepares the host list based on the given filter and sorting options.
	 *
	 * @param array  $filter                        Filter options.
	 * @param string $filter['name']                Filter hosts by name.
	 * @param array  $filter['groupids']            Filter hosts by host groups.
	 * @param string $filter['ip']                  Filter hosts by IP.
	 * @param string $filter['dns']	                Filter hosts by DNS.
	 * @param string $filter['port']                Filter hosts by port.
	 * @param string $filter['status']              Filter hosts by status.
	 * @param string $filter['evaltype']            Filter hosts by tags.
	 * @param string $filter['tags']                Filter hosts by tag names and values.
	 * @param string $filter['severities']          Filter problems on hosts by severities.
	 * @param string $filter['show_suppressed']     Filter suppressed problems.
	 * @param int    $filter['maintenance_status']  Filter hosts by maintenance.
	 * @param int    $filter['page']                Page number.
	 * @param string $filter['sort']                Sorting field.
	 * @param string $filter['sortorder']           Sorting order.
	 *
	 * @return array
	 */
	protected function getData(array $filter): array {
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$groupids = $filter['groupids'] ? getSubGroups($filter['groupids']): null;

		// print_r($filter['groupids']); 

		// $subgroup = getSubGroups($filter['groupids']);

		// if ($subgroup){
		// 	print("not empty");
		// 	$groupids = $filter['groupids'] ? $filter['groupids'] : null;
		// 	// print_r($groupids);
		// } else {
		// 	print("subgroup empty");
		// 	$groupids = $filter['groupids'] ? $subgroup : null;
		// 	// print_r($groupids);
		// }


		# get items by tag 
		$items = API::Item()->get([
			'output' => ['itemid', 'tags'],
			'selectTags' => ['tag', 'value'],
			'tags' => [['tag' => 'application', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'compliance']]
		]);

			
		$items_ids = [];
		$items_tags = [];
		$hosts = [];

		# collect items ids in one array to do one call to get hostsby items (speed case)
		foreach ($items as $item) {
			$items_ids[] = $item['itemid'];
			// $items_tags[] = $item['tags'];
		}

		// print_r($items_tags);

		# get hosts by itemids
		// foreach ($items as $item) {
		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status'],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'],
			'inheritedTags' => true,
			// 'groupids' => $filter['groupids']  ? $filter['groupids']  : null,
			// 'groupids' => $filter['groupids'],
			'groupids' => $groupids,
			'severities' => $filter['severities'] ? $filter['severities'] : null,
			'withProblemsSuppressed' => $filter['severities']
				? (($filter['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE) ? null : false)
				: null,
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name'],
				'ip' => ($filter['ip'] === '') ? null : $filter['ip'],
				'dns' => ($filter['dns'] === '') ? null : $filter['dns']
			],
			'filter' => [
				'status' => ($filter['status'] == -1) ? null : $filter['status'],
				'port' => ($filter['port'] === '') ? null : $filter['port'],
				'maintenance_status' => ($filter['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON)
					? null
					: HOST_MAINTENANCE_STATUS_OFF
			],
			'selectHostGroups' => ['groupid', 'name'],
			'sortfield' => 'name',
			'limit' => $limit,
			'preservekeys' => true,
			'itemids' => $items_ids,
			// 'selectTags' => ['tag', 'value'],
			// 'selectInheritedTags' => ['tag', 'value']
		]);


		$host_groups = []; // Information about all groups to build a tree
		$fake_group_id = 100000;

		// print_r($hosts);
		// foreach ($hosts as &$host){
		// 	foreach ($host['hostgroups'] as $group) {
		// 		$groupid = $group['groupid'];
		// 		$groupname_full = $group['name'];
		// 		if (str_contains($groupname_full, '/')) {
		// 			print($groupname_full);
		// 		}
		// 	// unset($tthosts[0]);
		// 	// print_r($tthosts );
		// 	}
		// }
		//not here
		foreach ($hosts as &$host) {
			// unset($host['hostgroups'][0]);
			foreach ($host['hostgroups'] as $group) {
				$groupid = $group['groupid'];
				$groupname_full = $group['name'];
				// if (str_contains($groupname_full, '/')) {
				// print_r($groupname_full);
				if (!array_key_exists($groupname_full, $host_groups)) {
					$host_groups[$groupname_full] = [
						'groupid' => $groupid,
						'hosts' => [
							$host['hostid']
						],
						'children' => [],
						'parent_group_name' => '',
						'num_of_hosts' => 1,
						'problem_count' => [],
						'is_collapsed' => true
					];
					for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
						$host_groups[$groupname_full]['problem_count'][$severity] = 0;
					}
				} else {
					$host_groups[$groupname_full]['hosts'][] = $host['hostid'];
					$host_groups[$groupname_full]['num_of_hosts']++;
				}

				$grp_arr = explode('/', $groupname_full);
				if (count($grp_arr) > 1) {
					// Find all parent groups and create respective array elements in $host_groups
					$this->add_parent($host_groups, $fake_group_id, $groupname_full, $filter);
				}
			}
		}
		unset($host);

		$filter['sortorder'] == 'ASC' ? ksort($host_groups) : krsort($host_groups);

		$hosts_sorted_by_group = [];
		foreach ($host_groups as $host_group_name => $host_group) {
			$this->add_hosts_of_child_group($hosts_sorted_by_group, $hosts, $host_groups, $host_group_name, $filter);
		}

		$view_curl = (new CUrl())->setArgument('action', 'compliance.view');

		// Split result array and create paging.
		$paging = CPagerHelper::paginate($filter['page'], $hosts_sorted_by_group, $filter['sortorder'], $view_curl);

		// print_r($hosts_sorted_by_group);
		// Get additional data to limited host amount.
		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status', 'maintenance_status', 'maintenanceid', 'maintenance_type'],
			// 'selectInterfaces' => ['ip', 'dns', 'port', 'main', 'type', 'useip', 'available', 'error', 'details'],
			// 'selectGraphs' => API_OUTPUT_COUNT,
			// 'selectHttpTests' => API_OUTPUT_COUNT,
			'selectTags' => ['tag', 'value'],
			'selectInheritedTags' => ['tag', 'value'],
			'hostids' => array_keys($hosts_sorted_by_group),
			'preservekeys' => true
		]);

		// Get only those groups that need to be shown
		$host_groups_to_show = [];

		#not here
		// print_r($hosts_sorted_by_group);
		foreach ($hosts_sorted_by_group as $host) {
			// print_r($hosts_sorted_by_group);
			// print_r($host['hostgroups']);
			// foreach ($host['hostgroups'] as $group) {
			// 	if (count($group > 1))
			// 	{
			// 		print_r($group);
			// 	}
			// }
				// if (!array_key_exists($group['name'], $host_groups_to_show)) {	
			// $subgroup =  $host['hostgroups'];
			// $subgroup_length = count($subgroup);
			// // print($subgroup);
			// // print($subgroup_length);
			// $all = [];
			// // Compare each element with the element that follows it
			// for ($i = 0; $i < $subgroup_length - 1; $i++) {
			// 	$currentElement = $subgroup[$i];
			// 	print_r($subgroup[$i]);
			// 	if (!(str_contains($currentElement['name'],'/'))) {
			// 		// print($currentElement['name']);
			// 		$all1[] = $currentElement['name'];
			// 		// for ($x = 0; $x < $subgroup_length - 1; $x++) {
			// 		// 	$nextElement = $subgroup[$x];
			// 		// 	if (str_contains($nextElement['name'],'/')){
			// 		// 		if (str_contains($currentElement['name'], $nextElement['name'])) {
			// 		// 			// $all = $nextElement['name'];
			// 		// 			print($currentElement['name']);
			// 		// 			$all[] = $currentElement['name'];
			// 		// 		}
			// 		// 		else{
			// 		// 			print('no');
			// 		// 		}
							
			// 		// 	}
			// 		// }
			// 	}
			// 	$all[] = $all + $all1;
			// 	// print_r($all);

			// 	// if ($currentElement['name'] === $nextElement['name']) {
			// 	// 	echo "Element at index $i and element at index " . ($i + 1) . " are the same: $currentElement\n";
			// 	// } else {
			// 	// 	echo "Element at index $i and element at index " . ($i + 1) . " are different.\n";
			// 	// }
			// }
			// $all_groups = [];
			// print_r($host['hostgroups']);
			// $test_value = "";
			foreach ($host['hostgroups'] as $group) {
				// $all_groups = array_merge($all_groups, $group);
				// print($all_groups['name']);
				// print_r($all_groups);
				// $test_value = $group['name'];
				if (!array_key_exists($group['name'], $host_groups_to_show)) {
					// if (str_contains($test_value, '/'))
					// if (str_contains($group['name'], '/') && $all_groups['name'] == $group['name']) {
					$host_groups_to_show[$group['name']] = $host_groups[$group['name']];
					$host_groups_to_show[$group['name']]['hosts'] = [ $host['hostid'] ];
					// Make sure parent group exists as well
					$grp_arr = explode('/', $group['name']);
					// print_r($grp_arr);
					for ($i = 1, $g_name = $grp_arr[0]; $i < count($grp_arr); $i++) {
						if (!array_key_exists($g_name, $host_groups_to_show)) {
							$host_groups_to_show[$g_name] = $host_groups[$g_name];
							$host_groups_to_show[$g_name]['hosts'] = [];
						}
						$g_name = $g_name.'/'.$grp_arr[$i];
					}
					// }		

				} else {
					$host_groups_to_show[$group['name']]['hosts'][] = $host['hostid'];
				}
			}

				// if (str_contains($group['name'], '/')) {
		}


		// Remove groups that are not to be shown from 'children' groups list
		// print_r($host_groups_to_show);
		$seenHosts = [];
		$groupsToDelete = [];
		
		foreach ($host_groups_to_show as $groupName => $group) {
			// Check if parent_group_name is empty and hosts have duplicates
			if (empty($group['parent_group_name']) && count($group['hosts']) !== count(array_unique($group['hosts']))) {
				$groupsToDelete[] = $groupName;
			}
		}
		
		// Remove groups with empty hosts array
		foreach ($groupsToDelete as $groupName) {
			unset($host_groups_to_show[$groupName]);
		}
				
		print_r($host_groups_to_show);
		unset($group);

		// print_r($host_groups_to_show);
		// Remove groups that are not to be shown from 'children' groups list
		foreach ($host_groups_to_show as $group_name => &$group) {
			$groups_to_delete = [];
			// print_r($group);
			// [
			// 	[
			// 		'groupid' => 27,
			// 		'hosts' => [10614],
			// 		'children' => [],
			// 		'parent_group_name' => '',
			// 		'num_of_hosts' => 1,
			// 		'problem_count' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0, 0 => 0],
			// 		'is_collapsed' => 1,
			// 	],
			// 	[
			// 		'groupid' => 29,
			// 		'hosts' => [10614],
			// 		'children' => [],
			// 		'parent_group_name' => 'mag2',
			// 		'num_of_hosts' => 1,
			// 		'problem_count' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0, 0 => 0],
			// 		'is_collapsed' => 1,
			// 	],
			// 	[
			// 		'groupid' => 100000,
			// 		'hosts' => [10616],
			// 		'children' => ['mag2/ap', 'mag2/switches'],
			// 		'parent_group_name' => '',
			// 		'num_of_hosts' => 3,
			// 		'problem_count' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0, 0 => 0],
			// 		'is_collapsed' => 1,
			// 	],
			// 	[
			// 		'groupid' => 30,
			// 		'hosts' => [10618],
			// 		'children' => [],
			// 		'parent_group_name' => 'mag2',
			// 		'num_of_hosts' => 1,
			// 		'problem_count' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0, 0 => 0],
			// 		'is_collapsed' => 1,
			// 	],
			// 	[
			// 		'groupid' => 28,
			// 		'hosts' => [10618],
			// 		'children' => [],
			// 		'parent_group_name' => '',
			// 		'num_of_hosts' => 1,
			// 		'problem_count' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0, 0 => 0],
			// 		'is_collapsed' => 1,
			// 	],
			// ];
			// $hosts_to_delete = [];


			// print_r($hosts);
			foreach ($group['children'] as $child_group_name) {
				if (!array_key_exists($child_group_name, $host_groups_to_show)) {
					$groups_to_delete[] = $child_group_name;
				}
			}
			// foreach ($group['hosts'] as $host_group_name) {
			// 	if (!array_key_exists($host_group_name, $host_groups_to_show)) {
			// 		$hosts_to_delete[] = $host_group_name;
			// 	}
			// }
			foreach ($groups_to_delete as $group_name) {
				if (($key = array_search($group_name, $group['children'])) !== false) {
				    unset($group['children'][$key]);
				}
			}
			// print_r(count($group['hosts']));
			// foreach ($hosts_to_delete as $group_name) {
			// 	if (($key = array_search($group_name, $group['hosts'])) !== false  && count($group['hosts']) > 1 ) {
			// 	    unset($group['hosts'][$key]);
			// 	}
			// }
		}
		unset($group);

		$filter['sortorder'] == 'ASC' ? ksort($host_groups_to_show) : krsort($host_groups_to_show);

		// Some hosts for shown groups can be on other pages thus not in $hosts_sorted_by_group
		// as we already applied paging. To calculate number of problems we need all hosts belonging to shown groups
		$all_hosts_in_groups_to_show = [];
		foreach ($host_groups_to_show as $group_name => $group) {
			foreach ($host_groups[$group_name]['hosts'] as $host) {
				$all_hosts_in_groups_to_show[] = $host;
			}
		}

		$maintenanceids = [];

		// Select triggers and problems to calculate number of problems for each host.
		$triggers = API::Trigger()->get([
			'output' => [],
			'selectHosts' => ['hostid'],
			'hostids' => $all_hosts_in_groups_to_show,
			'skipDependent' => true,
			'monitored' => true,
			'preservekeys' => true,
		]);

		$problems = API::Problem()->get([
			'output' => ['eventid', 'objectid', 'severity'],
			'objectids' => array_keys($triggers),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'suppressed' => ($filter['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE) ? null : false,
			'tags' => [['tag' => 'application', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'compliance']]

		]);

		// Group all problems per host per severity.
		$host_problems = [];
		foreach ($problems as $problem) {
			foreach ($triggers[$problem['objectid']]['hosts'] as $trigger_host) {
				$host_problems[$trigger_host['hostid']][$problem['severity']][$problem['eventid']] = true;
			}
		}

		// Count problems for each shown group - take into account only hosts belonging to each group (no parents/children)
		foreach ($host_groups_to_show as $group_name => &$group) {
			foreach($host_groups[$group_name]['hosts'] as $hostid) {
				// Count the number of problems (as value) per severity (as key).
				for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
					// Fill empty arrays for hosts without problems.
					if (array_key_exists($hostid, $host_problems)) {
						if (array_key_exists($severity, $host_problems[$hostid])) {
							$group['problem_count'][$severity] += count($host_problems[$hostid][$severity]);

							// Increment problems count in parent groups
							$grp_arr = explode('/', $group_name);
							for ($i = count($grp_arr)-1, $g_name_child = $group_name; $i > 0; $i--) {
								array_pop($grp_arr);
								$g_name_parent = implode('/', $grp_arr);
								$host_groups_to_show[$g_name_parent]['problem_count'][$severity] +=
									count($host_problems[$hostid][$severity]);
								$g_name_child = $g_name_parent;
							}
						}
					}
				}
			}
		}
		unset($group);

		foreach($hosts as &$host) {
			# get hosts items tags by host 
			$host_tags = $host['tags'];
			$items_tag_by_host = [];

			$items_by_hosts = API::Item()->get([
				'output' => ['tags'],
				'selectTags'  => ['tag', 'value'],
				'hostids'  => $host['hostid'],
				// 'tags' => [['tag' => 'application', 'operator' => TAG_OPERATOR_EQUAL, 'value' => 'compliance']]
				
			]);
			// print_r($items_by_hosts);

			foreach ($items_by_hosts as $item_elements) {
				foreach ($item_elements['tags'] as $item_element) {
					$items_tag_by_host[] = $item_element;	
					// print_r($items_tag_by_host);
				}
			}

			foreach ($items_tag_by_host as $item_tag) {
				foreach ($host_tags as $host_tag) {
					// Skip tags with same name and value.
					if ($host_tag['tag'] === $item_tag['tag']
							&& $host_tag['value'] === $item_tag['value']) {
						continue 2;
					}
				}

				$host_tags[] =  $item_tag;
			}

			$host['tags'] = $host_tags;
		}
		unset($host);


		foreach ($hosts as &$host) {
			// Count number of dashboards for each host.
			// $host['dashboards'] = count(getHostDashboards($host['hostid']));

			// CArrayHelper::sort($host['interfaces'], [['field' => 'main', 'order' => ZBX_SORT_DOWN]]);

			if ($host['status'] == HOST_STATUS_MONITORED && $host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenanceids[$host['maintenanceid']] = true;
			}

			// Fill empty arrays for hosts without problems.
			if (!array_key_exists($host['hostid'], $host_problems)) {
				$host_problems[$host['hostid']] = [];
			}

			// Count the number of problems (as value) per severity (as key).
			for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
				$host['problem_count'][$severity] = array_key_exists($severity, $host_problems[$host['hostid']]) 
					? count($host_problems[$host['hostid']][$severity])
					: 0;
			}

			// Merge host tags with template tags, and skip duplicate tags and values.
			if (!$host['inheritedTags']) {
				$tags = $host['tags'];
			}
			elseif (!$host['tags']) {
				$tags = $host['inheritedTags'];
			}
			else {
				$tags = $host['tags'];

				foreach ($host['inheritedTags'] as $template_tag) {
					foreach ($tags as $host_tag) {
						// Skip tags with same name and value.
						if ($host_tag['tag'] === $template_tag['tag']
								&& $host_tag['value'] === $template_tag['value']) {
							continue 2;
						}
					}
					$tags[] = $template_tag;
				}
				
			}

			$host['tags'] = $tags;

		}

		unset($host);

		$maintenances = [];

		if ($maintenanceids) {
			$maintenances = API::Maintenance()->get([
				'output' => ['name', 'description'],
				'maintenanceids' => array_keys($maintenanceids),
				'preservekeys' => true
			]);
		}

		$tags = makeTags($hosts, true, 'hostid', ZBX_TAG_COUNT_DEFAULT, $filter['tags']);

		foreach ($hosts as &$host) {
			$host['tags'] = $tags[$host['hostid']];
		}
		unset($host);

		return [
			'paging' => $paging,
			'hosts' => $hosts,
            'host_groups' => $host_groups_to_show,
			'maintenances' => $maintenances
		];
	}

	// Adds all hosts belonging to $host_group_name to global array $hosts_sorted_by_group
	protected function add_hosts_of_child_group(&$hosts_sorted_by_group, $hosts, $host_groups, $host_group_name, $filter) {
		// First add all the hosts belonging to this group
		$hosts_to_add = [];
		foreach($host_groups[$host_group_name]['hosts'] as $hostid) {
			$hosts_to_add[$hostid] = $hosts[$hostid];
		}
		$hosts_to_add = $this->array_sort($hosts_to_add, 'name', $filter['sortorder']);
		foreach($hosts_to_add as $hostid => $host){
			$hosts_sorted_by_group[$hostid] = $host;
		}
		// Add all hosts of children groups
		if (count($host_groups[$host_group_name]['children']) > 0) {
			foreach($host_groups[$host_group_name]['children'] as $child_group_name){
				$this->add_hosts_of_child_group($hosts_sorted_by_group, $hosts, $host_groups, $child_group_name, $filter);
			}
		}
	}

	protected function array_sort($array, $on, $order='ASC')
	{
		$new_array = array();
		$sortable_array = array();

		if (count($array) > 0) {
			foreach ($array as $k => $v) {
				if (is_array($v)) {
					foreach ($v as $k2 => $v2) {
						if ($k2 == $on) {
							$sortable_array[$k] = $v2;
						}
					}
				} else {
					$sortable_array[$k] = $v;
				}
			}

			switch ($order) {
				case 'ASC':
					asort($sortable_array, SORT_STRING);
					break;
				case 'DESC':
					arsort($sortable_array, SORT_STRING);
					break;
			}

			foreach ($sortable_array as $k => $v) {
				$new_array[$k] = $array[$k];
			}
		}

		return $new_array;
	}

	/**
	 * Adds parent group
	 *
	 * @param array $host_groups      All the groups to be shown in hierarchy
	 * @param int   $fake_group_id    ID for groups that do not exist in Zabbix DB (autoincremented)
	 * @param string $groupname_full  Group name parent group of which needs to be added
	 * @param array  $filter          Filter options.
	 *
	 * @return array $host_groups modified in-place
	 */
	protected function add_parent(&$host_groups, &$fake_group_id, $groupname_full, $filter) {
		// There is a '/' in group name
		$grp_arr = explode('/', $groupname_full);
		unset($grp_arr[count($grp_arr)-1]); // Remove last element
		$parent_group_name = implode('/', $grp_arr);
		// In Zabbix it is possible to have parent name that does not exist
		// e.g.: group '/level0/level1/level2' exists but '/level0/level1' does not
		if (array_key_exists($parent_group_name, $host_groups)) {
			// Parent group exists
			if (!in_array($groupname_full, $host_groups[$parent_group_name]['children'])) {
				$host_groups[$parent_group_name]['children'][] = $groupname_full;
			}
			$host_groups[$parent_group_name]['num_of_hosts']++;
		} else {
			// Parent group does not exist or does not have any hosts to show
			$host_groups[$parent_group_name] = [
				'groupid' => $fake_group_id++,
				'hosts' => [],
				'children' => [$groupname_full],
				'parent_group_name' => '',
				'num_of_hosts' => 1,
				'problem_count' => [],
				'is_collapsed' => true
			];
			for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
				$host_groups[$parent_group_name]['problem_count'][$severity] = 0;
			}
		}
		$host_groups[$groupname_full]['parent_group_name'] = $parent_group_name;
		$parent_group_name_arr = explode('/', $parent_group_name);
		if (count($parent_group_name_arr) > 1) {
			// Parent group also has parent
			$this->add_parent($host_groups, $fake_group_id, $parent_group_name, $filter);
		}
		// Sort group names
		$filter['sortorder'] == 'ASC' ? sort($host_groups[$parent_group_name]['children']) : rsort($host_groups[$parent_group_name]['children']);
	}

	/**
	 * Get additional data for filters. Selected groups for multiselect, etc.
	 *
	 * @param array $filter  Filter fields values array.
	 *
	 * @return array
	 */
	protected function getAdditionalData($filter): array {
		$data = [];

		if ($filter['groupids']) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groupids']
			]);
			$data['groups_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($groups), ['groupid' => 'id']);
		}

		return $data;
	}

	/**
	 * Clean passed filter fields in input from default values required for HTML presentation. Convert field
	 *
	 * @param array $input  Filter fields values.
	 *
	 * @return array
	 */
	protected function cleanInput(array $input): array {
		if (array_key_exists('filter_reset', $input) && $input['filter_reset']) {
			return array_intersect_key(['filter_name' => ''], $input);
		}

		if (array_key_exists('tags', $input) && $input['tags']) {
			$input['tags'] = array_filter($input['tags'], function($tag) {
				return !($tag['tag'] === '' && $tag['value'] === '');
			});
			$input['tags'] = array_values($input['tags']);
		}

		return $input;
	}
}