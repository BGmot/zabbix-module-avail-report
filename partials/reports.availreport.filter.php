<?php declare(strict_types = 1);

$filter_column = (new CFormList())
	->addRow((new CLabel(_('Template groups'), 'tpl_groupids_#{uniqid}_ms')),
		(new CMultiSelect([
			'name' => 'tpl_groupids[]',
			'object_name' => 'hostGroup',
			'data' => array_key_exists('tpl_groups_multiselect', $data) ? $data['tpl_groups_multiselect'] : [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'tpl_groupids_',
					'templated_hosts' => true,
					'enrich_parent_groups' => true
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setId('tpl_groupids_#{uniqid}')
	)
	->addRow((new CLabel(_('Templates'), 'templateids_#{uniqid}_ms')),
		(new CMultiSelect([
			'name' => 'templateids[]',
			'object_name' => 'templates',
			'data' => array_key_exists('templates_multiselect', $data) ? $data['templates_multiselect'] : [],
			'popup' => [
				'filter_preselect_fields' => [
					'hostgroups' => 'tpl_groupids_'
				],
				'parameters' => [
					'srctbl' => 'templates',
					'srcfld1' => 'hostid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'templateids_'
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setId('templateids_#{uniqid}')
	)
	->addRow((new CLabel(_('Template trigger'), 'tpl_triggerids_#{uniqid}_ms')),
		(new CMultiSelect([
			'name' => 'tpl_triggerids[]',
			'object_name' => 'triggers',
			'data' => array_key_exists('tpl_triggers_multiselect', $data) ? $data['tpl_triggers_multiselect'] : [],
			'popup' => [
				'filter_preselect_fields' => [
					'hosts' => 'templateids_'
				],
				'parameters' => [
					'noempty' => 'true',
					'srctbl' => 'triggers',
					'srcfld1' => 'triggerid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'tpl_triggerids_'
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setId('tpl_triggerids_#{uniqid}')
	)
	->addRow((new CLabel(_('Host groups'), 'groupids_#{uniqid}_ms')),
		(new CMultiSelect([
			'name' => 'hostgroupids[]',
			'object_name' => 'hostGroup',
			'data' => array_key_exists('hostgroups_multiselect', $data) ? $data['hostgroups_multiselect'] : [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'hostgroupids_',
					'real_hosts' => true,
					'enrich_parent_groups' => true
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setId('hostgroupids_#{uniqid}')
	)
	->addRow(_('Show only hosts with problems'),
		(new CCheckBox('only_with_problems'))
			->setChecked($data['only_with_problems'] == 1)
			->setUncheckedValue(0)
			->setId('only_with_problems_#{uniqid}')
		);

$template = (new CDiv())
	->addClass(ZBX_STYLE_TABLE)
	->addClass(ZBX_STYLE_FILTER_FORMS)
	->addItem((new CDiv($filter_column))->addClass(ZBX_STYLE_CELL));

$template = (new CForm('get'))
	->cleanItems()
	->setName('zbx_filter')
	->addItem([
		$template,
		(new CSubmitButton(null))->addClass(ZBX_STYLE_DISPLAY_NONE),
		(new CVar('filter_name', '#{filter_name}'))->removeId(),
		(new CVar('filter_show_counter', '#{filter_show_counter}'))->removeId(),
		(new CVar('filter_custom_time', '#{filter_custom_time}'))->removeId(),
		(new CVar('from', '#{from}'))->removeId(),
		(new CVar('to', '#{to}'))->removeId()
	]);

if (array_key_exists('render_html', $data)) {
	/**
	 * Render HTML to prevent filter flickering after initial page load. PHP created content will be replaced by
	 * javascript with additional event handling (dynamic rows, etc.) when page will be fully loaded and javascript
	 * executed.
	 */

	$template->show();

	return;
}

(new CScriptTemplate('filter-reports-availreport'))
	->setAttribute('data-template', 'reports.availreport.filter')
	->addItem($template)
	->show();
?>
<script type="text/javascript">
	let template = document.querySelector('[data-template="reports.availreport.filter"]');

	function render(data, container) {
		// "Save as" can contain only home tab, also home tab cannot contain "Update" button.
		$('[name="filter_new"],[name="filter_update"]').hide()
			.filter(data.filter_configurable ? '[name="filter_update"]' : '[name="filter_new"]').show();

		// Template groups multiselect.
		$('#tpl_groupids_' + data.uniqid, container).multiSelectHelper({
			id: 'tpl_groupids_' + data.uniqid,
			object_name: 'hostGroup',
			name: 'tpl_groupids[]',
			data: data.filter_view_data.tpl_groups_multiselect || [],
			objectOptions: {
				enrich_parent_groups: 1
			},
			selectedLimit: 1,
			popup: {
				parameters: {
					noempty: '1',
					srctbl: 'host_groups',
					srcfld1: 'groupid',
					dstfrm: 'zbx_filter',
					dstfld1: 'tpl_groupids_' + data.uniqid,
					templated_hosts: 1,
					enrich_parent_groups: 1
				}
			}
		});

		// Templates multiselect.
		$('#templateids_' + data.uniqid, container).multiSelectHelper({
			id: 'templateids_' + data.uniqid,
			object_name: 'templates',
			name: 'templateids[]',
			data: data.filter_view_data.templates_multiselect || [],
			selectedLimit: 1,
			popup: {
				filter_preselect_fields: {
					hostgroups: 'tpl_groupids_' + data.uniqid
				},
				parameters: {
					noempty: '1',
					srctbl: 'templates',
					srcfld1: 'hostid',
					dstfrm: 'zbx_filter',
					dstfld1: 'templateids_' + data.uniqid
				}
			}
		});

		// Template triggers multiselect.
		$('#tpl_triggerids_' + data.uniqid, container).multiSelectHelper({
			id: 'tpl_triggerids_' + data.uniqid,
			object_name: 'templates',
			name: 'tpl_triggerids[]',
			data: data.filter_view_data.tpl_triggers_multiselect || [],
			popup: {
				filter_preselect_fields: {
					hosts: 'templateids_' + data.uniqid
				},
				parameters: {
					multiselect: '1',
					noempty: '1',
					srctbl: 'triggers',
					srcfld1: 'triggerid',
					dstfrm: 'zbx_filter',
					dstfld1: 'tpl_triggerids_' + data.uniqid
				}
			}
		});

		// Host groups multiselect.
		$('#hostgroupids_' + data.uniqid, container).multiSelectHelper({
			id: 'hostgroupids_' + data.uniqid,
			object_name: 'hostGroup',
			name: 'hostgroupids[]',
			data: data.filter_view_data.hostgroups_multiselect || [],
			objectOptions: {
				real_hosts: 1,
				enrich_parent_groups: 1
			},
			popup: {
				parameters: {
					multiselect: '1',
					noempty: '1',
					srctbl: 'host_groups',
					srcfld1: 'groupid',
					dstfrm: 'zbx_filter',
					dstfld1: 'hostgroupids_' + data.uniqid,
					real_hosts: 1,
					enrich_parent_groups: 1
				}
			}
		});

		// Initialize src_url.
		this.resetUnsavedState();
		this.on(TABFILTERITEM_EVENT_ACTION, update.bind(this));
	}

	function expand(data, container) {
		// "Save as" can contain only home tab, also home tab cannot contain "Update" button.
		$('[name="filter_new"],[name="filter_update"]').hide()
			.filter(data.filter_configurable ? '[name="filter_update"]' : '[name="filter_new"]').show();
	}

	function update(ev) {
		let action = ev.detail.action,
			container = this._content_container;

		if (action !== 'filter_apply' && action !== 'filter_update') {
			return;
		}
	}

	// Tab filter item events handlers.
	template.addEventListener(TABFILTERITEM_EVENT_RENDER, function (ev) {
		render.call(ev.detail, ev.detail._data, ev.detail._content_container);
	});
	template.addEventListener(TABFILTERITEM_EVENT_EXPAND, function (ev) {
		expand.call(ev.detail, ev.detail._data, ev.detail._content_container);
	});
</script>
