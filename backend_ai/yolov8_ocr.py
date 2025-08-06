import easyocr
import numpy as np
import cv2
from PIL import Image
import io
from ultralytics import YOLO

# Load YOLO model (replace with your custom weights if available)
model = YOLO("yolov8n.pt")  # You can replace with "models/your_model.pt"

# Initialize EasyOCR
ocr_reader = easyocr.Reader(["en"], gpu=False)

def preprocess_image(image: Image.Image):
    """Enhance image contrast for better OCR."""
    img_np = np.array(image.convert("RGB"))
    gray = cv2.cvtColor(img_np, cv2.COLOR_RGB2GRAY)
    gray = cv2.equalizeHist(gray)
    return gray

def detect_serial(image_bytes: bytes):
    """Detect serial number from uploaded image bytes."""
    # Open image from bytes
    image = Image.open(io.BytesIO(image_bytes))

    # Run YOLO to detect potential serial number regions
    results = model.predict(np.array(image), conf=0.25)
    serial_texts = []

    for result in results:
        for box in result.boxes.xyxy:
            x1, y1, x2, y2 = map(int, box)
            cropped = image.crop((x1, y1, x2, y2))
            preprocessed = preprocess_image(cropped)
            ocr_result = ocr_reader.readtext(preprocessed)
            for (_, text, conf) in ocr_result:
                if conf > 0.5:
                    serial_texts.append(text)

    # If YOLO found nothing, run OCR on the whole image
    if not serial_texts:
        preprocessed = preprocess_image(image)
        ocr_result = ocr_reader.readtext(preprocessed)
        for (_, text, conf) in ocr_result:
            if conf > 0.5:
                serial_texts.append(text)

    return serial_texts[0] if serial_texts else None
