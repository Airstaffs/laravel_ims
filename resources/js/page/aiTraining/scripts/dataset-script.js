import axios from "axios";

const SITE_URL = window.location.origin.includes("localhost")
  ? "http://localhost:8001"
  : "https://test.techniquyality.com";

/**
 * 🔹 Fetch datasets for Dataset Manager
 * Backend returns:
 * {
 *   "datasets": [
 *     { "className": "BOSE_161_BLACK", "valCount": 48, "trainCount": 192 },
 *     ...
 *   ]
 * }
 */
export async function fetchDatasetFolders() {
  try {
    const res = await axios.get(`${SITE_URL}/api/datasets`);

    // Normalize response
    const data = res.data.datasets || res.data.classes || [];

    return data.map(item => ({
      name: item.className || item.name,
      val: item.valCount || item.val || 0,
      train: item.trainCount || item.train || 0,
    }));
  } catch (err) {
    console.error("[❌ ERROR] Failed to fetch dataset folders:", err);
    return [];
  }
}

/**
 * 🗑️ Delete a dataset folder by name
 */
export async function deleteDatasetFolder(name) {
  try {
    const res = await axios.delete(`${SITE_URL}/api/delete-dataset/${name}`);
    console.log("✅ Deleted dataset:", name);
    return res.data;
  } catch (err) {
    console.error(`[❌ ERROR] Failed to delete dataset "${name}":`, err);
    throw err;
  }
}
