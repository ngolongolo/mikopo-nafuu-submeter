(() => {
    const installButton = document.getElementById('pwa-install');
    const iosDialog = document.getElementById('pwa-ios-help');
    let installPrompt = null;

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js').catch(() => {}));
    }

    const installed = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
    if (installButton && ios && !installed) installButton.hidden = false;

    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        installPrompt = event;
        if (installButton) installButton.hidden = false;
    });

    installButton?.addEventListener('click', async () => {
        if (installPrompt) {
            installPrompt.prompt();
            await installPrompt.userChoice;
            installPrompt = null;
            installButton.hidden = true;
            return;
        }
        if (iosDialog) {
            typeof iosDialog.showModal === 'function' ? iosDialog.showModal() : iosDialog.setAttribute('open', '');
        }
    });

    iosDialog?.querySelector('[data-close-pwa-help]')?.addEventListener('click', () => iosDialog.close());
    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        if (installButton) installButton.hidden = true;
    });
})();
