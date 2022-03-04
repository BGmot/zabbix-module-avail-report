<?php

/**
 * @var CView $this
 */
?>
<script type="text/javascript">
	jQuery(function($) {
		function availreportPage() {
			let filter_options = <?= json_encode($data['filter_options']) ?>;
			this.refresh_url = '<?= $data['refresh_url'] ?>';
			this.refresh_interval = <?= $data['refresh_interval'] ?>;
			this.running = false;
			this.timeout = null;
			this.deferred = null;

			if (filter_options) {
				this.refresh_counters = this.createCountersRefresh(1);
				this.filter = new CTabFilter($('#reports_availreport_filter')[0], filter_options);
				this.filter.on(TABFILTER_EVENT_URLSET, (ev) => {
					let url = new Curl('', false);

					url.setArgument('action', 'availreport.view.refresh');
					this.refresh_url = url.getUrl();
					this.unscheduleRefresh();
					this.refresh();

					var filter_item = this.filter._active_item;

					if (this.filter._active_item.hasCounter()) {
						$.post('zabbix.php', {
							action: 'availreport.view.refresh',
							filter_counters: 1,
							counter_index: filter_item._index
						}).done((json) => {
							if (json.filter_counters) {
								filter_item.updateCounter(json.filter_counters.pop());
							}
						});
					}
				});
			}
		}

		availreportPage.prototype = {
			createCountersRefresh: function(timeout) {
				if (this.refresh_counters) {
					clearTimeout(this.refresh_counters);
					this.refresh_counters = null;
				}

				return setTimeout(() => this.getFiltersCounters(), timeout);
			},
			getFiltersCounters: function() {
				return $.post('zabbix.php', {
						action: 'availreport.view.refresh',
						filter_counters: 1
					}).done((json) => {
						if (json.filter_counters) {
							this.filter.updateCounters(json.filter_counters);
						}
					}).always(() => {
						if (this.refresh_interval > 0) {
							this.refresh_counters = this.createCountersRefresh(this.refresh_interval);
						}
					});
			},
			getCurrentForm: function() {
				return $('form[name=availreport_view]');
			},
			addMessages: function(messages) {
				$('.wrapper main').before(messages);
			},
			removeMessages: function() {
				$('.wrapper .msg-bad').remove();
			},
			refresh: function() {
				// Update export_csv url according to what's in filter fields
				const export_csv_url = new URL(this.refresh_url, 'http://example.com');
				for(var key of export_csv_url.searchParams.keys()) {
					if (key == 'action') {
						export_csv_url.searchParams.set(key, 'availreport.view.csv');
					}
				}
				var csv_url=export_csv_url.pathname.slice(1) + '?' + export_csv_url.searchParams.toString();
				var export_button = document.getElementById("export_csv");
				export_button.setAttribute("data-url", csv_url);

				this.setLoading();

				this.deferred = $.getJSON(this.refresh_url);

				return this.bindDataEvents(this.deferred);
			},
			setLoading: function() {
				//this.getCurrentForm().addClass('is-loading is-loading-fadein delayed-15s');
				$('div[id=reports_availreport_filter]').addClass('is-loading is-loading-fadein');
			},
			clearLoading: function() {
				//this.getCurrentForm().removeClass('is-loading is-loading-fadein delayed-15s');
				$('div[id=reports_availreport_filter]').removeClass('is-loading is-loading-fadein');
			},
			doRefresh: function(body) {
				this.getCurrentForm().replaceWith(body);
			},
			bindDataEvents: function(deferred) {
				var that = this;

				deferred
					.done(function(response) {
						that.onDataDone.call(that, response);
					})
					.fail(function(jqXHR) {
						that.onDataFail.call(that, jqXHR);
					})
					.always(this.onDataAlways.bind(this));

				return deferred;
			},
			onDataDone: function(response) {
				this.clearLoading();
				this.removeMessages();
				this.doRefresh(response.body);

				if ('messages' in response) {
					this.addMessages(response.messages);
				}
			},
			onDataFail: function(jqXHR) {
				// Ignore failures caused by page unload.
				if (jqXHR.status == 0) {
					return;
				}

				this.clearLoading();

				var messages = $(jqXHR.responseText).find('.msg-global');

				if (messages.length) {
					this.getCurrentForm().html(messages);
				}
				else {
					this.getCurrentForm().html(jqXHR.responseText);
				}
			},
			onDataAlways: function() {
				if (this.running) {
					this.deferred = null;
					this.scheduleRefresh();
				}
			},
			scheduleRefresh: function() {
				this.unscheduleRefresh();

				if (this.refresh_interval > 0) {
					this.timeout = setTimeout((function() {
						this.timeout = null;
						this.refresh();
					}).bind(this), this.refresh_interval);
				}
			},
			unscheduleRefresh: function() {
				if (this.timeout !== null) {
					clearTimeout(this.timeout);
					this.timeout = null;
				}

				if (this.deferred) {
					this.deferred.abort();
				}
			},
			start: function() {
				this.running = true;
				this.refresh();
			}
		};

		window.availreport_page = new availreportPage();
		window.availreport_page.start();
	});

	jQuery.subscribe('timeselector.rangeupdate', function(e, data) {
		if (window.availreport_page) {
			const url = new URL(window.availreport_page.refresh_url, 'http://example.com');
			for(var key of url.searchParams.keys()) {
				if (key == 'from' || key == 'to') {
					url.searchParams.set(key, data[key]);
				}
			}

			window.availreport_page.refresh_url=url.pathname.slice(1) + '?' + url.searchParams.toString();
			window.availreport_page.refresh();
		}
	});
</script>
