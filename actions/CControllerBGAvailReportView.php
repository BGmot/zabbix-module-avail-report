<?php declare(strict_types = 1);

namespace Modules\BGmotAR\Actions;

use CRoleHelper;
use CControllerResponseData;
use CControllerResponseFatal;
use CTabFilterProfile;
use CUrl;
use CWebUser;

class CControllerBGAvailReportView extends CControllerBGAvailReport {

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'name' =>			'string',
			'mode' =>			'in '.AVAILABILITY_REPORT_BY_HOST.','.AVAILABILITY_REPORT_BY_TEMPLATE,
			'tpl_groupids' =>		'array_id',
			'templateids' =>		'array_id',
			'tpl_triggerids' =>		'array_id',
			'hostgroupids' =>		'array_id',
			'hostids' =>			'array_id',
			'filter_reset' =>		'in 1',
			'only_with_problems' =>		'in 0,1',
			'page' =>			'ge 1',
			'counter_index' =>		'ge 0',
			'from' =>			'range_time',
			'to' =>				'range_time'
		];
		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT);
	}

	protected function doAction() {
		$filter_tabs = [];

		$profile = (new CTabFilterProfile(static::FILTER_IDX, static::FILTER_FIELDS_DEFAULT))->read();
		if ($this->hasInput('filter_reset')) {
			$profile->reset();
		}
		else {
			$profile->setInput($this->cleanInput($this->getInputAll()));
		}

		foreach ($profile->getTabsWithDefaults() as $index => $filter_tab) {
			if ($index == $profile->selected) {
				// Initialize multiselect data for filter_scr to allow tabfilter correctly handle unsaved state.
				$filter_tab['filter_src']['filter_view_data'] = $this->getAdditionalData($filter_tab['filter_src']);
			}

			$filter_tabs[] = $filter_tab + ['filter_view_data' => $this->getAdditionalData($filter_tab)];
		}

		// filter
		$filter = $filter_tabs[$profile->selected];
		$refresh_curl = (new CUrl('zabbix.php'));
		$filter['action'] = 'availreport.view.refresh';
		$filter['action_from_url'] = $this->getAction();
		array_map([$refresh_curl, 'setArgument'], array_keys($filter), $filter);

		$data = [
			'action' => $this->getAction(),
			'tabfilter_idx' => static::FILTER_IDX,
			'filter' => $filter,
			'filter_view' => 'reports.availreport.filter',
			'filter_defaults' => $profile->filter_defaults,
			'tabfilter_options' => [
				'idx' => static::FILTER_IDX,
				'selected' => $profile->selected,
				'support_custom_time' => 1,
				'expanded' => $profile->expanded,
				'page' => $filter['page'],
				'timeselector' => [
					'from' => $profile->from,
					'to' => $profile->to,
					'disabled' => false
				] + getTimeselectorActions($profile->from, $profile->to)
			],
			'filter_tabs' => $filter_tabs,
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 10000, //+++1000,
			'page' => $this->getInput('page', 1)
		] + $this->getData($filter);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Availability report'));

		if ($data['action'] === 'availreport.view.csv') {
			$response->setFileName('zbx_availability_report_export.csv');
		}

		$this->setResponse($response);
	}
}
?>
