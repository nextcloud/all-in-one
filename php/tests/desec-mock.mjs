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
//   POST /api/v1/auth/login/                -> 403 until verified, then 200 + {token} (a login token)
//   POST /api/v1/auth/tokens/               -> 201 + {token} (an API token; needs a token with perm_manage_tokens)
//   POST /api/v1/auth/tokens/{id}/policies/rrsets/ -> 201 (scoping policy; default policy must come first)
//   DELETE /api/v1/auth/tokens/{id}/        -> 204
//   POST /api/v1/auth/logout/               -> 204 (invalidates the token used)
//   POST /api/v1/domains/                    -> 201 (new) | 400 (taken by another zone) | 403 (account limit or no perm_create_domain)
//   GET  /api/v1/domains/{name}/             -> 200 (owned by this account) | 404 (not owned)
//   POST /api/v1/domains/{name}/rrsets/      -> 201 (wildcard CNAME) | 403 (token policy forbids)
//   GET  /update                             -> 200 "good" (dyndns update endpoint) | 401 "badauth" | 403 (token policy forbids)
//   POST /__control/verify                   -> test hook: mark accounts email-verified
//   POST /__control/reset                    -> test hook: wipe in-memory state
//   POST /__control/seed-domain              -> test hook: pre-assign a domain to an account
//   POST /__control/invalidate-tokens        -> test hook: make every issued token invalid (simulates expiry)
//   GET  /__control/state                    -> test hook: dump accounts, domains and tokens

import { createServer } from 'node:http'
import { randomBytes } from 'node:crypto'

const port = Number(process.argv[2] ?? process.env.DESEC_MOCK_PORT ?? 8090)

// In-memory state. Reset between independent test scenarios via /__control/reset.
const state = {
  accounts: new Map(), // email -> { password }
  domains: new Map(),  // fully-qualified domain name -> owner email (mirrors deSEC: a name is globally unique)
  tokens: new Map(),   // token secret -> { id, email, kind: 'login' | 'api', valid, policies: [] }
  verified: false,     // simulates whether the (most recent) account's email was verified
}

// Per-account domain limit. The real deSEC service caps the number of domains an
// (unverified-payment) account may hold and answers 403 once it is reached; we model
// that so the "user already owns this slug but is over quota" recovery path is testable.
const DOMAIN_LIMIT = Number(process.env.DESEC_MOCK_DOMAIN_LIMIT ?? 15)

// Resolve the token record from the Authorization header. Like deSEC, a login token
// (POST /auth/login/) may manage tokens and create domains; an API token created via
// POST /auth/tokens/ gets deSEC's defaults: no account-level permissions, no expiry.
function tokenFromRequest(req) {
  const auth = req.headers['authorization'] ?? ''
  const secret = auth.replace(/^Token\s+/i, '')
  const token = state.tokens.get(secret)
  return token && token.valid ? { secret, ...token } : undefined
}

function issueToken(email, kind) {
  const secret = `mock-${kind}-${randomBytes(12).toString('hex')}`
  const id = randomBytes(16).toString('hex')
  state.tokens.set(secret, { id, email, kind, valid: true, policies: [] })
  return { secret, id }
}

// RRset write permission under deSEC's token policies: a token without policies is
// unrestricted; otherwise the most specific matching policy (domain, subname, type; null
// matches anything) decides, falling back to the all-null default policy.
function mayWrite(token, domain, subname, type) {
  if (token.policies.length === 0) return true
  const specificity = (p) => (p.domain !== null) * 4 + (p.subname !== null) * 2 + (p.type !== null) * 1
  const matching = token.policies
    .filter((p) => (p.domain === null || p.domain === domain) && (p.subname === null || p.subname === subname) && (p.type === null || p.type === type))
    .sort((a, b) => specificity(b) - specificity(a))
  return matching.length > 0 && matching[0].perm_write
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
  if (body === undefined) {
    res.writeHead(code)
    return res.end()
  }
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
    state.tokens.clear()
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
  // Test hook: invalidate every token, as happens to a login token 7 days after it was
  // issued. Lets the token-renewal path of the periodic IP update be exercised.
  if (method === 'POST' && path === '/__control/invalidate-tokens') {
    for (const token of state.tokens.values()) token.valid = false
    return send(res, 200, { invalidated: true })
  }
  if (method === 'GET' && path === '/__control/state') {
    return send(res, 200, {
      accounts: [...state.accounts.keys()],
      domains: Object.fromEntries(state.domains),
      tokens: [...state.tokens.entries()].map(([secret, t]) => ({ token: secret, email: t.email, kind: t.kind, valid: t.valid, policies: t.policies })),
    })
  }

  // --- dyndns update endpoint (DesecManager::updateIpIfDesecDomain) ---
  if (method === 'GET' && path === '/update') {
    const caller = tokenFromRequest(req)
    if (!caller) {
      return send(res, 401, 'badauth')
    }
    const hostname = url.searchParams.get('hostname') ?? ''
    if (state.domains.get(hostname) !== caller.email || !mayWrite(caller, hostname, '', 'A') || !mayWrite(caller, hostname, '', 'AAAA')) {
      return send(res, 403, { detail: 'Insufficient permissions for this domain.' })
    }
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

  // --- Login: 403 until the email is verified, then 200 + a login token object. ---
  if (method === 'POST' && path === '/api/v1/auth/login/') {
    const body = await readBody(req)
    const account = body.email ? state.accounts.get(body.email) : undefined
    const passwordMatches = account && account.password === body.password
    if (!state.verified || !passwordMatches) {
      return send(res, 403, { detail: 'Invalid email or password, or email not verified.' })
    }
    const { secret, id } = issueToken(body.email, 'login')
    return send(res, 200, {
      token: secret,
      id,
      created: '2024-01-01T00:00:00.000000Z',
      max_age: '7 00:00:00',
      max_unused_period: '01:00:00',
      perm_create_domain: true,
      perm_delete_domain: true,
      perm_manage_tokens: true,
    })
  }

  // --- Token creation: requires perm_manage_tokens (only login tokens have it here). ---
  // --- The new token gets deSEC's defaults: no expiry, no account-level permissions. ---
  if (method === 'POST' && path === '/api/v1/auth/tokens/') {
    const caller = tokenFromRequest(req)
    if (!caller) return send(res, 401, { detail: 'Invalid token.' })
    if (caller.kind !== 'login') return send(res, 403, { detail: 'You do not have permission to perform this action.' })
    const body = await readBody(req)
    const { secret, id } = issueToken(caller.email, 'api')
    return send(res, 201, {
      token: secret,
      id,
      created: '2024-01-01T00:00:00.000000Z',
      name: body.name ?? '',
      max_age: null,
      max_unused_period: null,
      perm_create_domain: false,
      perm_delete_domain: false,
      perm_manage_tokens: false,
    })
  }

  // --- Token policies: require perm_manage_tokens; the default (all-null) policy must exist first. ---
  const policyRoute = path.match(/^\/api\/v1\/auth\/tokens\/([^/]+)\/policies\/rrsets\/$/)
  if (method === 'POST' && policyRoute) {
    const caller = tokenFromRequest(req)
    if (!caller) return send(res, 401, { detail: 'Invalid token.' })
    if (caller.kind !== 'login') return send(res, 403, { detail: 'You do not have permission to perform this action.' })
    const target = [...state.tokens.values()].find((t) => t.id === policyRoute[1] && t.email === caller.email)
    if (!target) return send(res, 404, { detail: 'Not found.' })
    const body = await readBody(req)
    const policy = { domain: body.domain ?? null, subname: body.subname ?? null, type: body.type ?? null, perm_write: body.perm_write === true }
    const isDefault = policy.domain === null && policy.subname === null && policy.type === null
    if (target.policies.length === 0 && !isDefault) {
      return send(res, 400, { detail: 'Policy precedence: The first policy must be the default policy.' })
    }
    target.policies.push(policy)
    return send(res, 201, { id: randomBytes(16).toString('hex'), ...policy })
  }

  // --- Token deletion: 204 even if the token was not found. ---
  const tokenRoute = path.match(/^\/api\/v1\/auth\/tokens\/([^/]+)\/$/)
  if (method === 'DELETE' && tokenRoute) {
    const caller = tokenFromRequest(req)
    if (!caller) return send(res, 401, { detail: 'Invalid token.' })
    if (caller.kind !== 'login') return send(res, 403, { detail: 'You do not have permission to perform this action.' })
    for (const [secret, t] of state.tokens) {
      if (t.id === tokenRoute[1] && t.email === caller.email) state.tokens.delete(secret)
    }
    return send(res, 204)
  }

  // --- Logout: invalidates the token used for the request. ---
  if (method === 'POST' && path === '/api/v1/auth/logout/') {
    const caller = tokenFromRequest(req)
    if (!caller) return send(res, 401, { detail: 'Invalid token.' })
    state.tokens.delete(caller.secret)
    return send(res, 204)
  }

  // --- Domain creation. Mirrors deSEC's status codes: ---
  //   403 if the token may not create domains (API tokens without perm_create_domain);
  //   400 if the name is already registered (to this or another account);
  //   403 once the account has reached its domain limit;
  //   201 otherwise.
  if (method === 'POST' && path === '/api/v1/domains/') {
    const body = await readBody(req)
    const name = String(body.name ?? '')
    const caller = tokenFromRequest(req)
    if (!caller) return send(res, 401, { detail: 'Invalid token.' })
    if (caller.kind !== 'login') return send(res, 403, { detail: 'You do not have permission to perform this action.' })
    if (state.domains.has(name)) {
      return send(res, 400, { detail: 'This domain name is unavailable.' })
    }
    const owned = [...state.domains.values()].filter((owner) => owner === caller.email).length
    if (owned >= DOMAIN_LIMIT) {
      return send(res, 403, { detail: 'Domain limit exceeded. Please contact support to create additional domains.' })
    }
    state.domains.set(name, caller.email)
    return send(res, 201, { name })
  }

  // --- Domain detail: 200 if the token's account owns the name, 404 otherwise. ---
  const domainDetail = path.match(/^\/api\/v1\/domains\/([^/]+)\/$/)
  if (method === 'GET' && domainDetail) {
    const name = decodeURIComponent(domainDetail[1])
    const caller = tokenFromRequest(req)
    if (!caller) return send(res, 401, { detail: 'Invalid token.' })
    if (state.domains.get(name) === caller.email) {
      return send(res, 200, { name })
    }
    return send(res, 404, { detail: 'Not found.' })
  }

  // --- RRset creation (wildcard CNAME): 201, or 403 when a token policy forbids the write. ---
  const rrsetsRoute = path.match(/^\/api\/v1\/domains\/([^/]+)\/rrsets\/$/)
  if (method === 'POST' && rrsetsRoute) {
    const caller = tokenFromRequest(req)
    if (!caller) return send(res, 401, { detail: 'Invalid token.' })
    const name = decodeURIComponent(rrsetsRoute[1])
    const body = await readBody(req)
    if (state.domains.get(name) !== caller.email || !mayWrite(caller, name, body.subname ?? '', body.type ?? '')) {
      return send(res, 403, { detail: 'Insufficient permissions for this RRset.' })
    }
    return send(res, 201, { subname: body.subname ?? '', type: body.type ?? '', records: body.records ?? [] })
  }

  return send(res, 404, { detail: 'Not found in mock: ' + method + ' ' + path })
})

server.listen(port, '0.0.0.0', () => {
  console.log(`deSEC mock listening on http://0.0.0.0:${port}`)
})
