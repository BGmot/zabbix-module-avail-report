<?php declare(strict_types = 1);

namespace Modules\BGmotAR\Actions;

use CUrl;
use CControllerResponseData;
use CTabFilterProfile;

class CControllerBGAvailReportViewRefresh extends CControllerBGAvailReportView {

	protected function doAction(): void {
		$filter = static::FILTER_FIELDS_DEFAULT;

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
?>
