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

    const liveDetectionActive = ref(false);
    const lastFrameBlob = ref(null);
    const targetBox = ref(null);
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
    async function startQuickDetection() {
      detectionFound = false;
      liveDetectionActive.value = true; // mark active

      // Clear any existing detection before starting
      clearInterval(detectionInterval);
      clearTimeout(detectionTimeout);

      // 20s fallback timer
      detectionTimeout = setTimeout(async () => {
        clearInterval(detectionInterval); // stop live scanning
        liveDetectionActive.value = false; // mark inactive
        console.log('[QuickDetect] No valid serial after 20s — capturing fallback frame...');
        await captureAndRunFullDetection();
      }, 20000);

      // Run every ~500ms
      detectionInterval = setInterval(async () => {
        if (!cameraPreview.value) return;

        const canvas = document.createElement('canvas');
        canvas.width = cameraPreview.value.videoWidth;
        canvas.height = cameraPreview.value.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(cameraPreview.value, 0, 0);

        const blob = await new Promise(resolve =>
          canvas.toBlob(resolve, 'image/jpeg')
        );

        const formData = new FormData();
        formData.append("file", blob, "frame.jpg");

        try {
          const response = await fetch("http://127.0.0.1:8001/detect-camera-frame", {
            method: "POST",
            body: formData
          });
          const result = await response.json();

          if (result.found) {
            detectionFound = true;
            liveDetectionActive.value = false; // stop flag
            clearInterval(detectionInterval); 
            clearTimeout(detectionTimeout);   
            updateTargetBox(result.bbox, 'green');
            console.log('[QuickDetect] Found serial:', result.serial);
          } else if (result.bbox) {
            updateTargetBox(result.bbox, 'red');
          } else {
            targetBoxStyle.value.borderColor = 'rgba(255,0,0,0.9)';
          }
        } catch (err) {
          console.error('[QuickDetect] API error:', err);
        }
      }, 500);
    }

    function updateTargetBox(bbox, color = 'red') {
      const box = targetBox.value;
      if (!box || !bbox) return;

      // bbox format: [[x1, y1], [x2, y2], [x3, y3], [x4, y4]]
      const x = Math.min(bbox[0][0], bbox[2][0]);
      const y = Math.min(bbox[0][1], bbox[2][1]);
      const width = Math.abs(bbox[1][0] - bbox[0][0]);
      const height = Math.abs(bbox[2][1] - bbox[1][1]);

      // Position & size relative to video
      const videoEl = cameraPreview.value;
      if (videoEl && videoEl.videoWidth && videoEl.videoHeight) {
        const scaleX = videoEl.clientWidth / videoEl.videoWidth;
        const scaleY = videoEl.clientHeight / videoEl.videoHeight;

        box.style.left = `${x * scaleX}px`;
        box.style.top = `${y * scaleY}px`;
        box.style.width = `${width * scaleX}px`;
        box.style.height = `${height * scaleY}px`;
      }

      // Change color
      box.style.border = `2px dashed ${color === 'green' ? 'rgba(0,255,0,0.9)' : 'rgba(255,0,0,0.9)'}`;
    }

    async function captureAndRunFullDetection() {
      if (!cameraPreview.value) return;

      const canvas = document.createElement('canvas');
      canvas.width = cameraPreview.value.videoWidth;
      canvas.height = cameraPreview.value.videoHeight;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(cameraPreview.value, 0, 0);

      const blob = await new Promise(resolve =>
        canvas.toBlob(resolve, 'image/jpeg')
      );

      const formData = new FormData();
      formData.append("file", blob, "fallback.jpg");

      try {
        const response = await fetch("http://127.0.0.1:8001/detect", {
          method: "POST",
          body: formData
        });
        const result = await response.json();
        console.log('[FallbackDetect] Result:', result);
      } catch (err) {
        console.error('[FallbackDetect] Error:', err);
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
      startQuickDetection,
      targetBoxStyle,
      liveDetectionActive,
      closeCameraModal
    };
  },
});
