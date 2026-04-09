document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector('form[data-scan-form="1"]');
    const scanInput = document.querySelector('input[name="scan_input"]');
    const startBtn = document.getElementById("startScannerBtn");
    const stopBtn = document.getElementById("stopScannerBtn");
    const fileInput = document.getElementById("scanImageFile");
    const cameraSelect = document.getElementById("cameraSelect");
    const autoSubmit = document.getElementById("autoSubmitScan");
    const statusBox = document.getElementById("scannerStatus");
    const previewBox = document.getElementById("qr-reader");

    if (!form || !scanInput || !startBtn || !stopBtn || !fileInput || !cameraSelect || !statusBox || !previewBox) {
        return;
    }

    let html5QrCode = null;
    let scannerRunning = false;
    let lastScanText = "";

    function setStatus(message, type = "secondary") {
        statusBox.className = "alert alert-" + type + " rounded-4 py-2 mb-3";
        statusBox.textContent = message;
    }

    function applyScannedValue(text) {
        const cleanText = String(text || "").trim();
        if (!cleanText) return;
        if (cleanText === lastScanText) return;

        lastScanText = cleanText;
        scanInput.value = cleanText;
        setStatus("Scan success. Token / ticket number filled automatically.", "success");

        if (autoSubmit.checked) {
            form.submit();
        }
    }

    async function loadCameras() {
        try {
            const cameras = await Html5Qrcode.getCameras();
            cameraSelect.innerHTML = "";

            if (!cameras || !cameras.length) {
                setStatus("No camera found on this device.", "warning");
                return [];
            }

            cameras.forEach((camera, index) => {
                const option = document.createElement("option");
                option.value = camera.id;
                option.textContent = camera.label || `Camera ${index + 1}`;
                cameraSelect.appendChild(option);
            });

            return cameras;
        } catch (error) {
            setStatus("Failed to load cameras: " + error, "danger");
            return [];
        }
    }

    async function startScanner() {
        try {
            if (scannerRunning) {
                return;
            }

            const cameras = await loadCameras();
            if (!cameras.length) {
                return;
            }

            html5QrCode = new Html5Qrcode("qr-reader");
            const cameraId = cameraSelect.value || cameras[0].id;

            await html5QrCode.start(
                cameraId,
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                },
                async (decodedText) => {
                    applyScannedValue(decodedText);

                    if (scannerRunning && html5QrCode) {
                        await html5QrCode.stop();
                        scannerRunning = false;
                        setStatus("Scanner stopped after successful scan.", "success");
                    }
                },
                () => {
                    // ignore continuous decode errors
                }
            );

            scannerRunning = true;
            setStatus("Scanner started. Point the camera at the QR code.", "primary");
        } catch (error) {
            setStatus("Unable to start scanner: " + error, "danger");
        }
    }

    async function stopScanner() {
        try {
            if (html5QrCode && scannerRunning) {
                await html5QrCode.stop();
                await html5QrCode.clear();
                scannerRunning = false;
                setStatus("Scanner stopped.", "secondary");
            }
        } catch (error) {
            setStatus("Failed to stop scanner: " + error, "danger");
        }
    }

    async function scanFromImage(file) {
        try {
            if (!file) {
                setStatus("Please choose an image file first.", "warning");
                return;
            }

            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode("qr-reader");
            }

            const decodedText = await html5QrCode.scanFile(file, true);
            applyScannedValue(decodedText);
        } catch (error) {
            setStatus("Image scan failed: " + error, "danger");
        }
    }

    startBtn.addEventListener("click", function () {
        startScanner();
    });

    stopBtn.addEventListener("click", function () {
        stopScanner();
    });

    fileInput.addEventListener("change", function (event) {
        const file = event.target.files[0];
        scanFromImage(file);
    });

    loadCameras();
    setStatus("Scanner ready. You can use webcam or upload a QR image.", "secondary");
});