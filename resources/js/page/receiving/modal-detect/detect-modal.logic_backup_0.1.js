import ScannerMixin from '../../../components/ScannerMixin.js';
import {
  ref,
  watch,
  nextTick,
  defineComponent,
  computed,
  shallowRef,
  onMounted,
  onUnmounted,
} from 'vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

export default defineComponent({
  name: 'ScannerModal',
  mixins: [ScannerMixin],
  props: {
    scannerTitle: {
      type: String,
      default: 'Detect Serial Numbers',
    },
    enableCamera: {
      type: Boolean,
      default: true,
    },
  },
  setup(props, { emit }) {
    // --- State ---
    const imageUrl = ref(null);
    const cropper = shallowRef(null);
    const imageElement = ref(null);
    const fileInput = ref(null);
    const rotation = ref(0);
    const isDragging = ref(false);
    const croppedImage = ref(null);
    const isCropperReady = ref(false);
    const showCameraModal = ref(false);
    const cameraCanvas = ref(null);
    const capturedImages = ref([]);
    const maxImages = 1;
    const apiResult = ref(null);
    const loading = ref(false);
    const targetBox = ref(null);
    const detectionInterval = ref(null);
    const videoElement = ref(null);
    const cameraPreview = ref(null);
    const hasCaptured = ref(false);
    const errorMessage = ref('');
    let stream = null;
    let detectionTimeout = null;

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

    async function cropImage(autoCapturedCanvas = null) {
      try {
        loading.value = true;
        apiResult.value = null;
        let blob, dataUrl;
        if (autoCapturedCanvas) {
          dataUrl = autoCapturedCanvas.toDataURL('image/jpeg');
          blob = await new Promise(resolve => autoCapturedCanvas.toBlob(resolve, 'image/jpeg'));
        } else {
          if (!cropper.value) throw new Error('Cropper not ready');
          const canvas = cropper.value.getCroppedCanvas();
          dataUrl = canvas.toDataURL('image/jpeg');
          blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg'));
        }
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

    // --- Camera ---
    const startCamera = async () => {
      if (stream) return; // Prevent multiple streams
      await nextTick();
      videoElement.value = cameraPreview.value;
      if (!videoElement.value) {
        console.error('Video element not found');
        return;
      }
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'environment' },
          audio: false,
        });
        videoElement.value.srcObject = stream;
        await videoElement.value.play();
        nextTick(() => initDraggableTarget());
        startSerialDetectionLoop();
      } catch (error) {
        console.error('Camera error:', error);
      }
      hasCaptured.value = false;
    };

    const stopCamera = () => {
      stream?.getTracks().forEach((track) => track.stop());
      stream = null; // Clear reference
      clearInterval(detectionInterval.value);
    };

    // --- Detection ---
    const startSerialDetectionLoop = () => {
      detectionInterval.value = setInterval(runOCROnVideoFrame, 1000);
    };

    const runOCROnVideoFrame = async () => {
      if (hasCaptured.value || !videoElement.value) return;
      const canvas = document.createElement('canvas');
      const video = videoElement.value;
      const context = canvas.getContext('2d');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      context.drawImage(video, 0, 0, canvas.width, canvas.height);
      const blob = await new Promise((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg')
      );
      try {
        const formData = new FormData();
        formData.append('file', blob, 'frame.jpg');
        const response = await fetch('http://127.0.0.1:8001/detect', {
          method: 'POST',
          body: formData,
        });
        if (response.ok) {
          const result = await response.json();
          if (result?.bboxes?.length) {
            clearTimeout(detectionTimeout);
            const box = result.bboxes[0];
            moveTargetBoxToBoundingBox(box, result.image_width, result.image_height);
            await nextTick();
            await autoCaptureFromBox(box, result.image_width, result.image_height);
          }
        }
      } catch (err) {
        console.error('Detection error:', err);
      }
    };

    const moveTargetBoxToBoundingBox = (box, imageWidth, imageHeight) => {
      const video = videoElement.value;
      if (!video || !targetBox.value) return;
      const videoRect = video.getBoundingClientRect();
      const scaleX = videoRect.width / imageWidth;
      const scaleY = videoRect.height / imageHeight;
      const left = box[0] * scaleX;
      const top = box[1] * scaleY;
      const width = (box[2] - box[0]) * scaleX;
      const height = (box[3] - box[1]) * scaleY;
      Object.assign(targetBox.value.style, {
        left: `${left}px`,
        top: `${top}px`,
        width: `${width}px`,
        height: `${height}px`,
      });
    };

    const autoCaptureFromBox = async (box, imageWidth, imageHeight) => {
      const video = videoElement.value;
      if (!video || !targetBox.value) return;
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      const videoRect = video.getBoundingClientRect();
      const scaleX = video.videoWidth / videoRect.width;
      const scaleY = video.videoHeight / videoRect.height;
      const left = box[0] * scaleX;
      const top = box[1] * scaleY;
      const width = Math.max(1, (box[2] - box[0]) * scaleX);
      const height = Math.max(1, (box[3] - box[1]) * scaleY);
      canvas.width = width;
      canvas.height = height;
      ctx.drawImage(video, left, top, width, height, 0, 0, width, height);
      const dataUrl = canvas.toDataURL('image/png');
      croppedImage.value = dataUrl; // Always set preview!
      loading.value = true;
      apiResult.value = null;
      errorMessage.value = '';
      try {
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        const formData = new FormData();
        formData.append('file', blob, 'cropped.png');
        const res = await fetch('http://127.0.0.1:8001/detect', {
          method: 'POST',
          body: formData,
        });
        if (!res.ok) throw new Error('OCR API returned status ' + res.status);
        const json = await res.json();
        apiResult.value = {
          serials: json.serials || [],
          raw_ocr: json.raw_ocr || '',
          bboxes: json.bboxes || [],
          image_width: json.image_width ?? width ?? null,
          image_height: json.image_height ?? height ?? null,
        };
        if (!apiResult.value.serials.length) {
          errorMessage.value = '⚠️ No serials detected.';
        } else {
          errorMessage.value = '';
        }
        // Always close camera modal after capture
        showCameraModal.value = false;
        stopCamera();
        hasCaptured.value = true;
      } catch (err) {
        console.error('OCR API error:', err);
        errorMessage.value = '❌ Failed to process image. Please try again.';
        apiResult.value = apiResult.value || { serials: [], raw_ocr: '' };
        showCameraModal.value = false;
        stopCamera();
        hasCaptured.value = true;
      } finally {
        loading.value = false;
      }
    };

    // --- Manual Camera Capture ---
    async function captureImage() {
      if (loading.value) return;
      loading.value = true;
      apiResult.value = null;
      errorMessage.value = '';
      const video = document.getElementById('camera-preview');
      const canvas = cameraCanvas.value;
      if (!video || !canvas || !canvas.getContext || !targetBox.value) {
        errorMessage.value = '❌ Camera or canvas not ready.';
        loading.value = false;
        return;
      }
      const ctx = canvas.getContext('2d');
      const overlayRect = targetBox.value.getBoundingClientRect();
      const videoRect = video.getBoundingClientRect();
      const scaleX = video.videoWidth / videoRect.width;
      const scaleY = video.videoHeight / videoRect.height;
      const sx = (overlayRect.left - videoRect.left) * scaleX;
      const sy = (overlayRect.top - videoRect.top) * scaleY;
      const sw = overlayRect.width * scaleX;
      const sh = overlayRect.height * scaleY;
      canvas.width = sw;
      canvas.height = sh;
      ctx.drawImage(video, sx, sy, sw, sh, 0, 0, sw, sh);
      const dataUrl = canvas.toDataURL('image/png');
      croppedImage.value = dataUrl;
      capturedImages.value.push({ data: dataUrl });

      try {
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        const formData = new FormData();
        formData.append('file', blob, 'cropped.png');
        const res = await fetch('http://127.0.0.1:8001/detect', {
          method: 'POST',
          body: formData,
        });
        if (!res.ok) throw new Error('OCR API returned status ' + res.status);
        const json = await res.json();
        apiResult.value = {
          serials: json.serials || [],
          raw_ocr: json.raw_ocr || '',
          bboxes: json.bboxes || [],
          image_width: json.image_width ?? sw ?? null,
          image_height: json.image_height ?? sh ?? null,
        };
        if (!apiResult.value.serials.length) {
          errorMessage.value = '⚠️ No serials detected.';
        } else {
          errorMessage.value = '';
          clearTimeout(detectionTimeout);
        }
      } catch (err) {
        console.error('OCR API error:', err);
        errorMessage.value = '❌ Failed to process image. Please try again.';
        apiResult.value = apiResult.value || { serials: [], raw_ocr: '' };
      } finally {
        loading.value = false;
        hasCaptured.value = true;
        stopCamera();
        // DO NOT close modal here!
      }
    }

    function loadImageFromCamera(dataUrl) {
      imageUrl.value = dataUrl;
      rotation.value = 0;
      croppedImage.value = null;
      isCropperReady.value = false;
    }

    // --- Modal/Timeout/UI ---
    function closeCameraModal() {
      showCameraModal.value = false;
      stopCamera();
      emit('close');
    }

    watch(showCameraModal, (isOpen) => {
      if (isOpen) {
        clearTimeout(detectionTimeout);
        detectionTimeout = setTimeout(() => {
          if (!apiResult.value || !apiResult.value.serials?.length) {
            stopCamera();
            // Do NOT close modal automatically!
            apiResult.value = apiResult.value || { serials: [], raw_ocr: '' };
            errorMessage.value = '⚠️ No serials detected.';
            hasCaptured.value = true;
          }
        }, 20000);
      } else {
        clearTimeout(detectionTimeout);
        detectionTimeout = null;
        stopCamera();
      }
    });

    // --- Draggable Target Box ---
    function initDraggableTarget() {
      const target = targetBox.value;
      if (!target || !target.parentElement) return;
      const parentRect = target.parentElement.getBoundingClientRect();
      target.style.left = `${(parentRect.width - target.offsetWidth) / 2}px`;
      target.style.top = `${(parentRect.height - target.offsetHeight) / 2}px`;
      let offsetX = 0,
        offsetY = 0,
        dragStartX = 0,
        dragStartY = 0,
        isDragging = false,
        isResizing = false,
        resizeDir = '';
      const sensitivity = window.innerWidth < 768 ? 0.7 : 1;
      const preventScroll = (e) => e.preventDefault();
      function endDragOrResize() {
        isDragging = false;
        isResizing = false;
        window.removeEventListener('pointermove', onPointerMove);
        window.removeEventListener('pointerup', endDragOrResize);
        document.body.style.overflow = '';
        document.removeEventListener('touchmove', preventScroll);
      }
      function onPointerMove(e) {
        const parentRect = target.parentElement.getBoundingClientRect();
        if (!isDragging && !isResizing) {
          if (
            Math.abs(e.clientX - dragStartX) > 5 ||
            Math.abs(e.clientY - dragStartY) > 5
          ) {
            isDragging = true;
          } else return;
        }
        if (isDragging) {
          let left = (e.clientX - parentRect.left - offsetX) * sensitivity;
          let top = (e.clientY - parentRect.top - offsetY) * sensitivity;
          left = Math.max(0, Math.min(left, parentRect.width - target.offsetWidth));
          top = Math.max(0, Math.min(top, parentRect.height - target.offsetHeight));
          target.style.left = `${left}px`;
          target.style.top = `${top}px`;
        }
        if (isResizing) {
          const rect = target.getBoundingClientRect();
          if (resizeDir.includes('right')) {
            target.style.width = `${Math.max(50, (e.clientX - rect.left) * sensitivity)}px`;
          }
          if (resizeDir.includes('left')) {
            const deltaX = (e.clientX - rect.left) * sensitivity;
            const newLeft = parseFloat(target.style.left) + deltaX;
            const newWidth = rect.width - deltaX;
            if (newWidth >= 50) {
              target.style.left = `${Math.max(0, newLeft)}px`;
              target.style.width = `${newWidth}px`;
            }
          }
          if (resizeDir.includes('bottom')) {
            target.style.height = `${Math.max(50, (e.clientY - rect.top) * sensitivity)}px`;
          }
          if (resizeDir.includes('top')) {
            const deltaY = (e.clientY - rect.top) * sensitivity;
            const newTop = parseFloat(target.style.top) + deltaY;
            const newHeight = rect.height - deltaY;
            if (newHeight >= 50) {
              target.style.top = `${Math.max(0, newTop)}px`;
              target.style.height = `${newHeight}px`;
            }
          }
        }
      }
      target.addEventListener('pointerdown', (e) => {
        if (e.target.classList.contains('resize-handle')) return;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        offsetX = e.clientX - target.getBoundingClientRect().left;
        offsetY = e.clientY - target.getBoundingClientRect().top;
        document.body.style.overflow = 'hidden';
        document.addEventListener('touchmove', preventScroll, { passive: false });
        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', endDragOrResize);
      });
      target.querySelectorAll('.resize-handle').forEach((handle) => {
        handle.addEventListener('pointerdown', (e) => {
          e.stopPropagation();
          isResizing = true;
          resizeDir = handle.classList[1];
          document.body.style.overflow = 'hidden';
          document.addEventListener('touchmove', preventScroll, { passive: false });
          window.addEventListener('pointermove', onPointerMove);
          window.addEventListener('pointerup', endDragOrResize);
        });
      });
    }

    // --- Lifecycle ---
    onMounted(() => {
      if (props.enableCamera && showCameraModal.value) {
        startCamera();
      }
    });

    onUnmounted(() => {
      stopCamera();
    });

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
      // toggleCamera,
      showCameraModal,
      cameraCanvas,
      capturedImages,
      maxImages,
      startCamera,
      captureImage,
      closeCameraModal,
      apiResult,
      loading,
      targetBox,
      cameraPreview,
      errorMessage,
    };
  },
});
