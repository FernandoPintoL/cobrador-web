<style>
    /* ============================================
       VARIABLES CSS Y PALETA DE COLORES GLOBAL
       ============================================ */
    :root {
        /* Colores Primarios */
        --color-primary: #4472C4;
        --color-primary-dark: #2E5090;
        --color-primary-light: #6B8FD9;

        /* Colores Secundarios */
        --color-success: #228B22;
        --color-warning: #FFA500;
        --color-danger: #FF0000;
        --color-info: #0000CD;

        /* Colores Neutrales */
        --color-background: #ffffff;
        --color-surface: #f5f5f5;
        --color-border: #999;
        --color-border-light: #ddd;
        --color-text: #000000;
        --color-text-secondary: #666666;

        /* Colores de Estado */
        --color-status-clean: #ffffff;
        --color-status-warning: #fffacd;
        --color-status-danger: #ffcccc;

        /* Tipografía */
        --font-family-base: Arial, sans-serif;
        --font-size-base: 10px;
        --font-size-header: 18px;
        --font-size-subheader: 11px;
        --font-size-body: 9px;
        --font-size-small: 8px;

        /* Espaciado */
        --spacing-xs: 2px;
        --spacing-sm: 5px;
        --spacing-md: 10px;
        --spacing-lg: 15px;
        --spacing-xl: 20px;
        --spacing-xxl: 30px;

        /* Bordes */
        --border-radius: 5px;
        --border-width: 1px;

        /* Sombras */
        --shadow-light: 0 1px 3px rgba(0,0,0,0.12);
        --shadow-medium: 0 2px 8px rgba(0,0,0,0.15);
    }

    /* ============================================
       ESTILOS GLOBALES
       ============================================ */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: var(--font-family-base);
        font-size: var(--font-size-base);
        color: var(--color-text);
        background-color: var(--color-background);
        line-height: 1.4;
    }

    /* ============================================
       COMPONENTE: HEADER
       ============================================ */
    .report-header {
        text-align: center;
        margin-bottom: var(--spacing-lg);
        border-bottom: 3px solid var(--color-primary);
        padding-bottom: var(--spacing-lg);
    }

    .report-header h1 {
        margin: 0 0 var(--spacing-xs) 0;
        font-size: var(--font-size-header);
        color: var(--color-primary);
        font-weight: bold;
    }

    .report-header .header-meta {
        display: flex;
        justify-content: center;
        gap: var(--spacing-lg);
        flex-wrap: wrap;
        font-size: var(--font-size-body);
        color: var(--color-text-secondary);
        margin-top: var(--spacing-md);
    }

    .report-header .meta-item {
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
    }

    .report-header .meta-label {
        font-weight: bold;
    }

    /* ============================================
       COMPONENTE: SUMMARY CARD
       ============================================ */
    .summary-section {
        background: var(--color-surface);
        padding: var(--spacing-lg);
        margin-bottom: var(--spacing-lg);
        border-radius: var(--border-radius);
        border-left: 4px solid var(--color-primary);
    }

    .summary-section h3 {
        margin: 0 0 var(--spacing-md) 0;
        font-size: var(--font-size-subheader);
        color: var(--color-primary-dark);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--spacing-md);
    }

    .summary-item {
        padding: var(--spacing-md);
        background: var(--color-background);
        border-radius: var(--border-radius);
        border: var(--border-width) solid var(--color-border-light);
    }

    .summary-item p {
        margin: var(--spacing-xs) 0;
        font-size: var(--font-size-body);
    }

    .summary-item strong {
        color: var(--color-primary);
        font-weight: bold;
    }

    .summary-item .value {
        font-size: 11px;
        font-weight: bold;
        color: var(--color-primary-dark);
        margin-top: var(--spacing-xs);
    }

    /* ============================================
       COMPONENTE: TABLE
       ============================================ */
    .report-table {
        width: 100%;
        border-collapse: collapse;
        margin: var(--spacing-lg) 0;
        font-size: var(--font-size-body);
        box-shadow: var(--shadow-light);
    }

    .report-table thead {
        background-color: var(--color-primary);
        color: var(--color-background);
    }

    .report-table th {
        padding: var(--spacing-md) var(--spacing-sm);
        text-align: left;
        font-weight: bold;
        font-size: var(--font-size-small);
        border: var(--border-width) solid var(--color-border);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .report-table td {
        padding: var(--spacing-md) var(--spacing-sm);
        border: var(--border-width) solid var(--color-border);
    }

    .report-table tbody tr:hover {
        background-color: #f9f9f9;
    }

    .report-table tbody tr.row-clean {
        background-color: var(--color-status-clean);
    }

    .report-table tbody tr.row-warning {
        background-color: var(--color-status-warning);
    }

    .report-table tbody tr.row-danger {
        background-color: var(--color-status-danger);
    }

    /* ============================================
       COMPONENTE: STATUS BADGES
       ============================================ */
    .badge {
        display: inline-block;
        padding: var(--spacing-xs) var(--spacing-sm);
        border-radius: var(--border-radius);
        font-size: var(--font-size-body);
        font-weight: bold;
        text-transform: uppercase;
    }

    .badge-success {
        background-color: var(--color-success);
        color: white;
    }

    .badge-warning {
        background-color: var(--color-warning);
        color: white;
    }

    .badge-danger {
        background-color: var(--color-danger);
        color: white;
    }

    .badge-info {
        background-color: var(--color-info);
        color: white;
    }

    /* Status styles inline */
    .status-active {
        color: var(--color-success);
        font-weight: bold;
    }

    .status-completed {
        color: var(--color-info);
        font-weight: bold;
    }

    .status-pending {
        color: var(--color-warning);
        font-weight: bold;
    }

    .status-overdue {
        color: var(--color-danger);
        font-weight: bold;
    }

    /* ============================================
       COMPONENTE: ICONOS Y INDICADORES
       ============================================ */
    .icon {
        display: inline-block;
        margin-right: var(--spacing-xs);
        font-size: 12px;
    }

    .icon-clean {
        color: var(--color-success);
    }

    .icon-warning {
        color: var(--color-warning);
    }

    .icon-danger {
        color: var(--color-danger);
    }

    .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: var(--spacing-xs);
        font-weight: bold;
        text-align: center;
    }

    /* ============================================
       COMPONENTE: FOOTER
       ============================================ */
    .report-footer {
        margin-top: var(--spacing-xl);
        padding-top: var(--spacing-lg);
        border-top: 2px solid var(--color-primary);
        text-align: center;
        font-size: var(--font-size-body);
        color: var(--color-text-secondary);
    }

    .report-footer p {
        margin: var(--spacing-xs) 0;
    }

    .footer-info {
        display: flex;
        justify-content: center;
        gap: var(--spacing-lg);
        flex-wrap: wrap;
        margin-top: var(--spacing-md);
        font-size: 8px;
    }

    /* ============================================
       COMPONENTE: INFO PANELS
       ============================================ */
    .info-panel {
        background: var(--color-surface);
        padding: var(--spacing-md);
        margin: var(--spacing-md) 0;
        border-radius: var(--border-radius);
        border-left: 3px solid var(--color-primary);
        font-size: var(--font-size-body);
    }

    .info-panel strong {
        color: var(--color-primary-dark);
    }

    /* ============================================
       UTILIDADES
       ============================================ */
    .text-center {
        text-align: center;
    }

    .text-right {
        text-align: right;
    }

    .text-left {
        text-align: left;
    }

    .text-bold {
        font-weight: bold;
    }

    .text-primary {
        color: var(--color-primary);
    }

    .text-success {
        color: var(--color-success);
    }

    .text-warning {
        color: var(--color-warning);
    }

    .text-danger {
        color: var(--color-danger);
    }

    .mt-sm {
        margin-top: var(--spacing-sm);
    }

    .mt-md {
        margin-top: var(--spacing-md);
    }

    .mt-lg {
        margin-top: var(--spacing-lg);
    }

    .mb-sm {
        margin-bottom: var(--spacing-sm);
    }

    .mb-md {
        margin-bottom: var(--spacing-md);
    }

    .mb-lg {
        margin-bottom: var(--spacing-lg);
    }

    /* ============================================
       RESPONSIVE PRINT STYLES
       ============================================ */
    @media print {
        body {
            margin: 0;
            padding: 10px;
        }

        .report-table {
            page-break-inside: avoid;
        }

        .summary-section {
            page-break-inside: avoid;
        }
    }
</style>
