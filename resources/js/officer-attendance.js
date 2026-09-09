import { BrowserQRCodeReader } from '@zxing/browser';

const dialog = document.querySelector('[data-scanner-dialog]');
const preview = document.querySelector('[data-scanner-preview]');
const status = document.querySelector('[data-scanner-status]');
const form = document.querySelector('[data-attendance-scan-form]');
const qrContent = form?.querySelector('[data-qr-content]');
const reader = new BrowserQRCodeReader(undefined, {
    delayBetweenScanAttempts: 80,
    delayBetweenScanSuccess: 1000,
});
let controls;
let submitting = false;

const applyOrientation = () => {
    preview?.style.setProperty('transform', 'scaleX(-1)', 'important');
};

const stopScanner = () => {
    controls?.stop();
    controls = null;
    if (preview) {
        preview.pause();
        preview.srcObject?.getTracks().forEach(track => track.stop());
        preview.srcObject = null;
    }
};

const closeScanner = () => {
    stopScanner();
    if (dialog?.open) dialog.close();
};

const cameraErrorMessage = error => {
    switch (error?.name) {
        case 'NotAllowedError':
        case 'PermissionDeniedError':
            return 'Camera permission was denied. Allow it in the browser address bar, reload, and try again.';
        case 'NotFoundError':
        case 'DevicesNotFoundError':
            return 'No camera was found on this device.';
        case 'NotReadableError':
        case 'TrackStartError':
            return 'The camera is being used by another app. Close that app and try again.';
        default:
            return error?.message || 'The camera could not start.';
    }
};

const onDecode = (result, _error, activeControls) => {
    if (!result || submitting || !form || !qrContent) return;

    submitting = true;
    status.textContent = 'QR recognized. Recording attendance…';
    qrContent.value = result.getText();
    form.querySelector('[name="id_number"]').value = '';
    activeControls.stop();
    form.requestSubmit();
};

const decodeWithConstraints = constraints => reader.decodeFromConstraints(
    { audio: false, video: constraints },
    preview,
    onDecode,
);

const startScanner = async () => {
    if (!dialog || !preview || !form || !qrContent) return;
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        status.textContent = 'Open this page through http://localhost or HTTPS to use the camera.';
        dialog.showModal();
        return;
    }

    stopScanner();
    submitting = false;
    applyOrientation();
    dialog.showModal();
    status.textContent = 'Requesting rear camera access…';

    const videoSize = {
        width: { ideal: 1280 },
        height: { ideal: 720 },
    };

    try {
        try {
            controls = await decodeWithConstraints({
                ...videoSize,
                facingMode: { exact: 'environment' },
            });
        } catch (error) {
            if (!['OverconstrainedError', 'NotFoundError'].includes(error?.name)) throw error;
            controls = await decodeWithConstraints({
                ...videoSize,
                facingMode: { ideal: 'environment' },
            });
        }
        applyOrientation();
        status.textContent = 'Camera ready — center the complete QR code inside the frame.';
    } catch (error) {
        status.textContent = cameraErrorMessage(error);
    }
};

document.querySelector('[data-scanner-open]')?.addEventListener('click', startScanner);
document.querySelector('[data-scanner-close]')?.addEventListener('click', closeScanner);
dialog?.addEventListener('click', event => {
    if (event.target === dialog) closeScanner();
});
dialog?.addEventListener('cancel', event => {
    event.preventDefault();
    closeScanner();
});
window.addEventListener('pagehide', stopScanner);
