# app/yolov8_ocr.py

import easyocr
import numpy as np
import cv2
from PIL import Image
import io
import re

# Initialize EasyOCR with detection enabled
ocr_reader = easyocr.Reader(["en"], detector=True)

# Clean + validate logic
def clean_text(text: str) -> str:
    return text.strip().replace(" ", "").upper()

def is_valid_serial(text: str) -> bool:
    if len(text) < 6:
        return False
    if not re.search(r'[A-Z]', text):
        return False
    if not re.search(r'[0-9]', text):
        return False
    junk_words = ["FC", "PATP", "ROHS", "CE", "CHINA"]
    return text not in junk_words

# Preprocessing for engraved/low-contrast text
def enhance_engraved_text(image: Image.Image):
    img_gray = np.array(image.convert("L"))  # grayscale
    eq = cv2.equalizeHist(img_gray)  # contrast boost
    edges = cv2.Canny(eq, 50, 150)   # edge detection
    combined = cv2.addWeighted(eq, 0.8, edges, 0.5, 0)
    return combined

def to_python(val):
    if isinstance(val, (np.generic,)):  # numpy float32, int32 etc.
        return val.item()
    elif isinstance(val, (list, tuple)):
        return [to_python(v) for v in val]
    elif isinstance(val, dict):
        return {k: to_python(v) for k, v in val.items()}
    return val

# Main detection function
def detect_serial_number(image_bytes: bytes):
    try:
        image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
        img_np = np.array(image)

        method_used = "normal"  # default to normal OCR
        serials = []
        raw_ocr = []

        # First OCR attempt (original image)
        ocr_results = ocr_reader.readtext(img_np)

        for bbox, text, _ in ocr_results:
            cleaned = clean_text(text)
            raw_ocr.append(cleaned)
            if is_valid_serial(cleaned):
                serials.append({
                    "text": cleaned,
                    "bbox": bbox  # 4-point bounding box (x, y coordinates)
                })

        # Fallback if no valid serials found
        if not serials:
            enhanced_img = enhance_engraved_text(image)
            ocr_results_enhanced = ocr_reader.readtext(enhanced_img)
            method_used = "engraved_fallback"

            for bbox, text, _ in ocr_results_enhanced:
                cleaned = clean_text(text)
                raw_ocr.append(cleaned)
                if is_valid_serial(cleaned):
                    serials.append({
                        "text": cleaned,
                        "bbox": bbox
                    })

        return to_python({
            "serials": serials,
            "raw_ocr": raw_ocr,
            "method": method_used
        })


    except Exception as e:
        return {
            "serials": [],
            "raw_ocr": [],
            "method": None,
            "error": str(e)
        }

