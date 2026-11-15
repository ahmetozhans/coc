# Live clan API setup

The frontend is static. The optional Cloudflare Pages Function at `functions/api/clan.js` proxies the official Clash of Clans API without exposing the token.

1. Request an API key at https://developer.clashofclans.com/.
2. Connect this repository to a Cloudflare Pages project. Set the build command to empty and the output directory to `.`.
3. Under Pages project settings, add the encrypted secret `CLASH_API_TOKEN` containing your API token.
4. The Supercell developer API may restrict keys by allowed outbound IP addresses. Verify that the Pages Function's outbound IP is supported; otherwise deploy the proxy on a host with a stable egress IP or use an authorized alternative. Cloudflare Pages alone does not guarantee a stable outbound IP.
5. After deployment, test `/api/clan?tag=%23CLANTAG` and check HTTP status.

Never commit API tokens, passwords, or personal credentials. Until the token and outbound-IP requirements are satisfied, the live endpoint returns an explicit unavailable response; it does not fabricate statistics.
