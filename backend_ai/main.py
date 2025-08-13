from fastapi import FastAPI, File, UploadFile
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from yolov8_ocr import detect_serial_number, detect_serial_number_quick
from fastapi.responses import RedirectResponse, Response

# FastAPI app for OCR detection using YOLOv8 and EasyOCR
app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8000", "http://127.0.0.1:8000"],  # your Vue dev server origin
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 🎯 Upload detection (existing endpoint)
@app.post("/detect")
async def detect(file: UploadFile = File(...)):
    try:
        image_data = await file.read()
        result = detect_serial_number(image_data)  # full scan with fallback
        return result
    except Exception as e:
        print(f"❌ Detection error: {str(e)}")
        return JSONResponse(
            status_code=500,
            content={"error": f"Internal server error: {str(e)}"}
        )

# 📷 Camera frame detection (quick detection for live camera feed )
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



