create table if not exists public.profiles (
  id uuid references auth.users on delete cascade primary key,
  email text,
  full_name text,
  created_at timestamptz default now()
);
alter table public.profiles enable row level security;
create policy "Users can view own profile" on profiles for select using (auth.uid() = id);
create policy "Users can update own profile" on profiles for update using (auth.uid() = id);

create or replace function public.handle_new_user()
returns trigger as $$
begin
  insert into public.profiles (id, email) values (new.id, new.email);
  return new;
end;
$$ language plpgsql security definer;
create or replace trigger on_auth_user_created
  after insert on auth.users for each row execute function public.handle_new_user();

create table if not exists public.tool_usage (
  id uuid default gen_random_uuid() primary key,
  user_id uuid references public.profiles(id) on delete set null,
  anonymous_id text,
  tool_name text not null,
  action text not null,
  metadata jsonb default '{}',
  created_at timestamptz default now()
);
alter table public.tool_usage enable row level security;
create policy "Anyone can insert usage" on tool_usage for insert with check (true);
create policy "Users can view own usage" on tool_usage for select using (auth.uid() = user_id);

create table if not exists public.user_settings (
  id uuid default gen_random_uuid() primary key,
  user_id uuid references public.profiles(id) on delete cascade not null,
  tool_name text not null,
  settings jsonb default '{}',
  updated_at timestamptz default now(),
  unique(user_id, tool_name)
);
alter table public.user_settings enable row level security;
create policy "Users can manage own settings" on user_settings
  for all using (auth.uid() = user_id) with check (auth.uid() = user_id);

create table if not exists public.link_opener_stats (
  id uuid default gen_random_uuid() primary key,
  user_id uuid references public.profiles(id) on delete cascade,
  anonymous_id text,
  date date not null default current_date,
  links_opened int default 0,
  created_at timestamptz default now()
);
alter table public.link_opener_stats enable row level security;
create policy "Users can insert own link stats" on link_opener_stats for insert with check (auth.uid() = user_id);
create policy "Users can update own link stats" on link_opener_stats for update using (auth.uid() = user_id);
create policy "Users can view own link stats" on link_opener_stats for select using (auth.uid() = user_id);

create index if not exists idx_tool_usage_user on tool_usage(user_id);
create index if not exists idx_tool_usage_tool on tool_usage(tool_name);
create index if not exists idx_link_stats_user on link_opener_stats(user_id);
create index if not exists idx_link_stats_date on link_opener_stats(date);

-- 5. AI API Providers (managed via api-settings.html)
create table if not exists public.api_providers (
  id uuid default gen_random_uuid() primary key,
  name text not null unique,
  display_name text not null,
  api_key text not null,
  base_url text,
  is_active boolean default true,
  created_at timestamptz default now(),
  updated_at timestamptz default now()
);

alter table public.api_providers enable row level security;
create policy "Admins can manage api_providers" on api_providers
  for all using (auth.role() = 'authenticated') with check (auth.role() = 'authenticated');

-- Seed default providers
insert into public.api_providers (name, display_name, api_key, base_url) values
  ('openrouter', 'OpenRouter', '', 'https://openrouter.ai/api/v1'),
  ('openai', 'OpenAI', '', 'https://api.openai.com/v1'),
  ('anthropic', 'Anthropic', '', 'https://api.anthropic.com/v1')
on conflict (name) do nothing;
