<?php declare(strict_types = 1);

if ($data['action'] == 'availreport.view') {
	$this->addJsFile('multiselect.js');
	$this->addJsFile('layout.mode.js');
	$this->addJsFile('gtlc.js');
	$this->addJsFile('class.calendar.js');
	$this->addJsFile('class.tabfilter.js');
	$this->addJsFile('class.tabfilteritem.js');

	$this->enableLayoutModes();
	$web_layout_mode = $this->getLayoutMode();
	$widget = (new CHtmlPage())
		->setTitle(_('Availability report'))
		->setWebLayoutMode($web_layout_mode)
		->setControls(
			(new CTag('nav', true, (new CList())
						->addItem((new CRedirectButton(_('Export to CSV'),
							(new CUrl())->setArgument('action', 'availreport.view.csv')
						))->setId('export_csv'))
						->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
			))->setAttribute('aria-label', _('Content controls'))
		);

	if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
		$filter = (new CTabFilter())
			->setId('reports_availreport_filter')
			->setOptions($data['tabfilter_options'])
			->addTemplate(new CPartial($data['filter_view'], $data['filter_defaults']));

		foreach ($data['filter_tabs'] as $tab) {
			$tab['tab_view'] = $data['filter_view'];
			$filter->addTemplatedTab($tab['filter_name'], $tab);
		}

		// Set javascript options for tab filter initialization in module.reports.availreport.js.php file.
		$data['filter_options'] = $filter->options;
		$widget->addItem($filter);
	}
	else {
		$data['filter_options'] = null;
	}

	$widget->addItem((new CForm())->setName('availreport_view')->addClass('is-loading'));
	$widget->show();
	$this->includeJsFile('module.reports.availreport.js.php', $data);

	(new CScriptTag('availreport_page.start();'))
	->setOnDocumentReady()
		->show();
} else {
	// $data['action'] = 'availreport.view.csv'
	if (sizeof($data['triggers']) == 0) {
		// Nothing to export
		print zbx_toCSV([]);
		return;
	}

	$csv = [];
        // Find out all the tags present in the report
	$tag_names = [];
	foreach ($data['triggers'] as &$trigger) {
		$trigger['tags_kv'] = [];
		foreach ($trigger['tags'] as $tag) {
			if (!in_array($tag['tag'], $tag_names)) {
				$tag_names[] = $tag['tag'];
			}
			$trigger['tags_kv'][$tag['tag']] = $tag['value'];
		}
	}
	$csv[] = array_filter([
                        _('Host'),
                        _('Name'),
                        _('Problems'),
                        _('Ok')
                ]);
	foreach ($tag_names as $tag_name) {
		$csv[0][] = $tag_name;
	}
	foreach ($data['triggers'] as $trigger) {
		// Add data
		$line_to_add = [
			$trigger['host_name'],
			$trigger['description'],
			($trigger['availability']['true'] < 0.00005)
				? ''
				: sprintf('%.4f%%', $trigger['availability']['true']),
			($trigger['availability']['false'] < 0.00005)
				? ''
				: sprintf('%.4f%%', $trigger['availability']['false'])
		];

		// Add tags
		foreach ($tag_names as $tag_name) {
			if (array_key_exists($tag_name, $trigger['tags_kv'])) {
				$line_to_add[] = $trigger['tags_kv'][$tag_name];
			} else {
				$line_to_add[] = '';
			}
		}
		$csv[] = $line_to_add;
	}

	print zbx_toCSV($csv);
}
?>
