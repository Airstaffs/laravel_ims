from fastapi import FastAPI, File, UploadFile
from yolov8_ocr import detect_serial_number
from fastapi.middleware.cors import CORSMiddleware

# FastAPI app for OCR detection using YOLOv8 and EasyOCR
# ✅ Create FastAPI app first
app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, replace "*" with your frontend's URL
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 🎯 Endpoint for serial detection
@app.post("/detect")
async def detect(file: UploadFile = File(...)):
    image_data = await file.read()
    result = detect_serial_number(image_data)
    return result
