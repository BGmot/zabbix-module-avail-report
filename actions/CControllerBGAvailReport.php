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

		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'value'],
			'selectHosts' => ['name'],
			'expandDescription' => true,
			'monitored' => true,
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

		// Split result array and create paging.
		$paging = CPagerHelper::paginate($filter['page'], $triggers, 'ASC', $view_curl);

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
}
