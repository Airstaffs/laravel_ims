# backend_ai/main.py
from fastapi import FastAPI, File, UploadFile
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import RedirectResponse, Response

# Import model pipelines
from serial_number.main_pipeline import detect_serial_number_pipeline
from asin_assign.detection import predict_asin
from serial_number.ocr import detect_serial_number_quick  # keep quick OCR for camera frames

# ✅ 1️⃣ Create FastAPI app FIRST
app = FastAPI()

# ✅ 2️⃣ Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "https://test.tecniquality.com",  # ✅ live
        "http://test.tecniquality.com",   # ✅ http fallback
        # "http://localhost:8001",          # ✅ local
        # "http://127.0.0.1:8001",
        "http://127.0.0.1:8000",
        "http://localhost:8000",
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
# ✅ 3️⃣ Import and register training stream router (AFTER app exists)
from asin_assign_training.train_stream import router as train_stream_router
app.include_router(train_stream_router)

from asin_assign_training import dataset_browser
app.include_router(dataset_browser.router)

from asin_assign_training import datasets  # ✅ add this
app.include_router(datasets.router)

# ✅ 3️⃣ Import and register upload router (AFTER app exists)
from asin_assign_training.upload_dataset import router as upload_router
app.include_router(upload_router)

from asin_assign_training import train_results
app.include_router(train_results.router)

from asin_assign_training.train_model import router as train_router
app.include_router(train_router)

from asin_assign_training.test_model import router as test_model_router
app.include_router(test_model_router)

from asin_assign_training.delete_dataset import router as delete_router
app.include_router(delete_router)

from asin_assign_training import image_manager
app.include_router(image_manager.router)

from asin_assign_training.update_model import router as update_model_router
app.include_router(update_model_router)

from asin_assign_training.upload_bulk_dataset import router as upload_bulk_router
app.include_router(upload_bulk_router)


# ✅ 4️⃣ Existing endpoints

# 🎯 Upload detection (Barcode → YOLO → EasyOCR → fallback)
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

# 🔢 ASIN assignment
@app.post("/asin-assign")
async def asin_assign(files: list[UploadFile] = File(...)):
    try:
        results = []
        for file in files:
            image_data = await file.read()
            results.append(predict_asin(image_data))

        # Aggregate top predictions across images
        all_preds = []
        for r in results:
            if r["found"]:
                all_preds.extend(r["predictions"])

        if not all_preds:
            return {"asin": None, "confidence": 0.0, "images_evaluated": len(files)}

        # Compute mean confidence per ASIN
        from collections import defaultdict
        asin_conf = defaultdict(list)
        for p in all_preds:
            asin_conf[p["asin"]].append(p["confidence"])

        avg_conf = {a: sum(c) / len(c) for a, c in asin_conf.items()}
        final_asin = max(avg_conf, key=avg_conf.get)
        confidence = avg_conf[final_asin]

        return {
            "asin": final_asin,
            "confidence": round(confidence, 4),
            "images_evaluated": len(files),
        }

    except Exception as e:
        print(f"❌ ASIN assign error: {e}")
        return JSONResponse(status_code=500, content={"error": str(e)})

# 📷 Camera frame detection (quick EasyOCR only)
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
