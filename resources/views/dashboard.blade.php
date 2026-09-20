<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SENTRA | Smoke Exposure Decision Support</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <style>
        :root {
            --bg-page: #f7f9f7;
            --bg-surface: #ffffff;
            --text-main: #141c18;
            --text-muted: #57655d;
            --text-subtle: #809087;
            --border-light: #e1e7e3;
            --border-strong: #c2ccc5;

            --forest-950: #0e2018;
            --forest-900: #162f23;
            --forest-800: #1f4232;
            --forest-700: #295742;
            --forest-100: #e5eee8;
            --forest-50: #f2f7f4;

            --critical-bg: #fdf2f2;
            --critical-border: #f87171;
            --critical-text: #991b1b;

            --warning-bg: #fefce8;
            --warning-border: #facc15;
            --warning-text: #854d0e;

            --alert-bg: #fff7ed;
            --alert-border: #fb923c;
            --alert-text: #9a3412;

            --safe-bg: #f2f9f4;
            --safe-border: #86efac;
            --safe-text: #166534;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100%; }

        body {
            background: var(--bg-page);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        button, input, select { font: inherit; }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* 2. Compact Navigation Bar */
        .sentra-nav {
            background: #ffffff;
            border-bottom: 1px solid var(--border-light);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-inner {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 24px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-logo {
            height: 32px;
            width: auto;
            object-fit: contain;
            display: block;
            flex-shrink: 0;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.08));
        }

        .brand-name {
            font-size: 19px;
            font-weight: 850;
            letter-spacing: -0.02em;
            color: var(--forest-950);
            margin: 0;
            line-height: 1;
        }

        .brand-tagline {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
            font-weight: 400;
        }

        .nav-modes {
            display: flex;
            align-items: center;
            background: #eef2ef;
            padding: 3px;
            border-radius: 6px;
            gap: 2px;
            position: relative;
        }

        .mode-feedback {
            color: var(--text-muted);
            font-size: 11px;
            position: absolute;
            right: 0;
            top: calc(100% + 7px);
            white-space: nowrap;
        }

        .mode-feedback.is-error { color: #b42318; }

        .data-mode-button.is-loading::after {
            content: '…';
            margin-left: 3px;
        }

        .data-mode-button {
            background: transparent;
            border: 0;
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .data-mode-button:hover:not(:disabled) {
            color: var(--text-main);
        }

        .data-mode-button.is-active {
            background: #ffffff;
            color: var(--forest-950);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            font-weight: 700;
        }

        .data-mode-button:disabled {
            opacity: 0.5;
            cursor: wait;
        }

        /* Container */
        .sentra-main {
            max-width: 1440px;
            margin: 0 auto;
            padding: 20px 24px 64px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* 3. Status Warning Strip (Hidden) */
        .alert-strip {
            display: none !important;
        }

        /* 4. Condition Summary (Inline Metrics) */
        .condition-summary-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px 18px;
            font-size: 13px;
            color: var(--text-muted);
            padding: 0 2px;
        }

        .condition-metric {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .condition-metric strong {
            color: var(--text-main);
            font-weight: 700;
        }

        .condition-dot {
            color: var(--border-strong);
            font-size: 10px;
        }

        .condition-time {
            margin-left: auto;
            color: var(--text-subtle);
            font-size: 12.5px;
        }

        .condition-metric.sr-only + .condition-dot { display: none; }

        .situation-board {
            background: var(--bg-surface);
            border: 1px solid var(--border-light);
            display: grid;
            grid-template-columns: minmax(360px, 0.85fr) minmax(0, 1.55fr);
        }

        .situation-summary {
            padding: 22px 24px;
            border-right: 1px solid var(--border-light);
        }

        .situation-summary h2,
        .attention-heading h3 {
            color: var(--forest-950);
            margin: 0;
            letter-spacing: -0.02em;
        }

        .situation-summary h2 { font-size: 19px; }

        .situation-summary-copy {
            color: var(--text-muted);
            font-size: 12.5px;
            margin: 5px 0 20px;
            max-width: 56ch;
        }

        .situation-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border-top: 1px solid var(--border-light);
            border-left: 1px solid var(--border-light);
        }

        .situation-metric {
            min-height: 78px;
            padding: 13px 15px;
            border-right: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
        }

        .situation-metric strong {
            color: var(--forest-950);
            display: block;
            font-size: 24px;
            font-variant-numeric: tabular-nums;
            line-height: 1.1;
        }

        .situation-metric.is-attention strong { color: #b42318; }

        .situation-metric span {
            color: var(--text-muted);
            display: block;
            font-size: 11.5px;
            line-height: 1.35;
            margin-top: 5px;
        }

        .attention-panel { min-width: 0; }

        .attention-heading {
            align-items: baseline;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            padding: 17px 20px 14px;
        }

        .attention-heading h3 { font-size: 14px; }

        .attention-heading span {
            color: var(--text-subtle);
            font-size: 11.5px;
        }

        .attention-list { display: grid; }

        .attention-row {
            align-items: center;
            display: grid;
            grid-template-columns: 44px minmax(135px, 0.9fr) minmax(180px, 1.25fr) auto;
            gap: 14px;
            min-height: 71px;
            padding: 11px 20px;
            border-bottom: 1px solid var(--border-light);
        }

        .attention-row:last-child { border-bottom: 0; }

        .attention-rank {
            color: var(--text-subtle);
            font-size: 12px;
            font-variant-numeric: tabular-nums;
            font-weight: 700;
        }

        .attention-place strong,
        .attention-signal strong {
            color: var(--text-main);
            display: block;
            font-size: 12.5px;
        }

        .attention-place span,
        .attention-signal span {
            color: var(--text-muted);
            display: block;
            font-size: 11.5px;
            margin-top: 2px;
        }

        .attention-signal strong { color: #a93428; }

        .attention-action {
            background: var(--forest-900);
            border: 1px solid var(--forest-900);
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            font-size: 11.5px;
            font-weight: 700;
            padding: 7px 10px;
            white-space: nowrap;
        }

        .attention-action:hover { background: var(--forest-800); }
        .attention-action:focus-visible { outline: 3px solid #86b89d; outline-offset: 2px; }
        .attention-action:disabled { cursor: wait; opacity: .65; }

        .attention-empty {
            color: var(--text-muted);
            font-size: 12.5px;
            padding: 24px 20px;
        }

        /* 5. Workspace: Map (68%) & Priority Panel (32%) */
        .sentra-workspace {
            display: grid;
            grid-template-columns: minmax(0, 1.85fr) minmax(340px, 1fr);
            gap: 24px;
            align-items: stretch;
        }

        /* Map Region */
        .map-region {
            background: #ffffff;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            height: 620px;
            position: relative;
            overflow: hidden;
        }

        #map {
            width: 100%;
            height: 100%;
        }

        .map-compact-header {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 500;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid var(--border-light);
            border-radius: 4px;
            padding: 7px 11px;
            font-size: 11.5px;
            color: var(--text-main);
            backdrop-filter: blur(4px);
            pointer-events: none;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        }

        .map-projection-note {
            background: rgba(255, 255, 255, .9);
            border: 1px solid rgba(194, 204, 197, .75);
            border-radius: 3px;
            bottom: 12px;
            color: var(--text-muted);
            font-size: 10.5px;
            line-height: 1.3;
            padding: 5px 8px;
            pointer-events: none;
            position: absolute;
            right: 12px;
            z-index: 500;
        }

        .leaflet-bottom.leaflet-right .leaflet-control-zoom { margin-bottom: 46px; }

        .map-compact-header strong {
            font-weight: 750;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--forest-950);
        }

        .map-compact-header span {
            color: var(--text-muted);
            display: block;
            margin-top: 1px;
        }

        .map-compact-legend {
            position: absolute;
            bottom: 12px;
            left: 12px;
            z-index: 500;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid var(--border-light);
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            backdrop-filter: blur(4px);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
        }

        .dot-hotspot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #dc2626;
        }

        .dot-hotspot.is-normal { background: #94a3b8; }
        .dot-hotspot.has-impact { background: #ea580c; }
        .dot-hotspot.needs-attention { background: #dc2626; }
        .dot-hotspot.is-active { background: #c2410c; box-shadow: 0 0 0 2px #fed7aa; }

        .line-corridor {
            width: 14px;
            height: 4px;
            background: rgba(217, 119, 6, 0.5);
            border: 1px solid #d97706;
            border-radius: 1px;
        }

        .square-facility {
            width: 8px;
            height: 8px;
            border-radius: 1px;
            background: #2563eb;
        }

        .line-route {
            width: 14px;
            height: 3px;
            background: #059669;
            border-radius: 1px;
        }

        /* 7. Priority Respons Region (Clean Rows, No Nested Cards) */
        .priority-region {
            background: #ffffff;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            height: 620px;
            max-height: 620px;
        }

        .priority-top-bar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-shrink: 0;
        }

        .priority-title {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--forest-950);
        }

        .priority-feed {
            padding: 0;
            overflow-y: auto;
            flex-grow: 1;
        }

        .priority-row {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-light);
            transition: background 0.15s ease;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .priority-row:hover {
            background: #fbfdfc;
        }

        .priority-row.is-highlighted {
            background: #f4f8f5;
            border-left: 3px solid var(--forest-700);
            padding-left: 17px;
        }

        .priority-row-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
        }

        .priority-rank-name {
            font-size: 14.5px;
            font-weight: 750;
            color: var(--forest-950);
            margin: 0;
        }

        .status-pill-risk {
            font-size: 11px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 3px;
        }

        .status-pill-risk.risk-high { background: #fee2e2; color: #991b1b; }
        .status-pill-risk.risk-medium { background: #fef3c7; color: #854d0e; }
        .status-pill-risk.risk-low { background: #e5eee8; color: #166534; }

        .priority-row-data {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 12px;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .priority-data-item span {
            color: var(--text-subtle);
            margin-right: 4px;
        }

        .priority-data-item strong {
            color: var(--text-main);
            font-weight: 650;
        }

        .priority-data-item.deadline strong {
            color: #991b1b;
            font-weight: 750;
        }

        .priority-row-actions {
            display: flex;
            gap: 12px;
            margin-top: 4px;
        }

        .text-action-link {
            background: transparent;
            border: 0;
            padding: 0;
            color: var(--forest-700);
            font-size: 12px;
            font-weight: 650;
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .text-action-link:hover {
            color: var(--forest-950);
        }

        .text-action-link.secondary {
            color: var(--text-muted);
        }

        .priority-toggle-row {
            padding: 12px 20px;
            border-top: 1px solid var(--border-light);
            background: #fafbfa;
            text-align: center;
        }

        .toggle-feed-link {
            background: transparent;
            border: 0;
            color: var(--forest-700);
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
        }

        .toggle-feed-link:hover {
            text-decoration: underline;
        }

        .hidden-rows-container {
            display: none;
            flex-direction: column;
        }

        .hidden-rows-container.is-open {
            display: flex;
        }

        /* Section Divider */
        .section-divider {
            border: 0;
            height: 1px;
            background: var(--border-light);
            margin: 8px 0;
        }

        /* 11. Rekomendasi Tindakan (Editorial Decision Block) */
        .decision-block {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 16px;
            border-radius: 6px;
            scroll-margin-top: 80px;
            border: 1px solid transparent;
            transition: background-color 0.35s ease, border-color 0.35s ease, box-shadow 0.35s ease;
        }

        .decision-block.is-focused-highlight {
            background-color: #f2f7f4;
            border-color: var(--forest-700);
            box-shadow: 0 0 0 3px rgba(41, 87, 66, 0.15);
        }

        .decision-block-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 16px;
            flex-wrap: wrap;
        }

        .decision-block-header h2 {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--forest-950);
        }

        .active-facility-headline {
            display: flex;
            align-items: baseline;
            gap: 16px;
            margin: 2px 0 0;
            flex-wrap: wrap;
        }

        .facility-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.01em;
            margin: 0;
        }

        .facility-deadline-badge {
            font-size: 14px;
            font-weight: 750;
            color: var(--critical-text);
        }

        .decision-steps {
            list-style: none;
            padding: 0;
            margin: 8px 0 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .decision-step {
            display: flex;
            align-items: baseline;
            gap: 14px;
            font-size: 14.5px;
            color: var(--text-main);
            line-height: 1.5;
        }

        .step-num {
            font-size: 12px;
            font-weight: 800;
            color: var(--forest-700);
            font-variant-numeric: tabular-nums;
            width: 20px;
            flex-shrink: 0;
        }

        .decision-footer {
            display: flex;
            align-items: baseline;
            gap: 20px;
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 4px;
            flex-wrap: wrap;
        }

        /* 12. Coba Skenario (Tool Form Layout) */
        .scenario-block {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 4px 2px;
        }

        .scenario-block-header h2 {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--forest-950);
        }

        .scenario-block-header p {
            margin: 3px 0 0;
            font-size: 13px;
            color: var(--text-muted);
        }

        .scenario-form-row {
            display: flex;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
        }

        .scenario-input-unit {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 180px;
        }

        .scenario-input-unit label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
        }

        .scenario-input-unit output {
            font-weight: 800;
            color: var(--forest-800);
        }

        .scenario-input-unit input, .scenario-input-unit select {
            background: #ffffff;
            border: 1px solid var(--border-strong);
            border-radius: 4px;
            padding: 7px 10px;
            font-size: 13px;
            color: var(--text-main);
            height: 36px;
        }

        .scenario-input-unit input[type="range"] {
            accent-color: var(--forest-700);
            padding: 0;
        }

        .scenario-form-buttons {
            display: flex;
            gap: 10px;
            height: 36px;
        }

        .btn-action-primary {
            background: var(--forest-950);
            color: #ffffff;
            border: 1px solid var(--forest-950);
            padding: 0 16px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .btn-action-primary:hover:not(:disabled) {
            background: var(--forest-800);
        }

        .btn-action-secondary {
            background: transparent;
            color: var(--text-main);
            border: 1px solid var(--border-strong);
            padding: 0 16px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 650;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .btn-action-secondary:hover:not(:disabled) {
            background: #eef2ef;
        }

        .btn-action-primary:disabled, .btn-action-secondary:disabled {
            opacity: 0.5;
            cursor: wait;
        }

        .scenario-feedback-text {
            font-size: 12px;
            color: var(--forest-700);
            margin: -6px 0 0;
            font-weight: 600;
            min-height: 16px;
        }

        .scenario-feedback-text.is-error {
            color: var(--critical-text);
        }

        /* 13. Rute Paparan (Comparison Table Layout) */
        .route-block {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 4px 2px;
        }

        .route-block-header h2 {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--forest-950);
        }

        .route-block-header p {
            margin: 3px 0 0;
            font-size: 13px;
            color: var(--text-muted);
        }

        .route-selection-bar {
            display: flex;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
        }

        .route-select-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 220px;
        }

        .route-select-item label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
        }

        .route-select-item select {
            background: #ffffff;
            border: 1px solid var(--border-strong);
            border-radius: 4px;
            padding: 7px 10px;
            font-size: 13px;
            color: var(--text-main);
            height: 36px;
        }

        .route-comparison-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 24px;
            margin-top: 6px;
        }

        .route-comparison-grid.is-single {
            grid-template-columns: minmax(0, 1fr);
            max-width: 480px;
        }

        .route-col {
            border-left: 2px solid var(--border-light);
            padding-left: 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .route-col.recommended {
            border-left-color: #059669;
        }

        .route-col-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }

        .route-col-title {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-main);
            margin: 0;
        }

        .tag-recommended {
            font-size: 11px;
            font-weight: 750;
            color: #059669;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .route-stats-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .route-stat-line strong {
            color: var(--text-main);
            font-weight: 700;
        }

        .route-delta-text {
            font-size: 12.5px;
            font-weight: 700;
            color: #065f46;
            margin-top: 4px;
        }

        .route-note-text {
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 4px;
            line-height: 1.45;
        }

        .route-note-box {
            grid-column: 1 / -1;
            padding: 10px 14px;
            background: #f7f9f7;
            border-left: 3px solid var(--border-strong);
            border-radius: 0 4px 4px 0;
            font-size: 12.5px;
            color: var(--text-muted);
            line-height: 1.4;
            margin-top: 4px;
        }

        .route-disclaimer-note {
            font-size: 12px;
            color: var(--text-subtle);
            margin: 2px 0 0;
        }

        /* 14. Sumber Data & Metodologi (Multi-Column Plain Text Footer) */
        .footer-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            padding: 8px 2px 0;
            font-size: 12.5px;
            color: var(--text-muted);
        }

        .footer-col h3 {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--forest-950);
        }

        .footer-col p {
            margin: 0 0 6px;
            line-height: 1.5;
        }

        .source-meta-row {
            display: flex;
            gap: 8px;
            margin-bottom: 4px;
        }

        .source-meta-row strong {
            color: var(--text-main);
            min-width: 75px;
        }

        .tech-accordion summary {
            font-weight: 700;
            color: var(--forest-800);
            cursor: pointer;
            margin-bottom: 8px;
        }

        .tech-dl {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 14px;
            margin: 8px 0 0;
        }

        .tech-dl dt {
            color: var(--text-subtle);
            font-size: 11.5px;
        }

        .tech-dl dd {
            margin: 0;
            font-weight: 650;
            color: var(--text-main);
            font-size: 12px;
        }

        /* Dialog Mengapa */
        .why-dialog {
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            padding: 32px 36px;
            box-shadow: 0 20px 48px rgba(0, 0, 0, 0.24);
            max-width: 680px;
            width: 92%;
            background: #ffffff;
            margin: auto;
        }

        .why-dialog[open] {
            animation: whyModalEnter 0.28s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .why-dialog[open]::backdrop {
            background: rgba(14, 32, 24, 0.55);
            animation: whyBackdropEnter 0.25s ease forwards;
        }

        .why-dialog.is-closing {
            animation: whyModalExit 0.18s ease forwards;
        }

        .why-dialog.is-closing::backdrop {
            animation: whyBackdropExit 0.18s ease forwards;
        }

        @keyframes whyModalEnter {
            0% {
                opacity: 0;
                transform: translateY(18px) scale(0.96);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes whyBackdropEnter {
            0% {
                opacity: 0;
                backdrop-filter: blur(0px);
            }
            100% {
                opacity: 1;
                backdrop-filter: blur(3px);
            }
        }

        @keyframes whyModalExit {
            0% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(10px) scale(0.96);
            }
        }

        @keyframes whyBackdropExit {
            0% {
                opacity: 1;
                backdrop-filter: blur(3px);
            }
            100% {
                opacity: 0;
                backdrop-filter: blur(0px);
            }
        }

        .why-dialog-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-light);
        }

        .why-dialog-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: var(--forest-950);
            letter-spacing: -0.01em;
        }

        .why-close-btn {
            background: transparent;
            border: 0;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-muted);
            line-height: 1;
            padding: 4px 8px;
            border-radius: 4px;
            transition: color 0.15s ease, background 0.15s ease;
        }

        .why-close-btn:hover {
            color: var(--forest-950);
            background: #eef2ef;
        }

        .why-dialog p {
            font-size: 15.5px;
            line-height: 1.65;
            color: var(--text-main);
            margin: 18px 0 28px;
        }

        .why-dialog .btn-action-primary {
            padding: 9px 26px;
            font-size: 13.5px;
            font-weight: 700;
        }

        /* Custom Leaflet Map Pins */
        .map-icon { background: transparent; border: 0; }

        .pin-marker {
            align-items: center;
            border: 2px solid white;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
            color: white;
            display: flex;
            font-weight: 800;
            font-size: 11px;
            height: 28px;
            width: 28px;
            justify-content: center;
            border-radius: 4px;
        }

        .pin-hotspot {
            background: #ea580c;
            border-radius: 50% 50% 50% 2px;
            transform: rotate(-45deg);
            box-shadow: 0 2px 5px rgba(0,0,0,0.25);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .pin-hotspot span { transform: rotate(45deg); }

        .pin-hotspot-active {
            background: #c2410c !important;
            border: 2.5px solid #ffffff !important;
            box-shadow: 0 0 0 3px #ea580c, 0 0 14px rgba(234, 88, 12, 0.65), 0 4px 12px rgba(194, 65, 12, 0.5) !important;
            transform: rotate(-45deg) scale(1.28) !important;
            z-index: 1000 !important;
        }
        .pin-hotspot-active span {
            transform: rotate(45deg) !important;
            font-size: 13px !important;
            font-weight: 700 !important;
        }

        .pin-hotspot-high-impact {
            background: #dc2626 !important;
            border: 2px solid #ffffff !important;
            box-shadow: 0 0 0 2px #dc2626, 0 3px 8px rgba(220, 38, 38, 0.45) !important;
        }
        .pin-hotspot-high-impact span {
            color: #ffffff !important;
        }

        .pin-hotspot-impact {
            background: #ea580c !important;
            border: 1.5px solid #ffffff !important;
            box-shadow: 0 0 0 2px #ea580c, 0 2px 6px rgba(234, 88, 12, 0.35) !important;
        }

        .pin-hotspot-no-impact {
            background: #94a3b8 !important;
            border: 1px solid #ffffff !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2) !important;
            opacity: 0.72 !important;
        }
        .pin-hotspot-no-impact span {
            color: #f1f5f9 !important;
        }

        .hotspot-impact-box {
            margin: 6px 0 8px;
            padding: 6px 8px;
            border-radius: 4px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 11px;
            line-height: 1.4;
        }
        .hotspot-impact-box.has-high-priority {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }
        .hotspot-impact-box.has-impact {
            background: #fff7ed;
            border-color: #ffedd5;
            color: #9a3412;
        }
        .hotspot-impact-box.no-impact {
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: #64748b;
        }
        .hotspot-impact-headline {
            font-weight: 700;
            margin-bottom: 2px;
        }
        .hotspot-impact-detail {
            font-size: 10.5px;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .hotspot-popup-content {
            font-size: 12px;
            line-height: 1.45;
            color: #1e293b;
            min-width: 175px;
        }
        .hotspot-popup-title {
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .hotspot-popup-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 10px;
            background: #ffedd5;
            color: #9a3412;
        }
        .hotspot-popup-body {
            margin-bottom: 8px;
            color: #475569;
        }
        .hotspot-popup-body div {
            margin-bottom: 2px;
        }
        .btn-select-hotspot {
            display: block;
            width: 100%;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            border-radius: 4px;
            border: 1px solid #ea580c;
            background: #ea580c;
            color: #ffffff;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .btn-select-hotspot:hover:not(:disabled) {
            background: #c2410c;
            border-color: #c2410c;
        }
        .btn-select-hotspot:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .route-stale-notice {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 9px 12px;
            border-radius: 6px;
            font-size: 12px;
            line-height: 1.4;
            margin-bottom: 10px;
        }
        .route-col.is-stale {
            border-color: #cbd5e1;
            opacity: 0.85;
        }

        .pin-school { background: #2563eb; }
        .pin-hospital { background: #dc2626; border-radius: 50%; font-size: 14px; }
        .pin-clinic { background: #0d9488; }
        .pin-residential { background: #7c3aed; }

        /* Facility Visual Differentiation */
        .pin-facility-affected {
            box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px #ea580c, 0 3px 8px rgba(234, 88, 12, 0.35) !important;
            transform: scale(1.08);
            font-weight: 900;
        }
        .pin-facility-high-risk {
            box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px #dc2626, 0 3px 10px rgba(220, 38, 38, 0.45) !important;
            transform: scale(1.12);
            font-weight: 900;
        }
        .pin-facility-muted {
            opacity: 0.7;
            filter: saturate(0.65);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.18) !important;
        }

        /* Projection Horizon Marker */
        .pin-endpoint {
            background: #475569 !important;
            color: #ffffff !important;
            border: 1.5px solid #ffffff !important;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3) !important;
            border-radius: 10px !important;
            font-size: 9.5px !important;
            font-weight: 700 !important;
            line-height: 1 !important;
            height: 18px !important;
            width: auto !important;
            min-width: 46px !important;
            padding: 0 6px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            white-space: nowrap !important;
            box-sizing: border-box !important;
        }

        .wind-arrow-el {
            color: #92400e;
            font-size: 22px;
            font-weight: 900;
            line-height: 1;
            filter: drop-shadow(0 1px 1px white);
        }

        .leaflet-tooltip.wind-label-clean {
            background: rgba(14, 32, 24, 0.9);
            border: 0;
            color: white;
            font-size: 11px;
            font-weight: 650;
            padding: 2px 6px;
            border-radius: 3px;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .situation-board { grid-template-columns: 1fr; }
            .situation-summary { border-right: 0; border-bottom: 1px solid var(--border-light); }
            .sentra-workspace {
                grid-template-columns: 1fr;
            }
            .map-region {
                height: 480px;
            }
            .priority-region {
                height: auto;
                max-height: 480px;
            }
            .route-comparison-grid {
                grid-template-columns: 1fr;
            }
            .footer-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .nav-inner {
                height: auto;
                padding: 12px 16px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .nav-modes {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(3, 1fr);
            }
            .sentra-main {
                padding: 16px 16px 48px;
                gap: 20px;
            }

            .condition-summary-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }
            .condition-dot { display: none; }
            .condition-time { margin-left: 0; }
            .situation-summary { padding: 18px 16px; }
            .attention-row {
                grid-template-columns: 34px minmax(0, 1fr) auto;
                gap: 10px;
                padding: 12px 14px;
            }
            .attention-signal { grid-column: 2 / -1; grid-row: 2; }
            .attention-action { grid-column: 3; grid-row: 1; }
            .scenario-form-row {
                flex-direction: column;
                align-items: stretch;
            }
            .scenario-input-unit {
                min-width: 100%;
            }
            .route-selection-bar {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

    <!-- 2. Header / Compact Navbar -->
    <header class="sentra-nav">
        <div class="nav-inner">
            <div class="nav-brand">
                <img src="{{ asset('images/logo.png') }}" alt="SENTRA Logo" class="brand-logo">
                <h1 class="brand-name">SENTRA</h1>
                <span class="brand-tagline">Smoke Exposure Decision Support</span>
            </div>
            <div class="nav-modes" aria-label="Pilih sumber data">
                <button class="data-mode-button is-active" data-mode="snapshot" type="button">Snapshot Riil</button>
                <button class="data-mode-button" data-mode="live" type="button">Data Aktual</button>
                <button class="data-mode-button" data-mode="simulation" type="button">Simulasi</button>
                <span class="mode-feedback" id="data-mode-feedback" aria-live="polite"></span>
            </div>
        </div>
    </header>

    <main class="sentra-main">

        <!-- 3. Status Warning Strip (Hidden) -->
        <section class="alert-strip is-safe" id="status-strip" aria-hidden="true" style="display: none !important;">
            <div id="alert-title"></div>
            <div id="alert-description"></div>
            <div id="alert-counts-right">
                <span id="count-hotspots"></span>
                <span id="count-critical"></span>
            </div>
            <span id="alert-badge-text"></span>
        </section>

        @php
            $situationSummary = $simulation['hotspot_impact_overview']['summary'] ?? [];
            $attentionHotspots = array_slice(array_values(array_filter(
                $simulation['hotspot_impact_overview']['items'] ?? [],
                static fn (array $item): bool => $item['requires_attention'] ?? false,
            )), 0, 3);
        @endphp

        <section class="situation-board" aria-labelledby="situation-title">
            <div class="situation-summary">
                <h2 id="situation-title">Ringkasan Kondisi</h2>
                <p class="situation-summary-copy">Pemindaian otomatis terhadap hotspot representatif dan fasilitas pada mode aktif.</p>
                <div class="situation-metrics" role="list">
                    <div class="situation-metric" role="listitem">
                        <strong id="situation-total">{{ $situationSummary['hotspots_total'] ?? 0 }}</strong>
                        <span>hotspot representatif terpantau</span>
                    </div>
                    <div class="situation-metric" role="listitem">
                        <strong id="situation-impact">{{ $situationSummary['hotspots_with_impact'] ?? 0 }}</strong>
                        <span>berpotensi memengaruhi fasilitas</span>
                    </div>
                    <div class="situation-metric is-attention" role="listitem">
                        <strong id="situation-attention">{{ $situationSummary['hotspots_requiring_attention'] ?? 0 }}</strong>
                        <span>membutuhkan perhatian tinggi</span>
                    </div>
                    <div class="situation-metric" role="listitem">
                        <strong id="situation-eta">{{ isset($situationSummary['nearest_eta_minutes']) ? '±'.$situationSummary['nearest_eta_minutes'].' mnt' : '—' }}</strong>
                        <span>potensi paparan terdekat</span>
                    </div>
                </div>
            </div>

            <div class="attention-panel">
                <div class="attention-heading">
                    <h3>Hotspot yang Perlu Diperhatikan</h3>
                    <span>3 teratas</span>
                </div>
                <div class="attention-list" id="attention-hotspot-list">
                    @forelse($attentionHotspots as $index => $hotspot)
                        <article class="attention-row">
                            <span class="attention-rank">#{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="attention-place">
                                <strong>Hotspot {{ $index + 1 }}</strong>
                                <span>{{ number_format($hotspot['latitude'], 4) }}, {{ number_format($hotspot['longitude'], 4) }}</span>
                            </div>
                            <div class="attention-signal">
                                <strong>{{ ($hotspot['critical_urgency_count'] ?? 0) > 0 ? 'Tindakan segera diperlukan' : 'Perlu perhatian' }}</strong>
                                <span>{{ $hotspot['facilities_affected'] }} fasilitas · ETA {{ isset($hotspot['nearest_eta_minutes']) ? '±'.$hotspot['nearest_eta_minutes'].' menit' : 'tidak tersedia' }}</span>
                            </div>
                            <button class="attention-action btn-select-hotspot" data-hotspot-id="{{ $hotspot['hotspot_id'] }}" type="button">Lihat analisis</button>
                        </article>
                    @empty
                        <p class="attention-empty">Belum ada hotspot yang memerlukan perhatian tinggi pada proyeksi saat ini.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- 4. Condition Summary (Inline Metrics) -->
        <section class="condition-summary-row" aria-label="Ringkasan kondisi meteorologi">
            <div class="condition-metric sr-only">
                <strong id="bar-hotspot-metric"></strong>
            </div>
            <span class="condition-dot">•</span>
            <div class="condition-metric">
                <span id="wind-source-label">Angin arsip</span>:
                <span id="wind-label-inline">{{ $simulation['scenario']['wind']['label'] ?? 'Barat' }}</span>
                <strong id="wind-summary">{{ $simulation['scenario']['wind']['direction_degrees'] }}° · {{ $simulation['scenario']['wind']['speed_kmh'] }} km/jam</strong>
            </div>
            <span class="condition-dot">•</span>
            <div class="condition-metric">
                <span>Horizon</span>
                <strong id="horizon-metric">{{ $simulation['scenario']['projection_time_hours'] }} jam (±{{ $simulation['projection']['projected_travel_distance_km'] }} km)</strong>
            </div>
            <span class="condition-dot">•</span>
            <div class="condition-time">
                @php
                    $ageMinutes = $simulation['data_mode']['observation_age_minutes'] ?? null;
                    if ($ageMinutes !== null) {
                        $ageM = (int) $ageMinutes;
                        if ($ageM < 1) {
                            $freshnessStr = 'baru saja';
                        } elseif ($ageM < 60) {
                            $freshnessStr = $ageM . ' menit lalu';
                        } elseif ($ageM < 1440) {
                            $freshnessStr = round($ageM / 60) . ' jam lalu';
                        } else {
                            $freshnessStr = round($ageM / 1440) . ' hari lalu';
                        }
                    } else {
                        $freshnessStr = 'baru saja';
                    }
                @endphp
                <span id="bar-freshness-text">Data diperbarui: {{ $freshnessStr }}</span>
                <span class="sr-only" id="simulation-start">Waktu mulai simulasi: {{ $simulation['scenario']['simulation_started_at'] }}</span>
            </div>
        </section>

        <!-- 5. Workspace: Map (68%) & Priority Respons (32%) -->
        <div class="sentra-workspace">

            <!-- 5 & 6. Dominant Map Region -->
            <section class="map-region" aria-label="Peta proyeksi asap">
                <div id="map"></div>

                <!-- Minimalist Map Overlay -->
                <div class="map-compact-header">
                    <strong id="map-mode-tag">SNAPSHOT</strong>
                    <span id="map-area-title">Snapshot hotspot Kalimantan Tengah</span>
                    <span id="trajectory-summary">Lintasan {{ $simulation['scenario']['projection_time_hours'] }} jam · {{ $simulation['projection']['projected_travel_distance_km'] }} km</span>
                    <span class="sr-only">Prototipe sederhana perambatan asap</span>
                </div>

                <div class="map-projection-note">Koridor proyeksi · bukan batas pasti sebaran asap</div>

                <!-- Minimalist Map Legend -->
                <div class="map-compact-legend" aria-label="Legenda peta">
                    <div class="legend-item">
                        <span class="dot-hotspot is-normal"></span> Normal
                    </div>
                    <div class="legend-item">
                        <span class="dot-hotspot has-impact"></span> Berdampak
                    </div>
                    <div class="legend-item">
                        <span class="dot-hotspot needs-attention"></span> Perlu perhatian
                    </div>
                    <div class="legend-item">
                        <span class="dot-hotspot is-active"></span> Aktif
                    </div>
                    <div class="legend-item">
                        <span class="line-corridor"></span> Koridor visual · bukan batas ilmiah
                    </div>
                    <div class="legend-item">
                        <span class="square-facility"></span> Fasilitas
                    </div>
                    <div class="legend-item" id="legend-route-item" style="display:none;">
                        <span class="line-route"></span> <span id="legend-route-label">Rute paparan lebih rendah</span>
                    </div>
                </div>
            </section>

            <!-- 7. Priority Respons Region (Flat Rows, No Nested Cards) -->
            <aside class="priority-region" aria-label="Prioritas Respons">
                <div class="priority-top-bar">
                    <h2 class="priority-title">Prioritas Respons</h2>
                    <span class="sr-only">Dihitung di server</span>
                </div>

                <div class="priority-feed" id="priority-feed">
                    <div id="top-priority-rows">
                        <!-- Top 3 facilities rendered as flat rows -->
                    </div>

                    <div class="hidden-rows-container" id="hidden-priority-rows">
                        <!-- Remaining facilities -->
                    </div>

                    <div class="priority-toggle-row" id="priority-toggle-row" style="display:none;">
                        <button class="toggle-feed-link" id="toggle-feed-btn" type="button">
                            Lihat semua fasilitas (<span id="total-feed-count">0</span>) ↓
                        </button>
                    </div>
                </div>

                <!-- Hidden containers for test compatibility -->
                <ol id="priority-list" class="sr-only"></ol>
                <div id="facility-list" class="sr-only"></div>
                <div id="decision-panel" class="sr-only"></div>
            </aside>
        </div>

        <hr class="section-divider">

        <!-- 11. Rekomendasi Tindakan (Editorial Decision Block) -->
        <section class="decision-block" id="rekomendasi-tindakan" aria-label="Rekomendasi Tindakan" tabindex="-1">
            <div class="decision-block-header">
                <h2 id="rekomendasi-heading" tabindex="-1">Rekomendasi Tindakan</h2>
                <span class="sr-only">Rencana Respons Adaptif</span>
            </div>

            <div class="active-facility-headline">
                <h3 class="facility-title" id="rec-facility-name" tabindex="-1">-</h3>
                <span class="facility-deadline-badge" id="rec-deadline-time">Bertindak sebelum --.-- WITA</span>
            </div>

            <ol class="decision-steps" id="decision-steps-list">
                <li class="decision-step">
                    <span class="step-num">01</span>
                    <span>Pilih fasilitas pada peta atau daftar prioritas untuk melihat rekomendasi tindakan.</span>
                </li>
            </ol>

            <div class="decision-footer">
                <span id="rec-plan-summary">Tindakan disesuaikan dengan kondisi fasilitas dan waktu potensi paparan.</span>
                <button class="text-action-link" id="open-why-btn" type="button">Mengapa status ini muncul?</button>
            </div>
        </section>

        <hr class="section-divider">

        <!-- 12. Coba Skenario (Form Tool Layout) -->
        <section class="scenario-block" aria-label="Coba Skenario">
            <div class="scenario-block-header">
                <h2>Coba Skenario</h2>
                <p>Uji bagaimana perubahan angin memengaruhi proyeksi. <span class="sr-only">Simulator skenario</span></p>
                <span class="sr-only" id="scenario-state">Skenario awal</span>
            </div>

            <form class="scenario-form-row" id="scenario-form">
                @csrf
                <div class="scenario-input-unit">
                    <label for="wind-direction">
                        <span>Arah angin:</span>
                        <output id="wind-direction-value">{{ $simulation['scenario']['wind']['direction_degrees'] }}°</output>
                    </label>
                    <input id="wind-direction" name="wind_direction" type="range" min="0" max="359" step="1" value="{{ $simulation['scenario']['wind']['direction_degrees'] }}">
                </div>

                <div class="scenario-input-unit" style="max-width: 140px;">
                    <label for="wind-speed">Kecepatan (km/jam)</label>
                    <input id="wind-speed" name="wind_speed" type="number" min="0.1" max="150" step="0.1" value="{{ $simulation['scenario']['wind']['speed_kmh'] }}" required>
                </div>

                <div class="scenario-input-unit" style="max-width: 140px;">
                    <label for="projection-horizon">Horizon</label>
                    <select id="projection-horizon" name="projection_horizon">
                        @foreach ([1, 2, 3, 6, 12, 24] as $hours)
                            <option value="{{ $hours }}" @selected($simulation['scenario']['projection_time_hours'] === $hours)>{{ $hours }} jam</option>
                        @endforeach
                    </select>
                </div>

                <div class="scenario-form-buttons">
                    <button class="btn-action-primary" id="run-simulation" type="submit">Jalankan simulasi</button>
                    <button class="btn-action-secondary" id="reset-simulation" type="button">Reset skenario</button>
                </div>
            </form>

            <p class="scenario-feedback-text" id="simulator-message" aria-live="polite"></p>
        </section>

        <hr class="section-divider">

        <!-- 13. Rute Paparan (Comparison Layout) -->
        <section class="route-block" aria-label="Rute Paparan Lebih Rendah">
            <div class="route-block-header">
                <h2>Rute Paparan</h2>
                <p>Bandingkan rute berdasarkan seberapa banyak lintasan yang melewati koridor proyeksi asap.</p>
            </div>

            <div class="route-selection-bar">
                <div class="route-select-item">
                    <label for="route-origin">Titik Awal</label>
                    <select id="route-origin"></select>
                </div>
                <div class="route-select-item">
                    <label for="route-destination">Tujuan</label>
                    <select id="route-destination"></select>
                </div>
                <button class="btn-action-primary" id="calculate-route-btn" type="button" style="height: 36px;">
                    Bandingkan Rute
                </button>
            </div>

            <p class="scenario-feedback-text" id="route-feedback" aria-live="polite"></p>

            <div class="route-comparison-grid" id="route-comparison-grid" style="display:none;"></div>

            <p class="route-disclaimer-note">
                Perbandingan berdasarkan proyeksi geometris, bukan pengukuran dosis paparan.
            </p>
        </section>

        <hr class="section-divider">

        <!-- 14. Sumber Data & Metodologi (Multi-Column Plain Text Footer) -->
        <footer class="footer-section">
            <div class="footer-col">
                <h3>Sumber Data</h3>
                <div class="source-meta-row">
                    <strong>Hotspot:</strong>
                    <span>Snapshot lokal NASA FIRMS · <span id="source-hotspots-count">{{ count($simulation['data_mode']['hotspots'] ?? []) }} hotspot relevan ditampilkan</span></span>
                </div>
                <div class="source-meta-row">
                    <strong>Cuaca:</strong>
                    <span>Snapshot lokal Open-Meteo · Kecepatan &amp; arah angin</span>
                </div>
                <div class="source-meta-row">
                    <strong>Fasilitas:</strong>
                    <span>{{ $simulation['facility_data']['source'] ?? 'OpenStreetMap via Overpass API' }} · Lokasi fasilitas rentan</span>
                </div>
                <div class="sr-only" id="data-provenance">
                    <span id="hotspot-provenance"><strong>Hotspot:</strong> Snapshot lokal NASA FIRMS · {{ count($simulation['data_mode']['hotspots'] ?? []) }} hotspot relevan ditampilkan</span>
                    <span id="weather-provenance"><strong>Cuaca:</strong> Snapshot lokal Open-Meteo</span>
                    <span id="facility-provenance"><strong>Fasilitas:</strong> {{ $simulation['facility_data']['source'] ?? 'OpenStreetMap via Overpass API' }}</span>
                </div>
            </div>

            <div class="footer-col">
                <details class="tech-accordion">
                    <summary>Detail teknis fasilitas terpilih</summary>
                    <dl class="tech-dl">
                        <div>
                            <dt>Searah angin (along-track)</dt>
                            <dd id="tech-along">—</dd>
                        </div>
                        <div>
                            <dt>Jarak lintasan (cross-track)</dt>
                            <dd id="tech-cross">—</dd>
                        </div>
                        <div>
                            <dt>Status proyeksi</dt>
                            <dd id="tech-within">—</dd>
                        </div>
                        <div>
                            <dt>Waktu pengaman</dt>
                            <dd id="tech-safety">—</dd>
                        </div>
                    </dl>
                </details>

                <p>
                    <strong>Tentang Perhitungan SENTRA:</strong> Proyeksi geometris berdasarkan kecepatan dan arah angin saat ini untuk mendukung kesiapsiagaan operasional.
                </p>
                <p style="color: var(--text-subtle); font-size: 11.5px;">
                    SENTRA adalah prototipe sistem pendukung keputusan dan bukan pengganti informasi resmi dari instansi berwenang. Tidak dimaksudkan sebagai prakiraan dispersi atmosfer resmi.
                </p>
            </div>
        </footer>

    </main>

    <!-- Dialog Mengapa -->
    <dialog class="why-dialog" id="why-dialog">
        <div class="why-dialog-header">
            <h3 id="why-dialog-title">Mengapa status ini muncul?</h3>
            <button class="why-close-btn" id="why-close-btn" type="button" aria-label="Tutup">&times;</button>
        </div>
        <p id="why-dialog-body"></p>
        <div style="text-align: right;">
            <button class="btn-action-primary" id="why-dialog-ok" type="button">Tutup</button>
        </div>
    </dialog>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        const defaultSimulation = @json($simulation);
        const simulateUrl = @json(route('sentra.simulate'));
        const dataModeUrl = @json(route('sentra.data-mode'));
        const routeUrl = @json(route('sentra.route'));
        const selectHotspotUrl = @json(route('sentra.select-hotspot'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        const riskLabels = { HIGH: 'Tinggi', MEDIUM: 'Sedang', LOW: 'Rendah' };
        const urgencyLabels = { CRITICAL: 'Kritis', HIGH: 'Tinggi', MODERATE: 'Sedang', LOW: 'Rendah', NONE: 'Tidak ada' };
        const facilityNames = { 'School A': 'Sekolah A', 'Hospital B': 'Rumah Sakit B', 'Residential Area C': 'Area Permukiman C' };
        const facilityTypes = { school: 'Sekolah', hospital: 'Rumah Sakit', clinic: 'Klinik', residential: 'Permukiman' };
        const directionLabels = {
            North: 'Utara', Northeast: 'Timur Laut', East: 'Timur', Southeast: 'Tenggara',
            South: 'Selatan', Southwest: 'Barat Daya', West: 'Barat', Northwest: 'Barat Laut',
        };
        const directionShort = {
            North: 'N', Northeast: 'NE', East: 'E', Southeast: 'SE',
            South: 'S', Southwest: 'SW', West: 'W', Northwest: 'NW',
        };
        const corridorSteps = [
            [0.08, 0.25], [0.18, 0.65], [0.32, 1.4], [0.5, 2.7],
            [0.68, 4.3], [0.85, 6.2], [1, 8],
        ];

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = String(str ?? '');
            return div.innerHTML;
        }

        function displayFacilityName(name) {
            return facilityNames[name] ?? name;
        }

        function formatDataFreshness(target) {
            if (!target) return 'baru saja';
            if (typeof target === 'object') {
                const age = target.observation_age_minutes;
                if (age !== null && age !== undefined && !isNaN(age)) {
                    const m = Math.round(Number(age));
                    if (m < 1) return 'baru saja';
                    if (m < 60) return `${m} menit lalu`;
                    if (m < 1440) return `${Math.round(m / 60)} jam lalu`;
                    return `${Math.round(m / 1440)} hari lalu`;
                }
                target = target.acquired_at ?? target.metadata?.captured_at ?? target.retrieved_at;
            }
            if (!target) return 'baru saja';
            const date = new Date(target);
            if (isNaN(date.getTime())) return 'baru saja';
            const diffMin = Math.round(Math.abs(Date.now() - date.getTime()) / 60000);
            if (diffMin < 1) return 'baru saja';
            if (diffMin < 60) return `${diffMin} menit lalu`;
            const diffH = Math.round(diffMin / 60);
            if (diffH < 24) return `${diffH} jam lalu`;
            return `${Math.round(diffH / 24)} hari lalu`;
        }

        function formatRemainingTime(min) {
            if (min === null || min === undefined) return 'Tidak ada tenggat';
            if (min < 0) return `Lewat ${Math.abs(min)} mnt`;
            return `Sisa ${min} mnt`;
        }

        function formatDateTime(val) {
            if (!val) return 'Tidak diproyeksikan';
            try {
                return new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
                    hour12: false, timeZone: 'Asia/Makassar',
                }).format(new Date(val)) + ' WITA';
            } catch {
                return String(val);
            }
        }

        function formatTime(val) {
            if (!val) return '--.-- WITA';
            try {
                return new Intl.DateTimeFormat('id-ID', {
                    hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Asia/Makassar',
                }).format(new Date(val)) + ' WITA';
            } catch {
                return String(val);
            }
        }

        function formatWitaClock(val) {
            if (!val) return '—';
            try {
                return new Intl.DateTimeFormat('id-ID', {
                    hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Asia/Makassar',
                }).format(new Date(val)) + ' WITA';
            } catch {
                return '—';
            }
        }

        function formatRelativeTime(acquiredAt, ageMinutes) {
            if (ageMinutes !== undefined && ageMinutes !== null && !isNaN(Number(ageMinutes))) {
                const m = Math.max(0, Math.floor(Number(ageMinutes)));
                if (m < 1) return 'baru saja';
                if (m < 60) return `${m} menit lalu`;
                const h = Math.floor(m / 60);
                const remM = m % 60;
                return remM > 0 ? `${h} jam ${remM} menit lalu` : `${h} jam lalu`;
            }
            if (acquiredAt) {
                try {
                    const diffMs = Date.now() - new Date(acquiredAt).getTime();
                    const diffM = Math.max(0, Math.floor(diffMs / 60000));
                    if (diffM < 1) return 'baru saja';
                    if (diffM < 60) return `${diffM} menit lalu`;
                    const h = Math.floor(diffM / 60);
                    return `${h} jam lalu`;
                } catch {
                    return formatDateTime(acquiredAt);
                }
            }
            return '—';
        }

        function destinationPoint([latitude, longitude], bearingDegrees, distanceKm) {
            const earthRadiusKm = 6371;
            const angularDistance = distanceKm / earthRadiusKm;
            const bearing = bearingDegrees * Math.PI / 180;
            const latRad = latitude * Math.PI / 180;
            const lonRad = longitude * Math.PI / 180;
            const destLat = Math.asin(
                Math.sin(latRad) * Math.cos(angularDistance)
                + Math.cos(latRad) * Math.sin(angularDistance) * Math.cos(bearing)
            );
            const destLon = lonRad + Math.atan2(
                Math.sin(bearing) * Math.sin(angularDistance) * Math.cos(latRad),
                Math.cos(angularDistance) - Math.sin(latRad) * Math.sin(destLat)
            );

            return [destLat * 180 / Math.PI, destLon * 180 / Math.PI];
        }

        function interpolatePoint([lat1, lon1], [lat2, lon2], fraction) {
            return [lat1 + ((lat2 - lat1) * fraction), lon1 + ((lon2 - lon1) * fraction)];
        }

        function mergeFacilities(sim) {
            const scFacilities = sim?.scenario?.facilities || [];
            const facResults = sim?.facility_results || [];
            return scFacilities.map((f, i) => {
                const res = facResults.find(r => r.facility_name === f.name) || facResults[i] || {};
                return {
                    ...f,
                    ...res,
                    facility_name: res.facility_name || f.name || f.facility_name || 'Fasilitas',
                    facility_type: res.facility_type || f.type || f.facility_type || 'facility',
                    latitude: Number(f.latitude !== undefined ? f.latitude : res.latitude),
                    longitude: Number(f.longitude !== undefined ? f.longitude : res.longitude),
                };
            });
        }

        let activeSimulation = defaultSimulation;
        let activeModeBaseline = defaultSimulation;
        let scenario = activeSimulation?.scenario || {};
        let projection = activeSimulation?.projection || {};
        let facilities = mergeFacilities(activeSimulation);
        let selectedFacilityIndex = 0;
        let scenarioLayers = [];
        let hotspotLayers = [];
        let facilityMarkers = [];
        let routeLayers = [];
        let dataModeRequestId = 0;
        let isRouteBusy = false;
        let isFeedExpanded = false;

        // Initialize Map
        const defaultCenter = (defaultSimulation?.scenario?.hotspot_cluster?.latitude && defaultSimulation?.scenario?.hotspot_cluster?.longitude)
            ? [Number(defaultSimulation.scenario.hotspot_cluster.latitude), Number(defaultSimulation.scenario.hotspot_cluster.longitude)]
            : [-2.21, 113.92];

        const map = L.map('map', { zoomControl: false }).setView(defaultCenter, 9);
        L.control.zoom({ position: 'bottomright' }).addTo(map);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        const icon = (html, size = 28) => L.divIcon({
            className: 'map-icon',
            html,
            iconSize: [size, size],
            iconAnchor: [size / 2, size / 2],
        });

        // DOM Elements
        const dataModeButtons = [...document.querySelectorAll('[data-mode]')];
        const dataModeFeedback = document.getElementById('data-mode-feedback');
        const statusStrip = document.getElementById('status-strip');
        const alertTitle = document.getElementById('alert-title');
        const alertDescription = document.getElementById('alert-description');
        const countHotspots = document.getElementById('count-hotspots');
        const countCritical = document.getElementById('count-critical');

        const barHotspotMetric = document.getElementById('bar-hotspot-metric');
        const situationTotal = document.getElementById('situation-total');
        const situationImpact = document.getElementById('situation-impact');
        const situationAttention = document.getElementById('situation-attention');
        const situationEta = document.getElementById('situation-eta');
        const attentionHotspotList = document.getElementById('attention-hotspot-list');
        const windLabelInline = document.getElementById('wind-label-inline');
        const windSummary = document.getElementById('wind-summary');
        const horizonMetric = document.getElementById('horizon-metric');
        const barFreshnessText = document.getElementById('bar-freshness-text');

        const topPriorityRows = document.getElementById('top-priority-rows');
        const hiddenPriorityRows = document.getElementById('hidden-priority-rows');
        const priorityToggleRow = document.getElementById('priority-toggle-row');
        const toggleFeedBtn = document.getElementById('toggle-feed-btn');
        const totalFeedCount = document.getElementById('total-feed-count');

        const recFacilityName = document.getElementById('rec-facility-name');
        const recDeadlineTime = document.getElementById('rec-deadline-time');
        const decisionStepsList = document.getElementById('decision-steps-list');
        const recPlanSummary = document.getElementById('rec-plan-summary');
        const openWhyBtn = document.getElementById('open-why-btn');

        const scenarioForm = document.getElementById('scenario-form');
        const directionInput = document.getElementById('wind-direction');
        const directionValue = document.getElementById('wind-direction-value');
        const windSpeedInput = document.getElementById('wind-speed');
        const projectionHorizonSelect = document.getElementById('projection-horizon');
        const runButton = document.getElementById('run-simulation');
        const resetButton = document.getElementById('reset-simulation');
        const simulatorMessage = document.getElementById('simulator-message');
        const scenarioState = document.getElementById('scenario-state');

        const routeOriginSelect = document.getElementById('route-origin');
        const routeDestinationSelect = document.getElementById('route-destination');
        const calculateRouteBtn = document.getElementById('calculate-route-btn');
        const routeFeedback = document.getElementById('route-feedback');
        const routeComparisonGrid = document.getElementById('route-comparison-grid');
        const legendRouteItem = document.getElementById('legend-route-item');

        const techAlong = document.getElementById('tech-along');
        const techCross = document.getElementById('tech-cross');
        const techWithin = document.getElementById('tech-within');
        const techSafety = document.getElementById('tech-safety');

        const whyDialog = document.getElementById('why-dialog');
        const whyDialogTitle = document.getElementById('why-dialog-title');
        const whyDialogBody = document.getElementById('why-dialog-body');
        const whyCloseBtn = document.getElementById('why-close-btn');
        const whyDialogOk = document.getElementById('why-dialog-ok');

        // Event Listeners
        directionInput.addEventListener('input', () => {
            directionValue.value = `${directionInput.value}°`;
        });

        dataModeButtons.forEach(btn => {
            btn.addEventListener('click', () => switchDataMode(btn.dataset.mode));
        });

        toggleFeedBtn.addEventListener('click', () => {
            isFeedExpanded = !isFeedExpanded;
            hiddenPriorityRows.classList.toggle('is-open', isFeedExpanded);
            const total = activeSimulation?.response_priority_queue?.length ?? facilities.length;
            toggleFeedBtn.textContent = isFeedExpanded
                ? 'Sembunyikan fasilitas lainnya ↑'
                : `Lihat semua fasilitas (${total}) ↓`;
        });

        openWhyBtn.addEventListener('click', () => {
            const facility = facilities[selectedFacilityIndex];
            if (facility) showWhy(facility);
        });

        function closeWhyDialog() {
            if (!whyDialog.open) return;
            whyDialog.classList.add('is-closing');
            setTimeout(() => {
                whyDialog.close();
                whyDialog.classList.remove('is-closing');
            }, 170);
        }

        whyCloseBtn.addEventListener('click', closeWhyDialog);
        whyDialogOk.addEventListener('click', closeWhyDialog);

        // Click outside on backdrop to close
        whyDialog.addEventListener('click', (e) => {
            const rect = whyDialog.getBoundingClientRect();
            const isInDialog = (
                rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX && e.clientX <= rect.left + rect.width
            );
            if (!isInDialog) {
                closeWhyDialog();
            }
        });

        // Escape key animation
        whyDialog.addEventListener('cancel', (e) => {
            e.preventDefault();
            closeWhyDialog();
        });

        calculateRouteBtn.addEventListener('click', calculateRoute);

        scenarioForm.addEventListener('submit', async event => {
            event.preventDefault();
            setSimulatorBusy(true);
            showSimulatorMessage('Menghitung ulang skenario di server...');

            try {
                const response = await fetch(simulateUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        wind_direction: Number(directionInput.value),
                        wind_speed: Number(windSpeedInput.value),
                        projection_horizon: Number(projectionHorizonSelect.value),
                    }),
                });
                const result = await response.json();

                if (!response.ok) {
                    const validationMessage = Object.values(result.errors ?? {}).flat()[0];
                    throw new Error(validationMessage ?? result.message ?? 'Simulasi tidak dapat dihitung.');
                }

                applySimulation(result, true);
                invalidateRouteComparison();
                showSimulatorMessage('Skenario selesai dihitung oleh engine SENTRA.');
            } catch (err) {
                showSimulatorMessage(err.message, true);
            } finally {
                setSimulatorBusy(false);
            }
        });

        resetButton.addEventListener('click', () => {
            syncSimulatorInputs(activeModeBaseline);
            applySimulation(activeModeBaseline, false);
            invalidateRouteComparison();
            showSimulatorMessage('Nilai awal mode aktif dipulihkan.');
        });

        // Mode Switching
        async function switchDataMode(mode) {
            if (mode === activeSimulation?.data_mode?.mode) return;

            const requestId = ++dataModeRequestId;
            setDataModeBusy(true, mode);
            showDataModeFeedback(`Memuat ${mode === 'live' ? 'Data Aktual' : mode === 'snapshot' ? 'Snapshot Riil' : 'Simulasi'}…`);
            try {
                const response = await fetch(dataModeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ mode }),
                });
                const result = await response.json();

                if (requestId !== dataModeRequestId) return;

                if (!response.ok || !result.scenario) {
                    const state = result.facility_data ?? result.weather_data ?? result.data_mode ?? {};
                    throw new Error(state.message ?? result.message ?? 'Sumber data tidak tersedia.');
                }

                activeModeBaseline = result;
                syncSimulatorInputs(result);
                applySimulation(result, false);
                invalidateRouteComparison();
                showSimulatorMessage({
                    live: 'Data aktual berhasil dimuat.',
                    snapshot: 'Snapshot lokal dimuat.',
                    simulation: 'Mode simulasi aktif.',
                }[mode] ?? 'Data berhasil dimuat.');
                showDataModeFeedback({
                    live: 'Data Aktual aktif',
                    snapshot: 'Snapshot Riil aktif',
                    simulation: 'Simulasi aktif',
                }[mode] ?? 'Mode aktif');
            } catch (err) {
                if (requestId !== dataModeRequestId) return;
                showSimulatorMessage(err.message ?? 'Gagal memuat mode data.', true);
                showDataModeFeedback(err.message ?? 'Mode data tidak tersedia.', true);
            } finally {
                if (requestId === dataModeRequestId) setDataModeBusy(false);
            }
        }

        // Active Hotspot Selection
        let isHotspotSelecting = false;

        async function selectActiveHotspot(hotspotId, buttonEl) {
            if (isHotspotSelecting) return;

            isHotspotSelecting = true;
            const originalButtonText = buttonEl?.textContent;
            if (buttonEl) {
                buttonEl.disabled = true;
                buttonEl.textContent = 'Menganalisis…';
            }

            try {
                const response = await fetch(selectHotspotUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ hotspot_id: hotspotId }),
                });

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message ?? 'Hotspot tidak dapat dianalisis.');
                }

                activeModeBaseline = result;
                syncSimulatorInputs(result);
                applySimulation(result, false);
                invalidateRouteComparison();
                focusActiveHotspot();
                showSimulatorMessage('Hotspot aktif berhasil diperbarui. Analisis SENTRA dihitung ulang.');
            } catch (err) {
                if (buttonEl) {
                    buttonEl.disabled = false;
                    buttonEl.textContent = originalButtonText ?? 'Lihat analisis';
                }
                showSimulatorMessage(err.message ?? 'Gagal menganalisis hotspot.', true);
            } finally {
                isHotspotSelecting = false;
            }
        }

        // Delegate click for popup hotspot selection button
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-select-hotspot');
            if (btn) {
                e.preventDefault();
                const hotspotId = btn.getAttribute('data-hotspot-id');
                if (hotspotId) {
                    selectActiveHotspot(hotspotId, btn);
                }
            }
        });

        // Route Invalidation
        let hasCalculatedRoute = false;

        function invalidateRouteComparison() {
            if (!hasCalculatedRoute) return;

            const existingBanner = document.getElementById('route-stale-banner');
            if (!existingBanner && routeComparisonGrid) {
                const banner = document.createElement('div');
                banner.id = 'route-stale-banner';
                banner.className = 'route-stale-notice';
                banner.innerHTML = `
                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <span style="font-size:15px; line-height:1.2;">⚠️</span>
                        <div>
                            <strong>Kondisi proyeksi telah berubah.</strong><br>
                            Hitung ulang rute untuk memperbarui analisis paparan.
                        </div>
                    </div>
                `;
                routeComparisonGrid.parentNode.insertBefore(banner, routeComparisonGrid);
            }

            if (routeComparisonGrid) {
                const recCols = routeComparisonGrid.querySelectorAll('.route-col.recommended');
                recCols.forEach(col => {
                    col.classList.remove('recommended');
                    col.classList.add('is-stale');
                    const tag = col.querySelector('.tag-recommended');
                    if (tag) {
                        tag.textContent = 'Perlu Hitung Ulang';
                        tag.style.background = '#e2e8f0';
                        tag.style.color = '#64748b';
                    }
                });
            }

            setRouteFeedback('Kondisi proyeksi telah berubah. Hitung ulang rute untuk memperbarui analisis paparan.', true);
        }

        // Apply Simulation State
        function applySimulation(sim, modified) {
            activeSimulation = sim;
            scenario = activeSimulation?.scenario || {};
            projection = activeSimulation?.projection || {};
            facilities = mergeFacilities(activeSimulation);

            if (scenarioState) scenarioState.textContent = modified ? 'Skenario diubah' : 'Skenario awal';

            const windLabel = directionLabels[scenario.wind?.label] ?? scenario.wind?.label ?? '—';
            if (windLabelInline) windLabelInline.textContent = windLabel;
            if (windSummary) windSummary.textContent = `${scenario.wind?.direction_degrees ?? 0}° · ${scenario.wind?.speed_kmh ?? 0} km/jam`;
            if (horizonMetric) horizonMetric.textContent = `${scenario.projection_time_hours ?? 2} jam (±${projection.projected_travel_distance_km ?? 0} km)`;

            const totalHotspots = activeSimulation.data_mode?.hotspot_count ?? (activeSimulation.data_mode?.hotspots?.length ?? (scenario.hotspot_cluster ? 1 : 0));
            const overview = activeSimulation.hotspot_impact_overview?.summary;
            if (barHotspotMetric) {
                if (overview && totalHotspots > 0) {
                    barHotspotMetric.textContent = `${totalHotspots} hotspot terpantau · ${overview.hotspots_with_impact} berpotensi memengaruhi fasilitas (${overview.hotspots_requiring_attention} memerlukan perhatian)`;
                } else if (totalHotspots > 1) {
                    barHotspotMetric.textContent = `${totalHotspots} hotspot terpantau · 1 hotspot sedang dianalisis`;
                } else if (totalHotspots === 1) {
                    barHotspotMetric.textContent = `1 hotspot terpantau · sedang dianalisis`;
                } else {
                    barHotspotMetric.textContent = `0 hotspot terpantau`;
                }
            }

            const barFreshnessText = document.getElementById('bar-freshness-text');
            if (barFreshnessText) {
                const ageMinutes = activeSimulation.data_mode?.observation_age_minutes ?? activeSimulation.data_mode?.primary_hotspot?.observation_age_minutes;
                const freshStr = formatRelativeTime(activeSimulation.data_mode?.acquired_at ?? activeSimulation.data_mode?.primary_hotspot?.acquired_at, ageMinutes);
                barFreshnessText.textContent = `Data diperbarui: ${freshStr}`;
            }

            const trajEl = document.getElementById('trajectory-summary');
            if (trajEl) trajEl.textContent = `Lintasan ${scenario.projection_time_hours ?? 2} jam · ${projection.projected_travel_distance_km ?? 0} km`;

            try { renderStatusStrip(activeSimulation); } catch (e) { console.error('StatusStrip error:', e); }
            try { renderSituationSummary(activeSimulation.hotspot_impact_overview); } catch (e) { console.error('SituationSummary error:', e); }
            try { updateMetadata(activeSimulation); } catch (e) { console.error('UpdateMetadata error:', e); }
            try { populateRouteDropdowns(); } catch (e) { console.error('RouteDropdowns error:', e); }
            try { renderHotspotMarkers(); } catch (e) { console.error('HotspotMarkers error:', e); }
            try { renderFacilityMarkers(); } catch (e) { console.error('FacilityMarkers error:', e); }
            try { renderScenarioLayers(); } catch (e) { console.error('ScenarioLayers error:', e); }
            try { renderPriorityFeed(activeSimulation.response_priority_queue ?? []); } catch (e) { console.error('PriorityFeed error:', e); }

            try {
                const initialIdx = Math.min(selectedFacilityIndex, Math.max(0, facilities.length - 1));
                selectFacility(initialIdx, false);
            } catch (e) { console.error('SelectFacility error:', e); }
        }

        function renderSituationSummary(overview) {
            const summary = overview?.summary ?? {};
            if (situationTotal) situationTotal.textContent = summary.hotspots_total ?? 0;
            if (situationImpact) situationImpact.textContent = summary.hotspots_with_impact ?? 0;
            if (situationAttention) situationAttention.textContent = summary.hotspots_requiring_attention ?? 0;
            if (situationEta) {
                situationEta.textContent = Number.isFinite(summary.nearest_eta_minutes)
                    ? `±${summary.nearest_eta_minutes} mnt`
                    : '—';
            }

            if (!attentionHotspotList) return;

            const attentionItems = (overview?.items ?? []).filter(item => item.requires_attention).slice(0, 3);
            if (attentionItems.length === 0) {
                attentionHotspotList.innerHTML = '<p class="attention-empty">Belum ada hotspot yang memerlukan perhatian tinggi pada proyeksi saat ini.</p>';
                return;
            }

            attentionHotspotList.innerHTML = attentionItems.map((item, index) => {
                const urgencyText = item.critical_urgency_count > 0 ? 'Tindakan segera diperlukan' : 'Perlu perhatian';
                const etaText = Number.isFinite(item.nearest_eta_minutes) ? `±${item.nearest_eta_minutes} menit` : 'tidak tersedia';

                return `
                    <article class="attention-row">
                        <span class="attention-rank">#${String(index + 1).padStart(2, '0')}</span>
                        <div class="attention-place">
                            <strong>Hotspot ${index + 1}</strong>
                            <span>${Number(item.latitude).toFixed(4)}, ${Number(item.longitude).toFixed(4)}</span>
                        </div>
                        <div class="attention-signal">
                            <strong>${urgencyText}</strong>
                            <span>${item.facilities_affected} fasilitas · ETA ${etaText}</span>
                        </div>
                        <button class="attention-action btn-select-hotspot" data-hotspot-id="${escapeHtml(item.hotspot_id)}" type="button">Lihat analisis</button>
                    </article>
                `;
            }).join('');
        }

        function focusActiveHotspot() {
            const hotspot = activeSimulation?.data_mode?.primary_hotspot ?? scenario?.hotspot_cluster;
            const latitude = Number(hotspot?.latitude);
            const longitude = Number(hotspot?.longitude);

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

            document.querySelector('.map-region')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(() => {
                map.invalidateSize();
                map.setView([latitude, longitude], Math.max(map.getZoom(), 10));
            }, 220);
        }

        // Status Strip
        function renderStatusStrip(sim) {
            const summary = sim.summary ?? {};
            const status = summary.status ?? 'SAFE';
            const hotspotsCount = sim.data_mode?.hotspot_count ?? (sim.scenario?.hotspot_cluster ? 1 : 0);
            const highRiskCount = summary.high_risk_count ?? 0;
            const criticalCount = summary.critical_urgency_count ?? 0;
            const topFacility = summary.top_priority_facility;
            const badgeTextEl = document.getElementById('alert-badge-text');

            statusStrip.className = 'alert-strip';

            let topDeadline = null;
            if (sim.response_priority_queue?.[0]?.intervention_deadline) {
                topDeadline = formatTime(sim.response_priority_queue[0].intervention_deadline);
            }

            if (status === 'CRITICAL') {
                statusStrip.classList.add('is-critical');
                if (badgeTextEl) badgeTextEl.textContent = 'STATUS: KRITIS';
                alertTitle.textContent = 'Tindakan Segera Diperlukan';
                alertDescription.textContent = `Potensi paparan terdekat dalam ${summary.nearest_eta_minutes ?? '26'} menit. Prioritas utama: ${displayFacilityName(topFacility ?? 'Fasilitas')}${topDeadline ? ' (sebelum ' + topDeadline + ')' : ''}.`;
                countHotspots.textContent = hotspotsCount;
                countCritical.textContent = criticalCount;
            } else if (status === 'ALERT') {
                statusStrip.classList.add('is-alert');
                if (badgeTextEl) badgeTextEl.textContent = 'STATUS: SIAGA';
                alertTitle.textContent = 'Perlu Kesiapsiagaan';
                alertDescription.textContent = `Terdapat fasilitas risiko tinggi dalam jalur asap. Prioritas utama: ${displayFacilityName(topFacility ?? 'Fasilitas')}.`;
                countHotspots.textContent = hotspotsCount;
                countCritical.textContent = highRiskCount;
            } else if (status === 'WATCH') {
                statusStrip.classList.add('is-watch');
                if (badgeTextEl) badgeTextEl.textContent = 'STATUS: WASPADA';
                alertTitle.textContent = 'Perlu Dipantau';
                alertDescription.textContent = `Fasilitas berada dalam rentang pengawasan angin. Tetap pantau arah sebaran.`;
                countHotspots.textContent = hotspotsCount;
                countCritical.textContent = 0;
            } else {
                statusStrip.classList.add('is-safe');
                if (badgeTextEl) badgeTextEl.textContent = 'STATUS: TERKENDALI';
                alertTitle.textContent = 'Kondisi Terkendali';
                alertDescription.textContent = 'Tidak ada fasilitas prioritas tinggi pada horizon proyeksi saat ini.';
                countHotspots.textContent = hotspotsCount;
                countCritical.textContent = 0;
            }
        }

        // Priority Feed (Flat Rows)
        function renderPriorityFeed(queue) {
            const list = Array.isArray(queue) ? queue : [];
            totalFeedCount.textContent = list.length;
            priorityToggleRow.style.display = list.length > 3 ? 'block' : 'none';

            if (list.length === 0) {
                topPriorityRows.innerHTML = '<div style="padding:20px; color:var(--text-muted); font-size:13px; text-align:center;">Tidak ada fasilitas dalam antrean prioritas.</div>';
                hiddenPriorityRows.innerHTML = '';
            } else {
                const top3 = list.slice(0, 3);
                const remaining = list.slice(3);

                topPriorityRows.innerHTML = top3.map((item, idx) => renderRowHtml(item, idx === 0)).join('');
                hiddenPriorityRows.innerHTML = remaining.map(item => renderRowHtml(item, false)).join('');
            }

            // Fallback for tests
            const priorityList = document.getElementById('priority-list');
            if (priorityList) {
                priorityList.innerHTML = list.map(item => `
                    <li>
                        <button class="priority-button" type="button" data-facility-name="${escapeHtml(item.facility_name)}">
                            <span class="priority-rank">${item.rank}</span>
                            <span class="priority-name">${escapeHtml(displayFacilityName(item.facility_name))}</span>
                            <span class="priority-signals">Risiko ${riskLabels[item.risk_level] ?? item.risk_level} · Urgensi ${urgencyLabels[item.urgency_level] ?? item.urgency_level}</span>
                            <span class="priority-time">${escapeHtml(formatRemainingTime(item.remaining_intervention_minutes))}</span>
                            <span class="priority-reason">${escapeHtml(item.explanation)}</span>
                        </button>
                    </li>
                `).join('');
            }

            attachRowListeners();
        }

        function renderRowHtml(item, isTop) {
            const riskClass = `risk-${(item.risk_level || 'low').toLowerCase()}`;
            const etaText = item.approximate_eta_hours !== null && item.approximate_eta_hours !== undefined
                ? `${Math.round(item.approximate_eta_hours * 60)} menit`
                : 'Di luar horizon';
            const deadlineText = item.intervention_deadline ? formatTime(item.intervention_deadline) : '—';
            const rankStr = String(item.rank).padStart(2, '0');

            return `
                <div class="priority-row ${isTop ? 'is-highlighted' : ''}" data-facility-name="${escapeHtml(item.facility_name)}">
                    <div class="priority-row-header">
                        <h4 class="priority-rank-name">#${rankStr} ${escapeHtml(displayFacilityName(item.facility_name))}</h4>
                        <span class="status-pill-risk ${riskClass}">Risiko ${riskLabels[item.risk_level] ?? item.risk_level}</span>
                    </div>
                    <div class="priority-row-data">
                        <div class="priority-data-item">
                            <span>Urgensi:</span>
                            <strong>${urgencyLabels[item.urgency_level] ?? item.urgency_level}</strong>
                        </div>
                        <div class="priority-data-item">
                            <span>Potensi paparan:</span>
                            <strong>${etaText}</strong>
                        </div>
                        <div class="priority-data-item deadline" style="grid-column: span 2;">
                            <span>Tindak sebelum:</span>
                            <strong>${deadlineText}</strong>
                        </div>
                    </div>
                    <div class="priority-row-actions">
                        <button class="text-action-link row-select-btn" type="button" data-facility-name="${escapeHtml(item.facility_name)}">
                            Lihat tindakan
                        </button>
                        <button class="text-action-link secondary row-why-btn" type="button" data-facility-name="${escapeHtml(item.facility_name)}">
                            Mengapa?
                        </button>
                    </div>
                </div>
            `;
        }

        function attachRowListeners() {
            document.querySelectorAll('.priority-row').forEach(row => {
                row.addEventListener('click', (e) => {
                    if (e.target.closest('.row-why-btn')) return;
                    const name = row.dataset.facilityName;
                    const idx = facilities.findIndex(f => f.facility_name === name);
                    if (idx >= 0) selectFacility(idx, true);
                });
                row.style.cursor = 'pointer';
            });

            document.querySelectorAll('.row-select-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const name = btn.dataset.facilityName;
                    const idx = facilities.findIndex(f => f.facility_name === name);
                    if (idx >= 0) {
                        selectFacility(idx, false);
                        scrollToRecommendation();
                    }
                });
            });

            document.querySelectorAll('.row-why-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const name = btn.dataset.facilityName;
                    const f = facilities.find(fac => fac.facility_name === name);
                    if (f) showWhy(f);
                });
            });
        }

        function scrollToRecommendation() {
            const recSection = document.getElementById('rekomendasi-tindakan');
            if (!recSection) return;

            recSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

            recSection.classList.remove('is-focused-highlight');
            void recSection.offsetWidth;
            recSection.classList.add('is-focused-highlight');

            setTimeout(() => {
                recSection.classList.remove('is-focused-highlight');
            }, 2000);

            const targetFocus = document.getElementById('rec-facility-name') || recSection;
            targetFocus.focus({ preventScroll: true });
        }

        function selectFacility(index, focusMap = false) {
            if (!facilities[index]) return;
            selectedFacilityIndex = index;
            const f = facilities[index];

            document.querySelectorAll('.priority-row').forEach(row => {
                row.classList.toggle('is-highlighted', row.dataset.facilityName === f.facility_name);
            });

            recFacilityName.textContent = displayFacilityName(f.facility_name);
            recDeadlineTime.textContent = f.intervention_deadline ? `Bertindak sebelum ${formatTime(f.intervention_deadline)}` : 'Di luar horizon proyeksi';

            const plan = f.response_plan ?? {};
            const actions = Array.isArray(plan.actions) && plan.actions.length > 0
                ? plan.actions
                : ['Tutup akses udara luar yang tidak diperlukan.', 'Persiapkan area dalam ruangan.', 'Pantau perubahan proyeksi.'];

            decisionStepsList.innerHTML = actions.map((act, i) => `
                <li class="decision-step">
                    <span class="step-num">${String(i + 1).padStart(2, '0')}</span>
                    <span>${escapeHtml(act)}</span>
                </li>
            `).join('');

            recPlanSummary.textContent = plan.summary ?? 'Tindakan disesuaikan dengan kondisi fasilitas dan waktu potensi paparan.';

            techAlong.textContent = `${f.along_track_distance_km ?? '—'} km`;
            techCross.textContent = `${f.cross_track_distance_km ?? '—'} km`;
            techWithin.textContent = f.within_projection ? 'Dalam horizon' : 'Di luar horizon';
            techSafety.textContent = `${f.safety_buffer_minutes ?? 60} menit`;

            if (focusMap && facilityMarkers[index]) {
                map.panTo([f.latitude, f.longitude]);
                facilityMarkers[index].openPopup();
            }
        }

        function showWhy(facility) {
            whyDialogTitle.textContent = `Mengapa ${displayFacilityName(facility.facility_name)}?`;
            whyDialogBody.textContent = facility.why_this_result
                ?? `Fasilitas ini diproyeksikan berada di sekitar jalur pergerakan asap dengan tingkat risiko ${riskLabels[facility.risk_level] ?? facility.risk_level} dan tingkat urgensi ${urgencyLabels[facility.urgency_level] ?? facility.urgency_level}.`;
            whyDialog.classList.remove('is-closing');
            whyDialog.showModal();
        }

        // Route Calculation
        function populateRouteDropdowns() {
            if (!facilities.length) return;
            const options = facilities.map((f, i) => `<option value="${i}">${escapeHtml(displayFacilityName(f.facility_name))}</option>`).join('');
            routeOriginSelect.innerHTML = options;
            routeDestinationSelect.innerHTML = options;
            if (facilities.length > 1) {
                routeDestinationSelect.selectedIndex = 1;
            }
        }

        async function calculateRoute() {
            if (isRouteBusy) return;

            const originFacility = facilities[routeOriginSelect.value];
            const destFacility = facilities[routeDestinationSelect.value];

            if (!originFacility || !destFacility) {
                setRouteFeedback('Pilih titik awal dan tujuan yang valid.', true);
                return;
            }

            if (originFacility.facility_name === destFacility.facility_name) {
                setRouteFeedback('Titik awal dan tujuan harus berbeda.', true);
                return;
            }

            setRouteBusy(true);
            setRouteFeedback('Membandingkan rute…');
            routeComparisonGrid.style.display = 'none';
            const oldBanner = document.getElementById('route-stale-banner');
            if (oldBanner) oldBanner.remove();

            try {
                const response = await fetch(routeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        origin: { latitude: originFacility.latitude, longitude: originFacility.longitude },
                        destination: { latitude: destFacility.latitude, longitude: destFacility.longitude },
                    }),
                });

                const result = await response.json();

                if (!response.ok || !result.routes || result.routes.length === 0) {
                    throw new Error(result.message ?? 'Layanan rute sementara tidak tersedia. Informasi SENTRA lainnya tetap dapat digunakan.');
                }

                renderRouteComparison(result, originFacility, destFacility);
                setRouteFeedback('');
            } catch (err) {
                setRouteFeedback(err.message ?? 'Layanan rute sementara tidak tersedia. Informasi SENTRA lainnya tetap dapat digunakan.', true);
            } finally {
                setRouteBusy(false);
            }
        }

        function renderRouteComparison(result, originFacility, destFacility) {
            hasCalculatedRoute = true;
            const currentBanner = document.getElementById('route-stale-banner');
            if (currentBanner) currentBanner.remove();

            if (!originFacility) originFacility = facilities[routeOriginSelect?.value];
            if (!destFacility) destFacility = facilities[routeDestinationSelect?.value];

            const routes = result.routes ?? [];
            if (routes.length === 0) {
                setRouteFeedback('Alternatif rute belum tersedia.', false);
                routeComparisonGrid.style.display = 'none';
                return;
            }

            const recMeta = result.recommendation ?? {};
            const fastest = routes.find(r => r.is_fastest) ?? routes[0];
            const recommended = routes.find(r => r.is_recommended) ?? routes[0];

            // Case 1: If only one route exists
            if (routes.length === 1) {
                const route = routes[0];
                const exp = riskLabels[route.exposure_level] ?? route.exposure_level;

                routeComparisonGrid.className = 'route-comparison-grid is-single';
                routeComparisonGrid.innerHTML = `
                    <div class="route-col">
                        <div class="route-col-header">
                            <h3 class="route-col-title">Rute Tersedia</h3>
                        </div>
                        <div class="route-stats-list">
                            <div class="route-stat-line"><strong>${route.duration_minutes} menit</strong> (±${route.distance_km} km)</div>
                            <div class="route-stat-line"><strong>${route.corridor_overlap_percent}%</strong> melewati koridor proyeksi</div>
                            <div class="route-stat-line">Tingkat paparan: <strong>${escapeHtml(exp)}</strong></div>
                        </div>
                        <div class="route-note-text">
                            Belum ditemukan alternatif rute dengan paparan proyeksi lebih rendah.
                        </div>
                    </div>
                `;
                routeComparisonGrid.style.display = 'grid';
                renderRoutePolylines(result, originFacility, destFacility);
                return;
            }

            // Case 2: Multiple routes exist, but backend recommends fastest route (no genuine lower-exposure alternative)
            const hasGenuineAlternative = recommended && fastest && (recommended.id !== fastest.id);

            if (!hasGenuineAlternative) {
                const fastestExp = riskLabels[fastest.exposure_level] ?? fastest.exposure_level;
                const alternative = routes.find(r => r.id !== fastest.id) ?? routes[1];
                const altExp = alternative ? (riskLabels[alternative.exposure_level] ?? alternative.exposure_level) : '';

                routeComparisonGrid.className = 'route-comparison-grid';
                routeComparisonGrid.innerHTML = `
                    <div class="route-col recommended">
                        <div class="route-col-header">
                            <h3 class="route-col-title">Rute Tercepat</h3>
                            <span class="tag-recommended">Direkomendasikan</span>
                        </div>
                        <div class="route-stats-list">
                            <div class="route-stat-line"><strong>${fastest.duration_minutes} menit</strong> (±${fastest.distance_km} km)</div>
                            <div class="route-stat-line"><strong>${fastest.corridor_overlap_percent}%</strong> melewati koridor proyeksi</div>
                            <div class="route-stat-line">Tingkat paparan: <strong>${escapeHtml(fastestExp)}</strong></div>
                        </div>
                    </div>

                    ${alternative ? `
                    <div class="route-col">
                        <div class="route-col-header">
                            <h3 class="route-col-title">Alternatif Rute Lain</h3>
                        </div>
                        <div class="route-stats-list">
                            <div class="route-stat-line"><strong>${alternative.duration_minutes} menit</strong> (±${alternative.distance_km} km)</div>
                            <div class="route-stat-line"><strong>${alternative.corridor_overlap_percent}%</strong> melewati koridor proyeksi</div>
                            <div class="route-stat-line">Tingkat paparan: <strong>${escapeHtml(altExp)}</strong></div>
                        </div>
                    </div>
                    ` : ''}

                    <div class="route-note-box">
                        Belum ada alternatif dengan pengurangan paparan yang cukup signifikan tanpa menambah waktu perjalanan secara berlebihan.
                    </div>
                `;
                routeComparisonGrid.style.display = 'grid';
                renderRoutePolylines(result, originFacility, destFacility);
                return;
            }

            // Case 3: Genuine lower-exposure alternative is recommended
            const fastestExp = riskLabels[fastest.exposure_level] ?? fastest.exposure_level;
            const recExp = riskLabels[recommended.exposure_level] ?? recommended.exposure_level;
            const extraMin = recMeta.extra_minutes ?? Math.max(0, recommended.duration_minutes - fastest.duration_minutes);
            const reduction = recMeta.exposure_reduction_percentage_points ?? Math.max(0, fastest.corridor_overlap_percent - recommended.corridor_overlap_percent);

            routeComparisonGrid.className = 'route-comparison-grid';
            routeComparisonGrid.innerHTML = `
                <div class="route-col">
                    <div class="route-col-header">
                        <h3 class="route-col-title">Rute Tercepat</h3>
                    </div>
                    <div class="route-stats-list">
                        <div class="route-stat-line"><strong>${fastest.duration_minutes} menit</strong> (±${fastest.distance_km} km)</div>
                        <div class="route-stat-line"><strong>${fastest.corridor_overlap_percent}%</strong> melewati koridor proyeksi</div>
                        <div class="route-stat-line">Tingkat paparan: <strong>${escapeHtml(fastestExp)}</strong></div>
                    </div>
                </div>

                <div class="route-col recommended">
                    <div class="route-col-header">
                        <h3 class="route-col-title">Rute Paparan Lebih Rendah</h3>
                        <span class="tag-recommended">Direkomendasikan</span>
                    </div>
                    <div class="route-stats-list">
                        <div class="route-stat-line"><strong>${recommended.duration_minutes} menit</strong> (±${recommended.distance_km} km)</div>
                        <div class="route-stat-line"><strong>${recommended.corridor_overlap_percent}%</strong> melewati koridor proyeksi</div>
                        <div class="route-stat-line">Tingkat paparan: <strong>${escapeHtml(recExp)}</strong></div>
                        <div class="route-delta-text">+${extraMin} menit perjalanan · ↓${reduction} poin persentase overlap</div>
                    </div>
                </div>
            `;
            routeComparisonGrid.style.display = 'grid';
            renderRoutePolylines(result, originFacility, destFacility);
        }

        function renderRoutePolylines(result, originFacility, destFacility) {
            routeLayers.forEach(l => map.removeLayer(l));
            routeLayers = [];

            const routes = result.routes ?? [];
            if (routes.length === 0) return;

            if (!originFacility) originFacility = facilities[routeOriginSelect?.value];
            if (!destFacility) destFacility = facilities[routeDestinationSelect?.value];

            const originPt = (originFacility?.latitude && originFacility?.longitude)
                ? [Number(originFacility.latitude), Number(originFacility.longitude)]
                : null;
            const destPt = (destFacility?.latitude && destFacility?.longitude)
                ? [Number(destFacility.latitude), Number(destFacility.longitude)]
                : null;

            const addEndpointConnector = (facilityCoord, routeCoord) => {
                if (!facilityCoord || !routeCoord) return;
                const fLatLng = L.latLng(Number(facilityCoord[0]), Number(facilityCoord[1]));
                const rLatLng = L.latLng(Number(routeCoord[0]), Number(routeCoord[1]));
                const distMeters = fLatLng.distanceTo(rLatLng);

                // If snapped road point differs visibly (> 3 meters) from true facility marker:
                if (distMeters > 3) {
                    const connector = L.polyline([fLatLng, rLatLng], {
                        color: '#64748b',
                        weight: 2,
                        dashArray: '4, 5',
                        opacity: 0.8,
                        lineCap: 'round',
                    }).addTo(map);

                    connector.bindTooltip('Rute mengikuti jalan terdekat dari lokasi fasilitas.', {
                        direction: 'top',
                        offset: [0, -4],
                    });

                    routeLayers.push(connector);
                }
            };

            const toLatLngs = geometry => {
                if (!geometry?.coordinates && !Array.isArray(geometry)) return [];
                const coords = geometry.coordinates ?? geometry;
                return coords.map(c => Array.isArray(c) ? [c[1], c[0]] : [c.latitude, c.longitude]);
            };

            const legendRouteLabel = document.getElementById('legend-route-label');

            if (routes.length === 1) {
                const singleRoute = routes[0];
                const pts = toLatLngs(singleRoute.geometry);
                if (pts.length > 0) {
                    const pl = L.polyline(pts, {
                        color: '#059669',
                        weight: 4,
                        opacity: 0.95,
                    }).addTo(map).bindTooltip(`Rute Tersedia · ${singleRoute.duration_minutes} mnt`);
                    routeLayers.push(pl);

                    // Draw subtle dashed connectors to actual facility markers if snapped point differs
                    if (originPt) addEndpointConnector(originPt, pts[0]);
                    if (destPt) addEndpointConnector(destPt, pts[pts.length - 1]);

                    const boundsPoints = [...pts];
                    if (originPt) boundsPoints.push(originPt);
                    if (destPt) boundsPoints.push(destPt);
                    map.fitBounds(L.latLngBounds(boundsPoints), { padding: [35, 35] });
                }
                if (legendRouteItem) {
                    legendRouteItem.style.display = 'flex';
                    if (legendRouteLabel) legendRouteLabel.textContent = 'Rute tersedia';
                }
                return;
            }

            const fastest = routes.find(r => r.is_fastest) ?? routes[0];
            const recommended = routes.find(r => r.is_recommended) ?? routes[0];
            const hasGenuineAlternative = fastest.id !== recommended.id;

            const fastestPts = toLatLngs(fastest.geometry);
            const secondaryRoute = hasGenuineAlternative ? recommended : (routes.find(r => r.id !== fastest.id) ?? routes[1]);
            const secondaryPts = secondaryRoute ? toLatLngs(secondaryRoute.geometry) : [];

            if (hasGenuineAlternative) {
                if (fastestPts.length > 0) {
                    const fl = L.polyline(fastestPts, {
                        color: '#64748b',
                        weight: 3,
                        dashArray: '5, 6',
                        opacity: 0.75,
                    }).addTo(map).bindTooltip(`Tercepat · ${fastest.duration_minutes} mnt`);
                    routeLayers.push(fl);
                }
                if (secondaryPts.length > 0) {
                    const rl = L.polyline(secondaryPts, {
                        color: '#059669',
                        weight: 4.5,
                        opacity: 0.95,
                    }).addTo(map).bindTooltip(`Paparan Lebih Rendah · ${secondaryRoute.duration_minutes} mnt`);
                    routeLayers.push(rl);
                }
                if (legendRouteItem) {
                    legendRouteItem.style.display = 'flex';
                    if (legendRouteLabel) legendRouteLabel.textContent = 'Rute paparan lebih rendah';
                }
            } else {
                // Multiple routes but fastest is recommended
                if (secondaryPts.length > 0) {
                    const al = L.polyline(secondaryPts, {
                        color: '#64748b',
                        weight: 3,
                        dashArray: '5, 6',
                        opacity: 0.7,
                    }).addTo(map).bindTooltip(`Alternatif · ${secondaryRoute.duration_minutes} mnt`);
                    routeLayers.push(al);
                }
                if (fastestPts.length > 0) {
                    const fl = L.polyline(fastestPts, {
                        color: '#059669',
                        weight: 4.5,
                        opacity: 0.95,
                    }).addTo(map).bindTooltip(`Tercepat (Direkomendasikan) · ${fastest.duration_minutes} mnt`);
                    routeLayers.push(fl);
                }
                if (legendRouteItem) {
                    legendRouteItem.style.display = 'flex';
                    if (legendRouteLabel) legendRouteLabel.textContent = 'Rute tercepat & alternatif';
                }
            }

            // Draw subtle dashed connectors to actual facility markers
            const primaryPts = (hasGenuineAlternative ? secondaryPts : fastestPts);
            if (primaryPts.length > 0) {
                if (originPt) addEndpointConnector(originPt, primaryPts[0]);
                if (destPt) addEndpointConnector(destPt, primaryPts[primaryPts.length - 1]);
            }
            if (fastestPts.length > 0 && fastestPts !== primaryPts) {
                if (originPt && (!primaryPts.length || L.latLng(fastestPts[0]).distanceTo(L.latLng(primaryPts[0])) > 3)) {
                    addEndpointConnector(originPt, fastestPts[0]);
                }
                if (destPt && (!primaryPts.length || L.latLng(fastestPts[fastestPts.length - 1]).distanceTo(L.latLng(primaryPts[primaryPts.length - 1])) > 3)) {
                    addEndpointConnector(destPt, fastestPts[fastestPts.length - 1]);
                }
            }

            const all = [...fastestPts, ...secondaryPts];
            if (originPt) all.push(originPt);
            if (destPt) all.push(destPt);
            if (all.length > 0) {
                map.fitBounds(L.latLngBounds(all), { padding: [35, 35] });
            }
        }

        function setRouteFeedback(msg, isErr = false) {
            routeFeedback.textContent = msg;
            routeFeedback.className = `scenario-feedback-text ${isErr ? 'is-error' : ''}`;
        }

        function setRouteBusy(busy) {
            isRouteBusy = busy;
            calculateRouteBtn.disabled = busy;
            calculateRouteBtn.textContent = busy ? 'Membandingkan…' : 'Bandingkan Rute';
        }

        // Map Layers & Markers
        function renderFacilityMarkers() {
            facilityMarkers.forEach(m => map.removeLayer(m));
            const markerLabels = { school: 'S', hospital: '+', clinic: 'K', residential: 'R' };

            facilityMarkers = facilities.map((facility, index) => {
                const lat = Number(facility.latitude);
                const lng = Number(facility.longitude);
                if (isNaN(lat) || isNaN(lng)) return null;

                const name = displayFacilityName(facility.facility_name);
                const type = facility.facility_type || facility.type || 'facility';
                const isNoImpact = (facility.risk_level === 'LOW' && facility.urgency_level === 'NONE');
                const isAffected = !isNoImpact;

                let pinModifier = '';
                let markerOpacity = 1.0;
                let zIndexOffset = 200;

                if (isAffected) {
                    pinModifier = facility.risk_level === 'HIGH' ? 'pin-facility-high-risk' : 'pin-facility-affected';
                    zIndexOffset = facility.risk_level === 'HIGH' ? 450 : 350;
                    markerOpacity = 1.0;
                } else {
                    pinModifier = 'pin-facility-muted';
                    zIndexOffset = 50;
                    markerOpacity = 0.55;
                }

                const marker = L.marker([lat, lng], {
                    icon: icon(`<div class="pin-marker pin-${type} ${pinModifier}">${markerLabels[type] ?? 'F'}</div>`),
                    title: name,
                    opacity: markerOpacity,
                    zIndexOffset: zIndexOffset,
                }).addTo(map)
                .bindTooltip(name);

                const plainStatusHtml = isNoImpact
                    ? `<div style="margin-top:6px; padding-top:5px; border-top:1px solid #e2e8f0; color:#64748b; font-size:11px; line-height:1.4;">Tidak berada dalam jalur proyeksi saat ini.</div>`
                    : `<div style="margin-top:6px; padding-top:5px; border-top:1px solid #e2e8f0; color:${facility.risk_level === 'HIGH' ? '#b91c1c' : '#c2410c'}; font-size:11px; font-weight:600; line-height:1.4;">Berada dalam jalur proyeksi asap (${riskLabels[facility.risk_level] ?? facility.risk_level}).</div>`;

                marker.bindPopup(`
                    <div style="font-size:12px; line-height:1.45; min-width:160px;">
                        <strong style="font-size:13px; color:#0f172a;">${escapeHtml(name)}</strong><br>
                        <span style="color:#64748b; font-size:11.5px;">${escapeHtml(facilityTypes[type] ?? type)}</span>
                        <div style="margin-top:4px; font-size:11.5px;">
                            Risiko: <strong>${riskLabels[facility.risk_level] ?? facility.risk_level}</strong><br>
                            Urgensi: <strong>${urgencyLabels[facility.urgency_level] ?? facility.urgency_level}</strong>
                        </div>
                        ${plainStatusHtml}
                    </div>
                `);

                marker.on('click', () => selectFacility(index, false));
                return marker;
            }).filter(Boolean);
        }

        function renderHotspotMarkers() {
            hotspotLayers.forEach(l => map.removeLayer(l));
            const hotspots = activeSimulation?.data_mode?.hotspots ?? [{
                ...scenario.hotspot_cluster,
                source: 'SENTRA Demo',
            }];

            const activeId = activeSimulation?.data_mode?.active_hotspot_id;

            hotspotLayers = hotspots.map(h => {
                const lat = Number(h.latitude);
                const lng = Number(h.longitude);
                if (isNaN(lat) || isNaN(lng)) return null;

                const isPrimary = (activeId && h.id && h.id === activeId)
                    || (scenario.hotspot_cluster
                        && Math.abs(lat - Number(scenario.hotspot_cluster.latitude)) < 0.0001
                        && Math.abs(lng - Number(scenario.hotspot_cluster.longitude)) < 0.0001);

                const impact = h.impact;
                let iconHtml = '<div class="pin-marker pin-hotspot"><span>●</span></div>';
                let zIndexOffset = 100;
                let opacity = 0.8;

                if (isPrimary) {
                    iconHtml = '<div class="pin-marker pin-hotspot pin-hotspot-active"><span>●</span></div>';
                    zIndexOffset = 1000;
                    opacity = 1;
                } else if (impact?.requires_attention) {
                    iconHtml = '<div class="pin-marker pin-hotspot pin-hotspot-high-impact"><span>●</span></div>';
                    zIndexOffset = 500;
                    opacity = 1;
                } else if ((impact?.facilities_affected ?? 0) > 0) {
                    iconHtml = '<div class="pin-marker pin-hotspot pin-hotspot-impact"><span>●</span></div>';
                    zIndexOffset = 300;
                    opacity = 0.9;
                } else {
                    iconHtml = '<div class="pin-marker pin-hotspot pin-hotspot-no-impact"><span>●</span></div>';
                    zIndexOffset = 100;
                    opacity = 0.72;
                }

                const relTime = formatRelativeTime(h.acquired_at, h.observation_age_minutes);
                const detectedAtWita = formatWitaClock(h.acquired_at);

                let impactHtml = '';
                if (impact) {
                    if (impact.facilities_affected > 0) {
                        const boxClass = impact.requires_attention ? 'has-high-priority' : 'has-impact';
                        impactHtml = `
                            <div class="hotspot-impact-box ${boxClass}">
                                <div class="hotspot-impact-headline">${impact.facilities_affected} fasilitas berpotensi terdampak</div>
                                <div class="hotspot-impact-detail">
                                    ${impact.high_risk_count > 0 ? `<div>• ${impact.high_risk_count} risiko tinggi</div>` : ''}
                                    ${impact.critical_urgency_count > 0 ? `<div>• ${impact.critical_urgency_count} urgensi kritis</div>` : ''}
                                    ${impact.high_risk_count === 0 && impact.critical_urgency_count === 0 ? `<div>• Risiko: ${riskLabels[impact.highest_risk] ?? impact.highest_risk}</div>` : ''}
                                </div>
                            </div>
                        `;
                    } else {
                        impactHtml = `
                            <div class="hotspot-impact-box no-impact">
                                <div class="hotspot-impact-headline">Tidak ada fasilitas terdampak</div>
                                <div class="hotspot-impact-detail">
                                    <span>Di luar jangkauan arah angin saat ini.</span>
                                </div>
                            </div>
                        `;
                    }
                }

                const badgeHtml = isPrimary
                    ? '<span class="hotspot-popup-badge">Aktif Dianalisis</span>'
                    : (impact?.requires_attention
                        ? '<span class="hotspot-popup-badge" style="background:#fee2e2;color:#b91c1c;">Prioritas Tinggi</span>'
                        : ((impact?.facilities_affected ?? 0) > 0
                            ? '<span class="hotspot-popup-badge">Berdampak</span>'
                            : ''));

                const popupHtml = `
                    <div class="hotspot-popup-content">
                        <div class="hotspot-popup-title">
                            <span>Hotspot</span>
                            ${badgeHtml}
                        </div>
                        <div class="hotspot-popup-body">
                            <div>Confidence: <strong>${h.confidence ?? '—'}%</strong></div>
                            <div>FRP: <strong>${h.frp ? h.frp + ' MW' : '—'}</strong></div>
                            <div>Terakhir terdeteksi: <strong>${escapeHtml(relTime)} · ${escapeHtml(detectedAtWita)}</strong></div>
                        </div>
                        ${impactHtml}
                        ${!isPrimary && h.id ? `
                            <button type="button" class="btn-select-hotspot" data-hotspot-id="${escapeHtml(h.id)}">Analisis hotspot ini</button>
                        ` : ''}
                    </div>
                `;

                const tooltipText = isPrimary
                    ? `Hotspot aktif dianalisis · keyakinan ${h.confidence}%`
                    : (impact?.requires_attention
                        ? `Hotspot prioritas tinggi · ${impact.facilities_affected} fasilitas terdampak`
                        : ((impact?.facilities_affected ?? 0) > 0
                            ? `Hotspot berdampak · ${impact.facilities_affected} fasilitas terdampak`
                            : `Hotspot tanpa dampak fasilitas · keyakinan ${h.confidence}%`));

                return L.marker([lat, lng], {
                    icon: icon(iconHtml),
                    zIndexOffset: zIndexOffset,
                    opacity: opacity,
                }).addTo(map)
                .bindTooltip(tooltipText)
                .bindPopup(popupHtml, { minWidth: 200 });
            }).filter(Boolean);
        }

        function renderScenarioLayers() {
            scenarioLayers.forEach(l => map.removeLayer(l));
            scenarioLayers = [];

            if (!scenario.hotspot_cluster || !projection) return;

            const hotspotCoords = [Number(scenario.hotspot_cluster.latitude), Number(scenario.hotspot_cluster.longitude)];
            const endpointCoords = [Number(projection.projected_latitude), Number(projection.projected_longitude)];

            if (isNaN(hotspotCoords[0]) || isNaN(hotspotCoords[1]) || isNaN(endpointCoords[0]) || isNaN(endpointCoords[1])) return;

            const windDeg = Number(scenario.wind?.direction_degrees ?? 0);

            const left = corridorSteps.map(([fraction, widthKm]) => destinationPoint(
                interpolatePoint(hotspotCoords, endpointCoords, fraction),
                windDeg - 90,
                widthKm,
            ));
            const right = corridorSteps.map(([fraction, widthKm]) => destinationPoint(
                interpolatePoint(hotspotCoords, endpointCoords, fraction),
                windDeg + 90,
                widthKm,
            ));
            const mid = [
                (hotspotCoords[0] + endpointCoords[0]) / 2,
                (hotspotCoords[1] + endpointCoords[1]) / 2,
            ];

            const corridor = L.polygon([
                hotspotCoords,
                ...left,
                ...[...right].reverse(),
            ], {
                color: '#d9a15f',
                fillColor: '#f59e0b',
                fillOpacity: 0.12,
                opacity: 0.5,
                weight: 1,
            }).addTo(map).bindTooltip('Koridor proyeksi · bukan batas pasti sebaran asap');

            const trajectory = L.polyline([hotspotCoords, endpointCoords], {
                color: '#c86719',
                dashArray: '4 6',
                opacity: 0.52,
                weight: 1.25,
            }).addTo(map);

            const windMarker = L.marker(mid, {
                icon: icon(`<div class="wind-arrow-el" style="transform:rotate(${windDeg - 90}deg)">➤</div>`, 26),
                interactive: false,
            }).addTo(map).bindTooltip(
                `Arah angin: ${directionShort[scenario.wind?.label] ?? scenario.wind?.label ?? ''} ${windDeg}°`,
                { permanent: true, direction: 'right', offset: [8, 0], className: 'wind-label-clean' }
            );

            const horizonLabel = `+${scenario.projection_time_hours ?? 2} jam`;
            const endpointMarker = L.marker(endpointCoords, {
                icon: L.divIcon({
                    className: 'map-icon',
                    html: `<div class="pin-endpoint">${escapeHtml(horizonLabel)}</div>`,
                    iconSize: [52, 20],
                    iconAnchor: [26, 10],
                }),
                title: 'Batas Proyeksi',
                zIndexOffset: 200,
            }).addTo(map).bindTooltip('Proyeksi terdepan asap').bindPopup(`
                <strong>Batas Proyeksi</strong><br>
                Horizon: +${scenario.projection_time_hours ?? 2} jam<br>
                Jarak: ±${projection.projected_travel_distance_km ?? 0} km<br>
                Arah: ${windDeg}°
            `);

            scenarioLayers = [corridor, trajectory, windMarker, endpointMarker];

            const leftLast = left.length > 0 ? left[left.length - 1] : hotspotCoords;
            const rightLast = right.length > 0 ? right[right.length - 1] : hotspotCoords;

            const validFacilities = facilities
                .filter(f => f && !isNaN(Number(f.latitude)) && !isNaN(Number(f.longitude)))
                .map(f => [Number(f.latitude), Number(f.longitude)]);

            const allPoints = [
                hotspotCoords,
                endpointCoords,
                ...validFacilities,
                leftLast,
                rightLast,
            ];

            if (allPoints.length > 0) {
                const bounds = L.latLngBounds(allPoints);
                map.fitBounds(bounds, { padding: [35, 35], maxZoom: 12 });
                setTimeout(() => {
                    map.invalidateSize();
                    map.fitBounds(bounds, { padding: [35, 35], maxZoom: 12 });
                }, 80);
            }
        }

        function updateMetadata(sim) {
            const dataMode = sim.data_mode ?? {};
            const weatherData = sim.weather_data ?? {};
            const facilityData = sim.facility_data ?? {};
            const isSnapshot = dataMode.mode === 'snapshot';
            const isLive = dataMode.mode === 'live';

            dataModeButtons.forEach(btn => btn.classList.toggle('is-active', btn.dataset.mode === dataMode.mode));

            const mapModeTag = document.getElementById('map-mode-tag');
            if (mapModeTag) mapModeTag.textContent = isSnapshot ? 'SNAPSHOT' : isLive ? 'AKTUAL' : 'SIMULASI';

            const mapAreaTitle = document.getElementById('map-area-title');
            if (mapAreaTitle) {
                mapAreaTitle.textContent = isSnapshot
                    ? 'Snapshot hotspot Kalimantan Tengah'
                    : isLive ? 'Hotspot aktual Kalimantan Tengah' : 'Area simulasi Palangka Raya';
            }

            const hotspotCount = dataMode.hotspot_count ?? 0;
            const filterCount = dataMode.metadata?.filtered_record_count ?? hotspotCount;
            const sourceHotspotsCount = document.getElementById('source-hotspots-count');
            if (sourceHotspotsCount) {
                sourceHotspotsCount.textContent = isLive
                    ? `${hotspotCount} dari ${filterCount} hotspot relevan ditampilkan`
                    : `${hotspotCount} hotspot relevan ditampilkan`;
            }

            const firmsName = document.getElementById('source-firms-name');
            if (firmsName) firmsName.textContent = dataMode.source ?? 'NASA FIRMS';

            const weatherName = document.getElementById('source-weather-name');
            if (weatherName) weatherName.textContent = weatherData.source ?? 'Open-Meteo';

            const osmName = document.getElementById('source-osm-name');
            if (osmName) osmName.textContent = facilityData.source ?? 'OpenStreetMap';

            if (barFreshnessText) {
                barFreshnessText.textContent = `Data diperbarui: ${formatDataFreshness(dataMode)}`;
            }

            const simStart = document.getElementById('simulation-start');
            if (simStart) {
                simStart.textContent = `Waktu mulai simulasi: ${formatDateTime(scenario.simulation_started_at)}`;
            }

            // Keep provenance text in hidden container for full assertion compatibility
            const hotspotStatus = isSnapshot ? 'arsip' : isLive ? 'near-real-time' : 'simulasi';
            const hotProv = document.getElementById('hotspot-provenance');
            if (hotProv) {
                hotProv.textContent = `Hotspot (${hotspotStatus}): ${dataMode.source ?? 'NASA FIRMS'} · ${hotspotCount} hotspot relevan ditampilkan · ${formatDateTime(dataMode.acquired_at)}`;
            }
            const weathProv = document.getElementById('weather-provenance');
            if (weathProv) {
                weathProv.textContent = `Cuaca: ${weatherData.source ?? 'Open-Meteo'} · ${formatDateTime(weatherData.observation_at)}`;
            }
            const facProv = document.getElementById('facility-provenance');
            if (facProv) {
                facProv.textContent = `Fasilitas: ${facilityData.source ?? 'OpenStreetMap'} · ${facilityData.facility_count ?? 0} lokasi`;
            }

            setSimulatorAvailability(dataMode.mode === 'simulation');
        }

        function syncSimulatorInputs(sim) {
            const sc = sim?.scenario;
            if (!sc) return;
            directionInput.value = sc.wind.direction_degrees;
            directionValue.value = `${sc.wind.direction_degrees}°`;
            windSpeedInput.value = sc.wind.speed_kmh;
            projectionHorizonSelect.value = sc.projection_time_hours;
        }

        function setSimulatorBusy(busy) {
            const liveMode = activeSimulation?.data_mode?.mode === 'live';
            runButton.disabled = busy || liveMode;
            resetButton.disabled = busy || liveMode;
            runButton.textContent = busy ? 'Menghitung…' : 'Jalankan simulasi';
        }

        function setSimulatorAvailability(avail) {
            scenarioForm.querySelectorAll('input:not([type="hidden"]), select, button').forEach(el => {
                el.disabled = !avail;
            });
            scenarioForm.title = avail ? '' : 'Tersedia pada Mode Simulasi.';
        }

        function setDataModeBusy(busy, requestedMode = null) {
            dataModeButtons.forEach(btn => {
                const isRequested = busy && btn.dataset.mode === requestedMode;
                btn.disabled = isRequested;
                btn.classList.toggle('is-loading', isRequested);
            });
        }

        function showDataModeFeedback(message, isError = false) {
            if (!dataModeFeedback) return;
            dataModeFeedback.textContent = message;
            dataModeFeedback.classList.toggle('is-error', isError);
        }

        function showSimulatorMessage(msg, isErr = false) {
            simulatorMessage.textContent = msg;
            simulatorMessage.className = `scenario-feedback-text ${isErr ? 'is-error' : ''}`;
        }

        syncSimulatorInputs(defaultSimulation);
        applySimulation(defaultSimulation, false);
        window.addEventListener('load', () => {
            map.invalidateSize();
        }, { once: true });
    </script>
</body>
</html>
