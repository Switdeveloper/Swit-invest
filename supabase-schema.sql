-- Run this in your Supabase SQL Editor (Dashboard → SQL Editor)
-- This creates all tables needed for the Swit-invest platform

-- 1. User Profiles (extends auth.users)
create table if not exists public.profiles (
  id uuid references auth.users on delete cascade primary key,
  email text,
  full_name text,
  created_at timestamptz default now()
);

alter table public.profiles enable row level security;

create policy "Users can view own profile"
  on profiles for select using (auth.uid() = id);

create policy "Users can update own profile"
  on profiles for update using (auth.uid() = id);

-- Auto-create profile on signup
create or replace function public.handle_new_user()
returns trigger as $$
begin
  insert into public.profiles (id, email)
  values (new.id, new.email);
  return new;
end;
$$ language plpgsql security definer;

create or replace trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_user();

-- 2. Tool Usage Analytics
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

create policy "Users can insert own usage"
  on tool_usage for insert with true;

create policy "Users can view own usage"
  on tool_usage for select using (auth.uid() = user_id);

-- 3. User Settings (cloud-saved preferences)
create table if not exists public.user_settings (
  id uuid default gen_random_uuid() primary key,
  user_id uuid references public.profiles(id) on delete cascade not null,
  tool_name text not null,
  settings jsonb default '{}',
  updated_at timestamptz default now(),
  unique(user_id, tool_name)
);

alter table public.user_settings enable row level security;

create policy "Users can manage own settings"
  on user_settings for all using (auth.uid() = user_id);

-- 4. Link Opener Stats (per-day counts)
create table if not exists public.link_opener_stats (
  id uuid default gen_random_uuid() primary key,
  user_id uuid references public.profiles(id) on delete cascade,
  anonymous_id text,
  date date not null default current_date,
  links_opened int default 0,
  created_at timestamptz default now(),
  unique(user_id, date)
);

alter table public.link_opener_stats enable row level security;

create policy "Users can insert own link stats"
  on link_opener_stats for insert with true;

create policy "Users can update own link stats"
  on link_opener_stats for update using (auth.uid() = user_id);

create policy "Users can view own link stats"
  on link_opener_stats for select using (auth.uid() = user_id);

-- Indexes for performance
create index if not exists idx_tool_usage_user on tool_usage(user_id);
create index if not exists idx_tool_usage_tool on tool_usage(tool_name);
create index if not exists idx_tool_usage_created on tool_usage(created_at);
create index if not exists idx_link_stats_user on link_opener_stats(user_id);
create index if not exists idx_link_stats_date on link_opener_stats(date);
