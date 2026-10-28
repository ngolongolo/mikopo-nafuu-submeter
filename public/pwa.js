(() => {
    const scriptUrl = document.currentScript?.src || document.baseURI;
    const publicUrl = path => new URL(path, scriptUrl).toString();
    const installButton = document.getElementById('pwa-install');
    const iosDialog = document.getElementById('pwa-ios-help');
    let installPrompt = null;

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register(publicUrl('service-worker.js?v=3')).catch(() => {}));
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
