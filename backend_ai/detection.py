from ultralytics import YOLO
from PIL import Image, ImageDraw
import numpy as np
import os

# Load YOLO model once
# _yolo_model = YOLO("yolov8n.pt") -> default model yolo
_yolo_model = YOLO("best.pt")  # Use your custom yolo trained model here

def _merge_boxes(boxes, iou_thresh=0.5):
    """Merge overlapping bounding boxes based on IoU threshold."""
    merged_boxes = []
    while boxes:
        bx1, by1, bx2, by2 = boxes.pop(0)
        remaining = []
        for ox1, oy1, ox2, oy2 in boxes:
            # Compute IoU
            inter_x1 = max(bx1, ox1)
            inter_y1 = max(by1, oy1)
            inter_x2 = min(bx2, ox2)
            inter_y2 = min(by2, oy2)
            inter_area = max(0, inter_x2 - inter_x1) * max(0, inter_y2 - inter_y1)

            base_area = (bx2 - bx1) * (by2 - by1)
            other_area = (ox2 - ox1) * (oy2 - oy1)
            union_area = base_area + other_area - inter_area
            iou = inter_area / union_area if union_area > 0 else 0

            if iou > iou_thresh:
                # Merge into base
                bx1 = min(bx1, ox1)
                by1 = min(by1, oy1)
                bx2 = max(bx2, ox2)
                by2 = max(by2, oy2)
            else:
                remaining.append([ox1, oy1, ox2, oy2])
        merged_boxes.append([bx1, by1, bx2, by2])
        boxes = remaining
    return merged_boxes


def detect_regions(image: Image.Image, iou_thresh: float = 0.5):
    """
    Run YOLOv8 on a PIL image and return cropped regions where serial numbers may exist.
    Overlapping boxes are merged into one.
    """
    results = _yolo_model.predict(np.array(image))
    boxes = []

    # Collect raw YOLO boxes
    for r in results:
        for box in r.boxes:
            x1, y1, x2, y2 = map(int, box.xyxy[0])
            boxes.append([x1, y1, x2, y2])

    # Merge overlapping boxes
    merged_boxes = _merge_boxes(boxes, iou_thresh=iou_thresh)

    # Crop merged regions
    crops = []
    for (x1, y1, x2, y2) in merged_boxes:
        crop = image.crop((x1, y1, x2, y2))
        crops.append(crop)

    return crops


def detect_and_visualize(image: Image.Image, filename: str = "yolo_result.jpg", iou_thresh: float = 0.5):
    """
    Run YOLO detection and save an annotated debug image 
    with merged bounding boxes inside backend_ai/debug_images/.
    """
    results = _yolo_model.predict(np.array(image))
    boxes = []

    # Collect raw YOLO boxes
    for r in results:
        for box in r.boxes:
            x1, y1, x2, y2 = map(int, box.xyxy[0])
            boxes.append([x1, y1, x2, y2])

    # Merge overlapping boxes
    merged_boxes = _merge_boxes(boxes, iou_thresh=iou_thresh)

    # Ensure debug folder exists
    debug_dir = "backend_ai/debug_images"
    os.makedirs(debug_dir, exist_ok=True)

    # Copy image to draw boxes
    image_copy = image.copy()
    draw = ImageDraw.Draw(image_copy)

    # Draw merged detections
    for (x1, y1, x2, y2) in merged_boxes:
        draw.rectangle([x1, y1, x2, y2], outline="red", width=3)
        draw.text((x1, y1 - 10), "Serial?", fill="red")

    # Save annotated result
    save_path = os.path.join(debug_dir, filename)
    image_copy.save(save_path)
    print(f"✅ YOLO detection result saved at: {save_path}")

    return image_copy
