from fastapi import FastAPI, File, UploadFile
from yolov8_ocr import detect_serial_number
from fastapi.middleware.cors import CORSMiddleware

# FastAPI app for OCR detection using YOLOv8 and EasyOCR
# ✅ Create FastAPI app first
app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8000"],  # your Vue dev server origin
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 🎯 Endpoint for serial detection
@app.post("/detect")
async def detect(file: UploadFile = File(...)):
    try:
        image_data = await file.read()
        result = detect_serial_number(image_data)
        return result
    except Exception as e:
        print(f"❌ Detection error: {str(e)}")  # Debug log in terminal
        return JSONResponse(
            status_code=500,
            content={"error": f"Internal server error: {str(e)}"}
        )
