import io
import sys
import numpy as np
from PIL import Image
from paddleocr import PaddleOCR

# 🔥 Initialize PaddleOCR once
_paddle_reader = PaddleOCR(
    use_angle_cls=True,
    lang="en",
)

def detect_serial_number_paddleocr(image_bytes: bytes, use_gpu: bool = False):
    """
    Quick detection using PaddleOCR (whole image).
    Returns raw text + confidence.
    """
    try:
        pil_img = Image.open(io.BytesIO(image_bytes)).convert("RGB")
        np_img = np.array(pil_img)

        results = _paddle_reader.ocr(np_img)  # ✅ no cls=True
        serials = []

        if results and results[0]:
            for line in results[0]:
                if line:
                    bbox, (text, conf) = line
                    serials.append({
                        "text": text,
                        "confidence": float(conf),
                        "bbox": bbox,
                        "source": "paddle_quick"
                    })

        return {
            "found": bool(serials),
            "method": "paddle_quick",
            "serials": serials,
            "codes": [],
            "raw_ocr": [f"{x['text']} (conf: {x['confidence']:.2f})" for x in serials]
        }

    except Exception as e:
        return {"error": str(e), "found": False, "method": "paddle-error"}


def detect_serial_number_paddleocr_full(image_bytes: bytes, use_gpu: bool = False):
    """
    Full detection pipeline placeholder.
    (For now: same as quick, later can add YOLO crops + preprocessing.)
    """
    return detect_serial_number_paddleocr(image_bytes, use_gpu)


def compare_paddleocr_vs_easyocr(image_bytes: bytes, use_gpu: bool = False):
    """
    Compare PaddleOCR vs EasyOCR on the same image.
    """
    try:
        from ocr import detect_serial_number_quick as easyocr_quick
        paddle_result = detect_serial_number_paddleocr(image_bytes, use_gpu)
        easy_result = easyocr_quick(image_bytes)

        return {
            "paddleocr": paddle_result,
            "easyocr": easy_result
        }
    except ImportError:
        return {
            "error": "EasyOCR not available",
            "paddleocr": detect_serial_number_paddleocr(image_bytes, use_gpu)
        }


# --- CLI debug mode ---
def test_paddleocr(image_path: str):
    results = _paddle_reader.ocr(image_path)
    if not results or not results[0]:
        print("⚠️ No text detected by PaddleOCR")
        return
    for line in results[0]:
        if line:
            bbox, (text, conf) = line
            print(f"RAW: '{text}'  (conf: {conf:.2f})  BBox: {bbox}")


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python ocr_paddle.py <image_path>")
        sys.exit(1)
    image_path = sys.argv[1]
    test_paddleocr(image_path)
