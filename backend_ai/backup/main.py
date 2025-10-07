from fastapi import FastAPI, File, UploadFile
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import RedirectResponse, Response

# Import existing pipelines
from main_pipeline import detect_serial_number_pipeline
from ocr import detect_serial_number_quick  # EasyOCR for camera frames

# Import PaddleOCR functions (with graceful fallback)
try:
    from ocr_paddle import (
        detect_serial_number_paddleocr, 
        detect_serial_number_paddleocr_full,
        compare_paddleocr_vs_easyocr
    )
    PADDLEOCR_AVAILABLE = True
    print("✅ PaddleOCR pipeline integration loaded successfully")
except ImportError as e:
    PADDLEOCR_AVAILABLE = False
    print(f"⚠️ PaddleOCR pipeline not available: {e}")


# FastAPI app for OCR detection using YOLOv8 + EasyOCR + PaddleOCR
app = FastAPI(
    title="Serial Number Detection API",
    description="Advanced OCR pipeline with YOLO detection, EasyOCR, and PaddleOCR support",
    version="2.0.0"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8009", "http://127.0.0.1:8009"],  # adjust for frontend
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 🎯 Original endpoints (EasyOCR-based)
@app.post("/detect")
async def detect(file: UploadFile = File(...)):
    """
    Full detection pipeline: Barcode → YOLO → EasyOCR → fallback
    Uses the original EasyOCR-based pipeline
    """
    try:
        image_data = await file.read()
        result = detect_serial_number_pipeline(image_data)
        return result
    except Exception as e:
        print(f"❌ Detection error: {str(e)}")
        return JSONResponse(
            status_code=500,
            content={"error": f"Internal server error: {str(e)}"}
        )

@app.post("/detect-camera-frame")
async def detect_camera_frame(file: UploadFile = File(...)):
    """
    Camera frame detection (quick EasyOCR only, for live feeds)
    Optimized for speed over accuracy
    """
    try:
        image_data = await file.read()
        result = detect_serial_number_quick(image_data)
        return result
    except Exception as e:
        print(f"❌ Camera frame detection error: {str(e)}")
        return JSONResponse(
            status_code=500,
            content={"error": f"Internal server error: {str(e)}"}
        )

# 🔥 NEW: PaddleOCR endpoints
@app.post("/detect-paddle")
async def detect_paddle(file: UploadFile = File(...)):
    """
    Direct PaddleOCR test (whole image, raw results).
    This uses the REAL PaddleOCR library, not our custom wrapper.
    """
    try:
        import io
        import numpy as np
        from PIL import Image
        from paddleocr import PaddleOCR  # ✅ ensure we load the real lib

        image_data = await file.read()

        # Init PaddleOCR (English, angle support)
        ocr = PaddleOCR(use_angle_cls=True, lang="en")

        pil_img = Image.open(io.BytesIO(image_data)).convert("RGB")
        np_img = np.array(pil_img)

        results = ocr.ocr(np_img)
        raw_texts = []
        serials = []

        if results and results[0]:
            for line in results[0]:
                # ✅ Safety checks
                if not line or len(line) < 2:
                    continue
                if not isinstance(line[1], (list, tuple)) or len(line[1]) < 2:
                    continue

                bbox = line[0]                     # polygon points
                text = str(line[1][0]).strip()     # detected text
                conf = float(line[1][1])           # confidence

                # ✅ Clean up text
                text = text.upper()
                if text.startswith("S/N"):
                    text = text.replace("S/N", "").replace(":", "").strip()

                raw_texts.append(f"{text} (conf: {conf:.2f})")

                if conf > 0.5 and any(c.isdigit() for c in text):  # must have numbers
                    serials.append({
                        "text": text,
                        "confidence": conf,
                        "bbox": bbox
                    })

        return {
            "found": bool(serials),
            "method": "paddle_raw",
            "serials": serials,
            "codes": [],
            "raw_ocr": raw_texts
        }

    except Exception as e:
        print(f"❌ PaddleOCR detection error: {str(e)}")
        return JSONResponse(
            status_code=500,
            content={"error": f"Internal server error: {str(e)}"}
        )

@app.post("/detect-paddle-quick")
async def detect_paddle_quick(file: UploadFile = File(...)):
    """
    Quick PaddleOCR detection (for camera frames)
    Faster than full pipeline, good for real-time processing
    """
    try:
        image_data = await file.read()
        result = detect_serial_number_paddleocr(image_data, use_gpu=False)
        return result
    except Exception as e:
        print(f"❌ PaddleOCR quick detection error: {str(e)}")
        return JSONResponse(
            status_code=500,
            content={"error": f"Internal server error: {str(e)}"}
        )

@app.post("/detect-paddle-gpu")
async def detect_paddle_gpu(file: UploadFile = File(...)):
    """
    GPU-accelerated PaddleOCR detection (if GPU available)
    Fastest and most accurate option
    """
    try:
        image_data = await file.read()
        result = detect_serial_number_paddleocr_full(image_data, use_gpu=True)
        return result
    except Exception as e:
        print(f"❌ PaddleOCR GPU detection error: {str(e)}")
        return JSONResponse(
            status_code=500,
            content={"error": f"Internal server error: {str(e)}"}
        )

# 🔬 Comparison and testing endpoints
@app.post("/detect-compare")
async def detect_compare(file: UploadFile = File(...)):
    """
    A/B test: Compare EasyOCR vs PaddleOCR side-by-side
    Returns results from both engines for comparison
    """
    try:
        image_data = await file.read()
        result = compare_paddleocr_vs_easyocr(image_data, use_gpu=False)
        return result
    except Exception as e:
        print(f"❌ Comparison detection error: {str(e)}")
        return JSONResponse(
            status_code=500,
            content={"error": f"Internal server error: {str(e)}"}
        )

# 📊 Status and info endpoints
@app.get("/status")
async def status():
    """
    API status and available OCR engines
    """
    return {
        "status": "running",
        "version": "2.0.0",
        "ocr_engines": {
            "easyocr": True,  # Assumed to be available
            "paddleocr": PADDLEOCR_AVAILABLE
        },
        "endpoints": {
            "easyocr": ["/detect", "/detect-camera-frame"],
            "paddleocr": ["/detect-paddle", "/detect-paddle-quick", "/detect-paddle-gpu"] if PADDLEOCR_AVAILABLE else [],
            "comparison": ["/detect-compare"] if PADDLEOCR_AVAILABLE else [],
            "utility": ["/status", "/health"]
        }
    }

@app.get("/health")
async def health():
    """
    Health check endpoint
    """
    return {"status": "healthy", "message": "Serial Number Detection API is running"}

@app.get("/")
async def root():
    """
    Root endpoint with API information
    """
    return {
        "message": "Serial Number Detection API v2.0.0",
        "description": "Advanced OCR pipeline with YOLO detection and dual OCR engine support",
        "engines": "EasyOCR + PaddleOCR",
        "docs": "/docs",
        "status": "/status"
    }

# 🔧 Development and debugging endpoints (optional)
if __name__ == "__main__":
    import uvicorn
    
    print("🚀 Starting Serial Number Detection API v2.0.0")
    print(f"📊 PaddleOCR Available: {PADDLEOCR_AVAILABLE}")
    print("📋 Available endpoints:")
    print("   🔍 /detect - Full EasyOCR pipeline")
    print("   📷 /detect-camera-frame - Quick EasyOCR")
    if PADDLEOCR_AVAILABLE:
        print("   🔥 /detect-paddle - Full PaddleOCR pipeline")
        print("   ⚡ /detect-paddle-quick - Quick PaddleOCR")
        print("   🚀 /detect-paddle-gpu - GPU PaddleOCR")
        print("   🔬 /detect-compare - A/B comparison")
    print("   📊 /status - API status")
    print("   💚 /health - Health check")
    print()
    
    # Run the server
    uvicorn.run(
        "main:app", 
        host="0.0.0.0", 
        port=8009, 
        reload=True,
        log_level="info"
    )
