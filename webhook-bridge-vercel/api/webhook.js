export default async function handler(req, res) {
  if (req.method !== 'POST') {
    return res.status(405).send('Only POST requests allowed');
  }

  const targetUrl = 'https://clasicoslafe.qrewards.com.mx/backend/webhook.php';
  
  // Read body from request
  let body = '';
  if (req.body) {
    body = typeof req.body === 'string' ? req.body : JSON.stringify(req.body);
  }

  // Copy and adapt headers
  const headers = {};
  for (const [key, value] of Object.entries(req.headers)) {
    if (key.toLowerCase() !== 'host' && key.toLowerCase() !== 'connection') {
      headers[key] = value;
    }
  }

  // Overwrite the User-Agent to look like a standard browser request
  headers['user-agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

  try {
    const response = await fetch(targetUrl, {
      method: 'POST',
      headers: headers,
      body: body
    });

    const responseText = await response.text();
    res.status(response.status).send(responseText);
  } catch (err) {
    res.status(502).send('Vercel Bridge Error: ' + err.message);
  }
}
