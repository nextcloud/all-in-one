// Minimal mock of the deSEC API, implementing exactly the endpoints that
// php/src/Desec/DesecManager.php calls, with the same HTTP status-code semantics
// as the real service (see https://desec.readthedocs.io). It exists so the deSEC
// register -> verify -> domain flow can be exercised end-to-end in CI without
// touching the real deSEC service (which would create real accounts, be flaky and
// rate-limited, and require a human to click an email verification link).
//
// We do not probe deSEC's live API (their ToS discourages automated requests, and the
// /api/v1/ interface is contractually stable). Instead, the scheduled workflow
// .github/workflows/desec-api-version-watch.yml watches deSEC's published API-version
// table for changes; if it goes red, review the change and update this mock (plus
// DesecManager) to match.
//
// Usage: node desec-mock.mjs [port]   (default port 8090)
// Routes:
//   POST /api/v1/auth/                      -> 202 (account requested; verify email)
//   POST /api/v1/auth/login/                -> 403 until verified, then 200 + {token}
//   POST /api/v1/domains/                    -> 201 (new) | 400 (taken by another zone) | 403 (account limit)
//   GET  /api/v1/domains/{name}/             -> 200 (owned by this account) | 404 (not owned)
//   POST /api/v1/domains/{name}/rrsets/      -> 201 (wildcard CNAME)
//   GET  /update                             -> 200 "good" (dyndns update endpoint)
//   POST /__control/verify                   -> test hook: mark accounts email-verified
//   POST /__control/reset                    -> test hook: wipe in-memory state

import { createServer } from 'node:http'

const port = Number(process.argv[2] ?? process.env.DESEC_MOCK_PORT ?? 8090)

// In-memory state. Reset between independent test scenarios via /__control/reset.
const state = {
  accounts: new Map(), // email -> { password }
  domains: new Map(),  // fully-qualified domain name -> owner email (mirrors deSEC: a name is globally unique)
  verified: false,     // simulates whether the (most recent) account's email was verified
}

// Per-account domain limit. The real deSEC service caps the number of domains an
// (unverified-payment) account may hold and answers 403 once it is reached; we model
// that so the "user already owns this slug but is over quota" recovery path is testable.
const DOMAIN_LIMIT = Number(process.env.DESEC_MOCK_DOMAIN_LIMIT ?? 15)

// The mock issues tokens of the form `mock-token-<hex(email)[:16]>` (see /auth/login/).
// Recover the owner email from the Authorization header so domain ops are per-account.
function emailFromToken(req) {
  const auth = req.headers['authorization'] ?? ''
  const token = auth.replace(/^Token\s+/i, '')
  const hex = token.replace(/^mock-token-/, '')
  for (const email of state.accounts.keys()) {
    if (Buffer.from(email).toString('hex').slice(0, 16) === hex) return email
  }
  return undefined
}

function readBody(req) {
  return new Promise((resolve) => {
    let data = ''
    req.on('data', (chunk) => { data += chunk })
    req.on('end', () => {
      if (data === '') return resolve({})
      try { resolve(JSON.parse(data)) } catch { resolve({}) }
    })
  })
}

function send(res, code, body) {
  const payload = typeof body === 'string' ? body : JSON.stringify(body)
  res.writeHead(code, { 'Content-Type': typeof body === 'string' ? 'text/plain' : 'application/json' })
  res.end(payload)
}

const server = createServer(async (req, res) => {
  const url = new URL(req.url, `http://localhost:${port}`)
  const path = url.pathname
  const method = req.method ?? 'GET'

  // --- Test control hooks (not part of the real deSEC API) ---
  if (method === 'POST' && path === '/__control/verify') {
    state.verified = true
    return send(res, 200, { verified: true })
  }
  if (method === 'POST' && path === '/__control/reset') {
    state.accounts.clear()
    state.domains.clear()
    state.verified = false
    return send(res, 200, { reset: true })
  }
  // Test hook: pre-assign a domain to an account, simulating one the user registered
  // earlier (optionally filling the account up to its domain limit).
  if (method === 'POST' && path === '/__control/seed-domain') {
    const body = await readBody(req)
    if (body.name && body.email) state.domains.set(String(body.name), String(body.email))
    return send(res, 200, { seeded: true })
  }

  // --- dyndns update endpoint (DesecManager::updateIpIfDesecDomain) ---
  if (method === 'GET' && path === '/update') {
    return send(res, 200, 'good')
  }

  // --- Account registration: deSEC always answers 202 and emails a link; ---
  // --- it returns 202 even for an already-registered email (anti-enumeration). ---
  if (method === 'POST' && path === '/api/v1/auth/') {
    const body = await readBody(req)
    if (body.email && !state.accounts.has(body.email)) {
      state.accounts.set(body.email, { password: body.password ?? '' })
      // A freshly created account starts unverified; the test flips this via /__control/verify.
      state.verified = false
    }
    return send(res, 202, '')
  }

  // --- Login: 403 until the email is verified, then 200 + a token object. ---
  if (method === 'POST' && path === '/api/v1/auth/login/') {
    const body = await readBody(req)
    const account = body.email ? state.accounts.get(body.email) : undefined
    const passwordMatches = account && account.password === body.password
    if (!state.verified || !passwordMatches) {
      return send(res, 403, { detail: 'Invalid email or password, or email not verified.' })
    }
    return send(res, 200, {
      token: 'mock-token-' + Buffer.from(body.email).toString('hex').slice(0, 16),
      id: 'mock-token-id',
      created: '2024-01-01T00:00:00.000000Z',
      max_age: '7 00:00:00',
    })
  }

  // --- Domain creation. Mirrors deSEC's status codes: ---
  //   400 if the name is already registered (to this or another account);
  //   403 once the account has reached its domain limit;
  //   201 otherwise.
  if (method === 'POST' && path === '/api/v1/domains/') {
    const body = await readBody(req)
    const name = String(body.name ?? '')
    const email = emailFromToken(req)
    if (state.domains.has(name)) {
      return send(res, 400, { detail: 'This domain name is unavailable.' })
    }
    const owned = [...state.domains.values()].filter((owner) => owner === email).length
    if (owned >= DOMAIN_LIMIT) {
      return send(res, 403, { detail: 'Domain limit exceeded. Please contact support to create additional domains.' })
    }
    state.domains.set(name, email)
    return send(res, 201, { name })
  }

  // --- Domain detail: 200 if the token's account owns the name, 404 otherwise. ---
  const domainDetail = path.match(/^\/api\/v1\/domains\/([^/]+)\/$/)
  if (method === 'GET' && domainDetail) {
    const name = decodeURIComponent(domainDetail[1])
    const email = emailFromToken(req)
    if (state.domains.get(name) === email && email !== undefined) {
      return send(res, 200, { name })
    }
    return send(res, 404, { detail: 'Not found.' })
  }

  // --- RRset creation (wildcard CNAME): 201. ---
  if (method === 'POST' && /^\/api\/v1\/domains\/[^/]+\/rrsets\/$/.test(path)) {
    const body = await readBody(req)
    return send(res, 201, { subname: body.subname ?? '', type: body.type ?? '', records: body.records ?? [] })
  }

  return send(res, 404, { detail: 'Not found in mock: ' + method + ' ' + path })
})

server.listen(port, '0.0.0.0', () => {
  console.log(`deSEC mock listening on http://0.0.0.0:${port}`)
})
