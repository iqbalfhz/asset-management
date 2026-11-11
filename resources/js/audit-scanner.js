import { BrowserMultiFormatReader } from '@zxing/browser';

// Kamera belakang membaca QR dan barcode label. Kode yang sama diabaikan 3 detik supaya tidak tercatat berulang.
export async function startScanner(video, onCode) {
    const reader = new BrowserMultiFormatReader();
    let lastCode = null;
    let lastAt = 0;

    return reader.decodeFromConstraints({ video: { facingMode: { ideal: 'environment' } } }, video, (result) => {
        if (!result) {
            return;
        }

        const code = result.getText();
        const now = Date.now();

        if (code === lastCode && now - lastAt < 3000) {
            return;
        }

        lastCode = code;
        lastAt = now;
        navigator.vibrate?.(60);
        onCode(code);
    });
}
