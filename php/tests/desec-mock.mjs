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
//   POST /api/v1/domains/                    -> 201 (new name) | 409 (name already taken)
//   POST /api/v1/domains/{name}/rrsets/      -> 201 (wildcard CNAME)
//   GET  /update                             -> 200 "good" (dyndns update endpoint)
//   POST /__control/verify                   -> test hook: mark accounts email-verified
//   POST /__control/reset                    -> test hook: wipe in-memory state

import { createServer } from 'node:http'

const port = Number(process.argv[2] ?? process.env.DESEC_MOCK_PORT ?? 8090)

// In-memory state. Reset between independent test scenarios via /__control/reset.
const state = {
  accounts: new Map(), // email -> { password }
  domains: new Set(),  // fully-qualified domain names that have been created
  verified: false,     // simulates whether the (most recent) account's email was verified
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

  // --- Domain creation: 201 for a new name, 409 if the same name already exists. ---
  if (method === 'POST' && path === '/api/v1/domains/') {
    const body = await readBody(req)
    const name = String(body.name ?? '')
    if (state.domains.has(name)) {
      return send(res, 409, { detail: 'This domain name is unavailable.' })
    }
    state.domains.add(name)
    return send(res, 201, { name })
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
