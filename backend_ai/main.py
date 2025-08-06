from fastapi import FastAPI, File, UploadFile
from yolov8_ocr import detect_serial

app = FastAPI()

@app.post("/detect")
async def detect(file: UploadFile = File(...)):
    image_data = await file.read()
    serial_number = detect_serial(image_data)
    return {"serial_number": serial_number}
