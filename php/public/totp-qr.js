"use strict";

// Renders the TOTP setup QR code in the browser from the otpauth:// URI that the
// server put into the #totp-qr element's data-otpauth attribute.
// Uses the vendored qrcode-generator library (vendor-qrcode.js).
//
// The QR is drawn onto a <canvas> rather than injected as HTML/an <img> because the page enforces Trusted
// Types (which forbids assigning a string to innerHTML) and may restrict img-src, so canvas drawing is the
// CSP-safe option. The QR is always black-on-white regardless of page theme, since scanners need that
// contrast.
(function () {
  const el = document.getElementById("totp-qr");
  if (!el || !el.dataset.otpauth || typeof qrcode === "undefined") {
    return;
  }

  const qr = qrcode(0, "M"); // type 0 = auto-size, error correction level M
  qr.addData(el.dataset.otpauth);
  qr.make();

  const count = qr.getModuleCount();
  const cell = 4; // px per module
  const quietZone = 4 * cell; // 4-module quiet zone, per the QR spec
  const size = count * cell + quietZone * 2;

  const canvas = document.createElement("canvas");
  canvas.width = size;
  canvas.height = size;
  canvas.setAttribute("role", "img");
  canvas.setAttribute("aria-label", "TOTP setup QR code");

  const ctx = canvas.getContext("2d");
  ctx.fillStyle = "#ffffff";
  ctx.fillRect(0, 0, size, size);
  ctx.fillStyle = "#000000";
  for (let row = 0; row < count; row++) {
    for (let col = 0; col < count; col++) {
      if (qr.isDark(row, col)) {
        ctx.fillRect(quietZone + col * cell, quietZone + row * cell, cell, cell);
      }
    }
  }

  el.appendChild(canvas);
})();
