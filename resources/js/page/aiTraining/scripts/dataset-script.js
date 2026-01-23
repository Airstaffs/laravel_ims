import axios from "axios";

// ✅ Vue should ONLY talk to Laravel
const API_BASE = "/api";

/**
 * 🔹 Fetch datasets for Dataset Manager
 */
export async function fetchDatasetFolders() {
  try {
    const res = await axios.get(`${API_BASE}/datasets`);

    const data = res.data.datasets || [];

    return data.map(item => ({
      name: item.className,
      val: item.valCount || 0,
      train: item.trainCount || 0,
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
    const res = await axios.delete(
      `${API_BASE}/datasets/${encodeURIComponent(name)}`
    );
    return res.data;
  } catch (err) {
    console.error(`[❌ ERROR] Failed to delete dataset "${name}":`, err);
    throw err;
  }
}
