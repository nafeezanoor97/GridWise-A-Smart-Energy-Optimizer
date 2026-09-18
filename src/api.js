const API_BASE_URL = "https://bup.hackathon.mdmaraz.net";

export async function checkHealth() {
  const response = await fetch(`${API_BASE_URL}/health`);

  if (!response.ok) {
    throw new Error("API health check failed");
  }

  return response.json();
}

export async function optimizeEnergy(scenario) {
  const response = await fetch(`${API_BASE_URL}/optimize-energy`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(scenario),
  });

  if (!response.ok) {
    const errorText = await response.text();

    throw new Error(
      errorText ||
      `API request failed with status ${response.status}`
    );
  }

  return response.json();
}
