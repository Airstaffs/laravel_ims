import io
import os
from PIL import Image
from detection import detect_regions
from ocr import _ocr_serial_candidates, _run_barcode_gate, _json_safe


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
        return {"error": f"Invalid image: {e}", "serials": [], "codes": []}

    # --- Step 1: Barcode / QR detection ---
    barcode_result = _run_barcode_gate(image)
    if barcode_result["serials"]:
        return _json_safe({
            "found": True,
            "method": "barcode_or_qr",
            "serials": barcode_result["serials"],
            "codes": barcode_result["code_hits"],
            "raw_ocr": ""
        })

    # --- Step 2: YOLO detection + OCR ---
    yolo_crops = detect_regions(image)
    all_serials, all_raw = [], []
    debug_image_path = None

    if yolo_crops:
        # 🔥 Generate and save debug visualization
        from detection import detect_and_visualize
        debug_filename = "pipeline_debug.jpg"
        detect_and_visualize(image, filename=debug_filename)
        debug_image_path = os.path.join("backend_ai", "debug_images", debug_filename)

        for crop in yolo_crops:
            ocr_result = _ocr_serial_candidates(crop)
            all_serials.extend(ocr_result["serials"])
            all_raw.append(ocr_result["raw_ocr"])

        return _json_safe({
            "found": bool(all_serials),
            "method": "yolo+ocr",
            "serials": all_serials,
            "codes": barcode_result["code_hits"],
            "raw_ocr": "\n".join(all_raw),
            "debug_image": debug_image_path   # 👈 Added path to JSON
        })


    # --- Step 3: Fallback EasyOCR ---
    ocr_result = _ocr_serial_candidates(image)
    return _json_safe({
        "found": bool(ocr_result["serials"]),
        "method": "ocr_fallback",
        "serials": ocr_result["serials"],
        "codes": barcode_result["code_hits"],
        "raw_ocr": ocr_result["raw_ocr"]
    })
