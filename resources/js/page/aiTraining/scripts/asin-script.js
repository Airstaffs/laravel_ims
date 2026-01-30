import axios from "axios";

// ✅ Vue talks to Laravel only
const API_BASE = "/api";

/**
 * 🧩 Fetch ASINs for a given dataset/class name
 */
export async function fetchAsinsForClass(className) {
  try {
    const res = await axios.get(
      `/api/asin-details/${encodeURIComponent(className)}`
    );

    return (res.data.asins || []).map(code => ({
      code,
      updated: res.data.updated_at || new Date().toISOString().split("T")[0],
    }));
  } catch (err) {
    console.error(`[ASIN FETCH FAILED] ${className}`, err);
    return [];
  }
}

/**
 * ➕ Add ASIN
 */
export async function addAsin(className, asinCode) {
  try {
    await axios.post(`${API_BASE}/asin-mappings`, {
      class_name: className,
      asin_code: asinCode,
    });

    console.log(`✅ Added ASIN ${asinCode} to ${className}`);
  } catch (err) {
    console.error(`[❌ ERROR] Failed to add ASIN ${asinCode}:`, err);
    throw err;
  }
}

/**
 * 🗑 Delete ASIN
 */
export async function deleteAsin(asinCode) {
  try {
    await axios.delete(
      `${API_BASE}/asin-mappings/${encodeURIComponent(asinCode)}`
    );
    console.log(`🗑 Deleted ASIN ${asinCode}`);
  } catch (err) {
    console.error(
      `[❌ ERROR] Failed to delete ASIN ${asinCode}:`,
      err
    );
    throw err;
  }
}
