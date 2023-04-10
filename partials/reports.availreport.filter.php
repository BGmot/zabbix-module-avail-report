<?php declare(strict_types = 1);

$filter_column = (new CFormList())
	->addRow((new CLabel(_('Template groups'), 'tpl_groupids_#{uniqid}_ms')),
		(new CMultiSelect([
			'name' => 'tpl_groupids[]',
			'object_name' => 'hostGroup',
			'data' => array_key_exists('tpl_groups_multiselect', $data) ? $data['tpl_groups_multiselect'] : [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'template_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'tpl_groupids_',
					'with_templates' => true,
					'editable' => true,
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
				'filter_preselect' => [
					'id' => 'tpl_groupids_',
					'submit_as' => 'templategroupid'
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
				'filter_preselect' => [
					'id' => 'templateids_',
					'submit_as' => 'templateid'
				],
				'parameters' => [
					'srctbl' => 'template_triggers',
					'srcfld1' => 'triggerid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'tpl_triggerids_',
					'templateid' => '4'
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
	->addRow((new CLabel(_('Hosts'), 'hostids_#{uniqid}_ms')),
		(new CMultiSelect([
			'name' => 'hostids[]',
			'object_name' => 'hosts',
			'data' => array_key_exists('hosts_multiselect', $data) ? $data['hosts_multiselect'] : [],
			'popup' => [
				'parameters' => [
					'srctbl' => 'hosts',
					'srcfld1' => 'hostid',
					'dstfrm' => 'zbx_filter',
					'dstfld1' => 'hostids_',
					'real_hosts' => true
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			->setId('hostids_#{uniqid}')
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

(new CTemplateTag('filter-reports-availreport'))
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
					srctbl: 'template_groups',
					srcfld1: 'groupid',
					dstfrm: 'zbx_filter',
					dstfld1: 'tpl_groupids_' + data.uniqid,
					with_templates: 1,
					editable: 1,
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
				filter_preselect: {
					id: 'tpl_groupids_' + data.uniqid,
					submit_as: 'templategroupid'
				},
				parameters: {
					srctbl: 'templates',
					srcfld1: 'hostid',
					dstfrm: 'zbx_filter',
					dstfld1: 'templateids_' + data.uniqid,
					multiselect: 1
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
				filter_preselect: {
					id: 'templateids_' + data.uniqid,
					submit_as: 'templateid'
				},
				parameters: {
					multiselect: '1',
					srctbl: 'template_triggers',
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
					srctbl: 'host_groups',
					srcfld1: 'groupid',
					dstfrm: 'zbx_filter',
					dstfld1: 'hostgroupids_' + data.uniqid,
					real_hosts: 1,
					enrich_parent_groups: 1
				}
			}
		});

		// Hosts multiselect.
		$('#hostids_' + data.uniqid, container).multiSelectHelper({
			id: 'hostids_' + data.uniqid,
			object_name: 'hosts',
			name: 'hostids[]',
			data: data.filter_view_data.hosts_multiselect || [],
			objectOptions: {
				real_hosts: 1
			},
			popup: {
				parameters: {
					multiselect: '1',
					srctbl: 'hosts',
					srcfld1: 'hostid',
					dstfrm: 'zbx_filter',
					dstfld1: 'hostids_' + data.uniqid,
					real_hosts: 1
				}
			}
		});

		let only_with_problems_checkbox = $('[name="only_with_problems"]');
		if (only_with_problems_checkbox.attr('unchecked-value') === data['only_with_problems']) {
			only_with_problems_checkbox.removeAttr('checked');
		}

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
