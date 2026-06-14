import { serve } from 'https://deno.land/std@0.208.0/http/server.ts'

const OPENROUTER_BASE = 'https://openrouter.ai/api/v1'
const OPENROUTER_KEY = Deno.env.get('OPENROUTER_API_KEY') || ''

serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response(null, {
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Requested-With',
        'Access-Control-Max-Age': '86400',
      },
    })
  }

  if (req.method !== 'POST') {
    return new Response(JSON.stringify({ error: 'Method not allowed' }), {
      status: 405,
      headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
    })
  }

  try {
    const body = await req.json()
    const path = new URL(req.url).pathname.replace(/^\/openrouter-proxy/, '') || '/chat/completions'
    const url = `${OPENROUTER_BASE}${path}`

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${OPENROUTER_KEY}`,
        'HTTP-Referer': req.headers.get('HTTP-Referer') || 'https://switdeveloper.github.io/Swit-invest/',
        'X-Title': req.headers.get('X-Title') || 'SwitDeveloper',
      },
      body: JSON.stringify(body),
    })

    const data = await response.text()

    return new Response(data, {
      status: response.status,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*',
      },
    })
  } catch (err) {
    return new Response(JSON.stringify({ error: err instanceof Error ? err.message : 'Internal error' }), {
      status: 500,
      headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
    })
  }
})
