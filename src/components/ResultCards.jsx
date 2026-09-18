function ResultCards({ result }) {
  if (!result) {
    return null;
  }

  return (
    <section className="results-section">

      {/* Section Header */}
      <div className="section-heading">
        <p className="eyebrow">
          RESULTS
        </p>

        <h2>
          Optimization Summary
        </h2>

        <p>
          Overview of the generated 24-hour
          campus energy plan.
        </p>
      </div>


      {/* Result Cards */}
      <div className="result-cards">

        {/* Total Grid */}
        <div className="result-card">

          <div className="result-card-icon">
            ⚡
          </div>

          <div>
            <p>
              Total Grid Energy
            </p>

            <h3>
              {Number(
                result.total_grid_kwh || 0
              ).toFixed(2)}
              <span> kWh</span>
            </h3>
          </div>

        </div>


        {/* Total Cost */}
        <div className="result-card">

          <div className="result-card-icon">
            ৳
          </div>

          <div>
            <p>
              Total Energy Cost
            </p>

            <h3>
              ৳
              {Number(
                result.total_cost_bdt || 0
              ).toFixed(2)}
            </h3>
          </div>

        </div>


        {/* Peak Grid */}
        <div className="result-card">

          <div className="result-card-icon">
            📊
          </div>

          <div>
            <p>
              Peak Grid Demand
            </p>

            <h3>
              {Number(
                result.peak_grid_kwh || 0
              ).toFixed(2)}
              <span> kWh</span>
            </h3>
          </div>

        </div>

      </div>


      {/* Plan Summary */}
      <div className="plan-summary">

        <div className="summary-icon">
          ✓
        </div>

        <div>

          <h3>
            Plan Summary
          </h3>

          <p>
            {result.plan_summary ||
              "Optimization plan generated successfully."}
          </p>

        </div>

      </div>

    </section>
  );
}

export default ResultCards;