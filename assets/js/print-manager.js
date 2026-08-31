/* Renee Farms centralized print manager — V2.2.41 */
(function () {
    'use strict';

    const nativePrint = window.print.bind(window);
    let pageStyle = null;

    function normalize(text) {
        return String(text || '').trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function markActionColumns() {
        document.querySelectorAll('table').forEach(table => {
            const headers = Array.from(table.querySelectorAll('thead th'));
            headers.forEach((th, index) => {
                const label = normalize(th.textContent);
                if (label === 'actions' || label === 'action') {
                    th.classList.add('print-action-column');
                    table.querySelectorAll('tr').forEach(row => {
                        const cell = row.children[index];
                        if (cell) cell.classList.add('print-action-column');
                    });
                }
            });
        });
    }

    function widestTableColumnCount() {
        let max = 0;
        document.querySelectorAll('table').forEach(table => {
            const row = table.querySelector('thead tr') || table.querySelector('tr');
            if (!row) return;
            const count = Array.from(row.children).filter(cell =>
                !cell.classList.contains('print-action-column') && !cell.classList.contains('no-print')
            ).length;
            max = Math.max(max, count);
        });
        return max;
    }

    function resolveOrientation(options) {
        const explicit = (options && options.orientation) || document.body.dataset.printOrientation;
        if (explicit === 'portrait' || explicit === 'landscape') return explicit;
        return widestTableColumnCount() >= 7 ? 'landscape' : 'portrait';
    }

    function prepare(options) {
        markActionColumns();
        const orientation = resolveOrientation(options || {});
        document.body.classList.toggle('print-wide', orientation === 'landscape');
        document.body.dataset.activePrintOrientation = orientation;
        if (!pageStyle) {
            pageStyle = document.createElement('style');
            pageStyle.id = 'platformPrintPageStyle';
            document.head.appendChild(pageStyle);
        }
        pageStyle.textContent = `@media print { @page { size: A4 ${orientation}; margin: 8mm; } }`;
        return orientation;
    }

    function cleanup() {
        document.body.classList.remove('print-wide');
        delete document.body.dataset.activePrintOrientation;
    }

    function print(options) {
        prepare(options || {});
        nativePrint();
    }

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-print]');
        if (!trigger) return;
        event.preventDefault();
        print({ orientation: trigger.dataset.printOrientation || undefined });
    });

    window.addEventListener('beforeprint', () => prepare({}));
    window.addEventListener('afterprint', cleanup);
    window.PrintManager = { print, prepare, cleanup };
})();
