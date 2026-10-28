(() => {
    const paths = {
        overview: 'M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6Zm10-12h8V3h-8v6Z', applications: 'M6 2h9l5 5v15H6V2Zm8 1.5V8h4.5M9 12h8M9 16h8M9 20h5', loans: 'M3 7h18v14H3V7Zm3-4h12v4H6V3Zm1 9h10M7 16h6', payments: 'M2 6h20v14H2V6Zm0 4h20M6 16h4', customers: 'M16 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM8 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8 0c-1.2 0-2.3.3-3.2.8A7 7 0 0 1 16 19v2h7v-2c0-3.3-3.1-6-7-6ZM8 15c-4.4 0-7 2.7-7 6h14c0-3.3-2.6-6-7-6Z', products: 'M4 4h16v16H4V4Zm4 4h8M8 12h8M8 16h5', financiers: 'M3 9 12 3l9 6M5 10v8m4-8v8m6-8v8m4-8v8M3 21h18', team: 'M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM1 21c0-4 3-7 7-7s7 3 7 7m1-8c4 0 7 2.5 7 6v2', reports: 'M4 20V10m6 10V4m6 16v-7m5 7H2', disbursements: 'M3 7h18M5 7V4h14v3M5 11h14v10H5V11Zm4 3v4m6-4v4'
    };
    const iconFor = label => { const text = label.toLowerCase(); if (/overdue|rejected|reversed|report|event|audit/.test(text)) return 'reports'; if (/application|submitted|approved/.test(text)) return 'applications'; if (/loan|debt|principal|outstanding/.test(text)) return 'loans'; if (/repayment|payment|collected|collection|amount|value|commission|finance|upfront/.test(text)) return 'payments'; if (/customer|lead/.test(text)) return 'customers'; if (/product/.test(text)) return 'products'; if (/financier/.test(text)) return 'financiers'; if (/supplier|user|team/.test(text)) return 'team'; if (/disbursement|batch|payout/.test(text)) return 'disbursements'; return 'overview'; };
    document.querySelectorAll('.grid:not(.two) > .card').forEach(card => {
        if (card.querySelector(':scope > .summary-card-icon')) return;
        const labelElement = [...card.children].find(child => child.matches('small, span'));
        const label = labelElement?.textContent.trim() || 'Summary';
        const icon = document.createElement('span');
        icon.className = 'summary-card-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = `<svg viewBox="0 0 24 24"><path d="${paths[iconFor(label)]}"/></svg>`;
        card.classList.add('summary-card');
        card.prepend(icon);
    });
})();
