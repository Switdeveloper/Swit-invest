const SUPABASE_URL = 'https://fyudrybtojwrmrbllewj.supabase.co';
const SUPABASE_ANON_KEY = 'sb_publishable_0QscxQutIBSIGo2czNHSmw_1EtCN2v_';

const supabase = window.supabase?.createClient
  ? window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY)
  : null;

if (!supabase) console.warn('Supabase client not loaded. Include: <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>');

async function getSession() {
  try {
    const { data } = await supabase.auth.getSession();
    return data.session;
  } catch { return null; }
}

async function getUserId() {
  const session = await getSession();
  return session?.user?.id || null;
}

async function trackUsage(toolName, action, metadata = {}) {
  try {
    const userId = await getUserId();
    let anonymousId = localStorage.getItem('anon_id');
    if (!anonymousId) {
      anonymousId = crypto.randomUUID();
      localStorage.setItem('anon_id', anonymousId);
    }
    await supabase.from('tool_usage').insert({
      user_id: userId,
      anonymous_id: userId ? null : anonymousId,
      tool_name: toolName,
      action,
      metadata
    });
  } catch (e) { console.warn('trackUsage error:', e); }
}

async function saveSetting(toolName, settings) {
  const userId = await getUserId();
  if (!userId) return;
  await supabase.from('user_settings').upsert({
    user_id: userId,
    tool_name: toolName,
    settings,
    updated_at: new Date().toISOString()
  }, { onConflict: 'user_id,tool_name' });
}

async function loadSetting(toolName) {
  const userId = await getUserId();
  if (!userId) return null;
  const { data } = await supabase.from('user_settings')
    .select('settings')
    .eq('user_id', userId)
    .eq('tool_name', toolName)
    .single();
  return data?.settings || null;
}

async function recordLinkOpenerStats(count) {
  try {
    const userId = await getUserId();
    let anonymousId = localStorage.getItem('anon_id');
    if (!anonymousId) {
      anonymousId = crypto.randomUUID();
      localStorage.setItem('anon_id', anonymousId);
    }
    const today = new Date().toISOString().split('T')[0];
    const { data: existing } = await supabase.from('link_opener_stats')
      .select('id, links_opened')
      .eq('user_id', userId)
      .eq('date', today)
      .maybeSingle();
    if (existing) {
      await supabase.from('link_opener_stats')
        .update({ links_opened: existing.links_opened + count })
        .eq('id', existing.id);
    } else {
      await supabase.from('link_opener_stats').insert({
        user_id: userId,
        anonymous_id: userId ? null : anonymousId,
        date: today,
        links_opened: count
      });
    }
  } catch (e) { console.warn('recordLinkOpenerStats error:', e); }
}

async function signIn(email, password) {
  const { data, error } = await supabase.auth.signInWithPassword({ email, password });
  return { data, error };
}

async function signUp(email, password) {
  const { data, error } = await supabase.auth.signUp({ email, password });
  return { data, error };
}

async function signOut() {
  await supabase.auth.signOut();
}
