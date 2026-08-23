// Test-side TOTP generator (RFC 6238, SHA-1, 6 digits, 30s) used by the
// two-factor E2E to produce the codes an authenticator app would show for the
// secret AIO displays during setup. Kept dependency-free (node:crypto only).
import { createHmac } from 'node:crypto';

const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32Decode(input) {
  let bits = '';
  for (const ch of input.replace(/=+$/, '').toUpperCase()) {
    const idx = BASE32_ALPHABET.indexOf(ch);
    if (idx === -1) {
      throw new Error(`Invalid base32 character: ${ch}`);
    }
    bits += idx.toString(2).padStart(5, '0');
  }
  const bytes = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) {
    bytes.push(parseInt(bits.slice(i, i + 8), 2));
  }
  return Buffer.from(bytes);
}

export const PERIOD = 30;

export function currentCounter(offsetSteps = 0) {
  return Math.floor(Date.now() / 1000 / PERIOD) + offsetSteps;
}

export function totpCode(secret, counter = currentCounter()) {
  const key = base32Decode(secret);
  const buf = Buffer.alloc(8);
  buf.writeBigUInt64BE(BigInt(counter));
  const hash = createHmac('sha1', key).update(buf).digest();
  const offset = hash[hash.length - 1] & 0xf;
  const binary =
    ((hash[offset] & 0x7f) << 24) |
    ((hash[offset + 1] & 0xff) << 16) |
    ((hash[offset + 2] & 0xff) << 8) |
    (hash[offset + 3] & 0xff);
  return String(binary % 1_000_000).padStart(6, '0');
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Return a code for a window strictly newer than `usedCounter`, waiting for the
 * clock to roll into it if necessary. Every successful verify consumes its
 * counter (replay protection), so each fresh login/enable/disable needs a new
 * window. Returns { code, counter }.
 */
export async function freshCode(secret, usedCounter = -1) {
  while (currentCounter() <= usedCounter) {
    await sleep(1000);
  }
  const counter = currentCounter();
  return { code: totpCode(secret, counter), counter };
}
