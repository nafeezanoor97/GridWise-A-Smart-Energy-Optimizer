import { useState } from "react";

import Header from "./components/Header";
import ScenarioForm from "./components/ScenarioForm";
import BatteryForm from "./components/BatteryForm";
import ResultCards from "./components/ResultCards";
import DirectiveTable from "./components/DirectiveTable";
import EnergyTable from "./components/EnergyTable";

import { validateScenario } from "./utils/validation";
import { sampleScenario } from "./utils/sampleData";

import { optimizeEnergy } from "./api";

import "./index.css";

// ============================================
// Create Empty Scenario
// ============================================

const createEmptyScenario = () => ({
  scenario_id: "GRID-101",

  operator_notes: [
    "",
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
        ...sampleScenario.operator_notes,
      ],

      hours: [
        ...sampleScenario.hours,
      ],

      battery: {
        ...sampleScenario.battery,
      },
    });

    // Clear previous result
    setResult(null);

    // Clear previous errors
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
  // REAL BACKEND OPTIMIZATION
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

      // --------------------------------------
      // Send Scenario To Backend
      // --------------------------------------

      console.log(
        "Sending scenario to backend:",
        scenario
      );


      const backendResult =
        await optimizeEnergy(scenario);


      // --------------------------------------
      // Backend Response
      // --------------------------------------

      console.log(
        "Backend optimization result:",
        backendResult
      );


      // --------------------------------------
      // Save Backend Result
      // --------------------------------------

      setResult(backendResult);


    } catch (error) {

      console.error(
        "Optimization Error:",
        error
      );


      // --------------------------------------
      // Show Error
      // --------------------------------------

      setErrors([
        error.message ||
        "Unable to connect to the optimization API.",
      ]);

      setResult(null);


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

            API Connected

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
            disabled={loading}
          >

            Load Sample Scenario

          </button>



          {/* Reset */}

          <button
            type="button"
            className="secondary-button danger"
            onClick={handleReset}
            disabled={loading}
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
                result.directive_interpretation || []
              }
            />


            {/* --------------------------------
                24 Hour Energy Plan
            -------------------------------- */}

            <EnergyTable
              plan={
                result.hourly_plan || []
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

              Sending your 24-hour scenario
              to the GridWise optimization API.

            </p>

          </section>

        )}


      </main>

    </div>

  );
}


export default App;
