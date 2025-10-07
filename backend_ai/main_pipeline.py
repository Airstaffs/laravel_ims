import io
import os
import cv2
import numpy as np
from PIL import Image
from detection import detect_regions
from ocr import _ocr_serial_candidates, _ocr_from_crop, _json_safe
from pyzbar.pyzbar import decode as zbar_decode
import zxingcpp  # pip install zxing-cpp

def _run_barcode_gate(pil_image):
    """Detect barcodes, QR codes, DataMatrix with pyzbar and fallback to zxingcpp."""
    try:
        cv_img = cv2.cvtColor(np.array(pil_image), cv2.COLOR_RGB2BGR)

        # 🔥 Preprocess: grayscale + resize + threshold
        gray = cv2.cvtColor(cv_img, cv2.COLOR_BGR2GRAY)
        gray = cv2.resize(gray, None, fx=2, fy=2, interpolation=cv2.INTER_LINEAR)
        _, thresh = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)

        # NEW: inverted versions (for white-on-black barcodes)
        inv_gray = cv2.bitwise_not(gray)
        inv_thresh = cv2.bitwise_not(thresh)

        # --- Try pyzbar first (QR + 1D barcodes) ---
        decoded = (
            zbar_decode(cv_img) or
            zbar_decode(gray) or
            zbar_decode(thresh) or
            zbar_decode(inv_gray) or
            zbar_decode(inv_thresh)
        )
        codes = []
        for obj in decoded:
            value = obj.data.decode("utf-8").strip()
            if value:
                codes.append({
                    "type": obj.type,  # e.g. QRCODE, CODE128, EAN13
                    "value": value,
                    "bbox": (obj.rect.left, obj.rect.top, obj.rect.width, obj.rect.height)
                })

        if codes:
            print("✅ pyzbar detected:", codes)
            return {
                "serials": [{"text": c["value"]} for c in codes],
                "code_hits": codes,
                "debug": f"✅ pyzbar detected: {codes}"
            }

        # --- Fallback: try ZXing (handles DataMatrix + multiple + inverted) ---
        results = (
            zxingcpp.read_barcodes(cv_img) or
            zxingcpp.read_barcodes(gray) or
            zxingcpp.read_barcodes(inv_gray)
        )

        if results:
            parsed = [
                {
                    "type": str(r.format),   # e.g. "DataMatrix", "QRCode", "Code128"
                    "value": r.text,
                    "bbox": None
                }
                for r in results if r.text
            ]
            print("✅ ZXing detected:", parsed)
            return {
                "serials": [{"text": r["value"]} for r in parsed],
                "code_hits": parsed,
                "debug": f"✅ ZXing detected: {parsed}"
            }

        # --- Nothing found ---
        print("❌ No barcode found")
        return {
            "serials": [],
            "code_hits": [],
            "debug": "❌ No barcode found"
        }

    except Exception as e:
        print("❌ Barcode error:", e)
        return {
            "serials": [],
            "code_hits": [],
            "error": str(e),
            "debug": f"❌ Barcode error: {e}"
        }

def detect_serial_number_pipeline(image_bytes: bytes):
    """
    Full pipeline for serial number detection:
    1. Barcode/QR scan
    2. YOLOv8 region detection + EasyOCR
    3. Fallback: EasyOCR on entire image
    """
    try:
        image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
    except Exception as e:
        return {
            "error": f"Invalid image: {e}",
            "serials": [],
            "codes": [],
            "debug": "❌ Invalid image"
        }

    # --- Step 1: Barcode / QR detection ---
    barcode_result = _run_barcode_gate(image)
    if barcode_result.get("serials"):   # ✅ only return if barcode found
        return _json_safe({
            "found": True,
            "method": "barcode_or_qr",
            "serials": barcode_result["serials"],
            "codes": barcode_result["code_hits"],
            "raw_ocr": "",
            "debug": barcode_result.get("debug", "No debug info")
        })

    # --- Step 2: YOLO detection + OCR ---
    yolo_crops = detect_regions(image)
    all_serials, all_raw = [], []
    debug_image_path = None

    if yolo_crops:
        from detection import detect_and_visualize
        debug_filename = "pipeline_debug.jpg"
        detect_and_visualize(image, filename=debug_filename)
        debug_image_path = os.path.join("backend_ai", "debug_images", debug_filename)

        for crop in yolo_crops:
            ocr_result = _ocr_from_crop(crop)
            all_serials.extend(ocr_result["serials"])
            all_raw.append(ocr_result["raw_ocr"])

        return _json_safe({
            "found": bool(all_serials),
            "method": "yolo+ocr",
            "serials": all_serials,
            "codes": barcode_result.get("code_hits", []),
            "raw_ocr": "\n".join(all_raw),
            "debug_image": debug_image_path,
            "debug": f"OCR results: {all_raw}"
        })

    # --- Step 3: Fallback EasyOCR ---
    ocr_result = _ocr_serial_candidates(image)
    return _json_safe({
        "found": bool(ocr_result["serials"]),
        "method": "ocr_fallback",
        "serials": ocr_result["serials"],
        "codes": barcode_result.get("code_hits", []),
        "raw_ocr": ocr_result["raw_ocr"],
        "debug": "Fallback OCR used"
    })
