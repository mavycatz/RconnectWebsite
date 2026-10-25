// ================= FIREBASE IMPORTS =================
import { initializeApp } from "https://www.gstatic.com/firebasejs/12.9.0/firebase-app.js";
import {
  getFirestore,
  collection,
  addDoc,
  getDocs,
  serverTimestamp
} from "https://www.gstatic.com/firebasejs/12.9.0/firebase-firestore.js";

// ================= FIREBASE CONFIG =================
const firebaseConfig = {
  apiKey: "AIzaSyCFrBLvgCFnkbeVQMJ67zUh2U5Vd2bNQVk",
  authDomain: "fareai-36aaf.firebaseapp.com",
  projectId: "fareai-36aaf",
  storageBucket: "fareai-36aaf.firebasestorage.app",
  messagingSenderId: "420881041483",
  appId: "1:420881041483:web:9ad5da08c697ff3e8e25ce"
};

// ================= INIT =================
const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

// ================= DOM READY =================
document.addEventListener("DOMContentLoaded", () => {
  console.log("🧠 Fare Chat Trainer Loaded");

  const input = document.getElementById("fare-chat-input");
  const sendBtn = document.getElementById("fare-chat-send");

  if (!input || !sendBtn) {
    console.error("❌ Chat input/button missing");
    return;
  }

  sendBtn.addEventListener("click", async () => {
    const text = input.value.trim().toLowerCase();
    console.log("💬 USER INPUT:", text);

    if (!text.includes(" to ") || !text.includes(" is ")) {
      alert("Invalid format. Use: cabanatuan to rizal is 78 regular, 62 student, 62 special");
      return;
    }

    try {
      const parsed = parseFareMessage(text);
      console.log("✅ PARSED DATA:", parsed);

      await saveFare(parsed);
      console.log("🔥 Fare saved to Firestore");

      alert("Fare recorded successfully!");
      input.value = "";

    } catch (err) {
      console.error("❌ Parse/Save failed:", err);
      alert("Invalid message format. Check console.");
    }
  });
});

// ================= PARSER =================
function parseFareMessage(text) {
  // Split route
  const [routePart, farePart] = text.split(" is ");

  const [from, to] = routePart.split(" to ").map(v => v.trim());

  if (!from || !to) throw "Route parse failed";

  // Extract numbers
  const regular = extractFare(farePart, "regular");
  const student = extractFare(farePart, "student");
  const special = extractFare(farePart, "special");

  if (
    regular === null ||
    student === null ||
    special === null
  ) {
    throw "Fare parse failed";
  }

  return {
    route_from: from,
    route_to: to,
    fare_regular: regular,
    fare_student: student,
    fare_special: special
  };
}

function extractFare(text, keyword) {
  const regex = new RegExp("(\\d+)\\s*" + keyword);
  const match = text.match(regex);
  return match ? Number(match[1]) : null;
}

// ================= SAVE TO FIRESTORE =================
async function saveFare(data) {
  await addDoc(collection(db, "learned_fares"), {
    ...data,
    createdAt: serverTimestamp()
  });
}
