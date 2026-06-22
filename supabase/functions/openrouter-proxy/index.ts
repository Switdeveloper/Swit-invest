import { serve } from 'https://deno.land/std@0.208.0/http/server.ts'
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response(null, {
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Requested-With, X-Provider',
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
    const supabaseUrl = Deno.env.get('SUPABASE_URL') || ''
    const supabaseKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') || ''
    const supabase = createClient(supabaseUrl, supabaseKey)

    let body: Record<string, unknown>
    try { body = await req.json() } catch {
      return new Response(JSON.stringify({ error: 'Invalid JSON' }), {
        status: 400, headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
      })
    }

    const providerName = (body._provider as string) || req.headers.get('X-Provider') || 'openrouter'
    delete body._provider

    const { data: provider, error: dbError } = await supabase
      .from('api_providers')
      .select('api_key, base_url')
      .eq('name', providerName)
      .eq('is_active', true)
      .single()

    if (dbError || !provider) {
      const known = ['openrouter', 'openai', 'anthropic'].join(', ')
      return new Response(JSON.stringify({
        error: `Provider '${providerName}' not found or inactive. Add it in API Settings. Available: ${known}`,
      }), {
        status: 404, headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
      })
    }

    if (!provider.api_key) {
      return new Response(JSON.stringify({
        error: `API key for '${providerName}' is not set. Add it in API Settings.`,
      }), {
        status: 401, headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
      })
    }

    let baseUrl = provider.base_url
    if (!baseUrl) {
      const defaults: Record<string, string> = {
        openrouter: 'https://openrouter.ai/api/v1',
        openai: 'https://api.openai.com/v1',
        anthropic: 'https://api.anthropic.com/v1',
      }
      baseUrl = defaults[providerName] || ''
    }

    const path = new URL(req.url).pathname.replace(/^\/openrouter-proxy/, '') || '/chat/completions'
    const targetUrl = `${baseUrl.replace(/\/$/, '')}${path}`

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'HTTP-Referer': req.headers.get('HTTP-Referer') || 'https://switdeveloper.github.io/Swit-invest/',
      'X-Title': req.headers.get('X-Title') || 'SwitDeveloper',
    }

    if (providerName === 'anthropic') {
      headers['x-api-key'] = provider.api_key
      headers['anthropic-version'] = '2023-06-01'
    } else {
      headers['Authorization'] = `Bearer ${provider.api_key}`
    }

    const response = await fetch(targetUrl, {
      method: 'POST',
      headers,
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
