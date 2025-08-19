import uvicorn
from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import logging
import os
import google.generativeai as genai
from PIL import Image
import io

# --- Gemini API Setup ---
# It's best practice to set your API key as an environment variable
# For testing, you can uncomment the line below and paste your key
os.environ['GOOGLE_API_KEY'] = "AIzaSyCtN8youYLKipJAeMqaBFwWk-psibFy6Lg"

try:
    genai.configure(api_key=os.environ["GOOGLE_API_KEY"])
except KeyError:
    print("ERROR: GOOGLE_API_KEY environment variable not set.")
    # You can add a fallback or exit here if needed

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="Gemini Predictive Serial Number Detector API")

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/")
def read_root():
    return {"message": "Welcome to the Gemini Predictive Serial Number Detector API"}

@app.post("/detect")
async def detect_serial_from_image(file: UploadFile = File(...)):
    """
    Accepts an image, sends it to Gemini for predictive analysis, and returns the serial number.
    It prioritizes the Pro model and falls back to the Flash model if Pro is unavailable.
    """
    if not file.content_type.startswith('image/'):
        raise HTTPException(status_code=400, detail="File must be an image.")

    try:
        image_bytes = await file.read()
        img = Image.open(io.BytesIO(image_bytes))

        # --- Define the prompt ---
        prompt = """
        Analyze the attached image.
        1. Identify the electronic device and its brand (e.g., "Bose SoundLink Color II").
        2. Read any visible or partial serial number (S/N).
        3. Based on the device brand and model, determine the standard format and length of its serial number.
        4. If the serial number is partially obscured, use the standard format to intelligently predict the complete serial number.
        5. Respond with ONLY the final, complete serial number. Do not include any labels, explanations, or extra text.
        6. Double-check your work for common OCR errors, paying close attention to visually similar characters like S/5, O/0, Z/7, and B/8. Prioritize the most likely character in the context of a serial number.
        """
        
        detected_text = ""
        source_model = ""

        # --- Step 1: Try the Pro model for the highest accuracy ---
        try:
            logger.info("Sending image to Gemini Pro for analysis...")
            pro_model = genai.GenerativeModel('gemini-1.5-pro-latest')
            response = pro_model.generate_content([prompt, img])
            detected_text = response.text.strip()
            source_model = "gemini-1.5-pro"
        except Exception as pro_error:
            logger.warning(f"Gemini Pro failed: {pro_error}. Falling back to Flash model.")
            
            # --- Step 2: Fallback to the Flash model if Pro fails ---
            try:
                logger.info("Sending image to Gemini Flash for analysis...")
                flash_model = genai.GenerativeModel('gemini-1.5-flash-latest')
                response = flash_model.generate_content([prompt, img])
                detected_text = response.text.strip()
                source_model = "gemini-1.5-flash-fallback"
            except Exception as flash_error:
                logger.error(f"Gemini Flash also failed: {flash_error}")
                raise HTTPException(status_code=500, detail="Both Pro and Flash models failed to process the image.")

        logger.info(f"Detection complete. Final response from {source_model}: '{detected_text}'")

        # --- Format the response ---
        if detected_text:
            result = {
                "found": True,
                "method": "gemini-predictive",
                "serials": [{
                    "text": detected_text,
                    "confidence": 0.95,
                    "bbox": None,
                    "source": source_model
                }],
                "codes": [],
                "raw_ocr": f"Gemini Predicted Response ({source_model}): {detected_text}"
            }
        else:
            result = {"found": False, "method": "gemini-predictive", "serials": [], "codes": [], "raw_ocr": "Could not predict serial number."}
            
        return result

    except Exception as e:
        logger.error(f"An error occurred: {e}")
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8001)
