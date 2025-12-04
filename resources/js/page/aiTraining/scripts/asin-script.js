import axios from "axios";

const SITE_URL = window.location.origin.includes("localhost")
  ? "http://localhost:8000" // Laravel API URL
  : "https://test.techniquyality.com"; // Production

/**
 * 🧩 Fetch ASINs for a given dataset/class name
 * Returns an array of ASIN codes like:
 * ["B00EWCUK98", "B0C9T2R1MJ"]
 */
export async function fetchAsinsForClass(className) {
  try {
    const res = await axios.get(`${SITE_URL}/asin-mappings`);
    const data = res.data[className] || [];
    return data.map(code => ({
      code,
      updated: new Date().toISOString().split("T")[0],
    }));
  } catch (err) {
    console.error(`[❌ ERROR] Failed to fetch ASINs for "${className}":`, err);
    return [];
  }
}

/**
 * ➕ Add ASIN
 */
export async function addAsin(className, asinCode) {
  try {
    const payload = { class_name: className, asin_code: asinCode };
    await axios.post(`${SITE_URL}/asin-mappings`, payload);
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
    await axios.delete(`${SITE_URL}/asin-mappings/${asinCode}`);
    console.log(`🗑 Deleted ASIN ${asinCode}`);
  } catch (err) {
    console.error(`[❌ ERROR] Failed to delete ASIN ${asinCode}:`, err);
    throw err;
  }
}
