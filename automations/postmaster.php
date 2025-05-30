<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mini Postman</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    input, select, button, textarea { margin: 5px 0; width: 100%; padding: 8px; }
    .var-row { display: flex; gap: 10px; margin-bottom: 5px; }
    .var-row input { flex: 1; }
    .var-row button { width: auto; }
    #vars { margin-bottom: 10px; }
    textarea { height: 200px; }
  </style>
</head>
<body>

  <h2>🛠️Postman</h2>
v
  <label>Request URL:</label>
  <input type="text" id="url" placeholder="https://api.example.com/endpoint">

  <label>Method:</label>
  <select id="method">
    <option value="GET">GET</option>
    <option value="POST">POST</option>
  </select>

  <h3>🔧 Variables</h3>
  <div id="vars"></div>
  <button onclick="addVariable()">+ Add Variable</button>

  <br><br>
  <button onclick="sendRequest()">🚀 Send Request</button>

  <h3>📦 Response</h3>
  <textarea id="response" readonly></textarea>

  <script>
    function addVariable(name = '', value = '') {
      const container = document.createElement('div');
      container.className = 'var-row';
      container.innerHTML = `
        <input type="text" placeholder="Key" value="${name}">
        <input type="text" placeholder="Value" value="${value}">
        <button onclick="this.parentElement.remove()">❌</button>
      `;
      document.getElementById('vars').appendChild(container);
    }

    async function sendRequest() {
      const method = document.getElementById('method').value;
      const url = document.getElementById('url').value;
      const varElements = document.querySelectorAll('#vars .var-row');
      const params = {};

      varElements.forEach(row => {
        const key = row.children[0].value.trim();
        const value = row.children[1].value.trim();
        if (key) {
          // Support array-like input (e.g., foo[] or foo[0])
          if (key.endsWith('[]')) {
            const name = key.slice(0, -2);
            if (!params[name]) params[name] = [];
            params[name].push(value);
          } else {
            params[key] = value;
          }
        }
      });

      let finalUrl = url;
      let options = { method };

      if (method === 'GET') {
        const query = new URLSearchParams(params).toString();
        finalUrl += (url.includes('?') ? '&' : '?') + query;
      } else {
        options.headers = { 'Content-Type': 'application/json' };
        options.body = JSON.stringify(params);
      }

      try {
        const res = await fetch(finalUrl, options);
        const text = await res.text();
        document.getElementById('response').value = text;
      } catch (err) {
        document.getElementById('response').value = '❌ Error: ' + err.message;
      }
    }

    // Add one default variable row
    addVariable();
  </script>

</body>
</html>