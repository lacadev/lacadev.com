/**
 * Project Charts — Chart.js dashboard widget.
 * Renders trên widget #laca-project-charts-widget.
 * Dữ liệu nhận từ wp_localize_script('lacaProjectCharts').
 */

import {
	Chart,
	ArcElement,
	DoughnutController,
	BarElement,
	BarController,
	CategoryScale,
	LinearScale,
	Tooltip,
	Legend,
} from 'chart.js';

Chart.register(
	ArcElement,
	DoughnutController,
	BarElement,
	BarController,
	CategoryScale,
	LinearScale,
	Tooltip,
	Legend
);

( function () {
	'use strict';

	const hubData = window.lacaProjectsHubCharts;

	const hubPalette = {
		ink: '#111827',
		muted: '#6b7280',
		grid: 'rgba(17, 24, 39, 0.08)',
		blue: '#2563eb',
		green: '#059669',
		amber: '#d97706',
		red: '#dc2626',
		violet: '#7c3aed',
		slate: '#94a3b8',
	};

	const defaultOptions = {
		responsive: true,
		maintainAspectRatio: false,
		plugins: {
			legend: {
				position: 'bottom',
				labels: {
					boxWidth: 10,
					color: hubPalette.muted,
					font: { size: 11 },
					padding: 12,
					usePointStyle: true,
				},
			},
			tooltip: {
				backgroundColor: '#111827',
				padding: 10,
				titleColor: '#ffffff',
				bodyColor: '#e5e7eb',
			},
		},
	};

	const renderHubDoughnut = ( canvasId, labels, values, colors ) => {
		const canvas = document.getElementById( canvasId );
		if ( ! canvas || canvas.dataset.lacaChartRendered === '1' ) {
			return;
		}

		canvas.dataset.lacaChartRendered = '1';

		new Chart( canvas, {
			type: 'doughnut',
			data: {
				labels,
				datasets: [
					{
						data: values,
						backgroundColor: colors,
						borderColor: '#ffffff',
						borderWidth: 3,
						hoverOffset: 5,
					},
				],
			},
			options: {
				...defaultOptions,
				cutout: '70%',
			},
		} );
	};

	const renderHubBar = ( canvasId, labels, values, colors ) => {
		const canvas = document.getElementById( canvasId );
		if ( ! canvas || canvas.dataset.lacaChartRendered === '1' ) {
			return;
		}

		canvas.dataset.lacaChartRendered = '1';

		new Chart( canvas, {
			type: 'bar',
			data: {
				labels,
				datasets: [
					{
						data: values,
						backgroundColor: colors,
						borderRadius: 8,
						borderSkipped: false,
						barPercentage: 0.58,
					},
				],
			},
			options: {
				...defaultOptions,
				plugins: {
					...defaultOptions.plugins,
					legend: { display: false },
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: { color: hubPalette.muted, font: { size: 11 } },
					},
					y: {
						beginAtZero: true,
						grid: { color: hubPalette.grid },
						ticks: {
							color: hubPalette.muted,
							font: { size: 11 },
							precision: 0,
						},
					},
				},
			},
		} );
	};

	if ( hubData ) {
		renderHubDoughnut(
			'laca-projects-finance-chart',
			hubData.finance?.labels || [],
			hubData.finance?.values || [],
			[ hubPalette.green, hubPalette.amber ]
		);

		renderHubBar(
			'laca-projects-alert-chart',
			hubData.alerts?.labels || [],
			hubData.alerts?.values || [],
			[ hubPalette.red, hubPalette.amber, hubPalette.blue ]
		);

		renderHubDoughnut(
			'laca-projects-status-chart',
			hubData.status?.labels || [],
			hubData.status?.values || [],
			[
				hubPalette.slate,
				hubPalette.blue,
				hubPalette.violet,
				hubPalette.amber,
				hubPalette.green,
			]
		);
	}

	const escapeHtml = ( value ) =>
		String( value || '' ).replace(
			/[&<>"']/g,
			( char ) =>
				( {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;',
				} )[ char ]
		);

	document.addEventListener( 'click', ( event ) => {
		const trigger = event.target.closest( '[data-laca-project-detail]' );
		if ( ! trigger || event.metaKey || event.ctrlKey || event.shiftKey ) {
			return;
		}

		if ( typeof window.Swal === 'undefined' ) {
			return;
		}

		event.preventDefault();

		const modalHtml = [
			'<div class="laca-projects-swal">',
			`<p class="laca-projects-swal__meta">${ escapeHtml(
				trigger.dataset.meta
			) }</p>`,
			`<p class="laca-projects-swal__message">${ escapeHtml(
				trigger.dataset.message
			).replace( /\n/g, '<br>' ) }</p>`,
			'</div>',
		].join( '' );

		window.Swal.fire( {
			title: trigger.dataset.title || 'Project detail',
			html: modalHtml,
			showCancelButton: true,
			confirmButtonText: 'Mở project',
			cancelButtonText: 'Đóng',
			confirmButtonColor: '#111827',
			cancelButtonColor: '#6b7280',
			width: 520,
		} ).then( ( result ) => {
			if ( result.isConfirmed && trigger.dataset.url ) {
				window.location.href = trigger.dataset.url;
			}
		} );
	} );

	if ( typeof lacaProjectCharts === 'undefined' ) {
		return;
	}

	const data = lacaProjectCharts;

	// ── Palette ───────────────────────────────────────────────

	const colors = {
		primary: data.primary || '#2ea2cc',
		pending: '#b0b8cc', // 🕐 Chờ làm     — xám
		inProgress: '#f5a623', // 🔨 Đang làm    — cam
		done: '#3ecf8e', // ✅ Đã xong     — xanh lá
		maintenance: '#a855f7', // 🔧 Bảo trì     — tím
		paused: '#f15d4f', // ⏸️ Tạm dừng   — đỏ
		grid: 'rgba(0,0,0,0.06)',
		text: '#1a1a2e',
	};

	// ── Doughnut — trạng thái project ────────────────────────

	const donutCanvas = document.getElementById( 'laca-chart-status' );
	if ( donutCanvas && data.byStatus ) {
		const statuses = data.byStatus; // { label, count }[]

		const colorMap = {
			pending: colors.pending,
			in_progress: colors.inProgress,
			done: colors.done,
			maintenance: colors.maintenance,
			paused: colors.paused,
		};

		new Chart( donutCanvas, {
			type: 'doughnut',
			data: {
				labels: statuses.map( ( s ) => s.label ),
				datasets: [
					{
						data: statuses.map( ( s ) => s.count ),
						backgroundColor: statuses.map(
							( s ) => colorMap[ s.key ] || colors.draft
						),
						borderWidth: 0,
						hoverOffset: 6,
					},
				],
			},
			options: {
				responsive: true,
				cutout: '68%',
				plugins: {
					legend: {
						position: 'bottom',
						labels: {
							color: colors.text,
							font: { size: 12 },
							padding: 14,
							boxWidth: 12,
							borderRadius: 6,
							usePointStyle: true,
						},
					},
					tooltip: {
						callbacks: {
							label: ( ctx ) =>
								` ${ ctx.label }: ${ ctx.parsed }`,
						},
					},
				},
			},
		} );
	}

	// ── Bar — projects theo tháng ─────────────────────────────

	const barCanvas = document.getElementById( 'laca-chart-monthly' );
	if ( barCanvas && data.byMonth ) {
		const months = data.byMonth; // { month: 'T1', count: 3 }[]

		new Chart( barCanvas, {
			type: 'bar',
			data: {
				labels: months.map( ( m ) => m.month ),
				datasets: [
					{
						label: 'Projects',
						data: months.map( ( m ) => m.count ),
						backgroundColor: colors.primary,
						borderRadius: 6,
						borderSkipped: false,
						barPercentage: 0.6,
					},
				],
			},
			options: {
				responsive: true,
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: ( ctx ) => ` ${ ctx.parsed.y } projects`,
						},
					},
				},
				scales: {
					x: {
						grid: { color: colors.grid },
						ticks: {
							color: colors.text,
							font: { size: 11 },
						},
					},
					y: {
						grid: { color: colors.grid },
						beginAtZero: true,
						ticks: {
							stepSize: 1,
							color: colors.text,
							font: { size: 11 },
						},
					},
				},
			},
		} );
	}
} )();
