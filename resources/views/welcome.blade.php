<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GridWise - Smart Campus Energy Optimization Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col">

<!-- Header -->
<header class="bg-slate-800 border-b border-slate-700 py-4 px-6 shadow-md">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent">
                GridWise Energy Optimizer
            </h1>
            <p class="text-sm text-slate-400">BUP CSE Fest 2026 • LLM-Assisted Operator Directive Interpretation</p>
        </div>
        <div class="flex items-center gap-3">
                <span id="health-status" class="px-3 py-1 text-xs font-semibold rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    Checking API Health...
                </span>
            <button onclick="checkHealth()" class="text-xs bg-slate-700 hover:bg-slate-600 px-3 py-1.5 rounded transition font-medium">
                Refresh Health
            </button>
        </div>
    </div>
</header>

<!-- Main Container -->
<main class="max-w-7xl mx-auto px-4 py-8 flex-grow w-full grid grid-cols-1 lg:grid-cols-12 gap-8">

    <!-- Left Panel: Input & Controls -->
    <section class="lg:col-span-5 flex flex-col gap-6">
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 shadow-lg">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-semibold text-emerald-400">API Configuration & Input</h2>
                <button onclick="loadSamplePayload()" class="text-xs bg-emerald-600/20 text-emerald-400 hover:bg-emerald-600/30 px-2.5 py-1 rounded border border-emerald-500/30 transition">
                    Load Sample JSON
                </button>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-medium text-slate-400 mb-1">Service Base URL</label>
                <input type="text" id="api-url" value="http://localhost:8000" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-emerald-500">
            </div>

            <div class="mb-4">
                <label class="block text-xs font-medium text-slate-400 mb-1">Scenario Payload (JSON)</label>
                <textarea id="scenario-input" rows="12" class="w-full bg-slate-900 border border-slate-700 rounded-lg p-3 text-xs font-mono focus:outline-none focus:border-emerald-500 text-slate-300"></textarea>
            </div>

            <button onclick="optimizeEnergy()" id="submit-btn" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-medium py-2.5 rounded-lg transition shadow-md flex items-center justify-center gap-2">
                <span>Run Optimization</span>
            </button>
        </div>

        <!-- Summary Metrics Card -->
        <div id="metrics-card" class="bg-slate-800 border border-slate-700 rounded-xl p-5 shadow-lg hidden">
            <h3 class="text-sm font-semibold text-slate-300 mb-3 uppercase tracking-wider">Optimization Summary</h3>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="bg-slate-900 p-3 rounded-lg border border-slate-700/50">
                    <span class="block text-xs text-slate-400">Total Cost</span>
                    <span id="metric-cost" class="text-lg font-bold text-emerald-400">0 BDT</span>
                </div>
                <div class="bg-slate-900 p-3 rounded-lg border border-slate-700/50">
                    <span class="block text-xs text-slate-400">Grid Energy</span>
                    <span id="metric-grid" class="text-lg font-bold text-cyan-400">0 kWh</span>
                </div>
                <div class="bg-slate-900 p-3 rounded-lg border border-slate-700/50">
                    <span class="block text-xs text-slate-400">Peak Grid</span>
                    <span id="metric-peak" class="text-lg font-bold text-amber-400">0 kWh</span>
                </div>
            </div>
            <div class="mt-4 text-xs text-slate-400 bg-slate-900/50 p-3 rounded-lg border border-slate-800">
                <span class="font-semibold text-slate-300">Strategy Summary:</span>
                <p id="plan-summary-text" class="mt-1 italic"></p>
            </div>
        </div>
    </section>

    <!-- Right Panel: Results & Visualization -->
    <section class="lg:col-span-7 flex flex-col gap-6">

        <!-- Directive Interpretations -->
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 shadow-lg">
            <h3 class="text-lg font-semibold text-cyan-400 mb-3">Operator Directive Interpretations</h3>
            <div id="directives-container" class="space-y-3">
                <p class="text-sm text-slate-500 italic">No optimization run yet. Submit a scenario to view parsed directives.</p>
            </div>
        </div>

        <!-- Hourly Plan Breakdown Table -->
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 shadow-lg flex-grow">
            <h3 class="text-lg font-semibold text-cyan-400 mb-3">24-Hour Schedule Plan</h3>
            <div class="overflow-x-auto max-h-[400px] border border-slate-700 rounded-lg">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="bg-slate-900 sticky top-0 text-slate-400 uppercase font-semibold">
                    <tr>
                        <th class="p-2.5 border-b border-slate-700">Hour</th>
                        <th class="p-2.5 border-b border-slate-700">Grid (kWh)</th>
                        <th class="p-2.5 border-b border-slate-700">Solar (kWh)</th>
                        <th class="p-2.5 border-b border-slate-700">Action</th>
                        <th class="p-2.5 border-b border-slate-700">Bat (kWh)</th>
                        <th class="p-2.5 border-b border-slate-700">SOC After</th>
                    </tr>
                    </thead>
                    <tbody id="hourly-table-body" class="divide-y divide-slate-700/50 text-slate-300 font-mono">
                    <tr>
                        <td colspan="6" class="p-4 text-center text-slate-500 italic font-sans">Awaiting execution data...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </section>
</main>

<!-- Footer -->
<footer class="bg-slate-800 border-t border-slate-700 py-4 text-center text-xs text-slate-500">
    BUP CSE Fest 2026 Hackathon • Smart Campus Energy Optimization Challenge
</footer>

<!-- JavaScript Logic -->
<script>
    const samplePayload = {
        "scenario_id": "test-00",
        "operator_notes": [
            "Do not charge during specified window.",
            "Maintain minimum battery reserve in window.",
            "Note irrelevant."
        ],
        "hours": Array.from({length: 24}, (_, i) => ({
            "hour": i,
            "demand_kwh": i >= 18 && i <= 20 ? 205 : 100,
            "solar_kwh": i >= 6 && i <= 17 ? 10 : 0,
            "tariff_bdt_per_kwh": i >= 18 && i <= 21 ? 25 : 10
        })),
        "battery": {
            "capacity_kwh": 200,
            "initial_energy_kwh": 100,
            "minimum_energy_kwh": 40,
            "max_charge_kwh_per_hour": 50,
            "max_discharge_kwh_per_hour": 50
        }
    };

    window.onload = () => {
        document.getElementById('scenario-input').value = JSON.stringify(samplePayload, null, 4);
        checkHealth();
    };

    function loadSamplePayload() {
        document.getElementById('scenario-input').value = JSON.stringify(samplePayload, null, 4);
    }

    async function checkHealth() {
        const baseUrl = document.getElementById('api-url').value.trim();
        const statusBadge = document.getElementById('health-status');
        statusBadge.className = "px-3 py-1 text-xs font-semibold rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20";
        statusBadge.innerText = "Checking...";

        try {
            const res = await fetch(`${baseUrl}/health`, { method: 'GET' });
            if (res.ok) {
                const data = await res.json();
                if (data.status === "ok") {
                    statusBadge.className = "px-3 py-1 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20";
                    statusBadge.innerText = "API Online (OK)";
                    return;
                }
            }
            throw new Error();
        } catch (err) {
            statusBadge.className = "px-3 py-1 text-xs font-semibold rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20";
            statusBadge.innerText = "API Offline / Unreachable";
        }
    }

    async function optimizeEnergy() {
        const baseUrl = document.getElementById('api-url').value.trim();
        const rawInput = document.getElementById('scenario-input').value;
        const submitBtn = document.getElementById('submit-btn');

        let payload;
        try {
            payload = JSON.parse(rawInput);
        } catch (e) {
            alert("Invalid JSON format in scenario input payload.");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerText = "Processing LLM & Optimizer...";

        try {
            const res = await fetch(`${baseUrl}/optimize-energy`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!res.ok) {
                throw new Error(`Server responded with status ${res.status}`);
            }

            const data = await res.json();
            renderResults(data);
        } catch (err) {
            alert("Optimization request failed: " + err.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = "Run Optimization";
        }
    }

    function renderResults(data) {
        // Show metrics card
        document.getElementById('metrics-card').classList.remove('hidden');
        document.getElementById('metric-cost').innerText = `${data.total_cost_bdt} BDT`;
        document.getElementById('metric-grid').innerText = `${data.total_grid_kwh} kWh`;
        document.getElementById('metric-peak').innerText = `${data.peak_grid_kwh} kWh`;
        document.getElementById('plan-summary-text').innerText = data.plan_summary || "No summary provided.";

        // Render directives
        const directiveContainer = document.getElementById('directives-container');
        directiveContainer.innerHTML = "";
        data.directive_interpretation.forEach(item => {
            const div = document.createElement('div');
            div.className = "bg-slate-900 p-3 rounded-lg border border-slate-700 text-xs flex flex-col gap-1";
            div.innerHTML = `
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-slate-200">Note #${item.note_index}: <span class="text-cyan-400">${item.directive_type}</span></span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold ${item.applies ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-slate-700 text-slate-400'}">
                            Applies: ${item.applies}
                        </span>
                    </div>
                    <p class="text-slate-400">${item.explanation}</p>
                    ${item.structured_adjustment ? `<code class="text-[10px] text-emerald-300 bg-slate-800 p-1 rounded mt-1">Adj: ${JSON.stringify(item.structured_adjustment)}</code>` : ''}
                `;
            directiveContainer.appendChild(div);
        });

        // Render hourly plan table
        const tableBody = document.getElementById('hourly-table-body');
        tableBody.innerHTML = "";
        data.hourly_plan.forEach(h => {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-700/30 transition";
            tr.innerHTML = `
                    <td class="p-2.5 border-b border-slate-700/50 font-bold text-slate-400">H${h.hour}</td>
                    <td class="p-2.5 border-b border-slate-700/50 text-cyan-300">${h.grid_kwh}</td>
                    <td class="p-2.5 border-b border-slate-700/50 text-emerald-300">${h.solar_used_kwh}</td>
                    <td class="p-2.5 border-b border-slate-700/50 uppercase tracking-wider text-[10px]">
                        <span class="px-1.5 py-0.5 rounded ${h.battery_action === 'charge' ? 'bg-blue-500/20 text-blue-400' : h.battery_action === 'discharge' ? 'bg-amber-500/20 text-amber-400' : 'bg-slate-700 text-slate-400'}">
                            ${h.battery_action}
                        </span>
                    </td>
                    <td class="p-2.5 border-b border-slate-700/50">${h.battery_kwh}</td>
                    <td class="p-2.5 border-b border-slate-700/50 text-purple-300">${h.battery_energy_after_kwh}</td>
                `;
            tableBody.appendChild(tr);
        });
    }
</script>
</body>
</html>
