import easyocr
import numpy as np
import cv2
from PIL import Image
import io
import re

# =============================
# Initialize EasyOCR (once)
# =============================
ocr_reader = easyocr.Reader(
    ["en"], 
    gpu=True, 
    detector=True
)

# =============================
# Utility functions
# =============================
def clean_text(text: str) -> str:
    return text.strip().replace(" ", "").upper()

# Serial number patterns (adjust to your actual formats)
SERIAL_PATTERNS = [
    r'^[A-Z]{2,4}\d{4,8}$',      # e.g., AB123456, ABCD12345678
    r'^\d{4,8}[A-Z]{2,4}$',      # e.g., 123456AB
    r'^[A-Z0-9\-]{6,15}$'        # general: mix of letters/numbers/hyphen, 6–15 chars
]

def is_valid_serial(text: str) -> bool:
    """
    Valid serial rules:
    - Pure digits: at least 4 in a row
    - Alphanumeric (uppercase + digits + dash): length >= 5
    """
    if not text:
        return False

    # Pure numbers (allow short ones like 8018)
    if re.fullmatch(r"\d{4,}", text):
        return True

    # Alphanumeric + dash (must have at least 5 total)
    if re.fullmatch(r"[A-Z0-9\-]{5,}", text):
        return True

    return False

def preprocess_image(image: Image.Image):
    """
    Preprocess image to enhance OCR accuracy.
    Works well for printed and engraved serials.
    """
    img_gray = np.array(image.convert("L"))  # grayscale
    img_denoised = cv2.fastNlMeansDenoising(img_gray, h=10)  # noise removal
    img_contrast = cv2.convertScaleAbs(img_denoised, alpha=1.5, beta=0)  # contrast boost
    img_thresh = cv2.adaptiveThreshold(
        img_contrast, 255, 
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C, 
        cv2.THRESH_BINARY, 31, 2
    )
    return img_thresh

def enhance_engraved_text(image: Image.Image):
    """
    Fallback enhancement for low-contrast engraved text.
    """
    img_gray = np.array(image.convert("L"))
    eq = cv2.equalizeHist(img_gray)
    edges = cv2.Canny(eq, 50, 150)
    combined = cv2.addWeighted(eq, 0.8, edges, 0.5, 0)
    return combined

def to_python(val):
    if isinstance(val, (np.generic,)):
        return val.item()
    elif isinstance(val, (list, tuple)):
        return [to_python(v) for v in val]
    elif isinstance(val, dict):
        return {k: to_python(v) for k, v in val.items()}
    return val

def strip_serial_noise(text: str) -> str:
    """
    Remove trailing manufacturing/location words from detected serials.
    """
    return re.sub(r'(MADE|IN|JAPAN|CHINA|USA|KOREA)$', '', text)


def merge_serial_fragments(ocr_results):
    """
    Merge OCR text chunks if they look like parts of the same serial.
    Works even if they are not directly consecutive in the OCR output.
    """
    merged = []
    used = set()

    for i in range(len(ocr_results)):
        if i in used:
            continue
        current = ocr_results[i]
        curr_text = current["text"]

        # Try to find a partner
        merged_found = False
        for j in range(i + 1, len(ocr_results)):
            if j in used:
                continue
            other = ocr_results[j]

            # Check horizontal alignment (same line-ish)
            if abs(current["bbox"][0][1] - other["bbox"][0][1]) < 20:
                combined = curr_text + other["text"]

                if is_valid_serial(combined):
                    merged.append({
                        "text": combined,
                        "confidence": (current["confidence"] + other["confidence"]) / 2,
                        "bbox": current["bbox"]
                    })
                    used.update([i, j])
                    merged_found = True
                    break

        if not merged_found:
            merged.append(current)
            used.add(i)

    return merged

# =============================
# Full detection (used for uploads)
# =============================
def detect_serial_number(image_bytes: bytes):
    try:
        image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
        method_used = "preprocessed"
        serials = []
        raw_ocr = []

        # Preprocess before OCR
        preprocessed_img = preprocess_image(image)
        ocr_results = ocr_reader.readtext(
            preprocessed_img,
            allowlist='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-'
        )

        # Store all OCR results
        for bbox, text, conf in ocr_results:
            cleaned = clean_text(text)
            raw_ocr.append({"text": cleaned, "confidence": conf, "bbox": bbox})

        # Clean noise words
        for o in raw_ocr:
            o["text"] = strip_serial_noise(o["text"])

        # Merge fragments (this may create more valid serial candidates)
        raw_ocr = merge_serial_fragments(raw_ocr)

        # Keywords and skip words
        KEYWORDS = {"SERIAL", "SERIALNO", "S/N", "SN"}
        SKIP_AFTER_KEYWORDS = {"NO", "NUMBER", "NUM"}

        chosen_serial = None

        # 1) Keyword in SAME chunk or next chunk
        keyword_indexes = [i for i, o in enumerate(raw_ocr) if any(k in o["text"] for k in KEYWORDS)]
        if keyword_indexes:
            for idx in keyword_indexes:
                candidate_text = raw_ocr[idx]["text"]

                # Same-chunk: e.g., "SN07458980460852JAI"
                for k in KEYWORDS:
                    if candidate_text.startswith(k) and len(candidate_text) > len(k):
                        possible_serial = candidate_text[len(k):]
                        if is_valid_serial(possible_serial):
                            chosen_serial = {
                                "text": possible_serial,
                                "confidence": raw_ocr[idx]["confidence"],
                                "bbox": raw_ocr[idx]["bbox"]
                            }
                            break

                # Next chunk
                if not chosen_serial:
                    look_ahead = idx + 1
                    while look_ahead < len(raw_ocr) and raw_ocr[look_ahead]["text"] in SKIP_AFTER_KEYWORDS:
                        look_ahead += 1
                    if look_ahead < len(raw_ocr):
                        candidate = raw_ocr[look_ahead]
                        if is_valid_serial(candidate["text"]):
                            chosen_serial = candidate
                            break

        # 2) Highest-confidence valid serial
        if not chosen_serial:
            valid_serials = [
                o for o in raw_ocr
                if o["confidence"] >= 0.5 and is_valid_serial(o["text"])
            ]
            if valid_serials:
                valid_serials.sort(key=lambda x: x["confidence"], reverse=True)
                chosen_serial = valid_serials[0]

        # 3) Engraved fallback
        if not chosen_serial:
            enhanced_img = enhance_engraved_text(image)  # numpy array
            ocr_results_enhanced = ocr_reader.readtext(
                enhanced_img,
                allowlist='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-'
            )
            method_used = "engraved_fallback"

            enhanced_ocr = []
            for bbox, text, conf in ocr_results_enhanced:
                cleaned = clean_text(text)
                enhanced_ocr.append({"text": cleaned, "confidence": conf, "bbox": bbox})
                raw_ocr.append({"text": cleaned, "confidence": conf, "bbox": bbox})

            keyword_indexes = [i for i, o in enumerate(enhanced_ocr) if any(k in o["text"] for k in KEYWORDS)]
            if keyword_indexes:
                for idx in keyword_indexes:
                    candidate_text = enhanced_ocr[idx]["text"]

                    for k in KEYWORDS:
                        if candidate_text.startswith(k) and len(candidate_text) > len(k):
                            possible_serial = candidate_text[len(k):]
                            if is_valid_serial(possible_serial):
                                chosen_serial = {
                                    "text": possible_serial,
                                    "confidence": enhanced_ocr[idx]["confidence"],
                                    "bbox": enhanced_ocr[idx]["bbox"]
                                }
                                break

                    if not chosen_serial:
                        look_ahead = idx + 1
                        while look_ahead < len(enhanced_ocr) and enhanced_ocr[look_ahead]["text"] in SKIP_AFTER_KEYWORDS:
                            look_ahead += 1
                        if look_ahead < len(enhanced_ocr):
                            candidate = enhanced_ocr[look_ahead]
                            if is_valid_serial(candidate["text"]):
                                chosen_serial = candidate
                                break

            if not chosen_serial:
                valid_serials = [
                    o for o in enhanced_ocr
                    if o["confidence"] >= 0.5 and is_valid_serial(o["text"])
                ]
                if valid_serials:
                    valid_serials.sort(key=lambda x: x["confidence"], reverse=True)
                    chosen_serial = valid_serials[0]

        # 3.5) High-contrast inverted pass (FIX: convert PIL -> NumPy)
        if not chosen_serial:
            from PIL import ImageOps, ImageEnhance

            method_used = "high_contrast_inverted"
            inverted_img = ImageOps.invert(image.convert("RGB"))
            contrast_img = ImageEnhance.Contrast(inverted_img).enhance(3)

            contrast_np = np.array(contrast_img)  # <-- crucial fix

            ocr_results_inverted = ocr_reader.readtext(
                contrast_np,
                allowlist='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-'
            )

            inverted_ocr = []
            for bbox, text, conf in ocr_results_inverted:
                cleaned = clean_text(text)
                inverted_ocr.append({"text": cleaned, "confidence": conf, "bbox": bbox})
                raw_ocr.append({"text": cleaned, "confidence": conf, "bbox": bbox})

            inverted_ocr = merge_serial_fragments(inverted_ocr)

            keyword_indexes = [i for i, o in enumerate(inverted_ocr) if any(k in o["text"] for k in KEYWORDS)]
            if keyword_indexes:
                for idx in keyword_indexes:
                    candidate_text = inverted_ocr[idx]["text"]

                    for k in KEYWORDS:
                        if candidate_text.startswith(k) and len(candidate_text) > len(k):
                            possible_serial = candidate_text[len(k):]
                            if is_valid_serial(possible_serial):
                                chosen_serial = {
                                    "text": possible_serial,
                                    "confidence": inverted_ocr[idx]["confidence"],
                                    "bbox": inverted_ocr[idx]["bbox"]
                                }
                                break

                    if not chosen_serial:
                        look_ahead = idx + 1
                        while look_ahead < len(inverted_ocr) and inverted_ocr[look_ahead]["text"] in SKIP_AFTER_KEYWORDS:
                            look_ahead += 1
                        if look_ahead < len(inverted_ocr):
                            candidate = inverted_ocr[look_ahead]
                            if is_valid_serial(candidate["text"]):
                                chosen_serial = candidate
                                break

            if not chosen_serial:
                valid_serials = [
                    o for o in inverted_ocr
                    if o["confidence"] >= 0.4 and is_valid_serial(o["text"])
                ]
                if valid_serials:
                    valid_serials.sort(key=lambda x: x["confidence"], reverse=True)
                    chosen_serial = valid_serials[0]

        # 4) Return
        serials = [chosen_serial] if chosen_serial else []
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
