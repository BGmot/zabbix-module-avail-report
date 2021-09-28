<?php declare(strict_types = 1);

namespace Modules\BGmotAR\Actions;

use CUrl;
use CControllerResponseData;
use CTabFilterProfile;

class CControllerBGAvailReportViewRefresh extends CControllerBGAvailReportView {

	protected function doAction(): void {
		$filter = static::FILTER_FIELDS_DEFAULT;

		if ($this->getInput('filter_counters', 0)) {
			$profile = (new CTabFilterProfile(static::FILTER_IDX, static::FILTER_FIELDS_DEFAULT))->read();
			$filters = $this->hasInput('counter_index')
				? [$profile->getTabFilter($this->getInput('counter_index'))]
				: $profile->getTabsWithDefaults();
			$filter_counters = [];

			foreach ($filters as $index => $tabfilter) {
				$filter_counters[$index] = $tabfilter['filter_show_counter'] ? $this->getCount($tabfilter) : 0;
			}

			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['filter_counters' => $filter_counters])
				]))->disableView()
			);
		}
		else {
			$this->getInputs($filter, array_keys($filter));
			$filter = $this->cleanInput($filter);
			$prepared_data = $this->getData($filter);

			$view_url = (new CUrl())
				->setArgument('action', 'availreport.view')
				->removeArgument('page');

			$data = [
				'filter' => $filter,
				'view_curl' => $view_url
			] + $prepared_data;

			$response = new CControllerResponseData($data);
			$this->setResponse($response);
		}
	}
}
?>
