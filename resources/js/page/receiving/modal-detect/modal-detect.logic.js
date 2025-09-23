import {
  ref,
  watch,
  nextTick,
  defineComponent,
  computed,
  shallowRef
} from 'vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

export default defineComponent({
  name: 'ScannerModal',
  props: {
    scannerTitle: {
      type: String,
      default: 'Detect Serial Numbers',
    }
  },
  setup(props) {
    // --- State ---
    const imageUrl = ref(null);
    const cropper = shallowRef(null);
    const imageElement = ref(null);
    const fileInput = ref(null);
    const rotation = ref(0);
    const isDragging = ref(false);
    const croppedImage = ref(null);
    const isCropperReady = ref(false);
    const apiResult = ref(null);
    const loading = ref(false);
    const errorMessage = ref('');
    // Camera modal state 
    const enableCamera = ref(true);
    const showCameraModal = ref(false);
    const cameraPreview = ref(null);

    // Live detection state
    const targetBox = ref(null);
    const liveDetectionActive = ref(false);
    const lastFrameBlob = ref(null);
    let stream = null;

    // Target box state
    // --- Detection state ---
    const targetBoxStyle = ref({
      borderColor: 'rgba(255,0,0,0.9)', // red default
      position: 'absolute',
      borderWidth: '2px',
      borderStyle: 'solid',
      width: '60%',
      height: '40%',
      top: '30%',
      left: '20%'
    });
    let detectionInterval = null;
    let detectionTimeout = null;
    let detectionFound = false;

    // --- File/Image Handling ---
    function triggerFileInput() {
      fileInput.value?.click();
    }

    function onFileChange(e) {
      const file = e.target.files[0];
      if (file) loadImage(file);
    }

    function handleDrop(e) {
      isDragging.value = false;
      const file = e.dataTransfer.files[0];
      if (file) loadImage(file);
    }

    function loadImage(file) {
      const reader = new FileReader();
      reader.onload = () => {
        imageUrl.value = reader.result;
        rotation.value = 0;
        croppedImage.value = null;
        isCropperReady.value = false;
      };
      reader.readAsDataURL(file);
    }

    // --- Cropper ---
    watch(imageUrl, () => {
      nextTick(() => {
        const img = imageElement.value;
        if (!img) return;

        const tryInit = (attempts = 5) => {
          if (!img.complete || img.naturalWidth === 0) {
            if (attempts > 0) setTimeout(() => tryInit(attempts - 1), 100);
            return;
          }
          if (cropper.value?.destroy) cropper.value.destroy();
          cropper.value = new Cropper(img, {
            viewMode: 1,
            autoCropArea: 1,
            responsive: true,
            background: false,
            ready() {
              isCropperReady.value = true;
              setTimeout(() => cropImage(), 500);
            },
          });
        };
        tryInit();
      });
    });

    async function cropImage() {
      try {
        loading.value = true;
        apiResult.value = null;

        if (!cropper.value) throw new Error('Cropper not ready');
        const canvas = cropper.value.getCroppedCanvas();
        const dataUrl = canvas.toDataURL('image/jpeg');
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg'));

        croppedImage.value = dataUrl;

        const formData = new FormData();
        formData.append("file", blob, "capture.jpg");

        const response = await fetch("http://127.0.0.1:8001/detect", {
          method: "POST",
          body: formData
        });

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        apiResult.value = result;

        if (!result.serials || result.serials.length === 0) {
          errorMessage.value = '⚠️ No serials detected.';
        } else {
          errorMessage.value = '';
        }
      } catch (err) {
        console.error("OCR API error:", err);
        errorMessage.value = '❌ Failed to process image. Please try again.';
      } finally {
        loading.value = false;
      }
    }

    function resetImage() {
      cropper.value?.destroy?.();
      cropper.value = null;
      imageUrl.value = null;
      croppedImage.value = null;
      rotation.value = 0;
      isCropperReady.value = false;
      apiResult.value = null;
      errorMessage.value = '';
    }

    function rotateLeft() {
      if (cropper.value?.rotate) cropper.value.rotate(-90);
    }

    function rotateRight() {
      if (cropper.value?.rotate) cropper.value.rotate(90);
    }

    const rotationStyle = computed(() => ({
      transform: `rotate(${rotation.value}deg)`,
      transition: 'transform 0.3s ease',
    }));

    //--- Camera Handling ---

    async function toggleCamera() {
    if (!showCameraModal.value) {
      console.log('[Camera] Opening camera…');

      try {
        // Try back camera first
        let newStream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: 'environment' } },
          audio: false
        });
        stream = newStream;

        // Show modal so the video element is rendered
        showCameraModal.value = true;

        // Wait for the DOM to update with the video element
        await nextTick();

        if (cameraPreview.value) {
          cameraPreview.value.srcObject = stream;
          await cameraPreview.value.play();
          console.log('[Camera] Back camera started.');
          startQuickDetection();
        } else {
          console.error('[Camera] cameraPreview ref still not found after nextTick.');
        }
      } catch (err) {
        console.warn('[Camera] Back camera failed, trying front camera:', err);

        try {
          let newStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: false
          });
          stream = newStream;

          showCameraModal.value = true;
          await nextTick();

          if (cameraPreview.value) {
            cameraPreview.value.srcObject = stream;
            await cameraPreview.value.play();
            console.log('[Camera] Front camera started.');
          } else {
            console.error('[Camera] cameraPreview ref still not found after nextTick.');
          }
        } catch (err2) {
          console.error('[Camera] Failed to start any camera:', err2);
          alert('Unable to access camera. Please check permissions and device settings.');
        }
      }
    } else {
      closeCameraModal();
    }
    }

    // --- live detection ---
    function waitForVideoReady(videoEl) {
      return new Promise((resolve) => {
        // If metadata already available, resolve
        if (videoEl && videoEl.videoWidth && videoEl.videoHeight) return resolve();
        // otherwise poll until videoWidth is available
        const check = () => {
          if (videoEl && videoEl.videoWidth && videoEl.videoHeight) return resolve();
          requestAnimationFrame(check);
        };
        check();
      });
    }

    async function startQuickDetection() {
      // Ensure video is ready
      if (!cameraPreview.value) {
        console.warn('[QuickDetect] No cameraPreview element');
        detectionFound = false;
        liveDetectionActive.value = true;
        return;
      }
      await waitForVideoReady(cameraPreview.value);

      detectionFound = false;
      liveDetectionActive.value = true;
      clearTimeout(detectionTimeout);

      // 20s fallback timer
      detectionTimeout = setTimeout(async () => {
        // stop the active loop
        liveDetectionActive.value = false;
        console.log('[QuickDetect] No valid serial after 20s — capturing fallback frame...');
        await captureAndRunFullDetection();

      }, 20000);

      // Async loop (prevents overlapping requests)
      (async () => {
        while (liveDetectionActive.value && !detectionFound) {
          try {
            // capture full-resolution frame (so bbox is in same coordinate space as videoWidth/videoHeight)
            const canvas = document.createElement('canvas');
            const videoEl = cameraPreview.value;
            canvas.width = videoEl.videoWidth;
            canvas.height = videoEl.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(videoEl, 0, 0, canvas.width, canvas.height);

            // Save last frame for fallback (full res)
            lastFrameBlob.value = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.8));

            const formData = new FormData();
            formData.append('file', lastFrameBlob.value, 'frame.jpg');

            const response = await fetch("http://127.0.0.1:8001/detect-camera-frame", {
              method: 'POST',
              body: formData
            });

            if (!response.ok) {
              console.warn('[QuickDetect] fetch failed', response.status);
            } else {
              const result = await response.json();

              if (result.found) {
                detectionFound = true;
                liveDetectionActive.value = false;
                clearTimeout(detectionTimeout);
                // update and show green box
                updateTargetBox(result.bbox, 'green');
                console.log('[QuickDetect] Found serial:', result.serial);
                // optionally you can set apiResult.value = result here or call submit flow
              } else if (result.bbox) {
                // update red box to follow 
                // 
                 bbox
                updateTargetBox(result.bbox, 'red');
              } else {
                // no text at all — hide or reset box style
                if (targetBox.value) {
                  targetBox.value.style.border = '2px dashed rgba(255,0,0,0.9)';
                }
              }
            }
          } catch (err) {
            console.error('[QuickDetect] API error:', err);
          }

          // wait 500ms before next capture
          await new Promise(r => setTimeout(r, 500));
        } // end loop
      })();
    }

    function updateTargetBox(bbox, color = 'red') {
      if (!bbox || !targetBox.value || !cameraPreview.value) return;

      // bbox format: [[x1,y1],[x2,y2],[x3,y3],[x4,y4]]
      const xs = bbox.map(p => p[0]);
      const ys = bbox.map(p => p[1]);
      const minX = Math.min(...xs);
      const minY = Math.min(...ys);
      const maxX = Math.max(...xs);
      const maxY = Math.max(...ys);
      const width = maxX - minX;
      const height = maxY - minY;

      // Map from video intrinsic size -> displayed size
      const videoEl = cameraPreview.value;
      const rect = videoEl.getBoundingClientRect();
      const scaleX = rect.width / videoEl.videoWidth;
      const scaleY = rect.height / videoEl.videoHeight;

      targetBox.value.style.left = `${minX * scaleX}px`;
      targetBox.value.style.top = `${minY * scaleY}px`;
      targetBox.value.style.width = `${width * scaleX}px`;
      targetBox.value.style.height = `${height * scaleY}px`;

      targetBox.value.style.border = `2px dashed ${color === 'green' ? 'rgba(0,255,0,0.9)' : 'rgba(255,0,0,0.9)'}`;
    }

    async function captureAndRunFullDetection() {
      try {
        // if we already have lastFrameBlob, use it; otherwise capture full-size now
        let blob = lastFrameBlob.value;
        if (!blob && cameraPreview.value) {
          const canvas = document.createElement('canvas');
          canvas.width = cameraPreview.value.videoWidth;
          canvas.height = cameraPreview.value.videoHeight;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(cameraPreview.value, 0, 0, canvas.width, canvas.height);
          blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.9));
        }
        if (!blob) {
          console.warn('[FallbackDetect] No blob captured');
          return;
        }

        const formData = new FormData();
        formData.append('file', blob, 'fallback.jpg');

        const response = await fetch("http://127.0.0.1:8001/detect", {
          method: 'POST',
          body: formData
        });
        if (!response.ok) {
          console.error('[FallbackDetect] HTTP error', response.status);
          return;
        }
        const result = await response.json();
        console.log('[FallbackDetect] Result:', result);

        // show result in your upload preview UI:
        // convert blob to dataURL to reuse your croppedImage display
        const objectUrl = URL.createObjectURL(blob);
        croppedImage.value = objectUrl;
        apiResult.value = result;
        if (!result.serials || result.serials.length === 0) {
          errorMessage.value = '⚠️ No serials detected.';
        } else {
          errorMessage.value = '';
        }

        // optionally close camera modal:
        // closeCameraModal();
      } catch (err) {
        console.error('[FallbackDetect] Error:', err);
        errorMessage.value = 'Fallback detection failed.';
      } finally {
        // stop detection flags
        liveDetectionActive.value = false;
        clearTimeout(detectionTimeout);
        detectionTimeout = null;
      }
    }

    function closeCameraModal() {
      console.log('[Camera] Closing camera.');
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
      }
      liveDetectionActive.value = false; // ensure stopped
      clearInterval(detectionInterval);
      clearTimeout(detectionTimeout);
      detectionInterval = null;
      detectionTimeout = null;
      showCameraModal.value = false;
    }

    // --- Expose ---
    return {
      imageUrl,
      cropper,
      imageElement,
      fileInput,
      rotation,
      rotationStyle,
      isDragging,
      isCropperReady,
      croppedImage,
      triggerFileInput,
      onFileChange,
      handleDrop,
      cropImage,
      resetImage,
      rotateLeft,
      rotateRight,
      apiResult,
      loading,
      errorMessage,
      // Camera related
      enableCamera,
      showCameraModal,
      cameraPreview,
      toggleCamera,
      targetBoxStyle,
      liveDetectionActive,
      targetBox,
      cameraPreview,
      startQuickDetection,
      closeCameraModal
    };
  },
});
