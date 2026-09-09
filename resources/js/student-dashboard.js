import QRCode from 'qrcode';

const dialog = document.querySelector('[data-qr-dialog]');
const canvas = dialog?.querySelector('[data-qr-canvas]');
const title = dialog?.querySelector('[data-qr-title]');
const schedule = dialog?.querySelector('[data-qr-schedule]');
const error = dialog?.querySelector('[data-qr-error]');

document.querySelectorAll('[data-open-qr]').forEach(button => {
    button.addEventListener('click', async () => {
        if (!dialog || !canvas) return;

        title.textContent = button.dataset.eventTitle + ' · ' + button.dataset.session + ' QR';
        schedule.textContent = button.dataset.schedule;
        error.textContent = '';
        dialog.showModal();

        try {
            await QRCode.toCanvas(canvas, button.dataset.payload, {
                width: 320,
                margin: 2,
                color: { dark: '#121017', light: '#ffffff' },
                errorCorrectionLevel: 'L',
            });
        } catch {
            error.textContent = 'The QR code could not be generated. Please refresh and try again.';
        }
    });
});

const closeDialog = () => {
    if (dialog?.open) dialog.close();
};

dialog?.querySelector('[data-qr-close]')?.addEventListener('click', closeDialog);
dialog?.addEventListener('click', event => {
    if (event.target === dialog) closeDialog();
});
