<?php

$form = (new CForm())->setName('availreport_view');

$table = (new CTableInfo());

$view_url = $data['view_curl']->getUrl();

$table->setHeader([
	(new CColHeader(_('Host'))),
	(new CColHeader(_('Name'))),
	(new CColHeader(_('Problems'))),
	(new CColHeader(_('Ok'))),
	(new CColHeader(_('Tags')))
]);

$allowed_ui_problems = CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);
$triggers = $data['triggers'];
$hosts = $data['hosts'];

$tags = makeTags($hosts, true, 'hostid', ZBX_TAG_COUNT_DEFAULT);
foreach ($hosts as &$host) {
	$host['tags'] = $tags[$host['hostid']];
}
unset($host);

foreach ($triggers as $trigger) {
	$table->addRow([
		$trigger['host_name'],
		$allowed_ui_problems
			? new CLink($trigger['description'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_name', '')
					->setArgument('triggerids', [$trigger['triggerid']])
			)
			: $trigger['description'],
		($trigger['availability']['true'] < 0.00005)
			? ''
			: (new CSpan(sprintf('%.4f%%', $trigger['availability']['true'])))->addClass(ZBX_STYLE_RED),
		($trigger['availability']['false'] < 0.00005)
			? ''
			: (new CSpan(sprintf('%.4f%%', $trigger['availability']['false'])))->addClass(ZBX_STYLE_GREEN),
		$hosts[$trigger['hosts'][0]['hostid']]['tags']
	]);
}

$form->addItem([$table,	$data['paging']]);

echo $form;
?>
