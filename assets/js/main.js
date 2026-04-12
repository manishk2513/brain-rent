/* =============================================
   assets/js/main.js
   BrainRent — Global JavaScript
   ============================================= */

"use strict";

// Resolve app base URL from meta tag (supports moved project folders).
const APP_BASE_URL = (() => {
  const meta = document.querySelector('meta[name="app-url"]');
  if (meta && meta.content) return meta.content.replace(/\/+$/, "");
  const path = window.location.pathname || "";
  const pagesIdx = path.indexOf("/pages/");
  if (pagesIdx !== -1) return window.location.origin + path.slice(0, pagesIdx);
  const adminIdx = path.indexOf("/admin/");
  if (adminIdx !== -1) return window.location.origin + path.slice(0, adminIdx);
  return window.location.origin;
})();

function apiUrl(path) {
  if (!path.startsWith("/")) return APP_BASE_URL + "/" + path;
  return APP_BASE_URL + path;
}

// =============================================
// VOICE RECORDER
// =============================================
class VoiceRecorder {
  constructor(opts = {}) {
    this.dotEl = document.getElementById(opts.dot || "rec-dot");
    this.timerEl = document.getElementById(opts.timer || "rec-timer");
    this.statusEl = document.getElementById(opts.status || "rec-status");
    this.waveEl = document.getElementById(opts.wave || "rec-wave");
    this.mainBtn = document.getElementById(opts.main || "rec-main");
    this.trashBtn = document.getElementById(opts.trash || "rec-trash");
    this.playBtn = document.getElementById(opts.play || "rec-play");
    this.audioEl = document.getElementById(opts.audio || "rec-audio");
    this.previewEl = document.getElementById(opts.preview || "rec-preview");

    this.recorder = null;
    this.chunks = [];
    this.blob = null;
    this.recording = false;
    this.seconds = 0;
    this.timerInt = null;
    this.waveInt = null;

    // Build wave bars
    if (this.waveEl) {
      for (let i = 0; i < 22; i++) {
        const b = document.createElement("div");
        b.className = "br-wave-bar";
        b.style.height = "4px";
        this.waveEl.appendChild(b);
      }
    }

    if (this.mainBtn)
      this.mainBtn.addEventListener("click", () => this.toggle());
    if (this.trashBtn)
      this.trashBtn.addEventListener("click", () => this.delete());
    if (this.playBtn) this.playBtn.addEventListener("click", () => this.play());
  }

  async toggle() {
    if (!this.recording) await this.start();
    else this.stop();
  }

  async start() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      this.recorder = new MediaRecorder(stream, { mimeType: "audio/webm" });
      this.chunks = [];

      this.recorder.ondataavailable = (e) => this.chunks.push(e.data);
      this.recorder.onstop = () => {
        this.blob = new Blob(this.chunks, { type: "audio/webm" });
        const url = URL.createObjectURL(this.blob);
        if (this.audioEl) this.audioEl.src = url;
        if (this.previewEl) this.previewEl.style.display = "block";
        if (this.playBtn) this.playBtn.disabled = false;
        if (this.trashBtn) this.trashBtn.disabled = false;
        stream.getTracks().forEach((t) => t.stop());
      };

      this.recorder.start();
      this.recording = true;
      this.seconds = 0;

      if (this.dotEl) this.dotEl.classList.add("recording");
      if (this.mainBtn) {
        this.mainBtn.innerHTML = "⏹";
        this.mainBtn.classList.add("stop");
      }
      if (this.statusEl) this.statusEl.textContent = "Recording…";

      this.timerInt = setInterval(() => {
        this.seconds++;
        const m = String(Math.floor(this.seconds / 60)).padStart(2, "0");
        const s = String(this.seconds % 60).padStart(2, "0");
        if (this.timerEl) this.timerEl.textContent = `${m}:${s}`;
      }, 1000);

      this.waveInt = setInterval(() => {
        document
          .querySelectorAll(`#${this.waveEl?.id} .br-wave-bar`)
          .forEach((b) => {
            const h = Math.random() * 34 + 4;
            b.style.height = h + "px";
            b.classList.toggle("active", h > 18);
          });
      }, 80);
    } catch (e) {
      BrainRent.toast("Microphone access denied. Please allow it.", "error");
    }
  }

  stop() {
    if (this.recorder && this.recorder.state !== "inactive")
      this.recorder.stop();
    this.recording = false;
    clearInterval(this.timerInt);
    clearInterval(this.waveInt);

    if (this.dotEl) this.dotEl.classList.remove("recording");
    if (this.mainBtn) {
      this.mainBtn.innerHTML = "🎙️";
      this.mainBtn.classList.remove("stop");
    }
    if (this.statusEl) this.statusEl.textContent = "✓ Recording saved";
    document
      .querySelectorAll(`#${this.waveEl?.id} .br-wave-bar`)
      .forEach((b) => {
        b.style.height = "4px";
        b.classList.remove("active");
      });
  }

  delete() {
    this.blob = null;
    this.seconds = 0;
    if (this.timerEl) this.timerEl.textContent = "00:00";
    if (this.statusEl) this.statusEl.textContent = "Ready to record";
    if (this.previewEl) this.previewEl.style.display = "none";
    if (this.playBtn) this.playBtn.disabled = true;
    if (this.trashBtn) this.trashBtn.disabled = true;
    BrainRent.toast("Recording deleted", "success");
  }

  play() {
    if (this.audioEl) this.audioEl.play();
  }

  /** Append blob to a FormData as a file */
  appendTo(formData, fieldName = "voice_recording") {
    if (this.blob) formData.append(fieldName, this.blob, "voice.webm");
  }
}

// =============================================
// GLOBAL HELPERS
// =============================================
const BrainRent = {
  /** Toast notification */
  toast(msg, type = "success") {
    let el = document.getElementById("br-toast");
    if (!el) {
      el = document.createElement("div");
      el.id = "br-toast";
      el.className = "br-toast";
      el.innerHTML =
        '<span class="br-toast-icon"></span><span class="br-toast-msg"></span>';
      document.body.appendChild(el);
    }
    el.className = `br-toast ${type}`;
    el.querySelector(".br-toast-icon").textContent =
      type === "success" ? "✓" : "✗";
    el.querySelector(".br-toast-msg").textContent = msg;
    el.classList.add("show");
    clearTimeout(BrainRent._toastTimer);
    BrainRent._toastTimer = setTimeout(() => el.classList.remove("show"), 3500);
  },

  /** Simple CSRF-aware fetch wrapper */
  async post(url, data) {
    const fd = data instanceof FormData ? data : new URLSearchParams(data);
    const res = await fetch(url, { method: "POST", body: fd });
    return res.json();
  },

  /** Confirm dialog with Bootstrap modal */
  confirm(msg, cb) {
    if (window.confirm(msg)) cb();
  },

  /** Load notifications into the nav dropdown */
  async loadNotifications() {
    const listEl = document.getElementById("notif-list");
    const badge = document.getElementById("notif-count");
    if (!listEl) return;
    try {
      const res = await fetch(apiUrl("/api/notifications.php"));
      const data = await res.json();
      if (!data.success) return;
      const { notifications, unread_count } = data;

      if (badge) {
        badge.textContent = unread_count;
        badge.style.display = unread_count > 0 ? "block" : "none";
      }

      if (!notifications.length) {
        listEl.innerHTML =
          '<div class="text-center text-muted py-4 small">No notifications yet</div>';
        return;
      }

      listEl.innerHTML = notifications
        .map(
          (n) => `
        <div class="br-notif-item ${n.is_read == 0 ? "unread" : ""}">
          <div class="fw-medium" style="font-size:.85rem">${escHtml(n.title)}</div>
          <div class="text-muted" style="font-size:.78rem">${escHtml(n.message)}</div>
          <div class="text-subtle" style="font-size:.72rem;margin-top:3px">${timeAgo(n.created_at)}</div>
        </div>`,
        )
        .join("");
    } catch (_) {
      /* silent */
    }
  },

  /** Init star rating widgets */
  initStarRatings() {
    document.querySelectorAll("[data-star-rating]").forEach((container) => {
      const stars = container.querySelectorAll(".br-star");
      let selected = 0;

      stars.forEach((star, i) => {
        star.addEventListener("mouseover", () => highlightTo(i));
        star.addEventListener("mouseleave", () => highlightTo(selected - 1));
        star.addEventListener("click", () => {
          selected = i + 1;
          container.querySelector("input[type=hidden]").value = selected;
          highlightTo(i);
        });
      });

      function highlightTo(idx) {
        stars.forEach((s, j) => s.classList.toggle("filled", j <= idx));
      }
    });
  },
};

function escHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

function timeAgo(dateStr) {
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
  if (diff < 60) return "just now";
  if (diff < 3600) return Math.floor(diff / 60) + " min ago";
  if (diff < 86400) return Math.floor(diff / 3600) + " hr ago";
  return Math.floor(diff / 86400) + " days ago";
}

// =============================================
// SCROLL-DRIVEN THEME SHIFT
// =============================================
function initScrollThemeShift() {
  const root = document.documentElement;
  if (!root) return;

  const reduceMotion =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (reduceMotion) return;

  const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
  let ticking = false;

  const update = () => {
    const maxScroll = Math.max(
      1,
      document.body.scrollHeight - window.innerHeight,
    );
    const pct = clamp(window.scrollY / maxScroll, 0, 1);

    const hueA = Math.round(28 + pct * 36);
    const hueB = Math.round(205 + pct * 26);
    const hueC = Math.round(140 + pct * 22);
    const shiftX = Math.round(pct * 28) + "%";
    const shiftY = Math.round(pct * 22) + "%";

    root.style.setProperty("--br-hue-a", hueA);
    root.style.setProperty("--br-hue-b", hueB);
    root.style.setProperty("--br-hue-c", hueC);
    root.style.setProperty("--br-shift-x", shiftX);
    root.style.setProperty("--br-shift-y", shiftY);
  };

  const onScroll = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      update();
      ticking = false;
    });
  };

  window.addEventListener("scroll", onScroll, { passive: true });
  window.addEventListener("resize", onScroll);
  update();
}

// =============================================
// SUBMIT PROBLEM — Step Wizard
// =============================================
function initSubmitWizard() {
  const step1 = document.getElementById("step1");
  const step2 = document.getElementById("step2");
  const step3 = document.getElementById("step3");
  if (!step1) return;

  // Char counter
  const txt = document.getElementById("problem-text");
  const cnt = document.getElementById("char-count");
  if (txt && cnt)
    txt.addEventListener(
      "input",
      () => (cnt.textContent = `${txt.value.length} / 3000 characters`),
    );

  // Next button
  document.getElementById("btn-next")?.addEventListener("click", () => {
    const title = document.getElementById("problem-title")?.value.trim();
    if (!title) {
      BrainRent.toast("Please add a problem title", "error");
      return;
    }
    document.getElementById("review-title").textContent = title;
    const body = document.getElementById("problem-text")?.value || "";
    document.getElementById("review-excerpt").textContent =
      body.length > 130
        ? body.substring(0, 130) + "…"
        : body || "(Voice recording only)";
    step1.style.display = "none";
    step2.style.display = "block";
    setStepActive(2);
  });

  document.getElementById("btn-back")?.addEventListener("click", () => {
    step2.style.display = "none";
    step1.style.display = "block";
    setStepActive(1);
  });

  // Urgency selector
  document.querySelectorAll(".br-urgency-opt").forEach((opt) => {
    opt.addEventListener("click", () => {
      document
        .querySelectorAll(".br-urgency-opt")
        .forEach((o) => o.classList.remove("selected"));
      opt.classList.add("selected");
      const price = parseInt(opt.dataset.price || 0);
      const base = parseInt(document.getElementById("base-rate")?.value || 0);
      const fee = Math.round((base + price) * 0.15);
      const total = base + price + fee;
      document
        .querySelectorAll(".sidebar-urgency")
        .forEach((el) => (el.textContent = "$" + price));
      document
        .querySelectorAll(".sidebar-total")
        .forEach((el) => (el.textContent = "$" + total));
      document.getElementById("urgency-input").value = opt.dataset.urgency;
    });
  });

  // File uploads
  const fileInput = document.getElementById("file-input");
  fileInput?.addEventListener("change", () => {
    const list = document.getElementById("file-list");
    Array.from(fileInput.files).forEach((f) => {
      const item = document.createElement("div");
      item.style.cssText =
        "display:flex;align-items:center;gap:8px;background:var(--br-dark3);border-radius:8px;padding:8px 12px;font-size:.8rem;margin-top:6px";
      item.innerHTML = `<i class="bi bi-paperclip"></i><span>${escHtml(f.name)}</span><span style="color:var(--br-text3)">${(f.size / 1024 / 1024).toFixed(2)} MB</span>`;
      list.appendChild(item);
    });
  });

  // Pay & Submit
  document.getElementById("btn-pay")?.addEventListener("click", async () => {
    const btn = document.getElementById("btn-pay");
    btn.disabled = true;
    btn.textContent = "Completing payment…";

    const formData = new FormData(document.getElementById("submit-form"));
    window._recorder?.appendTo(formData);

    try {
      const res = await fetch(apiUrl("/api/submit_request.php"), {
        method: "POST",
        body: formData,
      });
      const data = await res.json();
      if (!data.success) {
        BrainRent.toast(data.error || "Submission failed", "error");
        btn.disabled = false;
        btn.textContent = "Payment Done & Submit";
        return;
      }

      const nextUrl =
        data.problem_url ||
        APP_BASE_URL + "/pages/problem.php?id=" + data.request_id;
      const viewLink = document.getElementById("problem-view-link");
      if (viewLink) {
        viewLink.href = nextUrl;
      }

      const successText = document.getElementById("payment-success-text");
      if (successText) {
        successText.textContent =
          "Payment successful. Redirecting to your problem page…";
      }

      step2.style.display = "none";
      step3.style.display = "block";
      setStepActive(3);
      BrainRent.toast("Payment successful! Problem submitted.", "success");
      setTimeout(() => {
        window.location.href = nextUrl;
      }, 1400);
    } catch (e) {
      BrainRent.toast("Network error. Please try again.", "error");
      btn.disabled = false;
      btn.textContent = "Payment Done & Submit";
    }
  });
}

function setStepActive(n) {
  for (let i = 1; i <= 3; i++) {
    const dot = document.getElementById(`step-dot-${i}`);
    if (!dot) continue;
    if (i < n) {
      dot.classList.add("done");
      dot.innerHTML = "✓";
    } else if (i === n) {
      dot.classList.add("active");
      dot.classList.remove("done");
    } else {
      dot.classList.remove("active", "done");
      dot.innerHTML = i;
    }
  }
}

// =============================================
// DOM READY
// =============================================
document.addEventListener("DOMContentLoaded", () => {
  BrainRent.loadNotifications();
  BrainRent.initStarRatings();
  initSubmitWizard();
  initScrollThemeShift();

  // Mark-all-read
  document
    .getElementById("mark-all-read")
    ?.addEventListener("click", async (e) => {
      e.preventDefault();
      await fetch(apiUrl("/api/notifications.php?action=mark_all_read"));
      BrainRent.loadNotifications();
    });

  // Accept / Decline request buttons (expert dashboard)
  document.querySelectorAll("[data-action]").forEach((btn) => {
    btn.addEventListener("click", async function (e) {
      e.stopPropagation();
      const action = this.dataset.action;
      const reqId = this.dataset.requestId;
      if (!reqId) return;

      const fd = new URLSearchParams({ action, request_id: reqId });
      const res = await fetch(apiUrl("/api/manage_request.php"), {
        method: "POST",
        body: fd,
      });
      const data = await res.json();

      BrainRent.toast(
        data.success ? data.message : data.error || "Error",
        data.success ? "success" : "error",
      );
      if (data.success) setTimeout(() => location.reload(), 1200);
    });
  });
});
