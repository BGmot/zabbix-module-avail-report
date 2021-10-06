<?php declare(strict_types = 1);

namespace Modules\BGmotAR\Actions;

use CController;
use CSettingsHelper;
use API;
use CArrayHelper;
use CUrl;
use CPagerHelper;
use CRangeTimeParser;

abstract class CControllerBGAvailReport extends CController {

	// Filter idx prefix.
	const FILTER_IDX = 'web.avail_report.filter';

	// Filter fields default values.
	const FILTER_FIELDS_DEFAULT = [
		'name' => '',
		'mode' => AVAILABILITY_REPORT_BY_TEMPLATE,
		'tpl_groupids' => [],
		'templateids' => [],
		'tpl_triggerids' => [], 
		'hostgroupids' => [],
		'only_with_problems' => 1,
                'page' => null,
		'from' => '',
		'to' => ''
	];

	protected function getCount(array $filter): int {
		$groupids = $filter['hostgroupids'] ? getSubGroups($filter['hostgroupids']) : null;

		return (int) API::Host()->get([
			'countOutput' => true,
			'groupids' => $groupids,
			'monitored_hosts' => true,
			'groupids' => $filter['hostgroupids'],
			'templateids' => $filter['templateids'],
			'triggerids' => $filter['tpl_triggerids'],
			'filter' => [
				'status' => 1
			],
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1
		]);
	}

	protected function getData(array $filter): array {
		$host_group_ids = sizeof($filter['hostgroupids']) > 0 ? $this->getChildGroups($filter['hostgroupids']) : null;

		if (!array_key_exists('action_from_url', $filter) ||
			$filter['action_from_url'] != 'availreport.view.csv') {
			$limit = 5001;
		} else {
			$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		}

		// All CONFIGURED triggers that fall under selected filter
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'value'],
			'selectHosts' => ['name'],
			'expandDescription' => true,
			'monitored' => true,
			'groupids' => $host_group_ids,
			'filter' => [
				'templateid' => sizeof($filter['tpl_triggerids']) > 0 ? $filter['tpl_triggerids'] : null
			],
                        'limit' => $limit
		]);

		// Get timestamps from and to
		if ($filter['from'] != '' && $filter['to'] != '') {
			$range_time_parser = new CRangeTimeParser();
			$range_time_parser->parse($filter['from']);
			$filter['from_ts'] = $range_time_parser->getDateTime(true)->getTimestamp();
			$range_time_parser->parse($filter['to']);
			$filter['to_ts'] = $range_time_parser->getDateTime(false)->getTimestamp();
		} else {
			$filter['from_ts'] = null;
			$filter['to_ts'] = null;
		}

		if ($filter['only_with_problems']) {
			// Find all triggers that went into PROBLEM state
			// at any time in given time frame
			$triggerids_with_problems = [];
			$sql = 'SELECT e.objectid' .
				' FROM events e'.
				' WHERE e.source='.EVENT_SOURCE_TRIGGERS.
					' AND e.object='.EVENT_OBJECT_TRIGGER.
					' AND e.value='.TRIGGER_VALUE_TRUE;
			if ($filter['from_ts']) {
				$sql .= ' AND e.clock>='.zbx_dbstr($filter['from_ts']);
			}
			if ($filter['to_ts']) {
				$sql .= ' AND e.clock<='.zbx_dbstr($filter['to_ts']);
			}
			$dbEvents = DBselect($sql);
			while ($row = DBfetch($dbEvents)) {
				$triggerids_with_problems[] = $row['objectid'];
			}

			// Find all triggers that were in the PROBLEM state
			// at the start of this time frame
			foreach($triggers as $trigger) {
				$sql = 'SELECT e.objectid, e.value'.
						' FROM events e'.
						' WHERE e.objectid='.zbx_dbstr($trigger['triggerid']).
							' AND e.source='.EVENT_SOURCE_TRIGGERS.
							' AND e.object='.EVENT_OBJECT_TRIGGER.
							' AND e.clock<'.zbx_dbstr($filter['from_ts']).
						' ORDER BY e.eventid DESC';
				if ($row = DBfetch(DBselect($sql, 1))) {
					// Add the triggerid to the array if it is not there
					if ($row['value'] == TRIGGER_VALUE_TRUE &&
						!in_array($row['objectid'], $triggerids_with_problems)) {
						$triggerids_with_problems[] = $row['objectid'];
					}
				}
			}
			$triggers_with_problems = [];
			foreach ($triggers as $trigger) {
				if (in_array($trigger['triggerid'], $triggerids_with_problems)) {
					$triggers_with_problems[] = $trigger;
				}
			}
                        // Reset all previously selected triggers to only ones with problems
			$triggers = $triggers_with_problems;
		}

		// Now just prepare needed data.
		CArrayHelper::sort($triggers, ['host_name', 'description'], 'ASC');

		$view_curl = (new CUrl())->setArgument('action', 'availreport.view');

		if (!array_key_exists('action_from_url', $filter) ||
			$filter['action_from_url'] != 'availreport.view.csv') {
			// Not exporting data to CSV, just showing the data
			// Split result array and create paging. Only if not generating CSV.
			$paging = CPagerHelper::paginate($filter['page'], $triggers, 'ASC', $view_curl);
		}

		foreach ($triggers as &$trigger) {
			$trigger['availability'] = calculateAvailability($trigger['triggerid'], $filter['from_ts'], $filter['to_ts']);
		}

		// Remove triggers that are auto-resolved due to event correlation
		$triggers_idx_to_remove = [];
		if ($filter['only_with_problems']) {
			for ($i = 0; $i < sizeof($triggers); $i++) {
				if ($trigger['availability']['true'] < 0.00005) {
					$triggers_idx_to_remove[] = $i;
				}
			}
			foreach($triggers_idx_to_remove as $index) {
				unset($triggers[$index]);
			}
		}

		$hosts = []; // Array of hosts {hostname => hostid}
		foreach ($triggers as &$trigger) {
			$trigger['host_name'] = $trigger['hosts'][0]['name'];
			$hosts[$trigger['hosts'][0]['hostid']] = $trigger['hosts'][0]['name'];
		}
		unset($trigger);

		// Get tags for all involved hosts
		$hosts = API::Host()->get([
			'output' => ['hostid', 'name'],
			'selectTags' => ['tag', 'value'],
			'hostids' => array_keys($hosts),
			'preservekeys' => true
		]);

		return [
			'paging' => $paging,
			'triggers' => $triggers,
			'hosts' => $hosts
		];
	}

	protected function cleanInput(array $input): array {
		if (array_key_exists('filter_reset', $input) && $input['filter_reset']) {
			return array_intersect_key(['filter_name' => ''], $input);
		}
		return $input;
	}

	protected function getAdditionalData($filter): array {
		$data = [];

		if ($filter['tpl_groupids']) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['tpl_groupids']
			]);
			$data['tpl_groups_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($groups), ['groupid' => 'id']);
		}

		if ($filter['templateids']) {
			$templates= API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter['templateids']
			]);
			$data['templates_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($templates), ['templateid' => 'id']);
		}

		if ($filter['tpl_triggerids']) {
			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description'],
				'selectHosts' => 'extend',
				'triggerids' => $filter['tpl_triggerids']
			]);

			foreach($triggers as &$trigger) {
				sizeof($trigger['hosts']) > 0 ?
					$trigger['name'] = $trigger['hosts'][0]['host'] . ': ' . $trigger['description'] :
					$trigger['name'] = $trigger['description'];
				unset($trigger['hosts']);
				unset($trigger['description']);
			}

			$data['tpl_triggers_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($triggers), ['triggerid' => 'id']);
		}

		if ($filter['hostgroupids']) {
			$hostgroups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['hostgroupids']
			]);
			$data['hostgroups_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($hostgroups), ['groupid' => 'id']);
		}

		return $data;
	}

	protected function getChildGroups($parent_group_ids): array {
		$all_group_ids = [];
		foreach($parent_group_ids as $parent_group_id) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => [$parent_group_id]
			]);
			$parent_group_name = $groups[0]['name'].'/';
			$len = strlen($parent_group_name);

			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'search' => ['name' => $parent_group_name],
				'startSearch' => true
			]);

			$all_group_ids[] = $parent_group_id;
			foreach ($groups as $group) {
				if (substr($group['name'], 0, $len) === $parent_group_name) {
					$all_group_ids[] = $group['groupid'];
				}
			}
		}
		return $all_group_ids;
	}
}
