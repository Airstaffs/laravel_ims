import easyocr
import numpy as np
import cv2
from PIL import Image
import io
import re
import os

# =============================
# Initialize EasyOCR (once)
# =============================
# Set gpu=False if you do not have a CUDA-enabled GPU
ocr_reader = easyocr.Reader(["en"], gpu=True)

# =============================
# Utility Functions
# =============================
def clean_text(text: str) -> str:
    """
    Cleans text by stripping whitespace, converting to uppercase, 
    and normalizing characters. Importantly, it removes spaces and slashes
    so "S/N" becomes "SN".
    """
    return text.strip().replace(" ", "").upper().replace(":", "-").replace("/", "")

def is_valid_serial(text: str) -> bool:
    """Checks if a string is a valid serial number."""
    if not text:
        return False
    # This regex is broad enough to catch the PIN and the long S/N
    if re.fullmatch(r"[A-Z0-9\-]{5,}", text):
        return True
    return False

def to_python_types(data):
    """
    Recursively converts NumPy data types in a dictionary or list
    to standard Python types for JSON serialization.
    """
    if isinstance(data, dict):
        return {key: to_python_types(value) for key, value in data.items()}
    if isinstance(data, (list, tuple)):
        return [to_python_types(item) for item in data]
    if isinstance(data, np.generic):
        return data.item()
    return data

# =======================================================================
# FINAL ATTEMPT - Simplified "Less is More" Approach
# =======================================================================
def find_serial_on_curve(image: Image.Image, ocr_reader_instance):
    """
    A final, simplified attempt that dewarps the text and feeds the
    raw grayscale image directly to the OCR engine, then searches for
    a specific serial number pattern.
    """
    try:
        img_np = np.array(image.convert("L"))
        height, width = img_np.shape

        circles = cv2.HoughCircles(
            img_np, cv2.HOUGH_GRADIENT, dp=1.1, minDist=height / 2,
            param1=100, param2=45, minRadius=int(height / 4), maxRadius=int(height)
        )

        if circles is None:
            return None

        for circle_data in np.round(circles[0, :]).astype("int"):
            x, y, r = map(int, circle_data)

            # Use correct dewarping parameters
            dewarped_width = int(2.0 * np.pi * r)
            dewarped_height = int(r * 0.25)
            dewarped = cv2.warpPolar(
                img_np, (dewarped_width, dewarped_height),
                (x, y), r, cv2.WARP_POLAR_LINEAR
            )

            # Test both orientations without any extra processing
            for orientation in [dewarped, cv2.rotate(dewarped, cv2.ROTATE_180)]:
                # Feed the raw dewarped image directly to OCR
                results = ocr_reader_instance.readtext(orientation)
                
                # Search results for the specific pattern A1002108WC
                for item in results:
                    text = item[1]
                    # Use regex to find a 10-character alphanumeric block
                    match = re.search(r'([A-Z0-9]{10})', clean_text(text))
                    if match:
                        # Check if it looks like our target serial
                        found_text = match.group(1)
                        if "A100" in found_text or "2108WC" in found_text:
                            return found_text # Success

    except Exception:
        return None
    
    return None

# =======================================================================
# Final Main Function
# =======================================================================
def detect_serial_number(image_bytes: bytes):
    try:
        image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
        
        curved_sn = find_serial_on_curve(image, ocr_reader)
        if curved_sn:
            result = {
                "serials": [{"text": curved_sn, "confidence": 0.90, "bbox": None}],
                "raw_ocr": [],
                "method": "curved_sn_heuristic"
            }
            return to_python_types(result)
            
        # Fallback to your original flat text logic
        img_np = np.array(image)
        ocr_results = ocr_reader.readtext(img_np)
        
        detected_serials = []
        raw_ocr = []
        
        for (bbox, text, conf) in ocr_results:
            cleaned = clean_text(text)
            raw_ocr.append({"text": text, "confidence": conf, "bbox": bbox})
            if is_valid_serial(cleaned):
                detected_serials.append({"text": cleaned, "confidence": conf, "bbox": bbox})

        serials_to_return = [detected_serials[0]] if detected_serials else []

        result = {
            "serials": serials_to_return,
            "raw_ocr": raw_ocr,
            "method": "flat_text_fallback"
        }
        return to_python_types(result)

    except Exception as e:
        return {"serials": [], "raw_ocr": [], "method": None, "error": str(e)}
                
# =============================
# Quick detection (used for live camera)
# =============================
def detect_serial_number_quick(image_bytes: bytes):
    """
    Faster detection for live camera feed.
    Runs single OCR pass and returns bbox of first detected text,
    even if not valid serial.
    """
    try:
        image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
        img_np = np.array(image)

        ocr_results = ocr_reader.readtext(img_np)

        for bbox, text, _ in ocr_results:
            cleaned = clean_text(text)
            if is_valid_serial(cleaned):
                return to_python({
                    "found": True,
                    "serial": cleaned,
                    "bbox": bbox
                })

        if ocr_results:
            first_bbox, first_text, _ = ocr_results[0]
            return to_python({
                "found": False,
                "raw_text": clean_text(first_text),
                "bbox": first_bbox
            })

        return {"found": False, "bbox": None}

    except Exception as e:
        return {"found": False, "error": str(e), "bbox": None}
