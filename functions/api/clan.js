// Cloudflare Pages Function: /api/clan?tag=%23ABC123
// Configure CLASH_API_TOKEN as an encrypted Pages secret, never in client code.
export async function onRequestGet({ request, env }) {
  const headers = { "Content-Type": "application/json", "Cache-Control": "public, max-age=60" };
  const url = new URL(request.url);
  const tag = (url.searchParams.get("tag") || "").trim().toUpperCase().replace(/^#/, "").replace(/O/g, "0");
  if (!/^[0289PYLQGRJCUV]{3,15}$/.test(tag)) {
    return new Response(JSON.stringify({ error: "Invalid clan tag" }), { status: 400, headers });
  }
  if (!env.CLASH_API_TOKEN) {
    return new Response(JSON.stringify({ error: "API is not configured yet" }), { status: 503, headers: { "Content-Type": "application/json", "Cache-Control": "no-store" } });
  }
  try {
    const upstream = await fetch("https://api.clashofclans.com/v1/clans/%23" + tag, {
      headers: { Authorization: "Bearer " + env.CLASH_API_TOKEN, Accept: "application/json" },
      cf: { cacheTtl: 60, cacheEverything: true }
    });
    if (!upstream.ok) {
      const status = upstream.status === 404 ? 404 : upstream.status === 429 ? 429 : 502;
      return new Response(JSON.stringify({ error: status === 404 ? "Clan not found" : status === 429 ? "Rate limited" : "Upstream API unavailable" }), { status, headers: { "Content-Type": "application/json", "Cache-Control": "no-store" } });
    }
    const data = await upstream.json();
    const safe = {
      tag: data.tag, name: data.name, description: data.description,
      level: data.clanLevel, members: data.members, points: data.clanPoints,
      warWins: data.warWins, warLosses: data.warLosses, warTies: data.warTies,
      warWinStreak: data.warWinStreak, warFrequency: data.warFrequency,
      location: data.location?.name || null,
      memberList: (data.memberList || []).map(m => ({
        tag: m.tag, name: m.name, role: m.role, expLevel: m.expLevel,
        trophies: m.trophies, donations: m.donations, donationsReceived: m.donationsReceived
      }))
    };
    return new Response(JSON.stringify(safe), { status: 200, headers });
  } catch {
    return new Response(JSON.stringify({ error: "Service temporarily unavailable" }), { status: 502, headers: { "Content-Type": "application/json", "Cache-Control": "no-store" } });
  }
}
