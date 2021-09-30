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
		'only_with_problems' => 0,
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
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		$host_group_ids = sizeof($filter['hostgroupids']) > 0 ? $this->getChildGroups($filter['hostgroupids']) : null;

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

		// Now just prepare needed data.
		foreach ($triggers as &$trigger) {
			$trigger['host_name'] = $trigger['hosts'][0]['name'];
		}
		unset($trigger);

		CArrayHelper::sort($triggers, ['host_name', 'description'], 'ASC');

		$view_curl = (new CUrl())->setArgument('action', 'availreport.view');

		if (!array_key_exists('action_from_url', $filter) ||
			$filter['action_from_url'] != 'availreport.view.csv') {
			// Split result array and create paging. Only if not generating CSV.
			$paging = CPagerHelper::paginate($filter['page'], $triggers, 'ASC', $view_curl);
		}

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

		foreach ($triggers as &$trigger) {
			$trigger['availability'] = calculateAvailability($trigger['triggerid'], $filter['from_ts'], $filter['to_ts']);
		}
		if ($filter['only_with_problems']) {
			$triggers_with_problems = [];
			foreach ($triggers as $trigger) {
				if ($trigger['availability']['true'] > 0.00005) {
					// There was downtime
					$triggers_with_problems[] = $trigger;
				}
			}
			$paging = CPagerHelper::paginate($filter['page'], $triggers_with_problems, 'ASC', $view_curl);
			return [
				'paging' => $paging,
				'triggers' => $triggers_with_problems
			];
		}

		return [
			'paging' => $paging,
			'triggers' => $triggers
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

		if ($filter['hostgroupids']) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['hostgroupids']
			]);
			$data['hostgroups_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($groups), ['groupid' => 'id']);
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
