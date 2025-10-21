from fastapi import FastAPI, File, UploadFile
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import RedirectResponse, Response

# Import our new pipeline
from main_pipeline import detect_serial_number_pipeline
from ocr import detect_serial_number_quick  # keep quick OCR for camera frames

# FastAPI app for OCR detection using YOLOv8 + EasyOCR
app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "https://test.tecniquality.com",  # ✅ your HTTPS live site
        "http://test.tecniquality.com",   # ✅ in case your live uses HTTP (redirect or staging)
        "http://localhost:8000",          # ✅ for local Laravel frontend
        "http://127.0.0.1:8000",          # ✅ for local fallback (different origin technically)
    ],
    allow_credentials=True,  # ✅ required since Laravel/Axios use withCredentials
    allow_methods=["*"],     # ✅ allow GET, POST, PUT, etc.
    allow_headers=["*"],     # ✅ allow custom headers like X-CSRF-TOKEN, Content-Type
)

# 🎯 Upload detection (full pipeline: Barcode → YOLO → EasyOCR → fallback)
@app.post("/detect")
async def detect(file: UploadFile = File(...)):
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

# 📷 Camera frame detection (quick EasyOCR only, for live feeds)
@app.post("/detect-camera-frame")
async def detect_camera_frame(file: UploadFile = File(...)):
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
