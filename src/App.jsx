import { useState } from "react";

import Header from "./components/Header";
import ScenarioForm from "./components/ScenarioForm";
import BatteryForm from "./components/BatteryForm";
import ResultCards from "./components/ResultCards";
import DirectiveTable from "./components/DirectiveTable";
import EnergyTable from "./components/EnergyTable";

import { validateScenario } from "./utils/validation";
import { sampleScenario } from "./utils/sampleData";

import "./index.css";


// ============================================
// Create Empty Scenario
// ============================================

const createEmptyScenario = () => ({
  scenario_id: "GRID-101",

  operator_notes: [
    ""
  ],

  hours: Array.from(
    { length: 24 },
    (_, index) => ({
      hour: index,
      demand_kwh: 0,
      solar_kwh: 0,
      tariff_bdt_per_kwh: 0,
    })
  ),

  battery: {
    capacity_kwh: 500,
    initial_energy_kwh: 200,
    minimum_energy_kwh: 50,
    max_charge_kwh_per_hour: 100,
    max_discharge_kwh_per_hour: 100,
  },
});


// ============================================
// APP
// ============================================

function App() {

  // ------------------------------------------
  // Scenario State
  // ------------------------------------------

  const [scenario, setScenario] = useState(
    createEmptyScenario()
  );


  // ------------------------------------------
  // Optimization Result
  // ------------------------------------------

  const [result, setResult] = useState(null);


  // ------------------------------------------
  // Validation Errors
  // ------------------------------------------

  const [errors, setErrors] = useState([]);


  // ------------------------------------------
  // Loading State
  // ------------------------------------------

  const [loading, setLoading] = useState(false);


  // ==========================================
  // Update Scenario
  // ==========================================

  const updateScenario = (newData) => {

    setScenario(newData);

    // Clear previous errors
    setErrors([]);

  };


  // ==========================================
  // Load Sample Scenario
  // ==========================================

  const handleLoadSample = () => {

    setScenario({
      ...sampleScenario,

      operator_notes: [
        ...sampleScenario.operator_notes
      ],

      hours: [
        ...sampleScenario.hours
      ],

      battery: {
        ...sampleScenario.battery
      }
    });

    // Clear old result
    setResult(null);

    // Clear old errors
    setErrors([]);

  };


  // ==========================================
  // Reset Scenario
  // ==========================================

  const handleReset = () => {

    setScenario(
      createEmptyScenario()
    );

    setResult(null);

    setErrors([]);

  };


  // ==========================================
  // Frontend Energy Optimizer
  // ==========================================

  const handleOptimize = async () => {

    // ----------------------------------------
    // Validate Scenario
    // ----------------------------------------

    const validationErrors =
      validateScenario(scenario);


    if (validationErrors.length > 0) {

      setErrors(validationErrors);

      setResult(null);

      return;
    }


    // ----------------------------------------
    // Start Loading
    // ----------------------------------------

    setErrors([]);

    setLoading(true);

    setResult(null);


    try {

      // Simulate optimization processing
      await new Promise(
        (resolve) =>
          setTimeout(resolve, 1200)
      );


      // --------------------------------------
      // Create Hourly Optimization Plan
      // --------------------------------------

      const hourlyPlan =
        scenario.hours.map((item) => {

          // Solar used for demand
          const solarUsed =
            Math.min(
              item.demand_kwh,
              item.solar_kwh
            );


          // Remaining demand from grid
          const gridKwh =
            Math.max(
              0,
              item.demand_kwh -
              solarUsed
            );


          // Grid cost
          const cost =
            gridKwh *
            item.tariff_bdt_per_kwh;


          // ----------------------------------
          // Determine Action
          // ----------------------------------

          let action = "";

          if (
            item.solar_kwh >=
            item.demand_kwh
          ) {

            action =
              "Solar supplies demand";

          } else if (
            item.solar_kwh > 0
          ) {

            action =
              "Solar + Grid supply demand";

          } else {

            action =
              "Grid supplies demand";
          }


          // ----------------------------------
          // Return Hour Plan
          // ----------------------------------

          return {

            hour: item.hour,

            demand_kwh:
              item.demand_kwh,

            solar_kwh:
              item.solar_kwh,

            solar_used_kwh:
              solarUsed,

            grid_kwh:
              gridKwh,

            battery_kwh:
              0,

            cost_bdt:
              cost,

            action:
              action,
          };

        });


      // --------------------------------------
      // Total Grid Energy
      // --------------------------------------

      const totalGridKwh =
        hourlyPlan.reduce(
          (sum, item) =>
            sum + item.grid_kwh,
          0
        );


      // --------------------------------------
      // Total Cost
      // --------------------------------------

      const totalCostBdt =
        hourlyPlan.reduce(
          (sum, item) =>
            sum + item.cost_bdt,
          0
        );


      // --------------------------------------
      // Peak Grid Demand
      // --------------------------------------

      const peakGridKwh =
        Math.max(
          ...hourlyPlan.map(
            (item) =>
              item.grid_kwh
          )
        );


      // --------------------------------------
      // Operator Directives
      // --------------------------------------

      const directives =
        scenario.operator_notes
          .filter(
            (note) =>
              note.trim() !== ""
          )
          .map(
            (note, index) => ({

              directive_id:
                index + 1,

              operator_note:
                note,

              interpretation:
                "Instruction accepted for energy optimization",

            })
          );


      // --------------------------------------
      // Final Result
      // --------------------------------------

      const demoResult = {

        directive_interpretation:
          directives,

        hourly_plan:
          hourlyPlan,

        total_grid_kwh:
          totalGridKwh,

        total_cost_bdt:
          totalCostBdt,

        peak_grid_kwh:
          peakGridKwh,

        plan_summary:
          "24-hour energy plan generated successfully using available solar energy and grid supply.",

      };


      // --------------------------------------
      // Set Result
      // --------------------------------------

      console.log(
        "Optimization Result:",
        demoResult
      );

      setResult(
        demoResult
      );

    } catch (error) {

      console.error(
        "Optimization Error:",
        error
      );

      setErrors([
        "Unable to generate optimization result."
      ]);

    } finally {

      setLoading(false);

    }

  };


  // ==========================================
  // RETURN UI
  // ==========================================

  return (

    <div className="app">


      {/* ======================================
          HEADER
      ====================================== */}

      <Header />


      <main className="container">


        {/* ====================================
            HERO SECTION
        ==================================== */}

        <section className="hero-section">

          <div>

            <p className="eyebrow">
              SMART ENERGY SCHEDULING
            </p>


            <h1>

              GridWise Energy

              <span>
                Optimizer
              </span>

            </h1>


            <p className="hero-text">

              Interpret operator instructions
              and create an optimized
              24-hour campus energy schedule.

            </p>

          </div>


          {/* System Status */}

          <div className="status-badge">

            <span></span>

            API Ready

          </div>

        </section>



        {/* ====================================
            ERROR MESSAGE
        ==================================== */}

        {errors.length > 0 && (

          <div className="error-box">

            <div className="error-title">

              ⚠ Please fix the following:

            </div>


            <ul>

              {errors.map(
                (error, index) => (

                  <li key={index}>
                    {error}
                  </li>

                )
              )}

            </ul>

          </div>

        )}



        {/* ====================================
            SCENARIO FORM
        ==================================== */}

        <ScenarioForm
          scenario={scenario}
          setScenario={updateScenario}
        />



        {/* ====================================
            UTILITY BUTTONS
        ==================================== */}

        <div className="utility-buttons">


          {/* Load Sample */}

          <button
            type="button"
            className="secondary-button"
            onClick={handleLoadSample}
          >

            Load Sample Scenario

          </button>



          {/* Reset */}

          <button
            type="button"
            className="secondary-button danger"
            onClick={handleReset}
          >

            Reset

          </button>

        </div>



        {/* ====================================
            BATTERY FORM
        ==================================== */}

        <BatteryForm

          battery={
            scenario.battery
          }

          scenario={
            scenario
          }

          setScenario={
            updateScenario
          }

        />



        {/* ====================================
            OPTIMIZE BUTTON
        ==================================== */}

        <section className="action-section">

          <button
            type="button"
            className="optimize-button"
            onClick={handleOptimize}
            disabled={loading}
          >

            {loading

              ? "⚙ Optimizing..."

              : "⚡ Optimize Energy"

            }

          </button>

        </section>



        {/* ====================================
            OPTIMIZATION RESULTS
        ==================================== */}

        {result && (

          <>

            {/* --------------------------------
                Result Summary Cards
            -------------------------------- */}

            <ResultCards
              result={
                result
              }
            />


            {/* --------------------------------
                Directive Table
            -------------------------------- */}

            <DirectiveTable
              directives={
                result.directive_interpretation
              }
            />


            {/* --------------------------------
                24 Hour Energy Plan
            -------------------------------- */}

            <EnergyTable
              plan={
                result.hourly_plan
              }
            />

          </>

        )}



        {/* ====================================
            EMPTY RESULT
        ==================================== */}

        {!result && !loading && (

          <section className="empty-result">

            <div className="empty-icon">
              ⚡
            </div>


            <h2>
              Optimization Results
            </h2>


            <p>

              Enter your scenario data and
              click "Optimize Energy" to
              generate an optimized plan.

            </p>

          </section>

        )}



        {/* ====================================
            LOADING STATE
        ==================================== */}

        {loading && (

          <section className="loading-box">

            <div className="loading-icon">
              ⚙
            </div>


            <h2>
              Optimizing Energy...
            </h2>


            <p>

              Analyzing 24-hour demand,
              solar generation and
              electricity tariff.

            </p>

          </section>

        )}

      </main>

    </div>

  );

}


export default App;