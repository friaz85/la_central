export default {
  async fetch(request, env, ctx) {
    // Only accept POST requests
    if (request.method !== "POST") {
      return new Response("Only POST requests allowed", { status: 405 });
    }

    const targetUrl = "https://clasicoslafe.qrewards.com.mx/backend/webhook.php";
    
    // Read the body as ArrayBuffer to handle raw data safely
    const body = await request.arrayBuffer();

    // Copy original headers but overwrite the User-Agent to bypass SiteGround's WAF
    const headers = new Headers(request.headers);
    headers.set("User-Agent", "LaCentral-Webhook-Bridge/1.0");

    try {
      const response = await fetch(targetUrl, {
        method: "POST",
        headers: headers,
        body: body
      });

      const responseText = await response.text();
      return new Response(responseText, {
        status: response.status,
        headers: response.headers
      });
    } catch (err) {
      return new Response("Bridge Error: " + err.message, { status: 502 });
    }
  }
};
