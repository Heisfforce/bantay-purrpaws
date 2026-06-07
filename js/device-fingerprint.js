/**
 * Client-side device fingerprint for login security.
 */
window.BPP = window.BPP || {};

window.BPP.getDeviceFingerprint = function () {
    const parts = [
        navigator.userAgent || '',
        navigator.language || '',
        screen.width + 'x' + screen.height,
        screen.colorDepth || '',
        Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        navigator.platform || '',
    ];
    let hash = 0;
    const str = parts.join('|');
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i);
        hash |= 0;
    }
    return 'fp_' + Math.abs(hash).toString(16);
};
